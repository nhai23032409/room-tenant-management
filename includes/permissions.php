<?php
// permissions.php - Role-based access control for HomeStay Dorm Management System

// Define role permissions
define('PERMISSIONS', [
    'admin' => [
        'manage_hostels',
        'manage_rooms',
        'manage_beds',
        'manage_tenants',
        'manage_payments',
        'manage_services',
        'manage_contracts',
        'manage_viewings',
        'manage_assets',
        'view_reports',
        'manage_users'
    ],
    'sale' => [
        'manage_tenants',
        'manage_viewings',
        'create_deposit',
        'view_rooms'
    ],
    'manager' => [
        'manage_rooms',
        'manage_beds',
        'approve_deposit',
        'handover_room',
        'checkout_tenant',
        'manage_assets',
        'manage_contracts'
    ],
    'accountant' => [
        'manage_payments',
        'calculate_deposit',
        'generate_invoice',
        'manage_debts',
        'view_reports'
    ],
    'staff' => [
        'view_rooms',
        'view_tenants'
    ]
]);

/**
 * Check if user has a specific role
 */
function has_role($user_role, $required_role) {
    $hierarchy = ['admin' => 5, 'manager' => 4, 'accountant' => 3, 'sale' => 2, 'staff' => 1];
    return isset($hierarchy[$user_role]) && $hierarchy[$user_role] >= $hierarchy[$required_role];
}

/**
 * Check if user has a specific permission
 */
function has_permission($user_role, $permission) {
    if (!isset($_SESSION['user_role'])) {
        return false;
    }

    // Administrators are the system owners. Keeping this rule here prevents
    // individual endpoints from accidentally locking an admin out.
    if ($user_role === 'admin') {
        return true;
    }

    $user_perms = PERMISSIONS[$user_role] ?? [];
    return in_array($permission, $user_perms);
}

/**
 * Require specific role to access page
 */
function require_role($required_role) {
    if (!isset($_SESSION['user_role']) || !has_role($_SESSION['user_role'], $required_role)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied. Insufficient permissions.']);
        exit;
    }
}

/**
 * Require specific permission to access page
 */
function require_permission($permission) {
    if (!isset($_SESSION['user_role']) || !has_permission($_SESSION['user_role'], $permission)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied. Permission required: ' . $permission]);
        exit;
    }
}

/**
 * Get all permissions for current user
 */
function get_user_permissions() {
    return PERMISSIONS[$_SESSION['user_role']] ?? [];
}

/** JSON-safe guards for API endpoints. */
function require_api_permission($permission) {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Please login.']);
        exit;
    }

    if (!has_permission($_SESSION['user_role'] ?? '', $permission)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied.']);
        exit;
    }
}

// Role-checking convenience functions
function is_admin() { return has_role($_SESSION['user_role'] ?? '', 'admin'); }
function is_manager() { return has_role($_SESSION['user_role'] ?? '', 'manager'); }
function is_sale() { return has_role($_SESSION['user_role'] ?? '', 'sale'); }
function is_accountant() { return has_role($_SESSION['user_role'] ?? '', 'accountant'); }
function is_staff() { return has_role($_SESSION['user_role'] ?? '', 'staff'); }
?>
