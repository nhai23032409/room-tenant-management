<?php
// generate-contract-pdf.php - Generate PDF contract
require_once 'includes/config.php';

// Simple PDF generation without external library (using FPDF-like approach)
// For production, consider using TCPDF or mPDF

function generate_contract_pdf($contract_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT c.*, t.name as tenant_name, t.phone, t.email, t.address, r.room_number, r.monthly_rent, h.name as hostel_name, h.address as hostel_address FROM contracts c JOIN tenants t ON c.tenant_id = t.id JOIN rooms r ON c.room_id = r.id JOIN hostels h ON r.hostel_id = h.id WHERE c.id = ?");
    $stmt->execute([$contract_id]);
    $contract = $stmt->fetch();
    
    if (!$contract) {
        return false;
    }
    
    // Create PDF content (simple text-based, for production use TCPDF/mPDF)
    $pdf_content = "
HỢP ĐỒNG THUÊ PHÒNG TRỌ
========================

Bên thuê: {$contract['tenant_name']}
Số điện thoại: {$contract['phone']}
Email: {$contract['email']}
Địa chỉ: {$contract['address']}

Bên cho thuê: {$contract['hostel_name']}
Địa chỉ: {$contract['hostel_address']}

PHÙNG TRƯỢC:
- Phòng số: {$contract['room_number']}
- Giá thuê: ₹" . number_format($contract['monthly_rent']) . "/tháng
- Ngày bắt đầu: {$contract['start_date']}
- Ngày kết thúc: {$contract['end_date'] ?? 'Không thời hạn'}

TIỀN CỌC:
- Số tiền: ₹" . number_format($contract['deposit']) . "
- Tỷ lệ hoàn cọc:
  * Chưa ký HĐ: 80%
  * < 6 tháng: 50%
  * > 6 tháng: 70%
  * Hết hạn HĐ: 100%

NỘI QUY:
1. Giữ vệ sinh phòng ốc
2. Không chuyển phòng cho người khác
3. Thanh toán đúng hạn
4. Báo trước khi có khách đến

XÁC NHẬN:
- Khách hàng ký: _________________
- Quản lý ký: _________________
- Ngày: " . date('d/m/Y') . "
";
    
    return $pdf_content;
}

// Handle request
if (isset($_GET['contract_id'])) {
    $contract_id = $_GET['contract_id'];
    $pdf_content = generate_contract_pdf($contract_id);
    
    if ($pdf_content) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="contract_' . $contract_id . '.pdf"');
        echo $pdf_content;
    } else {
        echo "Contract not found";
    }
}
?>