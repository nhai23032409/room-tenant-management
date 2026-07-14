<?php
session_start();
include('includes/config.php');
include('includes/checklogin.php');
include_once('includes/permissions.php');

if (!is_admin()) {
    header("Location: dashboard.php");
    exit;
}

$message = '';
$error = '';
$hostel_filter_id = isset($_GET['hostel_id']) ? (int)$_GET['hostel_id'] : 0;

// Handle deactivation (soft delete)
if (isset($_GET['deactivate'])) {
    $roomId = intval($_GET['deactivate']);
    try {
        // Check if room has active tenants before deactivating
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM tenants t JOIN beds b ON t.bed_id = b.id WHERE b.room_id = ? AND t.status = 'active'");
        $checkStmt->execute([$roomId]);
        if ($checkStmt->fetchColumn() > 0) {
            $error = "Cannot deactivate room with active tenants.";
        } else {
            $stmt = $pdo->prepare("UPDATE rooms SET status = 'maintenance' WHERE id = :id");
            $stmt->execute([':id' => $roomId]);
            $message = "Room has been deactivated and set to 'maintenance' status.";
        }
    } catch (PDOException $e) {
        $error = "Error deactivating room: " . $e->getMessage();
    }
}

// Fetch all hostels for the filter dropdown
try {
    $hostels_stmt = $pdo->query("SELECT id, name FROM hostels ORDER BY name");
    $hostels = $hostels_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching hostels: " . $e->getMessage();
    $hostels = [];
}

// Base query
$sql = "SELECT 
            r.id, 
            r.room_number, 
            r.floor, 
            r.capacity, 
            r.status AS room_status,
            r.monthly_rent,
            h.name AS hostel_name,
            rt.name AS room_type_name
        FROM rooms AS r
        JOIN hostels AS h ON r.hostel_id = h.id
        LEFT JOIN room_types AS rt ON r.room_type_id = rt.id";

$params = [];
if ($hostel_filter_id > 0) {
    $sql .= " WHERE r.hostel_id = :hostel_id";
    $params[':hostel_id'] = $hostel_filter_id;
}
$sql .= " ORDER BY h.name, r.room_number ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include('includes/header.php'); ?>
<?php include('includes/sidebar.php'); ?>

<div class="container mt-5">
    <h2>Manage Rooms</h2>

    <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

    <div class="d-flex justify-content-between mb-3">
        <a href="create-room.php" class="btn btn-primary">+ Add New Room</a>
        <form method="GET" class="d-flex align-items-center">
            <label for="hostel_id" class="form-label me-2 mb-0">Filter by Hostel:</label>
            <select name="hostel_id" id="hostel_id" class="form-select w-auto" onchange="this.form.submit()">
                <option value="0">All Hostels</option>
                <?php foreach ($hostels as $hostel): ?>
                    <option value="<?php echo $hostel['id']; ?>" <?php if ($hostel_filter_id == $hostel['id']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($hostel['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>Hostel</th>
                            <th>Room No.</th>
                            <th>Room Type</th>
                            <th>Capacity</th>
                            <th>Rent</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($rooms) > 0): ?>
                            <?php foreach ($rooms as $room): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($room['hostel_name']); ?></td>
                                    <td><?php echo htmlspecialchars($room['room_number']); ?></td>
                                    <td><?php echo htmlspecialchars($room['room_type_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo $room['capacity']; ?></td>
                                    <td><?php echo number_format($room['monthly_rent'], 2); ?></td>
                                    <td>
                                        <span class="badge bg-info text-dark"><?php echo htmlspecialchars(ucfirst($room['room_status'])); ?></span>
                                    </td>
                                    <td>
                                        <a href="room-details.php?id=<?php echo $room['id']; ?>" class="btn btn-sm btn-secondary">Details</a>
                                        <a href="manage-rooms.php?deactivate=<?php echo $room['id']; ?>&hostel_id=<?php echo $hostel_filter_id; ?>"
                                           onclick="return confirm('Are you sure you want to deactivate this room? This will set its status to maintenance.');"
                                           class="btn btn-sm btn-warning">Deactivate</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center">No rooms found for the selected filter.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include('includes/footer.php'); ?>
