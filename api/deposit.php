<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/business.php';

header('Content-Type: application/json');

function current_hostel_id() {
    return (int)($_SESSION['hostel_id'] ?? 0);
}

function find_deposit_context(PDO $pdo, $bedId, $tenantId = null, $forUpdate = false) {
    $tenantJoin = $tenantId === null
        ? 'LEFT JOIN tenants t ON t.bed_id = b.id'
        : 'LEFT JOIN tenants t ON t.id = ?';
    $sql = "SELECT b.id AS bed_id, b.status AS bed_status, b.deposit_expires_at,
                   r.id AS room_id, r.hostel_id, r.monthly_rent, r.capacity,
                   t.id AS tenant_id, t.bed_id AS tenant_bed_id, t.status AS tenant_status
            FROM beds b
            JOIN rooms r ON r.id = b.room_id
            $tenantJoin
            WHERE b.id = ?" . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($tenantId === null ? [$bedId] : [$tenantId, $bedId]);
    return $stmt->fetch();
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'create_deposit_request':
        require_api_permission('create_deposit');
        require_csrf();
        $bedId = filter_input(INPUT_POST, 'bed_id', FILTER_VALIDATE_INT);
        $tenantId = filter_input(INPUT_POST, 'tenant_id', FILTER_VALIDATE_INT);
        $bedCount = filter_input(INPUT_POST, 'bed_count', FILTER_VALIDATE_INT) ?: 1;
        if (!$bedId || !$tenantId || $bedCount < 1) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Invalid bed, tenant, or bed count.']);
            exit;
        }

        $pdo->beginTransaction();
        try {
            $context = find_deposit_context($pdo, $bedId, $tenantId, true);
            if (!$context || (int)$context['hostel_id'] !== current_hostel_id()) {
                throw new RuntimeException('Bed not found in your branch.');
            }
            if (!$context['tenant_id'] || !in_array($context['tenant_status'], ['pending', 'inactive'], true)) {
                throw new RuntimeException('Tenant is not eligible for a deposit request.');
            }
            if ($context['bed_status'] !== 'available') {
                throw new RuntimeException('Bed is no longer available.');
            }
            if ($bedCount !== 1) {
                // A group/whole-room reservation needs one reservation record per
                // assigned bed. Do not silently reserve only the selected bed.
                throw new RuntimeException('Whole-room/group reservations require all member beds and are not available in this single-bed endpoint.');
            }

            $depositAmount = calculate_deposit_amount($context['monthly_rent'], $bedCount);
            $expiresAt = (new DateTime('+24 hours'))->format('Y-m-d H:i:s');
            $stmt = $pdo->prepare("UPDATE beds SET status = 'deposited', deposit_expires_at = ? WHERE id = ? AND status = 'available'");
            $stmt->execute([$expiresAt, $bedId]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Bed was reserved by another request.');
            }
            $stmt = $pdo->prepare("UPDATE tenants SET bed_id = ?, monthly_rent = ?, security_deposit = ?, status = 'pending' WHERE id = ?");
            $stmt->execute([$bedId, $context['monthly_rent'], $depositAmount, $tenantId]);
            $stmt = $pdo->prepare("INSERT INTO payments (tenant_id, amount, date, payment_type, method, notes) VALUES (?, ?, CURDATE(), 'security_deposit', 'pending', ?)");
            $stmt->execute([$tenantId, $depositAmount, 'Deposit request; expires ' . $expiresAt]);
            $paymentId = (int)$pdo->lastInsertId();
            log_activity($pdo, $_SESSION['user_id'], 'create_deposit', "Created deposit request #$paymentId for bed #$bedId");
            $pdo->commit();
            echo json_encode(['success' => true, 'payment_id' => $paymentId, 'amount' => $depositAmount, 'expires_at' => $expiresAt]);
        } catch (Throwable $e) {
            $pdo->rollBack();
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'confirm_deposit':
        require_api_permission('approve_deposit');
        require_csrf();
        $bedId = filter_input(INPUT_POST, 'bed_id', FILTER_VALIDATE_INT);
        $paymentId = filter_input(INPUT_POST, 'payment_id', FILTER_VALIDATE_INT);
        $method = $_POST['method'] ?? 'cash';
        if (!$bedId || !$paymentId || !in_array($method, ['cash', 'bank_transfer'], true)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Invalid payment confirmation.']);
            exit;
        }
        $pdo->beginTransaction();
        try {
            $context = find_deposit_context($pdo, $bedId, null, true);
            if (!$context || (int)$context['hostel_id'] !== current_hostel_id() || $context['bed_status'] !== 'deposited') {
                throw new RuntimeException('Deposit reservation is not valid.');
            }
            $stmt = $pdo->prepare("UPDATE payments SET method = ?, notes = CONCAT(COALESCE(notes, ''), ' | Confirmed') WHERE id = ? AND tenant_id = ? AND payment_type = 'security_deposit' AND method = 'pending'");
            $stmt->execute([$method, $paymentId, $context['tenant_id']]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Pending deposit payment was not found.');
            }
            // A confirmed deposit remains locked until contract, payment and handover are complete.
            $stmt = $pdo->prepare("UPDATE beds SET deposit_expires_at = NULL WHERE id = ?");
            $stmt->execute([$bedId]);
            log_activity($pdo, $_SESSION['user_id'], 'confirm_deposit', "Confirmed deposit payment #$paymentId for bed #$bedId");
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Deposit confirmed; bed remains reserved until handover.']);
        } catch (Throwable $e) {
            $pdo->rollBack();
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'cancel_expired_deposits':
        require_api_permission('manage_rooms');
        require_csrf();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT b.id, t.id AS tenant_id FROM beds b JOIN rooms r ON r.id = b.room_id LEFT JOIN tenants t ON t.bed_id = b.id AND t.status = 'pending' WHERE r.hostel_id = ? AND b.status = 'deposited' AND b.deposit_expires_at < NOW() FOR UPDATE");
            $stmt->execute([current_hostel_id()]);
            $expired = $stmt->fetchAll();
            foreach ($expired as $row) {
                $pdo->prepare("UPDATE beds SET status = 'available', deposit_expires_at = NULL WHERE id = ?")->execute([$row['id']]);
                if ($row['tenant_id']) {
                    $pdo->prepare("UPDATE tenants SET bed_id = NULL, status = 'inactive' WHERE id = ?")->execute([$row['tenant_id']]);
                    $pdo->prepare("UPDATE payments SET notes = CONCAT(COALESCE(notes, ''), ' | Expired') WHERE tenant_id = ? AND payment_type = 'security_deposit' AND method = 'pending'")->execute([$row['tenant_id']]);
                }
            }
            log_activity($pdo, $_SESSION['user_id'], 'cancel_expired_deposits', 'Released ' . count($expired) . ' expired deposit reservations');
            $pdo->commit();
            echo json_encode(['success' => true, 'cancelled' => count($expired)]);
        } catch (Throwable $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Could not release expired deposits.']);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
}
