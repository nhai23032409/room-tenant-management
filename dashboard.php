<?php
session_start();
include('includes/config.php');
include('includes/checklogin.php');  // make sure user is logged in

// Fetch total tenants
$stmt = $pdo->query("SELECT COUNT(*) FROM tenants");
$totalTenants = $stmt->fetchColumn();

// Fetch total rooms
$stmt = $pdo->query("SELECT COUNT(*) FROM rooms");
$totalRooms = $stmt->fetchColumn();

// Fetch total payments sum
$stmt = $pdo->query("SELECT IFNULL(SUM(amount), 0) FROM payments");
$totalPayments = $stmt->fetchColumn();

// Fetch current checked-in tenants count
$stmt = $pdo->query("SELECT COUNT(*) FROM checkin_history WHERE checkout_date IS NULL");
$currentCheckins = $stmt->fetchColumn();
?>

<?php include('includes/header.php'); ?>
<?php include('includes/sidebar.php'); ?>

<div class="container mt-5" style="margin-left: 220px;">
    <h1 class="mb-4">Dashboard</h1>
    <div class="row">
        <div class="col-md-3 mb-3">
            <div class="card text-white bg-primary">
                <div class="card-body">
                    <h5 class="card-title">Total Tenants</h5>
                    <p class="card-text display-4"><?php echo $totalTenants; ?></p>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <h5 class="card-title">Total Rooms</h5>
                    <p class="card-text display-4"><?php echo $totalRooms; ?></p>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="card text-white bg-warning">
                <div class="card-body">
                    <h5 class="card-title">Total Payments (₹)</h5>
                    <p class="card-text display-4">₹ <?php echo number_format($totalPayments, 2); ?></p>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="card text-white bg-info">
                <div class="card-body">
                    <h5 class="card-title">Currently Checked-In</h5>
                    <p class="card-text display-4"><?php echo $currentCheckins; ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include('includes/footer.php'); ?>