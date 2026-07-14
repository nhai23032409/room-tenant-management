# Product Requirements Document (PRD)
## HomeStay Dorm Management System

---

## 1. Tổng quan sản phẩm

Hệ thống quản lý vận hành ký túc xá tư nhân HomeStay Dorm, cho phép khách hàng đăng ký thuê phòng/giường, nhân viên sale tư vấn và xử lý đặt cọc, quản lý kiểm tra điều kiện và bàn giao, kế toán thực hiện đối soát tài chính. Hệ thống đảm bảo tính nhất quán dữ liệu, minh bạch quy trình và giảm thiểu xung đột đặt phòng chồng chéo.

## 2. Các Actor & Quyền hạn

| Actor | Quyền hạn chính |
|-------|-----------------|
| **Khách hàng** | Xem danh sách phòng trống, đăng ký xem phòng, theo dõi trạng thái đặt cọc, xem hợp đồng, thanh toán, yêu cầu trả phòng. |
| **Sale** | Tạo/Sửa/Xóa khách hàng, sắp xếp lịch xem phòng, tạo yêu cầu đặt cọc, cập nhật thông tin khách, theo dõi pipeline khách hàng. |
| **Quản lý** | Duyệt đặt cọc, kiểm tra điều kiện lưu trú, thực hiện bàn giao đầu/cuối, xác nhận trả phòng, quản lý tài sản phòng. |
| **Kế toán** | Tính toán tiền cọc, lập hóa đơn thuê/phí, đối soát hoàn cọc, khấu trừ chi phí, báo cáo thu chi. |
| **Admin** | Toàn quyền hệ thống, cấu hình danh mục, phân quyền người dùng, xem báo cáo tổng hợp. |

## 3. Yêu cầu chức năng (Functional Requirements)

### FR-1. Quản lý danh mục (Admin)
- **FR-1.1**: Quản lý chi nhánh (tên, địa chỉ, số điện thoại, trạng thái).
- **FR-1.2**: Quản lý phòng (mã phòng, chi nhánh, tầng, loại phòng, sức chứa tối đa, giới tính cho phép, giá thuê/giường, trang bị, trạng thái: trống/đã cọc/đã thuê/bảo trì).
- **FR-1.3**: Quản lý giường (thuộc phòng nào, mã giường, trạng thái).
- **FR-1.4**: Quản lý loại phòng (tên, mô tả, tiêu chuẩn).
- **FR-1.5**: Quản lý dịch vụ (điện, nước, wifi, gửi xe, giá đơn vị).
- **FR-1.6**: Quản lý tài sản phòng (danh mục vật dụng: giường, nệm, tủ, chìa khóa, thẻ từ…).

### FR-2. Quy trình đăng ký thuê phòng (Sale + Khách)
- **FR-2.1**: Khách hàng liên hệ sale (qua form/web/phone). Sale tạo hồ sơ khách hàng.
- **FR-2.2**: Hệ thống lưu thông tin khách: họ tên, SĐT, email, CCCD, giới tính, quốc tịch, nhu cầu (số người, khu vực, loại phòng, mức giá, thời gian vào ở, thời hạn thuê, tiêu chí ưu tiên).
- **FR-2.3**: Sale kiểm tra phòng/giường còn trống **và chưa được đặt cọc** (status = AVAILABLE), lọc theo điều kiện khách (giới tính, khu vực, sức chứa, giá, tiêu chí). Phòng/giường đã ở trạng thái DEPOSITED không được giới thiệu cho khách khác.
- **FR-2.4**: Sale sắp xếp lịch xem phòng (ngày, giờ, chi nhánh, phòng/giường). Hệ thống gửi thông báo (email/SMS) cho khách.
- **FR-2.5**: Sale dẫn khách xem phòng. Sau buổi xem, cập nhật kết quả: Khách đặt cọc ngay / Hẹn xem thêm / Từ chối.
- **FR-2.6**: Hỗ trợ hai hình thức: thuê nguyên phòng hoặc ở ghép.

### FR-3. Quy trình đặt cọc & xác nhận (Sale + Kế toán + Quản lý)
- **FR-3.1**: Khách xác nhận đồng ý nội quy và điều kiện thuê. Nếu thông tin thay đổi so với đăng ký, khách cập nhật trước khi cọc.
- **FR-3.2**: Sale chuyển thông tin sang kế toán. Kế toán tính tiền cọc theo công thức: `Tiền cọc = (Tiền thuê 2 tháng) × (Số giường thuê)`. Thuê nguyên phòng = số giường theo sức chứa tối đa.
- **FR-3.3**: Hệ thống tạo yêu cầu thanh toán cọc với hạn 24 giờ. Quá hạn tự động hủy và cập nhật trạng thái phòng/giường về trống.
- **FR-3.4**: Khách thanh toán (tiền mặt hoặc chuyển khoản). Sale/Kế toán ghi nhận chứng từ.
- **FR-3.5**: Quản lý đối chiếu chứng từ và xác nhận đã nhận cọc hợp lệ.
- **FR-3.6**: Sau xác nhận, hệ thống khóa phòng/giường (không cho đặt cọc/giới thiệu cho khách khác), ghi nhận thông tin cọc (khách, phòng/giường, thời điểm, chi nhánh).
- **FR-3.7**: Sale thông báo hoàn tất đặt cọc và thống nhất lịch nhận phòng.

### FR-4. Quy trình nhận phòng, ký thỏa thuận và bàn giao (Sale + Quản lý + Kế toán)
- **FR-4.1**: Khách đến nhận phòng theo lịch hẹn. Sale kiểm tra thông tin đặt cọc, đối chiếu giấy tờ (CCCD), thu thập thông tin cư trú.
- **FR-4.2**: Nhóm thuê: đại diện cung cấp thông tin thành viên. Hệ thống kiểm tra số lượng người phù hợp số giường/phòng đã cọc và tuân thủ giới tính/khu vực.
- **FR-4.3**: Quản lý kiểm tra điều kiện lưu trú (giới tính, quốc tịch, giấy tờ, tài chính nếu có). 
  - Cá nhân không đạt → từ chối ký hợp đồng.
  - Nhóm: thành viên không đạt → loại khỏi danh sách. Nhóm có thể tiếp tục (nếu số người còn lại phù hợp giường/phòng) hoặc dừng thủ tục.
- **FR-4.4**: Hệ thống tự động sinh hợp đồng thuê với các trường: phòng/giường, số giường, giá thuê, kỳ thanh toán, phí dịch vụ, quy định hoàn/khấu trừ cọc, nội quy, xử lý vi phạm.
- **FR-4.5**: Khách ký xác nhận (chữ ký số hoặc upload ảnh chữ ký). Hệ thống lưu hợp đồng PDF.
- **FR-4.6**: Kế toán tính các khoản cần thanh toán khi vào ở (thuê kỳ đầu + phí). Khách thanh toán.
- **FR-4.7**: Kế toán xác nhận đã thu đủ → cho phép bàn giao.
- **FR-4.8**: Quản lý bàn giao phòng/giường: kiểm tra hiện trạng, ghi nhận vật dụng cấp (giường, nệm, tủ, chìa khóa/thẻ từ), hướng dẫn tiện ích chung, lưu ý an toàn.
- **FR-4.9**: Sinh biên bản bàn giao đầu. Nhân viên và khách cùng xác nhận (chữ ký). Hệ thống cập nhật trạng thái phòng/giường thành "đã thuê", bắt đầu tính thời gian cư trú.

### FR-5. Quy trình trả phòng và hoàn cọc (Sale + Quản lý + Kế toán)
- **FR-5.1**: Khách (hoặc đại diện) liên hệ sale đăng ký trả phòng, cung cấp thông tin hợp đồng/phiếu cọc.
- **FR-5.2**: Quản lý kiểm tra tình trạng phòng/giường (tài sản, vệ sinh, hư hỏng), đối chiếu hợp đồng.
- **FR-5.3**: Quản lý chuyển thông tin cho kế toán để tính toán.
- **FR-5.4**: Kế toán xác định tỷ lệ hoàn cọc cơ bản:
  - Chưa ký hợp đồng (do không đạt điều kiện hoặc khách hủy): **80%**.
  - Đã ký, chưa hết hạn, lưu trú **< 6 tháng**: **50%**.
  - Đã ký, chưa hết hạn, lưu trú **> 6 tháng**: **70%**.
  - Hết hạn hợp đồng: **100%**.
- **FR-5.5**: Kế toán khấu trừ các chi phí phát sinh: tiền thuê còn nợ, điện nước/dịch vụ nợ, chi phí sửa chữa/bồi thường hư hỏng/mất mát, phạt vi phạm.
- **FR-5.6**: Hệ thống tính kết quả đối soát:
  - Còn dư → hoàn trả khách.
  - Chi phí > tiền cọc hoàn → khách thanh toán thêm.
- **FR-5.7**: Kế toán lập bảng đối soát/phiếu thanh toán. Quản lý thông báo chi tiết cho khách.
- **FR-5.8**: Khách xác nhận đồng ý trả phòng theo kết quả đối soát. Thanh toán thêm (nếu có) hoặc thống nhất phương thức hoàn (tiền mặt/chuyển khoản).
- **FR-5.9**: Khách ký biên bản trả phòng. Thanh lý hợp đồng (cập nhật trạng thái "đã kết thúc").
- **FR-5.10**: Kế toán thực hiện hoàn cọc (nếu có dư). Hệ thống ghi nhận hoàn tất thủ tục.
- **FR-5.11**: Thu hồi chìa khóa/thẻ, cập nhật tài sản, xác nhận kết thúc lưu trú. Phòng/giường chuyển trạng thái "trống".

### FR-6. Quản lý thanh toán & Hóa đơn (Kế toán)
- **FR-6.1**: Ghi nhận các khoản thanh toán: tiền cọc, tiền thuê kỳ, tiền điện/nước/dịch vụ, phạt, hoàn cọc.
- **FR-6.2**: Hỗ trợ phương thức: tiền mặt, chuyển khoản.
- **FR-6.3**: In phiếu thu, phiếu chi, hóa đơn dịch vụ.
- **FR-6.4**: Theo dõi công nợ khách hàng.

### FR-7. Báo cáo & Thống kê
- **FR-7.1**: Báo cáo tình trạng phòng/giường theo chi nhánh (trống, đã cọc, đã thuê, bảo trì).
- **FR-7.2**: Báo cáo doanh thu theo kỳ (thuê phòng, dịch vụ).
- **FR-7.3**: Báo cáo công nợ khách hàng.
- **FR-7.4**: Báo cáo hoàn cọc và khấu trừ.
- **FR-7.5**: Báo cáo tài sản phòng (hư hỏng, mất mát).

## 4. Yêu cầu phi chức năng (Non-Functional Requirements)

| Mã | Yêu cầu | Mức độ |
|----|---------|--------|
| NFR-1 | Hệ thống phải xử lý đồng thời ít nhất 50 người dùng mà không giảm hiệu năng | Phải có |
| NFR-2 | Thời gian phản hồi API trung bình < 500ms (p95 < 1s) | Phải có |
| NFR-3 | Uptime ≥ 99.5% (ngoại trừ bảo trì định kỳ) | Phải có |
| NFR-4 | Dữ liệu thanh toán, hợp đồng phải được mã hóa và sao lưu định kỳ | Phải có |
| NFR-5 | Tuân thủ quy định bảo vệ dữ liệu cá nhân (PDPA/ND168) | Phải có |
| NFR-6 | Giao diện responsive, hỗ trợ desktop (chính) và tablet (phụ) | Nên có |
| NFR-7 | Hỗ trợ đa chi nhánh, dữ liệu phân tách theo chi nhánh | Phải có |
| NFR-8 | Audit log đầy đủ cho mọi thao tác thay đổi trạng thái phòng, cọc, hợp đồng | Phải có |

## 5. Luồng dữ liệu chính (Data Flow)

```
Khách hàng → Đăng ký nhu cầu → Sale tạo lead → Kiểm tra phòng trống → Lên lịch xem phòng
    → Xem phòng → Đặt cọc (24h) → Kế toán xác nhận thanh toán → Quản lý khóa phòng
    → Nhận phòng → Kiểm tra điều kiện → Ký hợp đồng → Thanh toán kỳ đầu → Bàn giao tài sản
    → Cư trú → Đăng ký trả phòng → Kiểm tra tài sản → Đối soát cọc → Khấu trừ phí → Hoàn/Thu thêm
    → Ký biên bản trả → Thanh lý HĐ → Cập nhật phòng trống
```

## 6. Rủi ro & Giải pháp

| Rủi ro | Tác động | Giải pháp |
|--------|----------|-----------|
| Hai sale cùng giới thiệu một phòng cho 2 khách | Cao | Optimistic locking + trạng thái "đang giữ chỗ" tạm thời khi tạo yêu cầu cọc |
| Khách không thanh toán cọc trong 24h | Trung bình | Job tự động hủy + thông báo + mở lại phòng |
| Khách hủy sau khi ký HĐ nhưng chưa vào ở | Trung bình | Quy định rõ tỷ lệ hoàn cọc trong hợp đồng, hệ thống tính tự động |
| Mất dữ liệu thanh toán | Cao | Transaction DB + audit log + backup định kỳ |
| Phân quyền không chặt | Cao | RBAC kiểm tra ở cả frontend (UI) và backend (API) |

## 7. Phụ lục: Quy tắc nghiệp vụ (Business Rules)

- **BR-1**: Một phòng/giường ở trạng thái "đã cọc" không được phép giới thiệu cho khách khác.
- **BR-2**: Tiền cọc = 2 tháng thuê × số giường. Thuê nguyên phòng = sức chứa tối đa.
- **BR-3**: Yêu cầu cọc tự động hủy sau 24h nếu chưa thanh toán.
- **BR-4**: Khách thuê nhóm phải có cùng giới tính (trừ phòng gia đình nếu có).
- **BR-5**: Khách không đáp ứng điều kiện lưu trú không được ký hợp đồng.
- **BR-6**: Tỷ lệ hoàn cọc cố định theo 4 mốc thời gian (80%, 50%, 70%, 100%).
- **BR-7**: Phòng chỉ chuyển trạng thái "trống" sau khi hoàn tất thủ tục trả phòng và thanh lý HĐ.

---
*Phiên bản: 1.0 | Ngày cập nhật: 2026-07-06*
