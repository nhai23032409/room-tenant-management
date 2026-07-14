<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/business.php';

header('Content-Type: application/json');

function checkout_contract(PDO $pdo, $tenantId, $forUpdate = false) {
    $sql = "SELECT c.*, t.name AS tenant_name, t.bed_id AS tenant_bed_id, r.room_number, r.hostel_id
            FROM contracts c JOIN tenants t ON t.id = c.tenant_id JOIN rooms r ON r.id = c.room_id
            WHERE c.tenant_id = ? AND c.status = 'active'" . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tenantId]);
    return $stmt->fetch();
}

function valid_checkout_date($value) {
    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value;
}

$action = $_POST['action'] ?? '';
switch ($action) {
    case 'calculate_checkout':
        require_api_permission('manage_payments');
        $tenantId = filter_input(INPUT_POST, 'tenant_id', FILTER_VALIDATE_INT);
        $checkoutDate = $_POST['checkout_date'] ?? '';
        if (!$tenantId || !valid_checkout_date($checkoutDate)) { http_response_code(422); echo json_encode(['success' => false, 'message' => 'Invalid checkout data.']); exit; }
        $contract = checkout_contract($pdo, $tenantId);
        if (!$contract || (int)$contract['hostel_id'] !== (int)($_SESSION['hostel_id'] ?? 0)) { http_response_code(404); echo json_encode(['success' => false, 'message' => 'No active contract found.']); exit; }
        echo json_encode(['success' => true, 'calculation' => calculate_checkout_settlement($pdo, $tenantId, $checkoutDate, $contract)]);
        break;

    case 'process_checkout':
        require_api_permission('checkout_tenant');
        require_csrf();
        $tenantId = filter_input(INPUT_POST, 'tenant_id', FILTER_VALIDATE_INT);
        $checkoutDate = $_POST['checkout_date'] ?? '';
        $refundMethod = $_POST['refund_method'] ?? 'cash';
        $settlementConfirmed = ($_POST['settlement_confirmed'] ?? '') === '1';
        if (!$tenantId || !valid_checkout_date($checkoutDate) || !$settlementConfirmed || !in_array($refundMethod, ['cash', 'bank_transfer'], true)) {
            http_response_code(422); echo json_encode(['success' => false, 'message' => 'Checkout requires a confirmed settlement and valid method.']); exit;
        }
        $pdo->beginTransaction();
        try {
            $contract = checkout_contract($pdo, $tenantId, true);
            if (!$contract || (int)$contract['hostel_id'] !== (int)($_SESSION['hostel_id'] ?? 0)) {
                throw new RuntimeException('No active contract found in your branch.');
            }
            if ($checkoutDate < $contract['start_date']) {
                throw new RuntimeException('Checkout date cannot precede contract start date.');
            }
            $settlement = calculate_checkout_settlement($pdo, $tenantId, $checkoutDate, $contract);
            $stmt = $pdo->prepare("INSERT INTO checkout_settlements (tenant_id, contract_id, checkout_date, deposit_amount, refund_amount, deductions, amount_to_pay, final_refund, payment_method, confirmed_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$tenantId, $contract['id'], $checkoutDate, $settlement['deposit'], $settlement['refund_amount'], $settlement['total_deductions'], $settlement['amount_to_pay'], $settlement['final_refund'], $refundMethod, $_SESSION['user_id']]);
            if ($settlement['final_refund'] > 0) {
                $receipt = generate_receipt_number();
                $stmt = $pdo->prepare("INSERT INTO payments (tenant_id, amount, date, method, payment_type, receipt_number, notes) VALUES (?, ?, ?, ?, 'deposit_refund', ?, 'Deposit refund after checkout')");
                $stmt->execute([$tenantId, $settlement['final_refund'], $checkoutDate, $refundMethod, $receipt]);
            }
            // Settlement has been confirmed by an authorized user; the listed debts are now reconciled.
            $pdo->prepare("UPDATE debts SET status = 'paid' WHERE tenant_id = ? AND status IN ('pending', 'overdue')")->execute([$tenantId]);
            $pdo->prepare("UPDATE contracts SET status = 'ended' WHERE id = ?")->execute([$contract['id']]);
            $pdo->prepare("UPDATE tenants SET status = 'checked_out', checkout_date = ? WHERE id = ?")->execute([$checkoutDate, $tenantId]);
            $pdo->prepare("UPDATE beds SET status = 'available', deposit_expires_at = NULL WHERE id = ?")->execute([$contract['bed_id']]);
            $pdo->prepare("UPDATE checkin_history SET checkout_date = ? WHERE tenant_id = ? AND checkout_date IS NULL")->execute([$checkoutDate, $tenantId]);
            log_activity($pdo, $_SESSION['user_id'], 'process_checkout', "Completed checkout settlement for tenant #$tenantId");
            $pdo->commit();
            echo json_encode(['success' => true, 'settlement' => $settlement]);
        } catch (Throwable $e) {
            $pdo->rollBack(); http_response_code(422); echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    default:
        http_response_code(400); echo json_encode(['success' => false, 'message' => 'Invalid action.']);
}
