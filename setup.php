<?php
/**
 * Tenant Management System Setup Script
 * This script helps initialize the database and set up the application
 */

// Prevent direct access after setup is complete
if (file_exists('setup_complete.lock')) {
    die('Setup has already been completed. Delete setup_complete.lock to run setup again.');
}

// Handle setup form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_action'])) {
    $action = $_POST['setup_action'];
    
    switch ($action) {
        case 'test_connection':
            $host = $_POST['host'];
            $username = $_POST['username'];
            $password = $_POST['password'];
            
            try {
                $pdo = new PDO("mysql:host=$host", $username, $password);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                echo json_encode(['success' => true, 'message' => 'Connection successful!']);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Connection failed: ' . $e->getMessage()]);
            }
            exit;
            
        case 'create_database':
            $host = $_POST['host'];
            $username = $_POST['username'];
            $password = $_POST['password'];
            $database = $_POST['database'];
            
            try {
                $pdo = new PDO("mysql:host=$host", $username, $password);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Create database
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                
                echo json_encode(['success' => true, 'message' => 'Database created successfully!']);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Database creation failed: ' . $e->getMessage()]);
            }
            exit;
            
        case 'install_tables':
            $host = $_POST['host'];
            $username = $_POST['username'];
            $password = $_POST['password'];
            $database = $_POST['database'];

            if (empty($database) || $database === 'undefined') {
                echo json_encode(['success' => false, 'message' => 'Database name not provided or invalid. Please ensure you have completed the previous step to create the database.']);
                exit;
            }
            
            try {
                $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                // Helper function to execute SQL from a file
                $executeSqlFile = function($pdo, $filePath) {
                    $sql = file_get_contents($filePath);
                    if ($sql === false) {
                        throw new Exception("Could not read SQL file: $filePath");
                    }
                    
                    // Remove C-style and SQL-style comments
                    $sql = preg_replace('!/\*.*?\*/!s', '', $sql);
                    $sql = preg_replace('/--.*$/m', '', $sql);
                    
                    // Remove any CREATE DATABASE and USE statements
                    $sql = preg_replace('/^\s*(CREATE DATABASE|USE).+;/m', '', $sql);

                    // Trim whitespace from the whole script
                    $sql = trim($sql);

                    if (empty($sql)) {
                        return; // Nothing to execute
                    }
                    
                    // Split into individual statements
                    $statements = explode(';', $sql);
                    $statementCount = 0;
                    
                    foreach ($statements as $statement) {
                        $statement = trim($statement);
                        if (!empty($statement)) {
                            $statementCount++;
                            try {
                                $pdo->exec($statement);
                            } catch (PDOException $e) {
                                $errorInfo = $e->errorInfo[2] ?? $e->getMessage(); // Get the driver-specific error message.
                                throw new Exception("Error on statement #{$statementCount}: {$errorInfo}. Statement: `" . htmlspecialchars(substr($statement, 0, 200)) . "...`");
                            }
                        }
                    }
                };

                // Process schema.sql first
                $executeSqlFile($pdo, 'database/schema.sql');

                // Now process seeds.sql
                $executeSqlFile($pdo, 'database/seeds.sql');

                echo json_encode(['success' => true, 'message' => 'Database tables and sample data created successfully!']);
            } catch (Exception $e) {
                // Catch both PDOException and general Exception from our helper
                echo json_encode(['success' => false, 'message' => 'Table creation failed: ' . $e->getMessage()]);
            }
            exit;
            
        case 'create_config':
    $host = $_POST['host'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    $database = $_POST['database'];
    
    $config_content = <<<CONFIG
<?php
// Database Configuration
\$host = "$host";
\$dbname = "$database";
\$user = "$username";
\$pass = "$password";

// Security Headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");

// Database Connection
try {
    \$pdo = new PDO("mysql:host=\$host;dbname=\$dbname;charset=utf8mb4", \$user, \$pass);
    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    \$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    \$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    \$dbh = \$pdo; // Alias for compatibility
} catch (PDOException \$e) {
    die("Database Connection Error: " . \$e->getMessage());
}

// Utility Functions
function sanitize_input(\$data) {
    return htmlspecialchars(strip_tags(trim(\$data)));
}

function log_activity(\$pdo, \$user_id, \$action, \$description) {
    \$ip = \$_SERVER['REMOTE_ADDR'] ?? 'unknown';
    \$user_agent = \$_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    \$stmt = \$pdo->prepare("INSERT INTO activity_log (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
    \$stmt->execute([\$user_id, \$action, \$description, \$ip, \$user_agent]);
}

function generate_receipt_number() {
    return 'RCP' . date('Ymd') . rand(1000, 9999);
}

// Constants
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf']);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty(\$_SESSION['csrf_token'])){
    \$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function csrf_token() { return \$_SESSION['csrf_token']; }
function verify_csrf(\$token) { return is_string(\$token) && hash_equals(\$_SESSION['csrf_token'], \$token); }
function require_csrf() {
    if (!verify_csrf(\$_POST['csrf_token'] ?? '')) {
        http_response_code(419);
        echo json_encode(['success' => false, 'message' => 'Invalid or missing CSRF token.']);
        exit;
    }
}
?>
CONFIG;
            
    if (file_put_contents('includes/config.php', $config_content)) {
        echo json_encode(['success' => true, 'message' => 'Configuration file created successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create configuration file']);
    }
    exit;
            
        case 'create_admin':
            $name = $_POST['admin_name'];
            $email = $_POST['admin_email'];
            $password = $_POST['admin_password'];
            $hostel_name = $_POST['hostel_name'];
            $hostel_address = $_POST['hostel_address'];
            
            try {
                include 'includes/config.php';
                
                // Check if admin already exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'Admin user already exists']);
                    exit;
                }
                
                $pdo->beginTransaction();
                
                // Create hostel
                $stmt = $pdo->prepare("INSERT INTO hostels (name, address) VALUES (?, ?)");
                $stmt->execute([$hostel_name, $hostel_address]);
                $hostel_id = $pdo->lastInsertId();
                
                // Create admin user
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, hostel_id) VALUES (?, ?, ?, 'admin', ?)");
                $stmt->execute([$name, $email, $password_hash, $hostel_id]);
                
                $pdo->commit();
                
                echo json_encode(['success' => true, 'message' => 'Admin user created successfully!']);
            } catch (Exception $e) {
                $pdo->rollback();
                echo json_encode(['success' => false, 'message' => 'Failed to create admin user: ' . $e->getMessage()]);
            }
            exit;
            
        case 'complete_setup':
            // Create lock file to prevent re-running setup
            file_put_contents('setup_complete.lock', date('Y-m-d H:i:s'));
            echo json_encode(['success' => true, 'message' => 'Setup completed successfully!']);
            exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenant Management System Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .setup-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
        }
        
        .setup-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .setup-header {
            background: linear-gradient(135deg, #007bff, #17a2b8);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .setup-step {
            display: none;
            padding: 2rem;
        }
        
        .setup-step.active {
            display: block;
        }
        
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            padding: 0 1rem;
        }
        
        .step {
            flex: 1;
            text-align: center;
            padding: 0.5rem;
            background: #e9ecef;
            border-radius: 5px;
            margin: 0 0.2rem;
            font-size: 0.9rem;
        }
        
        .step.active {
            background: #007bff;
            color: white;
        }
        
        .step.completed {
            background: #28a745;
            color: white;
        }
        
        .btn {
            border-radius: 10px;
        }
        
        .form-control {
            border-radius: 10px;
        }
        
        .progress-bar {
            transition: width 0.3s ease;
        }
        
        .success-icon {
            color: #28a745;
            font-size: 1.5rem;
        }
        
        .error-icon {
            color: #dc3545;
            font-size: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-card">
            <div class="setup-header">
                <h1><i class="fas fa-cogs"></i> Tenant Management System Setup</h1>
                <p class="mb-0">Let's get your hostel management system ready!</p>
            </div>
            
            <div class="step-indicator">
                <div class="step active" id="step1">1. Database Connection</div>
                <div class="step" id="step2">2. Create Database</div>
                <div class="step" id="step3">3. Install Tables</div>
                <div class="step" id="step4">4. Configuration</div>
                <div class="step" id="step5">5. Admin Setup</div>
                <div class="step" id="step6">6. Complete</div>
            </div>
            
            <div class="progress mb-3">
                <div class="progress-bar" role="progressbar" style="width: 16.66%"></div>
            </div>
            
            <!-- Step 1: Database Connection -->
            <div class="setup-step active" id="setupStep1">
                <h3><i class="fas fa-database"></i> Database Connection</h3>
                <p>Enter your MySQL database connection details:</p>
                
                <form id="connectionForm">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Host</label>
                            <input type="text" class="form-control" name="host" value="localhost" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" name="username" value="root" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" name="password">
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plug"></i> Test Connection
                    </button>
                </form>
                
                <div id="connectionResult" class="mt-3"></div>
            </div>
            
            <!-- Step 2: Create Database -->
            <div class="setup-step" id="setupStep2">
                <h3><i class="fas fa-database"></i> Create Database</h3>
                <p>Create a new database for the tenant management system:</p>
                
                <form id="databaseForm">
                    <div class="mb-3">
                        <label class="form-label">Database Name</label>
                        <input type="text" class="form-control" name="database" value="tenant_management" required>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create Database
                    </button>
                </form>
                
                <div id="databaseResult" class="mt-3"></div>
            </div>
            
            <!-- Step 3: Install Tables -->
            <div class="setup-step" id="setupStep3">
                <h3><i class="fas fa-table"></i> Install Database Tables</h3>
                <p>Install the required database tables and sample data:</p>
                
                <button class="btn btn-primary" onclick="installTables()">
                    <i class="fas fa-download"></i> Install Tables
                </button>
                
                <div id="tablesResult" class="mt-3"></div>
            </div>
            
            <!-- Step 4: Configuration -->
            <div class="setup-step" id="setupStep4">
                <h3><i class="fas fa-cog"></i> Create Configuration</h3>
                <p>Generate the configuration file with your database settings:</p>
                
                <button class="btn btn-primary" onclick="createConfig()">
                    <i class="fas fa-file-code"></i> Create Configuration
                </button>
                
                <div id="configResult" class="mt-3"></div>
            </div>
            
            <!-- Step 5: Admin Setup -->
            <div class="setup-step" id="setupStep5">
                <h3><i class="fas fa-user-shield"></i> Create Admin Account</h3>
                <p>Set up the initial admin account and hostel:</p>
                
                <form id="adminForm">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Admin Name</label>
                            <input type="text" class="form-control" name="admin_name" value="Admin User" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Admin Email</label>
                            <input type="email" class="form-control" name="admin_email" value="admin@example.com" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Admin Password</label>
                        <input type="password" class="form-control" name="admin_password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Hostel Name</label>
                        <input type="text" class="form-control" name="hostel_name" value="My Hostel" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Hostel Address</label>
                        <textarea class="form-control" name="hostel_address" rows="3" required>123 Main Street, City, State</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Create Admin
                    </button>
                </form>
                
                <div id="adminResult" class="mt-3"></div>
            </div>
            
            <!-- Step 6: Complete -->
            <div class="setup-step" id="setupStep6">
                <h3><i class="fas fa-check-circle"></i> Setup Complete!</h3>
                <p>Your tenant management system is now ready to use.</p>
                
                <div class="alert alert-success">
                    <h5><i class="fas fa-thumbs-up"></i> Congratulations!</h5>
                    <p>The setup has been completed successfully. You can now:</p>
                    <ul>
                        <li>Access the application at <a href="mobile_app.php">mobile_app.php</a></li>
                        <li>Manage rooms and beds at <a href="room_management.php">room_management.php</a></li>
                        <li>Log in with your admin credentials</li>
                    </ul>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <a href="mobile_app.php" class="btn btn-success w-100">
                            <i class="fas fa-mobile-alt"></i> Open Mobile App
                        </a>
                    </div>
                    <div class="col-md-6">
                        <a href="room_management.php" class="btn btn-primary w-100">
                            <i class="fas fa-bed"></i> Manage Rooms
                        </a>
                    </div>
                </div>
                
                <div class="mt-3">
                    <button class="btn btn-warning" onclick="completeSetup()">
                        <i class="fas fa-lock"></i> Lock Setup (Prevent Re-running)
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentStep = 1;
        let setupData = {};
        
        function showStep(step) {
            document.querySelectorAll('.setup-step').forEach(s => s.classList.remove('active'));
            document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
            
            document.getElementById(`setupStep${step}`).classList.add('active');
            document.getElementById(`step${step}`).classList.add('active');
            
            // Mark previous steps as completed
            for (let i = 1; i < step; i++) {
                document.getElementById(`step${i}`).classList.add('completed');
            }
            
            // Update progress bar
            const progress = (step / 6) * 100;
            document.querySelector('.progress-bar').style.width = progress + '%';
            
            currentStep = step;
        }
        
        function showResult(containerId, success, message) {
            const container = document.getElementById(containerId);
            const iconClass = success ? 'fas fa-check-circle success-icon' : 'fas fa-times-circle error-icon';
            const alertClass = success ? 'alert-success' : 'alert-danger';
            
            container.innerHTML = `
                <div class="alert ${alertClass}">
                    <i class="${iconClass}"></i> ${message}
                </div>
            `;
            
            if (success) {
                setTimeout(() => {
                    showStep(currentStep + 1);
                }, 1500);
            }
        }
        
        // Step 1: Test Connection
        document.getElementById('connectionForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('setup_action', 'test_connection');
            
            // Store connection data
            setupData.host = formData.get('host');
            setupData.username = formData.get('username');
            setupData.password = formData.get('password');
            
            fetch('setup.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                showResult('connectionResult', data.success, data.message);
            });
        });
        
        // Step 2: Create Database
        document.getElementById('databaseForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('setup_action', 'create_database');
            formData.append('host', setupData.host);
            formData.append('username', setupData.username);
            formData.append('password', setupData.password);
            formData.append('database', this.database.value);
            
            setupData.database = this.database.value;
            
            fetch('setup.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                showResult('databaseResult', data.success, data.message);
            });
        });
        
        // Step 3: Install Tables
        function installTables() {
            const formData = new FormData();
            formData.append('setup_action', 'install_tables');
            formData.append('host', setupData.host);
            formData.append('username', setupData.username);
            formData.append('password', setupData.password);
            formData.append('database', setupData.database);
            
            fetch('setup.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                showResult('tablesResult', data.success, data.message);
            });
        }
        
        // Step 4: Create Configuration
        function createConfig() {
            const formData = new FormData();
            formData.append('setup_action', 'create_config');
            formData.append('host', setupData.host);
            formData.append('username', setupData.username);
            formData.append('password', setupData.password);
            formData.append('database', setupData.database);
            
            fetch('setup.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                showResult('configResult', data.success, data.message);
            });
        }
        
        // Step 5: Create Admin
        document.getElementById('adminForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('setup_action', 'create_admin');
            
            fetch('setup.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                showResult('adminResult', data.success, data.message);
            });
        });
        
        // Step 6: Complete Setup
        function completeSetup() {
            const formData = new FormData();
            formData.append('setup_action', 'complete_setup');
            
            fetch('setup.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Setup locked successfully! You can now use the application.');
                    location.reload();
                }
            });
        }
    </script>
</body>
</html>