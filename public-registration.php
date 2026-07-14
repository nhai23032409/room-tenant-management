<?php
// public-registration.php - Public registration form for customers
session_start();
include('includes/config.php');

$message = "";
$error = "";

// Get available hostels
$stmt = $pdo->query("SELECT * FROM hostels WHERE status = 'active' ORDER BY name");
$hostels = $stmt->fetchAll();

// Handle form submission
if (isset($_POST['submit'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = "Yêu cầu không hợp lệ. Vui lòng tải lại trang.";
    } else {
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);
    $phone = sanitize_input($_POST['phone']);
    $nationality = sanitize_input($_POST['nationality']);
    $gender = $_POST['gender'] ?? '';
    $id_proof_type = $_POST['id_proof_type'];
    $id_proof_number = sanitize_input($_POST['id_proof_number']);
    $hostel_id = $_POST['hostel_id'];
    $room_type = $_POST['room_type'];
    $num_people = $_POST['num_people'];
    $budget_min = $_POST['budget_min'];
    $budget_max = $_POST['budget_max'];
    $move_in_date = $_POST['move_in_date'];
    $duration = $_POST['duration'];
    
    // Basic validation
    if (empty($name) || empty($phone) || empty($hostel_id) || !in_array($gender, ['male', 'female', 'other'], true)) {
        $error = "Vui lòng điền đầy đủ thông tin bắt buộc.";
    } else {
        $pdo->beginTransaction();
        try {
            // Create tenant record (status: pending)
            $hostel = $pdo->prepare("SELECT id FROM hostels WHERE id = ? AND status = 'active'");
            $hostel->execute([(int)$hostel_id]);
            if (!$hostel->fetch()) {
                throw new RuntimeException('Chi nhánh không còn hoạt động.');
            }
            $stmt = $pdo->prepare("INSERT INTO tenants (name, email, phone, nationality, gender, id_proof_type, id_proof_number, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
            $stmt->execute([$name, $email, $phone, $nationality, $gender, $id_proof_type, $id_proof_number]);
            $tenant_id = $pdo->lastInsertId();
            
            // Create viewing request
            $stmt = $pdo->prepare("INSERT INTO viewings (tenant_id, hostel_id, scheduled_at, status, notes) VALUES (?, ?, ?, 'scheduled', ?)");
            $stmt->execute([$tenant_id, $hostel_id, $move_in_date, "Room type: $room_type, People: $num_people, Budget: $budget_min - $budget_max, Duration: $duration"]);
            
            $pdo->commit();
            $message = "Đăng ký thành công! Chúng tôi sẽ liên hệ sắp xếp lịch xem phòng trong 24h tới.";
        } catch (Exception $e) {
            $pdo->rollback();
            $error = "Lỗi: " . $e->getMessage();
        }
    }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký thuê phòng</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .registration-container {
            max-width: 600px;
            margin: 2rem auto;
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .form-control, .form-select {
            border-radius: 10px;
        }
        .btn {
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="registration-container">
            <h2 class="text-center mb-4">Đăng ký thuê phòng</h2>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">
                <div class="mb-3">
                    <label class="form-label">Họ tên <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="name" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" name="email">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Số điện thoại <span class="text-danger">*</span></label>
                    <input type="tel" class="form-control" name="phone" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Quốc tịch</label>
                    <input type="text" class="form-control" name="nationality" value="Việt Nam">
                </div>
                <div class="mb-3">
                    <label class="form-label">Giới tính <span class="text-danger">*</span></label>
                    <select class="form-select" name="gender" required>
                        <option value="">Chọn giới tính</option>
                        <option value="male">Nam</option>
                        <option value="female">Nữ</option>
                        <option value="other">Khác</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Loại giấy tờ <span class="text-danger">*</span></label>
                    <select class="form-select" name="id_proof_type" required>
                        <option value="cccd">CCCD</option>
                        <option value="passport">Hộ chiếu</option>
                        <option value="driving_license">Giấy phép lái xe</option>
                        <option value="other">Khác</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Số giấy tờ <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="id_proof_number" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Chi nhánh <span class="text-danger">*</span></label>
                    <select class="form-select" name="hostel_id" required>
                        <option value="">Chọn chi nhánh</option>
                        <?php foreach ($hostels as $hostel): ?>
                            <option value="<?php echo $hostel['id']; ?>"><?php echo htmlspecialchars($hostel['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Loại phòng mong muốn</label>
                    <select class="form-select" name="room_type">
                        <option value="single">Phòng đơn</option>
                        <option value="double">Phòng 2 người</option>
                        <option value="triple">Phòng 3 người</option>
                        <option value="shared">Ở ghép</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Số người ở</label>
                    <input type="number" class="form-control" name="num_people" min="1" max="10" value="1">
                </div>
                
                <div class="row mb-3">
                    <div class="col-6">
                        <label class="form-label">Ngân sách tối thiểu</label>
                        <input type="number" class="form-control" name="budget_min" step="0.01">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Ngân sách tối đa</label>
                        <input type="number" class="form-control" name="budget_max" step="0.01">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Ngày muốn vào ở</label>
                    <input type="date" class="form-control" name="move_in_date" value="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Thời hạn thuê</label>
                    <select class="form-select" name="duration">
                        <option value="1">1 tháng</option>
                        <option value="3">3 tháng</option>
                        <option value="6">6 tháng</option>
                        <option value="12">12 tháng</option>
                        <option value="0">Không thời hạn</option>
                    </select>
                </div>
                
                <button type="submit" name="submit" class="btn btn-primary w-100">Đăng ký</button>
            </form>
        </div>
    </div>
</body>
</html>
