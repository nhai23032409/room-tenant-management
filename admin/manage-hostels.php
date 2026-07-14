<?php
// admin/manage-hostels.php - Hostel (Branch) management for admin
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

// Handle form submission for adding a new hostel
if (isset($_POST['add_hostel'])) {
    // Verify CSRF token
    if (!verify_csrf($_POST['csrf_token'])) {
        $error = "Lỗi xác thực. Vui lòng thử lại.";
    } else {
        $name = sanitize_input($_POST['name']);
        $address = sanitize_input($_POST['address']);
        $phone = sanitize_input($_POST['phone']);
        $email = sanitize_input($_POST['email']);
        $description = sanitize_input($_POST['description']);
        
        // Basic validation
        if (empty($name) || empty($address)) {
            $error = "Tên chi nhánh và địa chỉ là bắt buộc.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO hostels (name, address, phone, email, description) VALUES (?, ?, ?, ?, ?)");
            if ($stmt->execute([$name, $address, $phone, $email, $description])) {
                log_activity($pdo, $_SESSION['user_id'], 'add_hostel', "Added new hostel: $name");
                $message = "Chi nhánh đã được thêm thành công!";
            } else {
                $error = "Lỗi khi thêm chi nhánh. Vui lòng thử lại.";
            }
        }
    }
}

// Handle status toggle
if (isset($_POST['toggle_status'])) {
    if (!verify_csrf($_POST['csrf_token'])) {
        $error = "Lỗi xác thực. Vui lòng thử lại.";
    } else {
        $hostel_id = filter_input(INPUT_POST, 'hostel_id', FILTER_VALIDATE_INT);
        $current_status = sanitize_input($_POST['current_status']);
        $new_status = $current_status === 'active' ? 'inactive' : 'active';
        
        $stmt = $pdo->prepare("UPDATE hostels SET status = ? WHERE id = ?");
        if ($stmt->execute([$new_status, $hostel_id])) {
            log_activity($pdo, $_SESSION['user_id'], 'toggle_hostel_status', "Changed status of hostel ID $hostel_id to $new_status");
            $message = "Trạng thái chi nhánh đã được cập nhật.";
        } else {
            $error = "Lỗi khi cập nhật trạng thái.";
        }
    }
}

// Get all hostels
$stmt = $pdo->query("SELECT * FROM hostels ORDER BY name");
$hostels = $stmt->fetchAll();
?>

<div class="container mt-5">
    <h2>Quản lý Chi nhánh (Hostels)</h2>
    
    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="card mb-4">
        <div class="card-header">
            <h5>Thêm Chi nhánh mới</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="manage-hostels.php">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Tên Chi nhánh</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Số điện thoại</label>
                        <input type="text" name="phone" class="form-control">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Địa chỉ</label>
                    <input type="text" name="address" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">Mô tả</label>
                    <textarea name="description" class="form-control" rows="2"></textarea>
                </div>
                <button type="submit" name="add_hostel" class="btn btn-primary">Thêm Chi nhánh</button>
            </form>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h5>Danh sách Chi nhánh</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Tên</th>
                            <th>Địa chỉ</th>
                            <th>Điện thoại</th>
                            <th>Trạng thái</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($hostels)): ?>
                            <tr>
                                <td colspan="6" class="text-center">Chưa có chi nhánh nào.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($hostels as $hostel): ?>
                            <tr>
                                <td><?php echo $hostel['id']; ?></td>
                                <td><?php echo htmlspecialchars($hostel['name']); ?></td>
                                <td><?php echo htmlspecialchars($hostel['address']); ?></td>
                                <td><?php echo htmlspecialchars($hostel['phone']); ?></td>
                                <td>
                                    <?php if ($hostel['status'] === 'active'): ?>
                                        <span class="badge bg-success">Hoạt động</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Không hoạt động</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="edit-hostel.php?id=<?php echo $hostel['id']; ?>" class="btn btn-sm btn-outline-primary">Sửa</a>
                                    <form method="POST" action="manage-hostels.php" style="display:inline-block;" onsubmit="return confirm('Bạn có chắc muốn thay đổi trạng thái chi nhánh này không?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                        <input type="hidden" name="hostel_id" value="<?php echo $hostel['id']; ?>">
                                        <input type="hidden" name="current_status" value="<?php echo $hostel['status']; ?>">
                                        <button type="submit" name="toggle_status" class="btn btn-sm <?php echo $hostel['status'] === 'active' ? 'btn-outline-warning' : 'btn-outline-success'; ?>">
                                            <?php echo $hostel['status'] === 'active' ? 'Tắt' : 'Bật'; ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include('../includes/footer.php'); ?>
