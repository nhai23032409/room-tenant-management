-- --------------------------------------------------------
-- Tenant Management Mobile Application SQL Schema
-- Updated for HomeStay Dorm Management System
-- --------------------------------------------------------

CREATE DATABASE IF NOT EXISTS tenant_management;
USE tenant_management;

-- Users table (for authentication and role management)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'staff', 'owner', 'sale', 'manager', 'accountant') DEFAULT 'staff',
    hostel_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Hostels table (organizations/hostels)
CREATE TABLE IF NOT EXISTS hostels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    address TEXT NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(100),
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Room Types table
CREATE TABLE IF NOT EXISTS room_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    standard VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Services table
CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    unit VARCHAR(20),
    price DECIMAL(10,2) DEFAULT 0.00,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Rooms table
CREATE TABLE IF NOT EXISTS rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hostel_id INT NOT NULL,
    room_number VARCHAR(20) NOT NULL,
    floor INT DEFAULT 1,
    room_type_id INT,
    capacity INT NOT NULL DEFAULT 1,
    gender_allowed ENUM('male', 'female', 'any') DEFAULT 'any',
    monthly_rent DECIMAL(10,2) DEFAULT 0.00,
    description TEXT,
    status ENUM('available', 'deposited', 'occupied', 'maintenance') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (hostel_id) REFERENCES hostels(id) ON DELETE CASCADE,
    FOREIGN KEY (room_type_id) REFERENCES room_types(id) ON DELETE SET NULL,
    UNIQUE KEY unique_room_per_hostel (hostel_id, room_number)
);

-- Beds table
CREATE TABLE IF NOT EXISTS beds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    bed_number VARCHAR(20) NOT NULL,
    status ENUM('available', 'deposited', 'occupied', 'maintenance') DEFAULT 'available',
    deposit_expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    UNIQUE KEY unique_bed_per_room (room_id, bed_number)
);

-- Room Assets table
CREATE TABLE IF NOT EXISTS room_assets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    asset_name VARCHAR(100) NOT NULL,
    quantity INT DEFAULT 1,
    status ENUM('good', 'damaged', 'missing') DEFAULT 'good',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
);

-- Tenants table
CREATE TABLE IF NOT EXISTS tenants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20) NOT NULL,
    address TEXT,
    nationality VARCHAR(50),
    gender ENUM('male', 'female', 'other') DEFAULT 'other',
    id_proof_type ENUM('cccd', 'passport', 'driving_license', 'other') DEFAULT 'cccd',
    id_proof_number VARCHAR(50),
    emergency_contact VARCHAR(20),
    emergency_contact_name VARCHAR(100),
    bed_id INT,
    checkin_date DATE,
    checkout_date DATE,
    monthly_rent DECIMAL(10,2) DEFAULT 0.00,
    security_deposit DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('pending', 'active', 'inactive', 'checked_out') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (bed_id) REFERENCES beds(id) ON DELETE SET NULL
);

-- Contracts table
CREATE TABLE IF NOT EXISTS contracts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    room_id INT NOT NULL,
    bed_id INT,
    start_date DATE NOT NULL,
    end_date DATE,
    deposit DECIMAL(10,2) DEFAULT 0.00,
    terms TEXT,
    signature_path VARCHAR(255),
    pdf_path VARCHAR(255),
    status ENUM('active', 'ended', 'cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (bed_id) REFERENCES beds(id) ON DELETE SET NULL
);

-- Viewings table
CREATE TABLE IF NOT EXISTS viewings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT,
    hostel_id INT NOT NULL,
    room_id INT,
    scheduled_at DATETIME,
    status ENUM('scheduled', 'completed', 'cancelled', 'no_show') DEFAULT 'scheduled',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL,
    FOREIGN KEY (hostel_id) REFERENCES hostels(id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL
);

-- Payments table
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    date DATE NOT NULL,
    method ENUM('cash', 'card', 'upi', 'bank_transfer', 'cheque', 'pending') DEFAULT 'cash',
    notes TEXT,
    payment_type ENUM('rent', 'security_deposit', 'deposit_refund', 'maintenance', 'penalty', 'other', 'electricity', 'water', 'wifi') DEFAULT 'rent',
    receipt_number VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

-- Debts table
CREATE TABLE IF NOT EXISTS debts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    type ENUM('rent', 'service', 'penalty', 'damage') DEFAULT 'rent',
    due_date DATE,
    status ENUM('pending', 'paid', 'overdue') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

-- Check-in/Check-out history table
CREATE TABLE IF NOT EXISTS checkin_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    bed_id INT NOT NULL,
    checkin_date DATE NOT NULL,
    checkout_date DATE,
    rent_amount DECIMAL(10,2),
    security_deposit DECIMAL(10,2),
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (bed_id) REFERENCES beds(id) ON DELETE CASCADE
);

-- Immutable financial snapshot created when an authorized checkout is settled.
CREATE TABLE IF NOT EXISTS checkout_settlements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    contract_id INT NOT NULL,
    checkout_date DATE NOT NULL,
    deposit_amount DECIMAL(10,2) NOT NULL,
    refund_amount DECIMAL(10,2) NOT NULL,
    deductions DECIMAL(10,2) NOT NULL DEFAULT 0,
    amount_to_pay DECIMAL(10,2) NOT NULL DEFAULT 0,
    final_refund DECIMAL(10,2) NOT NULL DEFAULT 0,
    payment_method ENUM('cash', 'bank_transfer') NOT NULL,
    confirmed_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
    FOREIGN KEY (confirmed_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Activity log table for audit trail
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Add foreign key constraint for hostel_id in users table
ALTER TABLE users ADD FOREIGN KEY (hostel_id) REFERENCES hostels(id) ON DELETE SET NULL;

-- Create indexes for better performance
ALTER TABLE tenants ADD INDEX idx_tenants_status (status);
ALTER TABLE tenants ADD INDEX idx_tenants_checkin (checkin_date);
ALTER TABLE payments ADD INDEX idx_payments_date (date);
ALTER TABLE payments ADD INDEX idx_payments_tenant (tenant_id);
ALTER TABLE beds ADD INDEX idx_beds_status (status);
ALTER TABLE users ADD INDEX idx_users_email (email);
ALTER TABLE activity_log ADD INDEX idx_activity_log_user (user_id);
ALTER TABLE activity_log ADD INDEX idx_activity_log_date (created_at);
