<?php
// manage-services.php - Service management for admin
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

$message = "";
$error = "";

// Handle form submission
if (isset($_POST['submit'])) {
    $name = sanitize_input($_POST['name']);
    $unit = sanitize_input($_POST['unit']);
    $price = $_POST['price'];
    $description = sanitize_input($_POST['description']);
    
    $stmt = $pdo->prepare("INSERT INTO services (name, unit, price, description) VALUES (?, ?, ?, ?)");
    if ($stmt->execute([$name, $unit, $price, $description])) {
        $message = "Dịch vụ đã được thêm thành công!";
    } else {
        $error = "Lỗi thêm dịch vụ.";
    }
}

// Get all services
$stmt = $pdo->query("SELECT * FROM services ORDER BY name");
$services = $stmt->fetchAll();
?>

<div class="container mt-5" style="margin-left:220px;">
    <h2>Quản lý dịch vụ</h2>
    
    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo $message; ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <div class="card mb-4">
        <div class="card-header">
            <h5>Thêm dịch vụ mới</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Tên dịch vụ</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Đơn vị</label>
                    <input type="text" name="unit" class="form-control" placeholder="VD: kWh, m3, tháng">
                </div>
                <div class="mb-3">
                    <label class="form-label">Giá</label>
                    <input type="number" name="price" class="form-control" step="0.01" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Mô tả</label>
                    <textarea name="description" class="form-control"></textarea>
                </div>
                <button type="submit" name="submit" class="btn btn-primary">Thêm dịch vụ</button>
            </form>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h5>Danh sách dịch vụ</h5>
        </div>
        <div class="card-body">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tên</th>
                        <th>Đơn vị</th>
                        <th>Giá</th>
                        <th>Mô tả</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($services as $service): ?>
                    <tr>
                        <td><?php echo $service['id']; ?></td>
                        <td><?php echo htmlspecialchars($service['name']); ?></td>
                        <td><?php echo htmlspecialchars($service['unit']); ?></td>
                        <td><?php echo number_format($service['price']); ?>₫</td>
                        <td><?php echo htmlspecialchars($service['description']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include('../includes/footer.php'); ?>