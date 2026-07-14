<?php
// reports-detailed.php - Detailed reports for admin
session_start();
include('../includes/config.php');
include('../includes/permissions.php');
include('../includes/header.php');
include('../includes/sidebar.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: ../mobile_app.php");
    exit;
}

$report_type = $_GET['type'] ?? 'occupancy';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
?>

<div class="container mt-5" style="margin-left:220px;">
    <h2>Báo cáo chi tiết</h2>
    
    <div class="card mb-4">
        <div class="card-header">
            <ul class="nav nav-tabs">
                <li class="nav-item">
                    <a class="nav-link <?php if($report_type == 'occupancy') echo 'active'; ?>" href="?type=occupancy">Tình trạng phòng</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php if($report_type == 'revenue') echo 'active'; ?>" href="?type=revenue">Doanh thu</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php if($report_type == 'debts') echo 'active'; ?>" href="?type=debts">Công nợ</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php if($report_type == 'refunds') echo 'active'; ?>" href="?type=refunds">Hoàn cọc</a>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <form method="GET" class="row mb-3">
                <input type="hidden" name="type" value="<?php echo $report_type; ?>">
                <div class="col-md-4">
                    <label class="form-label">Từ ngày</label>
                    <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Đến ngày</label>
                    <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">Xem báo cáo</button>
                </div>
            </form>
            
            <?php if ($report_type == 'occupancy'): ?>
                <h5>Báo cáo tình trạng phòng</h5>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Chi nhánh</th>
                            <th>Tổng giường</th>
                            <th>Trống</th>
                            <th>Đã cọc</th>
                            <th>Đã thuê</th>
                            <th>Bảo trì</th>
                            <th>Tỷ lệ lấp đầy</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $pdo->query("SELECT h.name as hostel_name, COUNT(b.id) as total_beds, SUM(CASE WHEN b.status = 'available' THEN 1 ELSE 0 END) as available_beds, SUM(CASE WHEN b.status = 'deposited' THEN 1 ELSE 0 END) as deposited_beds, SUM(CASE WHEN b.status = 'occupied' THEN 1 ELSE 0 END) as occupied_beds, SUM(CASE WHEN b.status = 'maintenance' THEN 1 ELSE 0 END) as maintenance_beds, ROUND((SUM(CASE WHEN b.status = 'occupied' THEN 1 ELSE 0 END) / COUNT(b.id)) * 100, 2) as occupancy_rate FROM hostels h LEFT JOIN rooms r ON h.id = r.hostel_id LEFT JOIN beds b ON r.id = b.room_id GROUP BY h.id, h.name");
                        $occupancy = $stmt->fetchAll();
                        foreach ($occupancy as $row):
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['hostel_name']); ?></td>
                            <td><?php echo $row['total_beds']; ?></td>
                            <td><span class="badge bg-success"><?php echo $row['available_beds']; ?></span></td>
                            <td><span class="badge bg-warning"><?php echo $row['deposited_beds']; ?></span></td>
                            <td><span class="badge bg-danger"><?php echo $row['occupied_beds']; ?></span></td>
                            <td><span class="badge bg-secondary"><?php echo $row['maintenance_beds']; ?></span></td>
                            <td><strong><?php echo $row['occupancy_rate']; ?>%</strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
            <?php elseif ($report_type == 'revenue'): ?>
                <h5>Báo cáo doanh thu</h5>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Ngày</th>
                            <th>Số giao dịch</th>
                            <th>Tổng tiền</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $pdo->prepare("SELECT DATE(date) as payment_date, COUNT(*) as transactions, SUM(amount) as total_amount FROM payments WHERE date BETWEEN ? AND ? GROUP BY DATE(date) ORDER BY date DESC");
                        $stmt->execute([$start_date, $end_date]);
                        $revenue = $stmt->fetchAll();
                        foreach ($revenue as $row):
                        ?>
                        <tr>
                            <td><?php echo $row['payment_date']; ?></td>
                            <td><?php echo $row['transactions']; ?></td>
                            <td>₹ <?php echo number_format($row['total_amount']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
            <?php elseif ($report_type == 'debts'): ?>
                <h5>Báo cáo công nợ</h5>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Khách hàng</th>
                            <th>Loại</th>
                            <th>Số tiền</th>
                            <th>Hạn thanh toán</th>
                            <th>Trạng thái</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $pdo->query("SELECT d.*, t.name as tenant_name FROM debts d JOIN tenants t ON d.tenant_id = t.id WHERE d.status = 'pending' ORDER BY d.due_date ASC");
                        $debts = $stmt->fetchAll();
                        foreach ($debts as $row):
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['tenant_name']); ?></td>
                            <td><?php echo $row['type']; ?></td>
                            <td>₹ <?php echo number_format($row['amount']); ?></td>
                            <td><?php echo $row['due_date']; ?></td>
                            <td><span class="badge bg-warning">Pending</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
            <?php elseif ($report_type == 'refunds'): ?>
                <h5>Báo cáo hoàn cọc</h5>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Khách hàng</th>
                            <th>Tiền cọc</th>
                            <th>Thời gian ở</th>
                            <th>Tỷ lệ hoàn</th>
                            <th>Số tiền hoàn</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $pdo->query("SELECT t.name as tenant_name, c.deposit, c.start_date, c.end_date, TIMESTAMPDIFF(MONTH, c.start_date, COALESCE(c.end_date, CURDATE())) as months_stayed FROM contracts c JOIN tenants t ON c.tenant_id = t.id WHERE c.status = 'ended'");
                        $refunds = $stmt->fetchAll();
                        foreach ($refunds as $row):
                            $refund_rate = 0.80;
                            if ($row['months_stayed'] >= 6) $refund_rate = 0.70;
                            if ($row['months_stayed'] < 6 && $row['months_stayed'] > 0) $refund_rate = 0.50;
                            $refund_amount = $row['deposit'] * $refund_rate;
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['tenant_name']); ?></td>
                            <td>₹ <?php echo number_format($row['deposit']); ?></td>
                            <td><?php echo $row['months_stayed']; ?> tháng</td>
                            <td><?php echo $refund_rate * 100; ?>%</td>
                            <td>₹ <?php echo number_format($refund_amount); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include('includes/footer.php'); ?>
