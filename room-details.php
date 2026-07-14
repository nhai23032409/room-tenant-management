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
$room_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($room_id === 0) {
    header("Location: manage-rooms.php");
    exit;
}

// Handle all POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && verify_csrf($_POST['csrf_token'])) {
    // Beds
    if (isset($_POST['add_bed'])) {
        $bed_number = sanitize_input($_POST['bed_number']);
        if (!empty($bed_number)) {
            $stmt = $pdo->prepare("INSERT INTO beds (room_id, bed_number) VALUES (?, ?)");
            $stmt->execute([$room_id, $bed_number]);
            $message = "Bed added successfully.";
        } else {
            $error = "Bed number cannot be empty.";
        }
    } elseif (isset($_POST['update_bed'])) {
        $bed_id = filter_input(INPUT_POST, 'bed_id', FILTER_VALIDATE_INT);
        $bed_number = sanitize_input($_POST['bed_number']);
        $bed_status = sanitize_input($_POST['bed_status']);
        if ($bed_id && !empty($bed_number) && !empty($bed_status)) {
            $stmt = $pdo->prepare("UPDATE beds SET bed_number = ?, status = ? WHERE id = ?");
            $stmt->execute([$bed_number, $bed_status, $bed_id]);
            $message = "Bed updated successfully.";
        } else {
            $error = "Invalid data for updating bed.";
        }
    } elseif (isset($_POST['delete_bed'])) {
        $bed_id = filter_input(INPUT_POST, 'bed_id', FILTER_VALIDATE_INT);
        if ($bed_id) {
            $stmt = $pdo->prepare("DELETE FROM beds WHERE id = ?");
            $stmt->execute([$bed_id]);
            $message = "Bed deleted successfully.";
        }
    }

    // Assets
    if (isset($_POST['add_asset'])) {
        $asset_name = sanitize_input($_POST['asset_name']);
        $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
        if (!empty($asset_name) && $quantity > 0) {
            $stmt = $pdo->prepare("INSERT INTO room_assets (room_id, asset_name, quantity) VALUES (?, ?, ?)");
            $stmt->execute([$room_id, $asset_name, $quantity]);
            $message = "Asset added successfully.";
        } else {
            $error = "Asset name and quantity are required.";
        }
    } elseif (isset($_POST['update_asset'])) {
        $asset_id = filter_input(INPUT_POST, 'asset_id', FILTER_VALIDATE_INT);
        $asset_name = sanitize_input($_POST['asset_name']);
        $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
        $asset_status = sanitize_input($_POST['asset_status']);
        if ($asset_id && !empty($asset_name) && $quantity > 0 && !empty($asset_status)) {
            $stmt = $pdo->prepare("UPDATE room_assets SET asset_name = ?, quantity = ?, status = ? WHERE id = ?");
            $stmt->execute([$asset_name, $quantity, $asset_status, $asset_id]);
            $message = "Asset updated successfully.";
        } else {
            $error = "Invalid data for updating asset.";
        }
    } elseif (isset($_POST['delete_asset'])) {
        $asset_id = filter_input(INPUT_POST, 'asset_id', FILTER_VALIDATE_INT);
        if ($asset_id) {
            $stmt = $pdo->prepare("DELETE FROM room_assets WHERE id = ?");
            $stmt->execute([$asset_id]);
            $message = "Asset deleted successfully.";
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = "Invalid or missing CSRF token. Please refresh and try again.";
}

// Fetch Room Details
$stmt = $pdo->prepare("SELECT r.*, h.name as hostel_name, rt.name as room_type_name FROM rooms r 
                       JOIN hostels h ON r.hostel_id = h.id 
                       LEFT JOIN room_types rt ON r.room_type_id = rt.id 
                       WHERE r.id = ?");
$stmt->execute([$room_id]);
$room = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$room) {
    $_SESSION['flash_error'] = "Room not found.";
    header("Location: manage-rooms.php");
    exit;
}

// Fetch Beds and Assets
$beds = $pdo->prepare("SELECT * FROM beds WHERE room_id = ? ORDER BY bed_number");
$beds->execute([$room_id]);
$beds = $beds->fetchAll(PDO::FETCH_ASSOC);

$assets = $pdo->prepare("SELECT * FROM room_assets WHERE room_id = ? ORDER BY asset_name");
$assets->execute([$room_id]);
$assets = $assets->fetchAll(PDO::FETCH_ASSOC);

$bed_statuses = ['available', 'deposited', 'occupied', 'maintenance'];
$asset_statuses = ['good', 'damaged', 'missing'];
?>

<?php include('includes/header.php'); ?>
<?php include('includes/sidebar.php'); ?>

<div class="container mt-5">
    <?php if ($message): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo $message; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3>Room Details: <?php echo htmlspecialchars($room['room_number']); ?></h3>
            <a href="manage-rooms.php" class="btn btn-secondary">Back to Room List</a>
        </div>
        <div class="card-body">
            <strong>Hostel/Branch:</strong> <?php echo htmlspecialchars($room['hostel_name']); ?><br>
            <strong>Room Type:</strong> <?php echo htmlspecialchars($room['room_type_name'] ?? 'N/A'); ?><br>
            <strong>Capacity:</strong> <?php echo htmlspecialchars($room['capacity']); ?> beds<br>
            <strong>Status:</strong> <span class="badge bg-info text-dark"><?php echo htmlspecialchars(ucfirst($room['status'])); ?></span>
        </div>
    </div>

    <div class="row">
        <!-- Beds Management -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header"><h5>Manage Beds</h5></div>
                <div class="card-body">
                    <form method="POST" class="mb-3">
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                        <div class="input-group">
                            <input type="text" name="bed_number" class="form-control" placeholder="Enter Bed Number/Code" required>
                            <button type="submit" name="add_bed" class="btn btn-primary">Add Bed</button>
                        </div>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-hover">
                            <thead><tr><th>Bed Number</th><th>Status</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($beds as $bed): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($bed['bed_number']); ?></td>
                                        <td><span class="badge bg-light text-dark"><?php echo htmlspecialchars(ucfirst($bed['status'])); ?></span></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editBedModal" data-bed-id="<?php echo $bed['id']; ?>" data-bed-number="<?php echo htmlspecialchars($bed['bed_number']); ?>" data-bed-status="<?php echo $bed['status']; ?>">Edit</button>
                                            <form method="POST" style="display:inline-block;" onsubmit="return confirm('Delete this bed?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                                <input type="hidden" name="bed_id" value="<?php echo $bed['id']; ?>">
                                                <button type="submit" name="delete_bed" class="btn btn-sm btn-outline-danger">Del</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if(empty($beds)): ?><tr><td colspan="3" class="text-center">No beds added yet.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assets Management -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header"><h5>Manage Room Assets</h5></div>
                <div class="card-body">
                    <form method="POST" class="mb-3">
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                        <div class="input-group">
                            <input type="text" name="asset_name" class="form-control" placeholder="Asset Name" required>
                            <input type="number" name="quantity" class="form-control" placeholder="Qty" value="1" min="1" required>
                            <button type="submit" name="add_asset" class="btn btn-primary">Add Asset</button>
                        </div>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-hover">
                            <thead><tr><th>Asset</th><th>Qty</th><th>Status</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($assets as $asset): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($asset['asset_name']); ?></td>
                                        <td><?php echo htmlspecialchars($asset['quantity']); ?></td>
                                        <td><span class="badge bg-light text-dark"><?php echo htmlspecialchars(ucfirst($asset['status'])); ?></span></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editAssetModal" data-asset-id="<?php echo $asset['id']; ?>" data-asset-name="<?php echo htmlspecialchars($asset['asset_name']); ?>" data-asset-quantity="<?php echo $asset['quantity']; ?>" data-asset-status="<?php echo $asset['status']; ?>">Edit</button>
                                            <form method="POST" style="display:inline-block;" onsubmit="return confirm('Delete this asset?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                                <input type="hidden" name="asset_id" value="<?php echo $asset['id']; ?>">
                                                <button type="submit" name="delete_asset" class="btn btn-sm btn-outline-danger">Del</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if(empty($assets)): ?><tr><td colspan="4" class="text-center">No assets added yet.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Bed Modal -->
<div class="modal fade" id="editBedModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Bed</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                    <input type="hidden" name="bed_id" id="edit-bed-id">
                    <div class="mb-3">
                        <label class="form-label">Bed Number</label>
                        <input type="text" name="bed_number" id="edit-bed-number" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="bed_status" id="edit-bed-status" class="form-select">
                            <?php foreach($bed_statuses as $status): ?>
                                <option value="<?php echo $status; ?>"><?php echo ucfirst($status); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="update_bed" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Asset Modal -->
<div class="modal fade" id="editAssetModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Asset</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                    <input type="hidden" name="asset_id" id="edit-asset-id">
                    <div class="mb-3">
                        <label class="form-label">Asset Name</label>
                        <input type="text" name="asset_name" id="edit-asset-name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quantity</label>
                        <input type="number" name="quantity" id="edit-asset-quantity" class="form-control" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="asset_status" id="edit-asset-status" class="form-select">
                            <?php foreach($asset_statuses as $status): ?>
                                <option value="<?php echo $status; ?>"><?php echo ucfirst($status); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="update_asset" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include('includes/footer.php'); ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // For Edit Bed Modal
    var editBedModal = document.getElementById('editBedModal');
    editBedModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        var bedId = button.getAttribute('data-bed-id');
        var bedNumber = button.getAttribute('data-bed-number');
        var bedStatus = button.getAttribute('data-bed-status');
        
        var modalTitle = editBedModal.querySelector('.modal-title');
        var modalBedIdInput = editBedModal.querySelector('#edit-bed-id');
        var modalBedNumberInput = editBedModal.querySelector('#edit-bed-number');
        var modalBedStatusSelect = editBedModal.querySelector('#edit-bed-status');

        modalTitle.textContent = 'Edit Bed ' + bedNumber;
        modalBedIdInput.value = bedId;
        modalBedNumberInput.value = bedNumber;
        modalBedStatusSelect.value = bedStatus;
    });

    // For Edit Asset Modal
    var editAssetModal = document.getElementById('editAssetModal');
    editAssetModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        var assetId = button.getAttribute('data-asset-id');
        var assetName = button.getAttribute('data-asset-name');
        var assetQuantity = button.getAttribute('data-asset-quantity');
        var assetStatus = button.getAttribute('data-asset-status');

        var modalTitle = editAssetModal.querySelector('.modal-title');
        var modalAssetIdInput = editAssetModal.querySelector('#edit-asset-id');
        var modalAssetNameInput = editAssetModal.querySelector('#edit-asset-name');
        var modalAssetQuantityInput = editAssetModal.querySelector('#edit-asset-quantity');
        var modalAssetStatusSelect = editAssetModal.querySelector('#edit-asset-status');

        modalTitle.textContent = 'Edit Asset ' + assetName;
        modalAssetIdInput.value = assetId;
        modalAssetNameInput.value = assetName;
        modalAssetQuantityInput.value = assetQuantity;
        modalAssetStatusSelect.value = assetStatus;
    });
});
</script>
