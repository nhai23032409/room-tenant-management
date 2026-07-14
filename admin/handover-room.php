<?php
// handover-room.php - Room handover process
session_start();
include('../includes/config.php');
include('../includes/permissions.php');
include('../includes/header.php');
include('../includes/sidebar.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../mobile_app.php");
    exit;
}
require_permission('handover_room');

$message = "";
$error = "";

// Get tenant for handover
$tenant_id = $_GET['tenant_id'] ?? 0;

if ($tenant_id) {
    $stmt = $pdo->prepare("SELECT t.*, b.status AS bed_status, r.id AS room_id, r.room_number, h.id AS hostel_id, h.name AS hostel_name FROM tenants t JOIN beds b ON t.bed_id = b.id JOIN rooms r ON b.room_id = r.id JOIN hostels h ON r.hostel_id = h.id WHERE t.id = ? AND h.id = ?");
    $stmt->execute([$tenant_id, $_SESSION['hostel_id']]);
    $tenant = $stmt->fetch();
}

// Process handover
if (isset($_POST['submit_handover'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Yêu cầu không hợp lệ. Vui lòng tải lại trang.';
    } else {
    $tenant_id = $_POST['tenant_id'];
    $bed_id = $_POST['bed_id'];
    $assets_status = $_POST['assets_status'] ?? [];
    
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT c.id FROM contracts c JOIN rooms r ON r.id = c.room_id WHERE c.tenant_id = ? AND c.bed_id = ? AND c.status = 'active' AND c.signature_path IS NOT NULL AND r.hostel_id = ? FOR UPDATE");
        $stmt->execute([$tenant_id, $bed_id, $_SESSION['hostel_id']]);
        if (!$stmt->fetch()) {
            throw new RuntimeException('Khách chưa có hợp đồng đã ký hợp lệ.');
        }
        $stmt = $pdo->prepare("SELECT id FROM payments WHERE tenant_id = ? AND payment_type = 'rent' AND method IN ('cash', 'bank_transfer') LIMIT 1");
        $stmt->execute([$tenant_id]);
        if (!$stmt->fetch()) {
            throw new RuntimeException('Cần xác nhận đã thu tiền thuê kỳ đầu trước khi bàn giao.');
        }
        $stmt = $pdo->prepare("SELECT b.status FROM beds b JOIN rooms r ON r.id = b.room_id WHERE b.id = ? AND r.hostel_id = ? FOR UPDATE");
        $stmt->execute([$bed_id, $_SESSION['hostel_id']]);
        if ($stmt->fetchColumn() !== 'deposited') {
            throw new RuntimeException('Giường không ở trạng thái đã đặt cọc.');
        }
        // Only assets belonging to the tenant's room may be changed.
        foreach ($assets_status as $asset_id => $status) {
            if (!in_array($status, ['good', 'damaged', 'missing'], true)) {
                throw new RuntimeException('Trạng thái tài sản không hợp lệ.');
            }
            $stmt = $pdo->prepare("UPDATE room_assets SET status = ? WHERE id = ? AND room_id = (SELECT room_id FROM beds WHERE id = ?)");
            $stmt->execute([$status, $asset_id, $bed_id]);
        }
        
        // Update bed status to occupied
        $stmt = $pdo->prepare("UPDATE beds SET status = 'occupied' WHERE id = ?");
        $stmt->execute([$bed_id]);
        
        // Activate the tenancy and preserve a check-in audit record.
        $stmt = $pdo->prepare("UPDATE tenants SET status = 'active', checkin_date = CURDATE() WHERE id = ? AND bed_id = ?");
        $stmt->execute([$tenant_id, $bed_id]);
        $stmt = $pdo->prepare("INSERT INTO checkin_history (tenant_id, bed_id, checkin_date, rent_amount, security_deposit) SELECT id, bed_id, CURDATE(), monthly_rent, security_deposit FROM tenants WHERE id = ?");
        $stmt->execute([$tenant_id]);
        
        $pdo->commit();
        $message = "Bàn giao phòng thành công!";
        log_activity($pdo, $_SESSION['user_id'], 'handover_room', "Handed over room for tenant ID $tenant_id");
    } catch (Exception $e) {
        $pdo->rollback();
        $error = "Lỗi: " . $e->getMessage();
    }
    }
}
?>

<div class="container mt-5" style="margin-left:220px;">
    <h2>Bàn giao phòng</h2>
    
    <?php if ($tenant): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5>Thông tin khách hàng</h5>
            </div>
            <div class="card-body">
                <p><strong>Họ tên:</strong> <?php echo htmlspecialchars($tenant['name']); ?></p>
                <p><strong>Phòng:</strong> <?php echo $tenant['room_number']; ?> - <?php echo $tenant['hostel_name']; ?></p>
                <p><strong>SĐT:</strong> <?php echo $tenant['phone']; ?></p>
            </div>
        </div>
        
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">
            <input type="hidden" name="tenant_id" value="<?php echo $tenant_id; ?>">
            <input type="hidden" name="room_id" value="<?php echo $tenant['room_id'] ?? ''; ?>">
            <input type="hidden" name="bed_id" value="<?php echo $tenant['bed_id'] ?? ''; ?>">
            
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Kiểm tra tài sản phòng</h5>
                </div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Tài sản</th>
                                <th>Số lượng</th>
                                <th>Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt = $pdo->prepare("SELECT * FROM room_assets WHERE room_id = ?");
                            $stmt->execute([$tenant['room_id'] ?? 0]);
                            $assets = $stmt->fetchAll();
                            foreach ($assets as $asset):
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($asset['asset_name']); ?></td>
                                <td><?php echo $asset['quantity']; ?></td>
                                <td>
                                    <select name="assets_status[<?php echo $asset['id']; ?>]" class="form-select">
                                        <option value="good" <?php if($asset['status'] == 'good') echo 'selected'; ?>>Tốt</option>
                                        <option value="damaged" <?php if($asset['status'] == 'damaged') echo 'selected'; ?>>Hư hỏng</option>
                                        <option value="missing" <?php if($asset['status'] == 'missing') echo 'selected'; ?>>Mất mát</option>
                                    </select>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <button type="submit" name="submit_handover" class="btn btn-primary">Xác nhận bàn giao</button>
        </form>
    <?php else: ?>
        <p class="text-muted">Không tìm thấy khách hàng.</p>
    <?php endif; ?>
</div>

<?php include('../includes/footer.php'); ?>
