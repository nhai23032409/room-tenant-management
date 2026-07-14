<?php
session_start();
include('includes/config.php');
include('includes/permissions.php');

// Handle AJAX requests
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        http_response_code(419);
        echo json_encode(['success' => false, 'message' => 'Invalid or missing CSRF token.']);
        exit;
    }
    
    switch ($action) {
        case 'login':
            $email = sanitize_input($_POST['email']);
            $password = $_POST['password'];
            
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['hostel_id'] = $user['hostel_id'];
                log_activity($pdo, $user['id'], 'login', 'User logged in');
                echo json_encode(['success' => true, 'message' => 'Login successful']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
            }
            break;
            
        case 'register':
            // Accounts are provisioned by an administrator; public account creation
            // would allow privilege and cross-branch abuse.
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Self-registration is disabled. Contact an administrator.']);
            break;
            
        case 'add_hostel':
            require_api_permission('manage_hostels');
            
            $name = sanitize_input($_POST['name']);
            $address = sanitize_input($_POST['address']);
            $phone = sanitize_input($_POST['phone']);
            $email = sanitize_input($_POST['email']);
            $description = sanitize_input($_POST['description']);
            
            $stmt = $pdo->prepare("INSERT INTO hostels (name, address, phone, email, description) VALUES (?, ?, ?, ?, ?)");
            if ($stmt->execute([$name, $address, $phone, $email, $description])) {
                $hostel_id = $pdo->lastInsertId();
                log_activity($pdo, $_SESSION['user_id'], 'add_hostel', "Added hostel: $name");
                echo json_encode(['success' => true, 'message' => 'Hostel added successfully', 'hostel_id' => $hostel_id]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to add hostel']);
            }
            break;
            
        case 'add_tenant':
            require_api_permission('manage_tenants');
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Use the registration, deposit, contract and handover workflow; direct check-in is disabled.']);
            break;
            
        case 'add_payment':
            require_api_permission('manage_payments');
            
            $tenant_id = $_POST['tenant_id'];
            $amount = $_POST['amount'];
            $date = $_POST['date'];
            $method = $_POST['method'];
            $notes = sanitize_input($_POST['notes']);
            $payment_type = $_POST['payment_type'];
            $receipt_number = generate_receipt_number();

            $tenantScope = $pdo->prepare("SELECT t.id FROM tenants t JOIN beds b ON b.id = t.bed_id JOIN rooms r ON r.id = b.room_id WHERE t.id = ? AND r.hostel_id = ?");
            $tenantScope->execute([$tenant_id, (int)($_SESSION['hostel_id'] ?? 0)]);
            if (!$tenantScope->fetch()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Tenant not found in your branch']);
                break;
            }
            
            $stmt = $pdo->prepare("INSERT INTO payments (tenant_id, amount, date, method, notes, payment_type, receipt_number) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$tenant_id, $amount, $date, $method, $notes, $payment_type, $receipt_number])) {
                log_activity($pdo, $_SESSION['user_id'], 'add_payment', "Added payment: $amount for tenant ID $tenant_id");
                echo json_encode(['success' => true, 'message' => 'Payment recorded successfully', 'receipt_number' => $receipt_number]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to record payment']);
            }
            break;
            
        case 'checkout_tenant':
            require_api_permission('checkout_tenant');
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Use api/checkout.php to calculate and confirm the financial settlement before checkout.']);
            break;
            
        case 'get_dashboard_data':
            if (!isset($_SESSION['user_id'])) {
                echo json_encode(['success' => false, 'message' => 'Please login']);
                break;
            }
            
            $hostel_filter = $_SESSION['hostel_id'] ? "AND h.id = " . (int)$_SESSION['hostel_id'] : "";
            
            // Get statistics
            $stats = [];
            
            // Total tenants
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tenants t JOIN beds b ON t.bed_id = b.id JOIN rooms r ON b.room_id = r.id JOIN hostels h ON r.hostel_id = h.id WHERE t.status = 'active' $hostel_filter");
            $stmt->execute();
            $stats['total_tenants'] = $stmt->fetch()['total'];
            
            // Total payments this month
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments p JOIN tenants t ON p.tenant_id = t.id JOIN beds b ON t.bed_id = b.id JOIN rooms r ON b.room_id = r.id JOIN hostels h ON r.hostel_id = h.id WHERE MONTH(p.date) = MONTH(CURRENT_DATE()) AND YEAR(p.date) = YEAR(CURRENT_DATE()) $hostel_filter");
            $stmt->execute();
            $stats['monthly_payments'] = $stmt->fetch()['total'];
            
            // Available beds
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM beds b JOIN rooms r ON b.room_id = r.id JOIN hostels h ON r.hostel_id = h.id WHERE b.status = 'available' $hostel_filter");
            $stmt->execute();
            $stats['available_beds'] = $stmt->fetch()['total'];
            
            // Occupied beds
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM beds b JOIN rooms r ON b.room_id = r.id JOIN hostels h ON r.hostel_id = h.id WHERE b.status = 'occupied' $hostel_filter");
            $stmt->execute();
            $stats['occupied_beds'] = $stmt->fetch()['total'];
            
            echo json_encode(['success' => true, 'stats' => $stats]);
            break;
            
        case 'get_hostels':
            $stmt = $pdo->prepare("SELECT * FROM hostels ORDER BY name");
            $stmt->execute();
            $hostels = $stmt->fetchAll();
            echo json_encode(['success' => true, 'hostels' => $hostels]);
            break;
            
        case 'get_available_beds':
            require_api_permission('view_rooms');
            $hostel_id = (int)($_SESSION['hostel_id'] ?? 0);
            $stmt = $pdo->prepare("
                SELECT b.id, b.bed_number, r.room_number, r.monthly_rent 
                FROM beds b 
                JOIN rooms r ON b.room_id = r.id 
                WHERE r.hostel_id = ? AND b.status = 'available' 
                ORDER BY r.room_number, b.bed_number
            ");
            $stmt->execute([$hostel_id]);
            $beds = $stmt->fetchAll();
            echo json_encode(['success' => true, 'beds' => $beds]);
            break;
            
        case 'get_tenants':
            require_api_permission('view_tenants');
            $hostel_filter = $_SESSION['hostel_id'] ? "AND h.id = " . $_SESSION['hostel_id'] : "";
            $stmt = $pdo->prepare("
                SELECT t.*, b.bed_number, r.room_number, h.name as hostel_name 
                FROM tenants t 
                JOIN beds b ON t.bed_id = b.id 
                JOIN rooms r ON b.room_id = r.id 
                JOIN hostels h ON r.hostel_id = h.id 
                WHERE t.status = 'active' $hostel_filter
                ORDER BY t.name
            ");
            $stmt->execute();
            $tenants = $stmt->fetchAll();
            echo json_encode(['success' => true, 'tenants' => $tenants]);
            break;
            
        case 'get_payments':
            require_api_permission('manage_payments');
            $start_date = $_POST['start_date'];
            $end_date = $_POST['end_date'];
            $hostel_filter = $_SESSION['hostel_id'] ? "AND h.id = " . $_SESSION['hostel_id'] : "";
            
            $stmt = $pdo->prepare("
                SELECT p.*, t.name as tenant_name, b.bed_number, r.room_number, h.name as hostel_name 
                FROM payments p 
                JOIN tenants t ON p.tenant_id = t.id 
                JOIN beds b ON t.bed_id = b.id 
                JOIN rooms r ON b.room_id = r.id 
                JOIN hostels h ON r.hostel_id = h.id 
                WHERE p.date BETWEEN ? AND ? $hostel_filter
                ORDER BY p.date DESC
            ");
            $stmt->execute([$start_date, $end_date]);
            $payments = $stmt->fetchAll();
            echo json_encode(['success' => true, 'payments' => $payments]);
            break;
            
        case 'logout':
            if (isset($_SESSION['user_id'])) {
                log_activity($pdo, $_SESSION['user_id'], 'logout', 'User logged out');
            }
            session_destroy();
            echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
            break;
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenant Management Mobile App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #007bff;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .mobile-container {
            max-width: 480px;
            margin: 0 auto;
            background: white;
            min-height: 100vh;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .header {
            background: linear-gradient(135deg, var(--primary-color), var(--info-color));
            color: white;
            padding: 1rem;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            font-size: 1.2rem;
            margin: 0;
            text-align: center;
        }
        
        .nav-pills .nav-link {
            border-radius: 20px;
            margin: 0 2px;
            font-size: 0.9rem;
        }
        
        .nav-pills .nav-link.active {
            background: var(--primary-color);
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--light-color), white);
            border-bottom: 1px solid rgba(0,0,0,0.1);
            border-radius: 15px 15px 0 0 !important;
        }
        
        .stat-card {
            background: linear-gradient(135deg, var(--primary-color), var(--info-color));
            color: white;
            text-align: center;
            padding: 1rem;
            border-radius: 15px;
            margin-bottom: 1rem;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            margin: 0;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            border: 1px solid rgba(0,0,0,0.2);
        }
        
        .btn {
            border-radius: 10px;
            padding: 0.5rem 1rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--info-color));
            border: none;
        }
        
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }
        
        .table th {
            background: var(--light-color);
            border: none;
            font-weight: 600;
        }
        
        .section {
            display: none;
            padding: 1rem;
        }
        
        .section.active {
            display: block;
        }
        
        .floating-add-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            border-radius: 50px;
            width: 60px;
            height: 60px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .login-form {
            max-width: 400px;
            margin: 2rem auto;
            padding: 2rem;
        }
        
        .tenant-card {
            border-left: 4px solid var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .payment-card {
            border-left: 4px solid var(--success-color);
            margin-bottom: 0.5rem;
        }
        
        .badge {
            border-radius: 10px;
        }
        
        .spinner-border-sm {
            width: 1rem;
            height: 1rem;
        }
        
        @media (max-width: 576px) {
            .nav-pills {
                font-size: 0.8rem;
            }
            
            .nav-pills .nav-link {
                padding: 0.5rem 0.8rem;
            }
            
            .stat-value {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="mobile-container">
        <?php if (!isset($_SESSION['user_id'])): ?>
            <!-- Login/Register Section -->
            <div class="header">
                <h1><i class="fas fa-building"></i> Tenant Management</h1>
            </div>
            
            <div class="login-form">
                <div class="card">
                    <div class="card-header">
                        <ul class="nav nav-pills nav-justified">
                            <li class="nav-item">
                                <a class="nav-link active" href="#" onclick="showLogin()">Login</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#" onclick="showRegister()">Register</a>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <!-- Login Form -->
                        <div id="loginForm">
                            <form id="loginFormData">
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Password</label>
                                    <input type="password" class="form-control" name="password" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-sign-in-alt"></i> Login
                                </button>
                            </form>
                        </div>
                        
                        <!-- Register Form -->
                        <div id="registerForm" style="display: none;">
                            <form id="registerFormData">
                                <div class="mb-3">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" class="form-control" name="name" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Password</label>
                                    <input type="password" class="form-control" name="password" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Select Hostel</label>
                                    <select class="form-select" name="hostel_id" required>
                                        <option value="">Select Hostel</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-user-plus"></i> Register
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Main App -->
            <div class="header">
                <div class="d-flex justify-content-between align-items-center">
                    <h1><i class="fas fa-building"></i> Tenant Management</h1>
                    <div class="dropdown">
                        <button class="btn btn-outline-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?php echo $_SESSION['user_name']; ?>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" onclick="logout()"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
                
                <div class="mt-2">
                    <ul class="nav nav-pills nav-justified">
                        <li class="nav-item">
                            <a class="nav-link active" href="#" onclick="showSection('dashboard')">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" onclick="showSection('tenants')">
                                <i class="fas fa-users"></i> Tenants
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" onclick="showSection('payments')">
                                <i class="fas fa-money-bill-wave"></i> Payments
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" onclick="showSection('reports')">
                                <i class="fas fa-chart-bar"></i> Reports
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Dashboard Section -->
            <div id="dashboard" class="section active">
                <div class="row" id="dashboardStats">
                    <!-- Stats will be loaded here -->
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-plus"></i> Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6 mb-2">
                                <button class="btn btn-primary w-100" onclick="showAddTenantModal()">
                                    <i class="fas fa-user-plus"></i> Add Tenant
                                </button>
                            </div>
                            <div class="col-6 mb-2">
                                <button class="btn btn-success w-100" onclick="showAddPaymentModal()">
                                    <i class="fas fa-money-bill"></i> Add Payment
                                </button>
                            </div>
                            <div class="col-6 mb-2">
                                <button class="btn btn-info w-100" onclick="showAddHostelModal()">
                                    <i class="fas fa-building"></i> Add Hostel
                                </button>
                            </div>
                            <div class="col-6 mb-2">
                                <button class="btn btn-warning w-100" onclick="showSection('reports')">
                                    <i class="fas fa-chart-line"></i> View Reports
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tenants Section -->
            <div id="tenants" class="section">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-users"></i> Active Tenants</h5>
                        <button class="btn btn-primary btn-sm" onclick="showAddTenantModal()">
                            <i class="fas fa-plus"></i> Add
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="tenantsList">
                            <!-- Tenants will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Payments Section -->
            <div id="payments" class="section">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-money-bill-wave"></i> Payment Records</h5>
                        <button class="btn btn-success btn-sm" onclick="showAddPaymentModal()">
                            <i class="fas fa-plus"></i> Add
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-6">
                                <input type="date" class="form-control" id="paymentStartDate" value="<?php echo date('Y-m-01'); ?>">
                            </div>
                            <div class="col-6">
                                <input type="date" class="form-control" id="paymentEndDate" value="<?php echo date('Y-m-t'); ?>">
                            </div>
                        </div>
                        <button class="btn btn-outline-primary btn-sm mb-3" onclick="loadPayments()">
                            <i class="fas fa-search"></i> Load Payments
                        </button>
                        <div id="paymentsList">
                            <!-- Payments will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Reports Section -->
            <div id="reports" class="section">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-bar"></i> Reports</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-6">
                                <label class="form-label">From Date</label>
                                <input type="date" class="form-control" id="reportStartDate" value="<?php echo date('Y-m-01'); ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label">To Date</label>
                                <input type="date" class="form-control" id="reportEndDate" value="<?php echo date('Y-m-t'); ?>">
                            </div>
                        </div>
                        <button class="btn btn-primary mb-3" onclick="generateReport()">
                            <i class="fas fa-chart-line"></i> Generate Report
                        </button>
                        <div id="reportResults">
                            <!-- Report results will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Floating Action Button -->
            <button class="btn btn-primary floating-add-btn" onclick="showAddTenantModal()">
                <i class="fas fa-plus"></i>
            </button>
        <?php endif; ?>
    </div>
    
    <!-- Modals -->
    <!-- Add Hostel Modal -->
    <div class="modal fade" id="addHostelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-building"></i> Add Hostel</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addHostelForm">
                        <div class="mb-3">
                            <label class="form-label">Hostel Name</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control" name="phone">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="2"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="addHostel()">Add Hostel</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Tenant Modal -->
    <div class="modal fade" id="addTenantModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus"></i> Add Tenant</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addTenantForm">
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control" name="phone" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Select Bed</label>
                            <select class="form-select" name="bed_id" required>
                                <option value="">Select Bed</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Check-in Date</label>
                            <input type="date" class="form-control" name="checkin_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Monthly Rent</label>
                            <input type="number" class="form-control" name="monthly_rent" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Security Deposit</label>
                            <input type="number" class="form-control" name="security_deposit" step="0.01">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="addTenant()">Add Tenant</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Payment Modal -->
    <div class="modal fade" id="addPaymentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-money-bill"></i> Add Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addPaymentForm">
                        <div class="mb-3">
                            <label class="form-label">Select Tenant</label>
                            <select class="form-select" name="tenant_id" required>
                                <option value="">Select Tenant</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Amount</label>
                            <input type="number" class="form-control" name="amount" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Payment Date</label>
                            <input type="date" class="form-control" name="date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Payment Method</label>
                            <select class="form-select" name="method" required>
                                <option value="cash">Cash</option>
                                <option value="card">Card</option>
                                <option value="upi">UPI</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="cheque">Cheque</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Payment Type</label>
                            <select class="form-select" name="payment_type" required>
                                <option value="rent">Rent</option>
                                <option value="security_deposit">Security Deposit</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="penalty">Penalty</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="2"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success" onclick="addPayment()">Add Payment</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Loading Overlay -->
    <div id="loadingOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999;">
        <div class="d-flex justify-content-center align-items-center h-100">
            <div class="spinner-border text-light" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const csrfToken = <?php echo json_encode(csrf_token()); ?>;
        const nativeFetch = window.fetch.bind(window);
        window.fetch = (input, init = {}) => {
            if ((init.method || 'GET').toUpperCase() === 'POST') {
                if (init.body instanceof FormData) {
                    init.body.set('csrf_token', csrfToken);
                } else if (typeof init.body === 'string') {
                    init.body += (init.body ? '&' : '') + 'csrf_token=' + encodeURIComponent(csrfToken);
                }
            }
            return nativeFetch(input, init);
        };
        // Global variables
        let currentSection = 'dashboard';
        
        // Utility functions
        function showLoading() {
            document.getElementById('loadingOverlay').style.display = 'block';
        }
        
        function hideLoading() {
            document.getElementById('loadingOverlay').style.display = 'none';
        }
        
        function showAlert(message, type = 'info') {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.querySelector('.mobile-container').insertBefore(alertDiv, document.querySelector('.mobile-container').firstChild);
            
            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }
        
        // Authentication functions
        function showLogin() {
            document.getElementById('loginForm').style.display = 'block';
            document.getElementById('registerForm').style.display = 'none';
            document.querySelector('.nav-link.active').classList.remove('active');
            document.querySelector('a[onclick="showLogin()"]').classList.add('active');
        }
        
        function showRegister() {
            document.getElementById('loginForm').style.display = 'none';
            document.getElementById('registerForm').style.display = 'block';
            document.querySelector('.nav-link.active').classList.remove('active');
            document.querySelector('a[onclick="showRegister()"]').classList.add('active');
            loadHostels();
        }
        
        // Load hostels for registration
        function loadHostels() {
            fetch('mobile_app.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get_hostels'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const select = document.querySelector('#registerForm select[name="hostel_id"]');
                    select.innerHTML = '<option value="">Select Hostel</option>';
                    data.hostels.forEach(hostel => {
                        select.innerHTML += `<option value="${hostel.id}">${hostel.name}</option>`;
                    });
                }
            });
        }
        
        // Login form submission
        document.getElementById('loginFormData').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'login');
            
            showLoading();
            fetch('mobile_app.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    location.reload();
                } else {
                    showAlert(data.message, 'danger');
                }
            });
        });
        
        // Register form submission
        document.getElementById('registerFormData').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'register');
            
            showLoading();
            fetch('mobile_app.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showAlert(data.message, 'success');
                    showLogin();
                    document.getElementById('registerFormData').reset();
                } else {
                    showAlert(data.message, 'danger');
                }
            });
        });
        
        // Navigation functions
        function showSection(sectionName) {
            document.querySelectorAll('.section').forEach(section => {
                section.classList.remove('active');
            });
            document.getElementById(sectionName).classList.add('active');
            
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
            });
            document.querySelector(`a[onclick="showSection('${sectionName}')"]`).classList.add('active');
            
            currentSection = sectionName;
            
            // Load data for the section
            switch(sectionName) {
                case 'dashboard':
                    loadDashboard();
                    break;
                case 'tenants':
                    loadTenants();
                    break;
                case 'payments':
                    loadPayments();
                    break;
            }
        }
        
        // Dashboard functions
        function loadDashboard() {
            fetch('mobile_app.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get_dashboard_data'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const stats = data.stats;
                    document.getElementById('dashboardStats').innerHTML = `
                        <div class="col-6 mb-3">
                            <div class="stat-card">
                                <div class="stat-value">${stats.total_tenants}</div>
                                <div class="stat-label">Active Tenants</div>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="stat-card" style="background: linear-gradient(135deg, var(--success-color), var(--warning-color));">
                                <div class="stat-value">₹${parseFloat(stats.monthly_payments).toLocaleString()}</div>
                                <div class="stat-label">Monthly Payments</div>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="stat-card" style="background: linear-gradient(135deg, var(--info-color), var(--primary-color));">
                                <div class="stat-value">${stats.available_beds}</div>
                                <div class="stat-label">Available Beds</div>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="stat-card" style="background: linear-gradient(135deg, var(--warning-color), var(--danger-color));">
                                <div class="stat-value">${stats.occupied_beds}</div>
                                <div class="stat-label">Occupied Beds</div>
                            </div>
                        </div>
                    `;
                }
            });
        }
        
        // Tenant functions
        function loadTenants() {
            fetch('mobile_app.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get_tenants'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let html = '';
                    data.tenants.forEach(tenant => {
                        html += `
                            <div class="card tenant-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="card-title mb-1">${tenant.name}</h6>
                                            <p class="card-text small text-muted mb-1">
                                                <i class="fas fa-bed"></i> Room ${tenant.room_number} - Bed ${tenant.bed_number}
                                            </p>
                                            <p class="card-text small text-muted mb-1">
                                                <i class="fas fa-phone"></i> ${tenant.phone}
                                            </p>
                                            <p class="card-text small text-muted mb-0">
                                                <i class="fas fa-calendar"></i> Check-in: ${tenant.checkin_date}
                                            </p>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-success">Active</span>
                                            <div class="mt-2">
                                                <button class="btn btn-sm btn-outline-danger" onclick="checkoutTenant(${tenant.id}, '${tenant.name}')">
                                                    <i class="fas fa-sign-out-alt"></i> Check Out
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    document.getElementById('tenantsList').innerHTML = html || '<p class="text-muted">No active tenants found.</p>';
                }
            });
        }
        
        // Payment functions
        function loadPayments() {
            const startDate = document.getElementById('paymentStartDate').value;
            const endDate = document.getElementById('paymentEndDate').value;
            
            const formData = new FormData();
            formData.append('action', 'get_payments');
            formData.append('start_date', startDate);
            formData.append('end_date', endDate);
            
            fetch('mobile_app.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let html = '';
                    let totalAmount = 0;
                    
                    data.payments.forEach(payment => {
                        totalAmount += parseFloat(payment.amount);
                        html += `
                            <div class="card payment-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="card-title mb-1">${payment.tenant_name}</h6>
                                            <p class="card-text small text-muted mb-1">
                                                <i class="fas fa-bed"></i> Room ${payment.room_number} - Bed ${payment.bed_number}
                                            </p>
                                            <p class="card-text small text-muted mb-1">
                                                <i class="fas fa-calendar"></i> ${payment.date}
                                            </p>
                                            <p class="card-text small text-muted mb-0">
                                                <i class="fas fa-receipt"></i> ${payment.receipt_number}
                                            </p>
                                        </div>
                                        <div class="text-end">
                                            <h6 class="text-success mb-1">₹${parseFloat(payment.amount).toLocaleString()}</h6>
                                            <span class="badge bg-info">${payment.payment_type}</span>
                                            <div class="small text-muted mt-1">${payment.method}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    
                    if (data.payments.length > 0) {
                        html = `
                            <div class="alert alert-info">
                                <strong>Total Amount: ₹${totalAmount.toLocaleString()}</strong>
                            </div>
                        ` + html;
                    }
                    
                    document.getElementById('paymentsList').innerHTML = html || '<p class="text-muted">No payments found for the selected date range.</p>';
                }
            });
        }
        
        // Modal functions
        function showAddHostelModal() {
            const modal = new bootstrap.Modal(document.getElementById('addHostelModal'));
            modal.show();
        }
        
        function showAddTenantModal() {
            loadAvailableBeds();
            const modal = new bootstrap.Modal(document.getElementById('addTenantModal'));
            modal.show();
        }
        
        function showAddPaymentModal() {
            loadTenantsForPayment();
            const modal = new bootstrap.Modal(document.getElementById('addPaymentModal'));
            modal.show();
        }
        
        function loadAvailableBeds() {
            // For now, load beds from the default hostel
            const formData = new FormData();
            formData.append('action', 'get_available_beds');
            formData.append('hostel_id', '1'); // Default hostel
            
            fetch('mobile_app.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const select = document.querySelector('#addTenantForm select[name="bed_id"]');
                    select.innerHTML = '<option value="">Select Bed</option>';
                    data.beds.forEach(bed => {
                        select.innerHTML += `<option value="${bed.id}">Room ${bed.room_number} - Bed ${bed.bed_number} (₹${bed.monthly_rent})</option>`;
                    });
                }
            });
        }
        
        function loadTenantsForPayment() {
            fetch('mobile_app.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get_tenants'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const select = document.querySelector('#addPaymentForm select[name="tenant_id"]');
                    select.innerHTML = '<option value="">Select Tenant</option>';
                    data.tenants.forEach(tenant => {
                        select.innerHTML += `<option value="${tenant.id}">${tenant.name} - Room ${tenant.room_number}</option>`;
                    });
                }
            });
        }
        
        // Add functions
        function addHostel() {
            const formData = new FormData(document.getElementById('addHostelForm'));
            formData.append('action', 'add_hostel');
            
            showLoading();
            fetch('mobile_app.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showAlert(data.message, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('addHostelModal')).hide();
                    document.getElementById('addHostelForm').reset();
                } else {
                    showAlert(data.message, 'danger');
                }
            });
        }
        
        function addTenant() {
            const formData = new FormData(document.getElementById('addTenantForm'));
            formData.append('action', 'add_tenant');
            
            showLoading();
            fetch('mobile_app.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showAlert(data.message, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('addTenantModal')).hide();
                    document.getElementById('addTenantForm').reset();
                    if (currentSection === 'tenants') {
                        loadTenants();
                    }
                    if (currentSection === 'dashboard') {
                        loadDashboard();
                    }
                } else {
                    showAlert(data.message, 'danger');
                }
            });
        }
        
        function addPayment() {
            const formData = new FormData(document.getElementById('addPaymentForm'));
            formData.append('action', 'add_payment');
            
            showLoading();
            fetch('mobile_app.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showAlert(`Payment recorded successfully! Receipt: ${data.receipt_number}`, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('addPaymentModal')).hide();
                    document.getElementById('addPaymentForm').reset();
                    if (currentSection === 'payments') {
                        loadPayments();
                    }
                    if (currentSection === 'dashboard') {
                        loadDashboard();
                    }
                } else {
                    showAlert(data.message, 'danger');
                }
            });
        }
        
        // Checkout function
        function checkoutTenant(tenantId, tenantName) {
            if (confirm(`Are you sure you want to check out ${tenantName}?`)) {
                const formData = new FormData();
                formData.append('action', 'checkout_tenant');
                formData.append('tenant_id', tenantId);
                formData.append('checkout_date', new Date().toISOString().split('T')[0]);
                
                showLoading();
                fetch('mobile_app.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    hideLoading();
                    if (data.success) {
                        showAlert(data.message, 'success');
                        loadTenants();
                        if (currentSection === 'dashboard') {
                            loadDashboard();
                        }
                    } else {
                        showAlert(data.message, 'danger');
                    }
                });
            }
        }
        
        // Report function
        function generateReport() {
            const startDate = document.getElementById('reportStartDate').value;
            const endDate = document.getElementById('reportEndDate').value;
            
            const formData = new FormData();
            formData.append('action', 'get_payments');
            formData.append('start_date', startDate);
            formData.append('end_date', endDate);
            
            showLoading();
            fetch('mobile_app.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    let totalAmount = 0;
                    let paymentsByType = {};
                    
                    data.payments.forEach(payment => {
                        totalAmount += parseFloat(payment.amount);
                        if (!paymentsByType[payment.payment_type]) {
                            paymentsByType[payment.payment_type] = 0;
                        }
                        paymentsByType[payment.payment_type] += parseFloat(payment.amount);
                    });
                    
                    let html = `
                        <div class="card">
                            <div class="card-header">
                                <h6>Payment Report (${startDate} to ${endDate})</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <div class="stat-card">
                                            <div class="stat-value">₹${totalAmount.toLocaleString()}</div>
                                            <div class="stat-label">Total Payments</div>
                                        </div>
                                    </div>
                                </div>
                                <h6>Payment Breakdown:</h6>
                                <div class="row">
                    `;
                    
                    Object.keys(paymentsByType).forEach(type => {
                        html += `
                            <div class="col-6 mb-2">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h6>₹${paymentsByType[type].toLocaleString()}</h6>
                                        <small class="text-muted">${type.replace('_', ' ').toUpperCase()}</small>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    
                    html += `
                                </div>
                                <div class="mt-3">
                                    <small class="text-muted">Total Transactions: ${data.payments.length}</small>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    document.getElementById('reportResults').innerHTML = html;
                } else {
                    showAlert(data.message, 'danger');
                }
            });
        }
        
        // Logout function
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                fetch('mobile_app.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=logout'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    }
                });
            }
        }
        
        // Initialize app
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_SESSION['user_id'])): ?>
                loadDashboard();
                
                // Auto-update bed rent when bed is selected
                const bedSelect = document.querySelector('#addTenantForm select[name="bed_id"]');
                if (bedSelect) {
                    bedSelect.addEventListener('change', function() {
                        const selectedOption = this.options[this.selectedIndex];
                        const rentText = selectedOption.text.match(/₹(\d+)/);
                        if (rentText) {
                            document.querySelector('#addTenantForm input[name="monthly_rent"]').value = rentText[1];
                        }
                    });
                }
            <?php endif; ?>
        });
    </script>
</body>
</html>
