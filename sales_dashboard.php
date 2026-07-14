<?php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/permissions.php';

// Require at least a 'sale' role to access this page
require_role('sale');

$message = '';
$error = '';
$hostelId = (int)($_SESSION['hostel_id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);

// Handle POST requests for viewings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && verify_csrf($_POST['csrf_token'])) {
    if (isset($_POST['schedule_viewing'])) {
        $tenant_id = filter_input(INPUT_POST, 'tenant_id', FILTER_VALIDATE_INT);
        $room_id = filter_input(INPUT_POST, 'room_id', FILTER_VALIDATE_INT);
        $scheduled_at = $_POST['scheduled_at'] ?? '';

        if ($tenant_id && $room_id && !empty($scheduled_at)) {
            $stmt = $pdo->prepare("INSERT INTO viewings (tenant_id, hostel_id, room_id, scheduled_at, status) VALUES (?, ?, ?, ?, 'scheduled')");
            if ($stmt->execute([$tenant_id, $hostelId, $room_id, $scheduled_at])) {
                $message = "Room viewing scheduled successfully.";
            } else {
                $error = "Failed to schedule viewing.";
            }
        } else {
            $error = "Please provide all details for scheduling.";
        }
    } elseif (isset($_POST['update_viewing_status'])) {
        $viewing_id = filter_input(INPUT_POST, 'viewing_id', FILTER_VALIDATE_INT);
        $status = sanitize_input($_POST['status']);
        $notes = sanitize_input($_POST['notes']);
        
        if ($viewing_id && in_array($status, ['completed', 'cancelled', 'no_show'])) {
            $stmt = $pdo->prepare("UPDATE viewings SET status = ?, notes = ? WHERE id = ? AND hostel_id = ?");
            if ($stmt->execute([$status, $notes, $viewing_id, $hostelId])) {
                $message = "Viewing status updated.";
            } else {
                $error = "Failed to update viewing status.";
            }
        } else {
            $error = "Invalid data provided.";
        }
    }
}

// Fetch data for the dashboard
$leads = workflow_query($pdo, "SELECT id, name, phone FROM tenants WHERE status = 'pending' AND bed_id IS NULL ORDER BY created_at DESC");
$available_rooms = workflow_query($pdo, "SELECT id, room_number FROM rooms WHERE status = 'available' AND hostel_id = ? ORDER BY room_number", [$hostelId]);
$scheduled_viewings = workflow_query($pdo, "SELECT v.*, t.name as tenant_name, r.room_number FROM viewings v JOIN tenants t ON v.tenant_id = t.id JOIN rooms r ON v.room_id = r.id WHERE v.hostel_id = ? ORDER BY v.scheduled_at DESC", [$hostelId]);

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="container mt-5">
    <h2 class="mb-4">Sales Dashboard</h2>

    <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

    <!-- Section for Scheduling Viewings -->
    <div class="card mb-4">
        <div class="card-header">
            <h4>Schedule a Room Viewing</h4>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Lead/Potential Tenant</label>
                        <select name="tenant_id" class="form-select" required>
                            <option value="">Select a Lead</option>
                            <?php foreach($leads as $lead): ?>
                                <option value="<?php echo $lead['id']; ?>"><?php echo htmlspecialchars($lead['name'] . ' (' . $lead['phone'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Available Room</label>
                        <select name="room_id" class="form-select" required>
                            <option value="">Select a Room</option>
                             <?php foreach($available_rooms as $room): ?>
                                <option value="<?php echo $room['id']; ?>"><?php echo htmlspecialchars($room['room_number']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date and Time</label>
                        <input type="datetime-local" name="scheduled_at" class="form-control" required>
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="submit" name="schedule_viewing" class="btn btn-primary">Schedule</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Section for Tracking Scheduled Viewings -->
    <div class="card">
        <div class="card-header">
            <h4>Track Scheduled Viewings</h4>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Tenant</th>
                            <th>Room</th>
                            <th>Scheduled Time</th>
                            <th>Status</th>
                            <th>Notes</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($scheduled_viewings)): ?>
                            <tr><td colspan="6" class="text-center">No viewings scheduled yet.</td></tr>
                        <?php else: ?>
                            <?php foreach($scheduled_viewings as $viewing): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($viewing['tenant_name']); ?></td>
                                    <td><?php echo htmlspecialchars($viewing['room_number']); ?></td>
                                    <td><?php echo date('d M Y, H:i', strtotime($viewing['scheduled_at'])); ?></td>
                                    <td><span class="badge bg-primary"><?php echo ucfirst($viewing['status']); ?></span></td>
                                    <td><?php echo htmlspecialchars($viewing['notes'] ?? ''); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#updateViewingModal" data-viewing-id="<?php echo $viewing['id']; ?>" data-viewing-status="<?php echo $viewing['status']; ?>" data-viewing-notes="<?php echo htmlspecialchars($viewing['notes'] ?? ''); ?>">
                                            Update
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal for updating viewing status -->
<div class="modal fade" id="updateViewingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Update Viewing Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                    <input type="hidden" name="viewing_id" id="viewing-id-input">
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" id="viewing-status-select" class="form-select">
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="no_show">No Show</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" id="viewing-notes-textarea" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="update_viewing_status" class="btn btn-primary">Save changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var updateModal = document.getElementById('updateViewingModal');
    updateModal.addEventListener('show.bs.modal', function(event) {
        var button = event.relatedTarget;
        var viewingId = button.getAttribute('data-viewing-id');
        var status = button.getAttribute('data-viewing-status');
        var notes = button.getAttribute('data-viewing-notes');

        var modalViewingIdInput = updateModal.querySelector('#viewing-id-input');
        var modalStatusSelect = updateModal.querySelector('#viewing-status-select');
        var modalNotesTextarea = updateModal.querySelector('#viewing-notes-textarea');

        modalViewingIdInput.value = viewingId;
        modalStatusSelect.value = status;
        modalNotesTextarea.value = notes;
    });
});
</script>

<?php 
include __DIR__ . '/includes/footer.php'; 
?>
