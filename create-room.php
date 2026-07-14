<?php
session_start();
include('includes/config.php');
include('includes/checklogin.php'); // Ensures user is logged in
include_once('includes/permissions.php'); // For is_admin()

// Ensure user is an admin
if (!is_admin()) {
    // Redirect non-admin users to a safe page
    header("Location: dashboard.php");
    exit;
}

$message = "";
$error = "";

// Fetch hostels and room types for dropdowns
try {
    $hostels_stmt = $pdo->query("SELECT id, name FROM hostels ORDER BY name");
    $hostels = $hostels_stmt->fetchAll(PDO::FETCH_ASSOC);

    $room_types_stmt = $pdo->query("SELECT id, name FROM room_types ORDER BY name");
    $room_types = $room_types_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching data: " . $e->getMessage();
    $hostels = [];
    $room_types = [];
}

// Handle room creation form
if (isset($_POST['submit'])) {
    // Sanitize and validate input
    $hostel_id = filter_input(INPUT_POST, 'hostel_id', FILTER_VALIDATE_INT);
    $room_number = trim($_POST['room_number']);
    $floor = filter_input(INPUT_POST, 'floor', FILTER_VALIDATE_INT);
    $room_type_id = filter_input(INPUT_POST, 'room_type_id', FILTER_VALIDATE_INT);
    $capacity = filter_input(INPUT_POST, 'capacity', FILTER_VALIDATE_INT);
    $gender_allowed = in_array($_POST['gender_allowed'], ['male', 'female', 'any']) ? $_POST['gender_allowed'] : 'any';
    $monthly_rent = filter_input(INPUT_POST, 'monthly_rent', FILTER_VALIDATE_FLOAT);

    if (!$hostel_id || empty($room_number) || $capacity <= 0 || $monthly_rent < 0) {
        $error = "Please fill all required fields correctly.";
    } else {
        try {
            $sql = "INSERT INTO rooms (hostel_id, room_number, floor, room_type_id, capacity, gender_allowed, monthly_rent) 
                    VALUES (:hostel_id, :room_number, :floor, :room_type_id, :capacity, :gender_allowed, :monthly_rent)";
            
            $stmt = $pdo->prepare($sql);
            
            $stmt->execute([
                ':hostel_id' => $hostel_id,
                ':room_number' => $room_number,
                ':floor' => $floor,
                ':room_type_id' => $room_type_id,
                ':capacity' => $capacity,
                ':gender_allowed' => $gender_allowed,
                ':monthly_rent' => $monthly_rent
            ]);
            $message = "Room created successfully!";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // Integrity constraint violation
                $error = "A room with this number already exists in the selected hostel.";
            } else {
                $error = "Error creating room: " . $e->getMessage();
            }
        }
    }
}
?>

<?php include('includes/header.php'); ?>
<?php include('includes/sidebar.php'); ?>

<div class="container mt-5">
    <h2>Create New Room</h2>

    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Hostel / Branch</label>
                        <select name="hostel_id" class="form-select" required>
                            <option value="">-- Select Hostel --</option>
                            <?php foreach ($hostels as $hostel): ?>
                                <option value="<?php echo $hostel['id']; ?>"><?php echo htmlspecialchars($hostel['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Room Number</label>
                        <input type="text" name="room_number" class="form-control" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Room Type</label>
                        <select name="room_type_id" class="form-select">
                            <option value="">-- Select Type --</option>
                             <?php foreach ($room_types as $type): ?>
                                <option value="<?php echo $type['id']; ?>"><?php echo htmlspecialchars($type['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Floor</label>
                        <input type="number" name="floor" class="form-control" value="1">
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Capacity (Beds)</label>
                        <input type="number" name="capacity" class="form-control" min="1" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Gender Allowed</label>
                        <select name="gender_allowed" class="form-select" required>
                            <option value="any">Any</option>
                            <option value="male">Male Only</option>
                            <option value="female">Female Only</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Monthly Rent (per bed)</label>
                        <input type="number" name="monthly_rent" class="form-control" min="0" step="0.01" required>
                    </div>
                </div>

                <button type="submit" name="submit" class="btn btn-primary">Add Room</button>
                <a href="manage-rooms.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
</div>

<?php include('includes/footer.php'); ?>
