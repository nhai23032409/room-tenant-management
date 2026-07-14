-- Test data for checkout and refund logic (T-02)
-- Use this data to test the 4 refund tiers in workflow.php

USE tenant_management;

-- We need 4 new beds in an existing room (e.g., room_id = 1)
-- Assuming room 1 in hostel 1 exists and has capacity.
INSERT INTO beds (room_id, bed_number, status) VALUES
(1, 'T-01', 'occupied'),
(1, 'T-02', 'occupied'),
(1, 'T-03', 'occupied'),
(1, 'T-04', 'occupied');

-- Get the IDs of the newly inserted beds
SET @bed_A = LAST_INSERT_ID();
SET @bed_B = @bed_A + 1;
SET @bed_C = @bed_A + 2;
SET @bed_D = @bed_A + 3;

-- Insert 4 tenants for each scenario
INSERT INTO tenants (name, phone, bed_id, checkin_date, status) VALUES
('Test Tenant A (No Signature)', '0900000001', @bed_A, CURDATE(), 'active'),
('Test Tenant B (<6 Months)', '0900000002', @bed_B, CURDATE() - INTERVAL 2 MONTH, 'active'),
('Test Tenant C (>6 Months + Debt)', '0900000003', @bed_C, CURDATE() - INTERVAL 8 MONTH, 'active'),
('Test Tenant D (Expired)', '0900000004', @bed_D, CURDATE() - INTERVAL 14 MONTH, 'active');

-- Get the IDs of the newly inserted tenants
SET @tenant_A = LAST_INSERT_ID();
SET @tenant_B = @tenant_A + 1;
SET @tenant_C = @tenant_A + 2;
SET @tenant_D = @tenant_A + 3;

-- Insert deposit payments for all 4 tenants
-- Assuming rent for this room is 5,000,000, deposit is 2 months = 10,000,000
INSERT INTO payments (tenant_id, amount, date, method, payment_type, notes) VALUES
(@tenant_A, 10000000, CURDATE(), 'cash', 'security_deposit', 'Initial deposit'),
(@tenant_B, 10000000, CURDATE() - INTERVAL 2 MONTH, 'cash', 'security_deposit', 'Initial deposit'),
(@tenant_C, 10000000, CURDATE() - INTERVAL 8 MONTH, 'cash', 'security_deposit', 'Initial deposit'),
(@tenant_D, 10000000, CURDATE() - INTERVAL 14 MONTH, 'cash', 'security_deposit', 'Initial deposit');

-- Insert contracts for each tenant
INSERT INTO contracts (tenant_id, room_id, bed_id, start_date, end_date, deposit, signature_path, status) VALUES
-- Tenant A: No signature, should get 80% refund.
(@tenant_A, 1, @bed_A, CURDATE(), CURDATE() + INTERVAL 1 YEAR, 10000000, NULL, 'active'),

-- Tenant B: Signed, stay < 6 months, should get 50% refund.
(@tenant_B, 1, @bed_B, CURDATE() - INTERVAL 2 MONTH, CURDATE() + INTERVAL 10 MONTH, 10000000, 'uploads/signatures/sample_sig.png', 'active'),

-- Tenant C: Signed, stay > 6 months, should get 70% refund.
(@tenant_C, 1, @bed_C, CURDATE() - INTERVAL 8 MONTH, CURDATE() + INTERVAL 4 MONTH, 10000000, 'uploads/signatures/sample_sig.png', 'active'),

-- Tenant D: Signed, contract expired, should get 100% refund.
(@tenant_D, 1, @bed_D, CURDATE() - INTERVAL 14 MONTH, CURDATE() - INTERVAL 2 MONTH, 10000000, 'uploads/signatures/sample_sig.png', 'active');

-- Add outstanding debts for Tenant C to test deductions
-- 500,000 in rent and 50,000 in services
INSERT INTO debts (tenant_id, amount, type, due_date, status) VALUES
(@tenant_C, 500000, 'rent', CURDATE() - INTERVAL 1 MONTH, 'pending'),
(@tenant_C, 50000, 'service', CURDATE() - INTERVAL 1 MONTH, 'pending');

-- Note: To run this script, you may need a MySQL client.
-- After running, go to workflow.php in the application.
-- Select each of the 4 "Test Tenant"s in the checkout section, set the checkout date to today,
-- and click "Calculate".
-- Expected results (Deposit = 10,000,000):
-- Tenant A: Refund = 8,000,000 (80%)
-- Tenant B: Refund = 5,000,000 (50%)
-- Tenant C: Refund = 7,000,000 (70%) - 550,000 (debt) = 6,450,000
-- Tenant D: Refund = 10,000,000 (100%)
