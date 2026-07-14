<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/permissions.php';

header('Content-Type: application/json');

require_api_permission('manage_payments');
require_csrf();

if (($_POST['action'] ?? '') !== 'record_payment') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    exit;
}

$tenantId = filter_input(INPUT_POST, 'tenant_id', FILTER_VALIDATE_INT);
$amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
$paymentDate = $_POST['date'] ?? date('Y-m-d');
$method = $_POST['method'] ?? '';
$paymentType = $_POST['payment_type'] ?? '';
$notes = sanitize_input($_POST['notes'] ?? '');
$allowedTypes = ['rent', 'maintenance', 'penalty', 'other', 'electricity', 'water', 'wifi'];

if (!$tenantId || $amount === false || $amount <= 0 || !DateTime::createFromFormat('Y-m-d', $paymentDate)
    || !in_array($method, ['cash', 'bank_transfer'], true) || !in_array($paymentType, $allowedTypes, true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid payment data.']);
    exit;
}

$scope = $pdo->prepare("SELECT t.id FROM tenants t JOIN beds b ON b.id = t.bed_id JOIN rooms r ON r.id = b.room_id WHERE t.id = ? AND r.hostel_id = ?");
$scope->execute([$tenantId, (int)($_SESSION['hostel_id'] ?? 0)]);
if (!$scope->fetch()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Tenant not found in your branch.']);
    exit;
}

$receipt = generate_receipt_number();
$stmt = $pdo->prepare("INSERT INTO payments (tenant_id, amount, date, method, notes, payment_type, receipt_number) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->execute([$tenantId, $amount, $paymentDate, $method, $notes, $paymentType, $receipt]);
log_activity($pdo, $_SESSION['user_id'], 'record_payment', "Recorded $paymentType payment #$receipt for tenant #$tenantId");
echo json_encode(['success' => true, 'receipt_number' => $receipt]);
