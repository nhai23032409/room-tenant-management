# Hệ thống Quản lý Nhà trọ HomeStay

Đây là một hệ thống quản lý nhà trọ, ký túc xá được xây dựng bằng PHP, MySQL và Bootstrap. Ứng dụng cung cấp các tính năng quản lý khách thuê, phòng, giường, thanh toán, hợp đồng và báo cáo.

## Tính năng chính

- **Quản lý Khách thuê:** Đăng ký, check-in, check-out, quản lý thông tin khách.
- **Quản lý Phòng & Giường:** Quản lý theo chi nhánh (hostel), loại phòng, trạng thái giường (trống, đã cọc, đang ở).
- **Quy trình Đặt cọc:** Hỗ trợ tạo yêu cầu đặt cọc, xác nhận và tự động hủy sau 24h.
- **Hợp đồng & Bàn giao:** Tạo hợp đồng điện tử, tích hợp chữ ký số và quy trình bàn giao phòng.
- **Thanh toán & Công nợ:** Ghi nhận các khoản thanh toán, quản lý công nợ và xuất phiếu thu.
- **Phân quyền người dùng:** Hỗ trợ các vai trò Admin, Quản lý (Manager), Kế toán (Accountant), và Nhân viên Kinh doanh (Sale).
- **Báo cáo:** Thống kê về doanh thu, tình trạng phòng, và công nợ.

## Hướng dẫn Cài đặt & Chạy dự án

Cách cài đặt dễ nhất là sử dụng trình hướng dẫn cài đặt tự động của chúng tôi.

### Yêu cầu hệ thống
- **PHP 7.4+** với PDO extension.
- **MySQL 5.7+** hoặc MariaDB.
- **Web Server:** Apache (với mod_rewrite) hoặc Nginx. Khuyến nghị sử dụng **XAMPP** hoặc **WAMP** trên Windows.
- **Composer:** Cần thiết để cài đặt các thư viện PHP.

### Bước 1: Chuẩn bị
1.  Tải và giải nén mã nguồn vào thư mục gốc của web server (ví dụ: `C:\xampp\htdocs\hostel-management`).
2.  Mở terminal hoặc Command Prompt (CMD) tại thư mục gốc của dự án và chạy lệnh sau để cài đặt các thư viện cần thiết:
    ```bash
    composer install
    ```
3.  Đảm bảo web server (ví dụ: Apache) có quyền ghi vào thư mục `uploads/`. Nếu thư mục này chưa tồn tại, hãy tạo nó.

### Bước 2: Chạy Trình cài đặt tự động
1.  Mở trình duyệt web và truy cập vào URL của dự án (ví dụ: `http://localhost/hostel-management/`).
2.  Bạn sẽ được tự động chuyển đến trang cài đặt (`setup.php`).
3.  Làm theo các bước hướng dẫn trên màn hình để cấu hình:
    - **Bước 1**: Kiểm tra kết nối đến cơ sở dữ liệu.
    - **Bước 2**: Tạo cơ sở dữ liệu.
    - **Bước 3**: Cài đặt các bảng dữ liệu.
    - **Bước 4**: Tạo tệp cấu hình `config.php`.
    - **Bước 5**: Tạo tài khoản quản trị (admin).
    - **Bước 6**: Hoàn tất cài đặt.

Trình hướng dẫn sẽ tự động tạo database, import schema và tạo file `includes/config.php` cho bạn.

### Bước 3: Đăng nhập
Sau khi cài đặt hoàn tất, bạn có thể đăng nhập vào hệ thống bằng tài khoản admin vừa tạo.

- **Trang Dashboard:** `http://localhost/hostel-management/dashboard.php`
- Nếu bạn không tạo tài khoản ở bước cài đặt, có thể sử dụng thông tin đăng nhập mặc định:
    - **Email:** admin@tenantmanagement.com
    - **Password:** admin123

---
## Cron Job (Tùy chọn - Dành cho máy chủ)

Để hệ thống tự động hủy các yêu cầu đặt cọc đã hết hạn (sau 24 giờ), bạn cần cấu hình một cron job trên máy chủ để gọi đến API sau mỗi 30 phút.

```bash
*/30 * * * * curl -s "http://yourdomain.com/api/deposit.php?action=cancel_expired_deposits"
```
