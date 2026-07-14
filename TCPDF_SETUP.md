# Hướng dẫn cài đặt TCPDF

## Cài đặt TCPDF bằng Composer

```bash
composer require tecnickcom/tcpdf
```

## Hoặc tải thủ công

1. Tải TCPDF từ: https://tcpdf.org
2. Giải nén vào thư mục `vendor/tcpdf`

## Sử dụng trong dự án

```php
// Ví dụ tạo PDF hợp đồng
require_once 'vendor/tcpdf/tcpdf.php';

$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('HomeStay Dorm');
$pdf->SetTitle('Hợp đồng thuê phòng');
$pdf->SetHeaderData('', 0, 'Hợp đồng thuê phòng', 'HomeStay Dorm Management System');

$pdf->AddPage();
$pdf->writeHTML($contract_content, true, false, true, false, '');
$pdf->Output('contract.pdf', 'D');
```

## File PDF mẫu đã có sẵn

- `generate-contract-pdf.php` - Đã có sẵn logic tính toán, cần chỉ thay đổi output thành PDF thực