<?php
// admin/edit-hostel.php - Edit hostel details
session_start();
include('../includes/config.php');
include('../includes/permissions.php');
include('../includes/header.php');
include('../includes/sidebar.php');

// Check if user is an admin
if (!isset($_SESSION['user_id']) || !is_admin()) {
    header("Location: ../dashboard.php");
    exit;
}

$message = "";
$error = "";
$hostel_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// Redirect if ID is not provided or invalid
if (!$hostel_id) {
    header("Location: manage-hostels.php");
    exit;
}

// Handle form submission for updating the hostel
if (isset($_POST['update_hostel'])) {
    if (!verify_csrf($_POST['csrf_token'])) {
        $error = "Lỗi xác thực. Vui lòng thử lại.";
    } else {
        $name = sanitize_input($_POST['name']);
        $address = sanitize_input($_POST['address']);
        $phone = sanitize_input($_POST['phone']);
        $email = sanitize_input($_POST['email']);
        $description = sanitize_input($_POST['description']);
        $status = sanitize_input($_POST['status']);
        
        // Basic validation
        if (empty($name) || empty($address)) {
            $error = "Tên chi nhánh và địa chỉ là bắt buộc.";
        } elseif (!in_array($status, ['active', 'inactive'])) {
            $error = "Trạng thái không hợp lệ.";
        } else {
            $stmt = $pdo->prepare("UPDATE hostels SET name = ?, address = ?, phone = ?, email = ?, description = ?, status = ? WHERE id = ?");
            if ($stmt->execute([$name, $address, $phone, $email, $description, $status, $hostel_id])) {
                log_activity($pdo, $_SESSION['user_id'], 'update_hostel', "Updated details for hostel ID: $hostel_id");
                $_SESSION['flash_message'] = "Chi nhánh đã được cập nhật thành công!";
                header("Location: manage-hostels.php");
                exit;
            } else {
                $error = "Lỗi khi cập nhật chi nhánh. Vui lòng thử lại.";
            }
        }
    }
}

// Get hostel details for pre-filling the form
$stmt = $pdo->prepare("SELECT * FROM hostels WHERE id = ?");
$stmt->execute([$hostel_id]);
$hostel = $stmt->fetch();

// Redirect if hostel not found
if (!$hostel) {
    $_SESSION['flash_error'] = "Không tìm thấy chi nhánh!";
    header("Location: manage-hostels.php");
    exit;
}
?>

<div class="container mt-5">
    <h2>Sửa thông tin Chi nhánh</h2>
    
    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['flash_message']; unset($_SESSION['flash_message']); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h5>Chỉnh sửa: <?php echo htmlspecialchars($hostel['name']); ?></h5>
        </div>
        <div class="card-body">
            <form method="POST" action="edit-hostel.php?id=<?php echo $hostel['id']; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Tên Chi nhánh</label>
                        <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($hostel['name']); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Số điện thoại</label>
                        <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($hostel['phone']); ?>">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Địa chỉ</label>
                    <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($hostel['address']); ?>" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($hostel['email']); ?>">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Mô tả</label>
                    <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($hostel['description']); ?></textarea>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Trạng thái</label>
                    <select name="status" class="form-select">
                        <option value="active" <?php if ($hostel['status'] === 'active') echo 'selected'; ?>>Hoạt động</option>
                        <option value="inactive" <?php if ($hostel['status'] === 'inactive') echo 'selected'; ?>>Không hoạt động</option>
                    </select>
                </div>
                
                <a href="manage-hostels.php" class="btn btn-secondary">Hủy bỏ</a>
                <button type="submit" name="update_hostel" class="btn btn-primary">Cập nhật Chi nhánh</button>
            </form>
        </div>
    </div>
</div>

<?php include('../includes/footer.php'); ?>
