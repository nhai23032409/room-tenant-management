<?php
// generate-pdf-contract.php - Generate PDF contract using TCPDF
session_start();
require_once 'includes/config.php';
require_once 'includes/permissions.php';
require_api_permission('manage_contracts');

// Check if TCPDF is available
if (!file_exists('vendor/autoload.php') && !file_exists('vendor/tecnickcom/tcpdf/tcpdf.php')) {
    // Fallback to simple text output
    header('Content-Type: text/plain; charset=utf-8');
    $contract_id = $_GET['contract_id'] ?? 0;
    
    $stmt = $pdo->prepare("SELECT c.*, t.name as tenant_name, t.phone, t.email, t.address, r.room_number, r.monthly_rent, h.name as hostel_name, h.address as hostel_address FROM contracts c JOIN tenants t ON c.tenant_id = t.id JOIN rooms r ON c.room_id = r.id JOIN hostels h ON r.hostel_id = h.id WHERE c.id = ? AND h.id = ?");
    $stmt->execute([$contract_id, $_SESSION['hostel_id']]);
    $contract = $stmt->fetch();
    
    if ($contract) {
        echo "HỢP ĐỒNG THUÊ PHÒNG TRỌ\n";
        echo "========================\n\n";
        echo "Bên thuê: {$contract['tenant_name']}\n";
        echo "Số điện thoại: {$contract['phone']}\n";
        echo "Email: {$contract['email']}\n";
        echo "Địa chỉ: {$contract['address']}\n\n";
        echo "Bên cho thuê: {$contract['hostel_name']}\n";
        echo "Địa chỉ: {$contract['hostel_address']}\n\n";
        echo "PHÒNG TRỌ:\n";
        echo "- Phòng số: {$contract['room_number']}\n";
        echo "- Giá thuê: ₹" . number_format($contract['monthly_rent']) . "/tháng\n";
        echo "- Ngày bắt đầu: {$contract['start_date']}\n";
        echo "- Ngày kết thúc: {$contract['end_date'] ?? 'Không thời hạn'}\n\n";
        echo "TIỀN CỌC: ₹" . number_format($contract['deposit']) . "\n\n";
        echo "NỘI QUY:\n";
        echo "1. Giữ vệ sinh phòng ốc\n";
        echo "2. Không chuyển phòng cho người khác\n";
        echo "3. Thanh toán đúng hạn\n";
        echo "4. Báo trước khi có khách đến\n\n";
        echo "XÁC NHẬN:\n";
        echo "- Khách hàng ký: _________________\n";
        echo "- Quản lý ký: _________________\n";
        echo "- Ngày: " . date('d/m/Y') . "\n";
    }
    exit;
}

// Use TCPDF if available
require_once 'vendor/autoload.php';

$contract_id = $_GET['contract_id'] ?? 0;

$stmt = $pdo->prepare("SELECT c.*, t.name as tenant_name, t.phone, t.email, t.address, r.room_number, r.monthly_rent, h.name as hostel_name, h.address as hostel_address FROM contracts c JOIN tenants t ON c.tenant_id = t.id JOIN rooms r ON c.room_id = r.id JOIN hostels h ON r.hostel_id = h.id WHERE c.id = ? AND h.id = ?");
$stmt->execute([$contract_id, $_SESSION['hostel_id']]);
$contract = $stmt->fetch();

if (!$contract) {
    die("Contract not found");
}

// Create new PDF document
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('HomeStay Dorm');
$pdf->SetTitle('Hợp đồng thuê phòng');
$pdf->SetSubject('Hợp đồng thuê phòng trọ');

// Set header and footer
$pdf->SetHeaderData('', 0, 'HỢP ĐỒNG THUÊ PHÒNG TRỌ', 'HomeStay Dorm Management System');
$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

// Set margins
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// Add a page
$pdf->AddPage();

// Set font
$pdf->SetFont('dejavusans', '', 12);

// Contract content
$html = '
<h3 style="text-align: center;">HỢP ĐỒNG THUÊ PHÒNG TRỌ</h3>
<br>

<table border="0" cellpadding="5">
<tr>
    <td width="50%"><strong>Bên thuê:</strong></td>
    <td width="50%"><strong>Bên cho thuê:</strong></td>
</tr>
<tr>
    <td>' . $contract['tenant_name'] . '</td>
    <td>' . $contract['hostel_name'] . '</td>
</tr>
<tr>
    <td>SĐT: ' . $contract['phone'] . '</td>
    <td>Địa chỉ: ' . $contract['hostel_address'] . '</td>
</tr>
</table>

<br>

<h4>THÔNG TIN PHÒNG TRỌ</h4>
<ul>
    <li>Phòng số: ' . $contract['room_number'] . '</li>
    <li>Giá thuê: ₹' . number_format($contract['monthly_rent']) . '/tháng</li>
    <li>Ngày bắt đầu: ' . $contract['start_date'] . '</li>
    <li>Ngày kết thúc: ' . ($contract['end_date'] ?? 'Không thời hạn') . '</li>
</ul>

<h4>THÔNG TIN CỌC</h4>
<ul>
    <li>Số tiền cọc: ₹' . number_format($contract['deposit']) . '</li>
    <li>Tỷ lệ hoàn cọc: 
        <ul>
            <li>Chưa ký HĐ: 80%</li>
            <li>Đã ký, < 6 tháng: 50%</li>
            <li>Đã ký, > 6 tháng: 70%</li>
            <li>Hết hạn HĐ: 100%</li>
        </ul>
    </li>
</ul>

<h4>NỘI QUY</h4>
<ol>
    <li>Giữ vệ sinh phòng ốc</li>
    <li>Không chuyển phòng cho người khác</li>
    <li>Thanh toán đúng hạn</li>
    <li>Báo trước khi có khách đến</li>
</ol>

<br><br>

<table border="0" cellpadding="10">
<tr>
    <td width="50%" style="text-align: center;">
        <p>Khách hàng ký</p>
        <br><br><br>
        <p>_________________<br>(<small>Ký và ghi rõ họ tên</small>)</p>
    </td>
    <td width="50%" style="text-align: center;">
        <p>Quản lý ký</p>
        <br><br><br>
        <p>_________________<br>(<small>Ký và ghi rõ họ tên</small>)</p>
    </td>
</tr>
</table>

<p style="text-align: center; font-size: 10px;">Ngày ' . date('d/m/Y') . '</p>
';

// Output the HTML content
$pdf->writeHTML($html, true, false, true, false, '');

// Close and output PDF
$pdf->Output('contract_' . $contract_id . '.pdf', 'D');
?>
