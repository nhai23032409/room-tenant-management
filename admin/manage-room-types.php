<?php
session_start();
include('../includes/config.php');
include('../includes/checklogin.php');
include_once('../includes/permissions.php');

if (!is_admin()) {
    header("Location: ../dashboard.php");
    exit;
}

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $standard = $_POST['standard'];

    if (empty($name)) {
        $error = "Room type name is required.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO room_types (name, description, standard) VALUES (:name, :description, :standard)");
            $stmt->execute([
                ':name' => $name,
                ':description' => $description,
                ':standard' => $standard
            ]);
            $message = "Room type added successfully.";
        } catch (PDOException $e) {
            $error = "Error adding room type: " . $e->getMessage();
        }
    }
}

// Handle deletion
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    try {
        $stmt = $pdo->prepare("DELETE FROM room_types WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $message = "Room type deleted successfully.";
    } catch (PDOException $e) {
        $error = "Error deleting room type: " . $e->getMessage();
    }
}

// Fetch all room types
try {
    $stmt = $pdo->query("SELECT * FROM room_types ORDER BY name");
    $room_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching room types: " . $e->getMessage();
    $room_types = [];
}
?>

<?php include('../includes/header.php'); ?>
<?php include('../includes/sidebar.php'); ?>

<div class="container mt-5">
    <h2>Manage Room Types</h2>

    <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">Add New Room Type</div>
        <div class="card-body">
            <form method="POST">
                <div class="mb-3">
                    <label for="name" class="form-label">Room Type Name</label>
                    <input type="text" class="form-control" id="name" name="name" required>
                </div>
                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description"></textarea>
                </div>
                <div class="mb-3">
                    <label for="standard" class="form-label">Standard</label>
                    <input type="text" class="form-control" id="standard" name="standard">
                </div>
                <button type="submit" class="btn btn-primary">Add Room Type</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Existing Room Types</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Standard</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($room_types) > 0): ?>
                            <?php foreach ($room_types as $type): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($type['name']); ?></td>
                                    <td><?php echo htmlspecialchars($type['description']); ?></td>
                                    <td><?php echo htmlspecialchars($type['standard']); ?></td>
                                    <td>
                                        <a href="manage-room-types.php?delete=<?php echo $type['id']; ?>"
                                           onclick="return confirm('Are you sure you want to delete this room type?');"
                                           class="btn btn-sm btn-danger">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center">No room types found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include('../includes/footer.php'); ?>
