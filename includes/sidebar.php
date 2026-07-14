<?php
// sidebar.php
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark flex-column vh-100" style="width: 220px; position: fixed;">
  <a class="navbar-brand px-3 mt-3 mb-4" href="dashboard.php">Hostel Management</a>
  <ul class="navbar-nav flex-column w-100">
    <li class="nav-item">
      <a class="nav-link <?php if ($currentPage == 'dashboard.php') echo 'active'; ?>" href="dashboard.php">Dashboard</a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?php if ($currentPage == 'registration.php') echo 'active'; ?>" href="registration.php">Register Tenant</a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?php if ($currentPage == 'manage-tenants.php') echo 'active'; ?>" href="manage-tenants.php">Manage Tenants</a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?php if ($currentPage == 'create-room.php') echo 'active'; ?>" href="create-room.php">Add Room</a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?php if ($currentPage == 'manage-rooms.php') echo 'active'; ?>" href="manage-rooms.php">Manage Rooms</a>
    </li>

    <li class="nav-item mt-3">
        <h6 class="text-muted px-3">Admin Settings</h6>
    </li>

    <li class="nav-item">
      <a class="nav-link <?php if ($currentPage == 'manage-hostels.php') echo 'active'; ?>" href="admin/manage-hostels.php">Manage Hostels</a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?php if ($currentPage == 'manage-room-types.php') echo 'active'; ?>" href="admin/manage-room-types.php">Manage Room Types</a>
    </li>

    <li class="nav-item">
      <a class="nav-link <?php if ($currentPage == 'admin/add-payment.php') echo 'active'; ?>" href="admin/add-payment.php">Add Payment</a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?php if ($currentPage == 'admin/view-payments.php') echo 'active'; ?>" href="admin/view-payments.php">View Payments</a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?php if ($currentPage == 'admin/checkin.php') echo 'active'; ?>" href="admin/checkin.php">Check-In</a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?php if ($currentPage == 'admin/checkout.php') echo 'active'; ?>" href="admin/checkout.php">Check-Out</a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?php if ($currentPage == 'admin/reports.php') echo 'active'; ?>" href="admin/reports.php">Reports</a>
    </li>
    <li class="nav-item mt-4">
      <a class="nav-link text-danger" href="logout.php">Logout</a>
    </li>
  </ul>
</nav>

<style>
  body {
    margin-left: 220px; /* leave space for sidebar */
  }
  .nav-link.active {
    background-color: #0d6efd;
    color: white !important;
    font-weight: 600;
  }
</style>