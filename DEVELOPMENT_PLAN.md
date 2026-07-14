# Development Plan: HomeStay Dorm Management System

This document outlines the development plan to complete the HomeStay Dorm Management System based on the `PRD.md`.

## Phase 1: Core Functionality (FR-1, FR-2, FR-3)

### 1.1. Category Management (Admin)
- **Objective:** Complete the management of all master data.
- **Tasks:**
    - [ ] Create UI for managing room types (`room_types`).
    - [ ] Create UI for managing services (`services`).
    - [ ] Create UI for managing room assets (`room_assets`).
    - [ ] Create UI for managing hostels (`hostels`).
    - [ ] Ensure all category management pages are only accessible by Admins.

### 1.2. Rental Registration Process
- **Objective:** Implement the full workflow for sales to register and manage potential tenants.
- **Tasks:**
    - [ ] Enhance `registration.php` to capture all customer information as per FR-2.2.
    - [ ] Implement the logic for sales to check for available rooms and beds based on customer criteria (FR-2.3).
    - [ ] Create a UI for sales to schedule and manage viewing appointments (`viewings`) (FR-2.4).
    - [ ] Create a UI for sales to update the status of a viewing (FR-2.5).

### 1.3. Deposit & Confirmation Process
- **Objective:** Implement the deposit and confirmation workflow.
- **Tasks:**
    - [ ] Implement the logic for the accountant to calculate the deposit amount (FR-3.2).
    - [ ] Create a mechanism to generate a deposit payment request with a 24-hour expiration (FR-3.3).
    - [ ] Create a background job to cancel expired deposit requests.
    - [ ] Create a UI for the manager to confirm deposit payments and lock the room/bed (FR-3.5, FR-3.6).

## Phase 2: Check-in, Check-out, and Payments (FR-4, FR-5, FR-6)

### 2.1. Check-in, Contract Signing, and Handover
- **Objective:** Implement the full check-in and handover process.
- **Tasks:**
    - [ ] Implement logic to check accommodation conditions (FR-4.3).
    - [ ] Automatically generate rental contracts with all required fields (FR-4.4).
    - [ ] Integrate `signature-pad.php` for digital signatures on contracts (FR-4.5).
    - [ ] Implement the payment process for the first rental period and other fees (FR-4.6).
    - [ ] Create a UI for the manager to handle the room/bed handover process (FR-4.8).
    - [ ] Generate a handover receipt (FR-4.9).

### 2.2. Check-out and Deposit Refund
- **Objective:** Implement the check-out and deposit refund process.
- **Tasks:**
    - [ ] Create a UI for tenants to request check-out (FR-5.1).
    - [ ] Implement the logic for the accountant to calculate the deposit refund based on the rules in FR-5.4.
    - [ ] Implement the logic for deducting outstanding costs (FR-5.5).
    - [ ] Generate a final settlement statement (FR-5.7).
    - [ ] Implement the process of contract liquidation and room/bed status updates (FR-5.9, FR-5.11).

### 2.3. Payment & Invoicing
- **Objective:** Complete the payment and invoicing module.
- **Tasks:**
    - [ ] Create a UI for the accountant to manage all payments (FR-6.1).
    - [ ] Implement the generation of receipts and invoices (FR-6.3).
    - [ ] Implement a system for tracking customer debt (FR-6.4).

## Phase 3: Reporting and Non-Functional Requirements (FR-7, NFR)

### 3.1. Reporting & Statistics
- **Objective:** Implement all required reports.
- **Tasks:**
    - [ ] Implement a report on room/bed status by branch (FR-7.1).
    - [ ] Implement a revenue report by period (FR-7.2).
    - [ ] Implement a customer debt report (FR-7.3).
    - [ ] Implement a deposit refund and deduction report (FR-7.4).
    - [ ] Implement a report on damaged/lost room assets (FR-7.5).

### 3.2. Non-Functional Requirements
- **Objective:** Ensure the system meets all non-functional requirements.
- **Tasks:**
    - [ ] Implement a robust audit log system (`activity_log`) for all critical actions (NFR-8).
    - [ ] Implement role-based access control (RBAC) on all relevant pages and APIs.
    - [ ] Review and optimize database queries for performance (NFR-2).
    - [ ] Implement data encryption for sensitive information (NFR-4).
    - [ ] Ensure the UI is responsive, especially for tablets (NFR-6).
