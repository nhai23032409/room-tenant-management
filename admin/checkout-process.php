<?php
// checkout-process.php - Checkout process for admin
session_start();
include('../includes/config.php');
include('../includes/permissions.php');
// The old page bypassed financial settlement. The workflow page is the only
// supported checkout entry point.
header('Location: ../workflow.php');
exit;
include('../includes/header.php');
include('../includes/sidebar.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../mobile_app.php");
    exit;
}

$message = "";
$error = "";

// Get tenant for checkout
$tenant_id = $_GET['tenant_id'] ?? 0;

if ($tenant_id) {
    $stmt = $pdo->prepare("SELECT t.*, r.room_number, h.name as hostel_name, c.deposit, c.start_date FROM tenants t JOIN beds b ON t.bed_id = b.id JOIN rooms r ON b.room_id = r.id JOIN hostels h ON r.hostel_id = h.id LEFT JOIN contracts c ON t.id = c.tenant_id AND c.status = 'active' WHERE t.id = ?");
    $stmt->execute([$tenant_id]);
    $tenant = $stmt->fetch();
}

// Process checkout
if (isset($_POST['submit_checkout'])) {
    $tenant_id = $_POST['tenant_id'];
    $checkout_date = $_POST['checkout_date'];
    
    $pdo->beginTransaction();
    try {
        // Update tenant status
        $stmt = $pdo->prepare("UPDATE tenants SET status = 'checked_out', checkout_date = ? WHERE id = ?");
        $stmt->execute([$checkout_date, $tenant_id]);
        
        // Update bed status to available
        $stmt = $pdo->prepare("UPDATE beds b JOIN tenants t ON b.id = t.bed_id SET b.status = 'available' WHERE t.id = ?");
        $stmt->execute([$tenant_id]);
        
        // Update contract status
        $stmt = $pdo->prepare("UPDATE contracts SET status = 'ended' WHERE tenant_id = ? AND status = 'active'");
        $stmt->execute([$tenant_id]);
        
        $pdo->commit();
        $message = "Trả phòng thành công!";
        log_activity($pdo, $_SESSION['user_id'], 'checkout', "Checked out tenant ID $tenant_id");
    } catch (Exception $e) {
        $pdo->rollback();
        $error = "Lỗi: " . $e->getMessage();
    }
}
?>

<div class="container mt-5" style="margin-left:220px;">
    <h2>Đăng ký trả phòng</h2>
    
    <?php if ($tenant): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5>Thông tin khách hàng</h5>
            </div>
            <div class="card-body">
                <p><strong>Họ tên:</strong> <?php echo htmlspecialchars($tenant['name']); ?></p>
                <p><strong>Phòng:</strong> <?php echo $tenant['room_number']; ?> - <?php echo $tenant['hostel_name']; ?></p>
                <p><strong>SĐT:</strong> <?php echo $tenant['phone']; ?></p>
                <p><strong>Tiền cọc:</strong> <?php echo number_format($tenant['deposit'] ?? 0); ?>₫</p>
                <p><strong>Ngày vào ở:</strong> <?php echo $tenant['checkin_date']; ?></p>
            </div>
        </div>
        
        <form method="POST">
            <input type="hidden" name="tenant_id" value="<?php echo $tenant_id; ?>">
            
            <div class="mb-3">
                <label class="form-label">Ngày trả phòng</label>
                <input type="date" name="checkout_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            
            <button type="submit" name="submit_checkout" class="btn btn-primary">Xác nhận trả phòng</button>
        </form>
    <?php else: ?>
        <p class="text-muted">Không tìm thấy khách hàng.</p>
    <?php endif; ?>
</div>

<?php include('../includes/footer.php'); ?>
