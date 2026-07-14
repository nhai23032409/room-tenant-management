<?php
// export-payments-pdf.php - Export payments to PDF
require_once 'includes/config.php';

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

$stmt = $pdo->prepare("SELECT p.*, t.name as tenant_name, r.room_number FROM payments p JOIN tenants t ON p.tenant_id = t.id JOIN beds b ON t.bed_id = b.id JOIN rooms r ON b.room_id = r.id WHERE p.date BETWEEN ? AND ? ORDER BY p.date DESC");
$stmt->execute([$start_date, $end_date]);
$payments = $stmt->fetchAll();

// Simple PDF output (fallback)
header('Content-Type: text/plain; charset=utf-8');
echo "PHIẾU THU\n";
echo "========================\n\n";
echo "Từ ngày: $start_date\n";
echo "Đến ngày: $end_date\n\n";

$total = 0;
foreach ($payments as $payment) {
    $total += $payment['amount'];
    echo "Số: {$payment['receipt_number']}\n";
    echo "Khách: {$payment['tenant_name']}\n";
    echo "Phòng: {$payment['room_number']}\n";
    echo "Số tiền: ₹" . number_format($payment['amount']) . "\n";
    echo "Ngày: {$payment['date']}\n";
    echo "Loại: {$payment['payment_type']}\n";
    echo "------------------------\n";
}

echo "\nTỔNG CỘNG: ₹" . number_format($total) . "\n";
?>