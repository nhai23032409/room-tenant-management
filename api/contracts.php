<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/permissions.php';

header('Content-Type: application/json');

function contract_context(PDO $pdo, $tenantId, $bedId, $forUpdate = false) {
    $sql = "SELECT t.*, b.id AS bed_id, b.status AS bed_status, r.id AS room_id, r.room_number,
                   r.monthly_rent, r.hostel_id, h.name AS hostel_name
            FROM tenants t JOIN beds b ON b.id = t.bed_id
            JOIN rooms r ON r.id = b.room_id JOIN hostels h ON h.id = r.hostel_id
            WHERE t.id = ? AND b.id = ?" . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tenantId, $bedId]);
    return $stmt->fetch();
}

$action = $_POST['action'] ?? '';
switch ($action) {
    case 'generate_contract':
        require_api_permission('manage_contracts');
        require_csrf();
        $tenantId = filter_input(INPUT_POST, 'tenant_id', FILTER_VALIDATE_INT);
        $bedId = filter_input(INPUT_POST, 'bed_id', FILTER_VALIDATE_INT);
        $startDate = $_POST['start_date'] ?? '';
        $endDate = $_POST['end_date'] ?: null;
        if (!$tenantId || !$bedId || !DateTime::createFromFormat('Y-m-d', $startDate)) {
            http_response_code(422); echo json_encode(['success' => false, 'message' => 'Invalid contract data.']); exit;
        }
        $pdo->beginTransaction();
        try {
            $tenant = contract_context($pdo, $tenantId, $bedId, true);
            if (!$tenant || (int)$tenant['hostel_id'] !== (int)($_SESSION['hostel_id'] ?? 0) || $tenant['bed_status'] !== 'deposited') {
                throw new RuntimeException('Tenant does not have a confirmed reservation in this branch.');
            }
            $payment = $pdo->prepare("SELECT amount FROM payments WHERE tenant_id = ? AND payment_type = 'security_deposit' AND method IN ('cash', 'bank_transfer') ORDER BY id DESC LIMIT 1");
            $payment->execute([$tenantId]);
            $deposit = $payment->fetchColumn();
            if ($deposit === false) {
                throw new RuntimeException('A confirmed deposit is required before creating a contract.');
            }
            $active = $pdo->prepare("SELECT id FROM contracts WHERE tenant_id = ? AND status = 'active'");
            $active->execute([$tenantId]);
            if ($active->fetch()) {
                throw new RuntimeException('Tenant already has an active contract.');
            }
            $stmt = $pdo->prepare("INSERT INTO contracts (tenant_id, room_id, bed_id, start_date, end_date, deposit, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
            $stmt->execute([$tenantId, $tenant['room_id'], $bedId, $startDate, $endDate, $deposit]);
            $contractId = (int)$pdo->lastInsertId();
            log_activity($pdo, $_SESSION['user_id'], 'generate_contract', "Generated contract #$contractId for tenant #$tenantId");
            $pdo->commit();
            echo json_encode(['success' => true, 'contract_id' => $contractId, 'deposit' => (float)$deposit]);
        } catch (Throwable $e) {
            $pdo->rollBack(); http_response_code(422); echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'get_contract':
        require_api_permission('manage_contracts');
        $contractId = filter_input(INPUT_POST, 'contract_id', FILTER_VALIDATE_INT);
        $stmt = $pdo->prepare("SELECT c.*, t.name AS tenant_name, t.phone, t.email, r.room_number, h.name AS hostel_name FROM contracts c JOIN tenants t ON t.id = c.tenant_id JOIN rooms r ON r.id = c.room_id JOIN hostels h ON h.id = r.hostel_id WHERE c.id = ? AND h.id = ?");
        $stmt->execute([$contractId, (int)($_SESSION['hostel_id'] ?? 0)]);
        echo json_encode(['success' => true, 'contract' => $stmt->fetch() ?: null]);
        break;

    case 'save_signature':
        require_api_permission('manage_contracts');
        require_csrf();
        $contractId = filter_input(INPUT_POST, 'contract_id', FILTER_VALIDATE_INT);
        $encoded = $_POST['signature'] ?? '';
        if (!$contractId || !preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $encoded, $matches)) {
            http_response_code(422); echo json_encode(['success' => false, 'message' => 'Invalid signature image.']); exit;
        }
        $binary = base64_decode($matches[1], true);
        if ($binary === false || strlen($binary) > 2 * 1024 * 1024) {
            http_response_code(422); echo json_encode(['success' => false, 'message' => 'Signature image is too large.']); exit;
        }
        $stmt = $pdo->prepare("SELECT c.id FROM contracts c JOIN rooms r ON r.id = c.room_id WHERE c.id = ? AND r.hostel_id = ?");
        $stmt->execute([$contractId, (int)($_SESSION['hostel_id'] ?? 0)]);
        if (!$stmt->fetch()) { http_response_code(404); echo json_encode(['success' => false, 'message' => 'Contract not found.']); exit; }
        $directory = dirname(__DIR__) . '/uploads/signatures';
        if (!is_dir($directory) && !mkdir($directory, 0755, true)) { http_response_code(500); echo json_encode(['success' => false, 'message' => 'Cannot create signature directory.']); exit; }
        $relativePath = "uploads/signatures/contract_$contractId.png";
        if (file_put_contents(dirname(__DIR__) . '/' . $relativePath, $binary, LOCK_EX) === false) { http_response_code(500); echo json_encode(['success' => false, 'message' => 'Could not save signature.']); exit; }
        $pdo->prepare('UPDATE contracts SET signature_path = ? WHERE id = ?')->execute([$relativePath, $contractId]);
        log_activity($pdo, $_SESSION['user_id'], 'save_signature', "Saved signature for contract #$contractId");
        echo json_encode(['success' => true, 'signature_path' => $relativePath]);
        break;

    default:
        http_response_code(400); echo json_encode(['success' => false, 'message' => 'Invalid action.']);
}
