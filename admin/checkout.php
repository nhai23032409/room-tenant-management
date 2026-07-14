<?php
header('Location: ../workflow.php');
exit;
// checkout.php
session_start();
include_once '../db_connect.php'; // adjust path as needed

// Check if user is logged in - simple example
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../login.php");
    exit;
}

// Process checkout form
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['checkin_id'])) {
    $checkinId = intval($_POST['checkin_id']);
    $checkoutDate = date('Y-m-d H:i:s');

    // Update checkin record with checkout date
    $stmt = $conn->prepare("UPDATE checkins SET checkout_date = ? WHERE id = ? AND checkout_date IS NULL");
    $stmt->bind_param("si", $checkoutDate, $checkinId);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $message = "Tenant checked out successfully.";
    } else {
        $message = "Error during checkout or tenant already checked out.";
    }
}

// Fetch tenants currently checked in (checkout_date IS NULL)
$sql = "SELECT c.id as checkin_id, t.name, c.checkin_date 
        FROM checkins c
        JOIN tenants t ON c.tenant_id = t.id
        WHERE c.checkout_date IS NULL";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Check-Out Tenant</title>
<link href="../assets/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="container mt-4">
  <h2>Tenant Check-Out</h2>

  <?php if (isset($message)): ?>
    <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
  <?php endif; ?>

  <?php if ($result && $result->num_rows > 0): ?>
    <form method="POST" action="checkout.php">
      <div class="mb-3">
        <label for="checkin_id" class="form-label">Select Tenant to Check-Out:</label>
        <select class="form-select" id="checkin_id" name="checkin_id" required>
          <option value="" selected disabled>-- Select Tenant --</option>
          <?php while ($row = $result->fetch_assoc()): ?>
            <option value="<?php echo $row['checkin_id']; ?>">
              <?php echo htmlspecialchars($row['name'] . " (Checked in: " . date('d M Y H:i', strtotime($row['checkin_date'])) . ")"); ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-primary">Check Out</button>
    </form>
  <?php else: ?>
    <p>No tenants are currently checked in.</p>
  <?php endif; ?>
</div>

<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
