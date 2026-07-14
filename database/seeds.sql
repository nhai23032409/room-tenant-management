-- --------------------------------------------------------
-- Sample Data for HomeStay Dorm Management System
-- --------------------------------------------------------

-- Use the correct database
USE tenant_management;

-- Insert default room types
INSERT INTO room_types (name, description, standard) VALUES
('Single', 'Phòng đơn', 'Tiêu chuẩn cao'),
('Double', 'Phòng 2 người', 'Tiêu chuẩn trung bình'),
('Triple', 'Phòng 3 người', 'Tiêu chuẩn cơ bản');

-- Insert default services
INSERT INTO services (name, unit, price, description) VALUES
('Điện', 'kWh', 3000.00, 'Tiền điện'),
('Nước', 'm3', 20000.00, 'Tiền nước'),
('Wifi', 'tháng', 100000.00, 'Dịch vụ internet');

-- Insert default admin user and sample data
INSERT INTO hostels (name, address, phone, email, description) VALUES
('Green Valley Hostel', '123 Main Street, City Center', '+1-555-0123', 'info@greenvalley.com', 'Premium hostel with modern amenities'),
('Sunrise Hostel', '456 Oak Avenue, Downtown', '+1-555-0456', 'contact@sunrisehostel.com', 'Budget-friendly accommodation for students');

-- Insert default admin user (password: admin123)
INSERT INTO users (name, email, password_hash, role, hostel_id) VALUES
('Admin User', 'admin@tenantmanagement.com', '$2y$10$wHfXfE3EB4h8V6IbRHfWJOsUZmkHiVim2k8Sx06aTCWhWmHAB7Uwa', 'admin', 1),
('Staff User', 'staff@greenvalley.com', '$2y$10$wHfXfE3EB4h8V6IbRHfWJOsUZmkHiVim2k8Sx06aTCWhWmHAB7Uwa', 'staff', 1);

-- Insert sample rooms
INSERT INTO rooms (hostel_id, room_number, floor, room_type_id, capacity, monthly_rent, description) VALUES
(1, '101', 1, 2, 2, 5000.00, 'Double sharing room with attached bathroom'),
(1, '102', 1, 1, 1, 7000.00, 'Single room with AC'),
(1, '103', 2, 3, 3, 4000.00, 'Triple sharing room'),
(2, '201', 1, 2, 2, 4500.00, 'Double sharing room'),
(2, '202', 1, 1, 1, 6000.00, 'Single room with balcony');

-- Insert sample beds
INSERT INTO beds (room_id, bed_number, status) VALUES
(1, 'A', 'available'),
(1, 'B', 'available'),
(2, 'A', 'available'),
(3, 'A', 'available'),
(3, 'B', 'available'),
(3, 'C', 'available'),
(4, 'A', 'available'),
(4, 'B', 'available'),
(5, 'A', 'available');
