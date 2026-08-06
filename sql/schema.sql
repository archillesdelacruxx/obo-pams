-- ============================================================================
-- PAMS — Full database schema (latest, consolidated)
-- Database: pams_db  ·  Charset: utf8mb4 / utf8mb4_unicode_ci
-- This single file replaces all earlier incremental migration files.
-- Seed login: admin / admin123  ·  jdelacruz / user123
-- ============================================================================

CREATE DATABASE IF NOT EXISTS pams_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pams_db;

-- ---------------------------------------------------------------------------
-- USERS & ACCESS
-- ---------------------------------------------------------------------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) DEFAULT NULL,
    profile_photo VARCHAR(255) DEFAULT 'assets/images/OBO LOGO.png',
    is_active TINYINT(1) DEFAULT 1,
    is_admin TINYINT(1) DEFAULT 0,
    last_login DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY username (username),
    KEY idx_username (username),
    KEY idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    module_key VARCHAR(50) NOT NULL,
    is_granted TINYINT(1) DEFAULT 0,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_module (user_id, module_key),
    CONSTRAINT user_permissions_ibfk_1 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    failed_count INT NOT NULL DEFAULT 0,
    locked_until DATETIME DEFAULT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_login_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- ORDER OF PAYMENT
-- ---------------------------------------------------------------------------
CREATE TABLE order_of_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_no VARCHAR(50) NOT NULL,
    applicant_name VARCHAR(150) NOT NULL,
    permit_type VARCHAR(100) NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    payment_status ENUM('Pending','Paid','Cancelled') NOT NULL DEFAULT 'Pending',
    official_receipt_no VARCHAR(50) DEFAULT NULL,
    payment_date DATE DEFAULT NULL,
    encoded_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    elapsed_minutes INT DEFAULT NULL,
    time_in TIME DEFAULT NULL,
    time_out TIME DEFAULT NULL,
    UNIQUE KEY transaction_no (transaction_no),
    KEY idx_op_transaction_no (transaction_no),
    KEY idx_op_payment_date (payment_date),
    KEY idx_op_status (payment_status),
    CONSTRAINT order_of_payments_ibfk_1 FOREIGN KEY (encoded_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- PERMIT WORKFLOW
-- ---------------------------------------------------------------------------
CREATE TABLE permit_workflows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_no VARCHAR(50) NOT NULL,
    applicant_name VARCHAR(150) NOT NULL,
    project_type VARCHAR(100) NOT NULL,
    location VARCHAR(255) NOT NULL,
    current_stage VARCHAR(100) NOT NULL DEFAULT 'Initial Review',
    status ENUM('Pending','Under Review','Approved','Disapproved','Released') NOT NULL DEFAULT 'Pending',
    encoded_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    first_in DATE DEFAULT NULL,
    first_out DATE DEFAULT NULL,
    no_of_days INT DEFAULT 0,
    current_round INT DEFAULT 1,
    UNIQUE KEY application_no (application_no),
    KEY idx_pw_application_no (application_no),
    KEY idx_pw_status (status),
    KEY idx_pw_stage (current_stage),
    CONSTRAINT permit_workflows_ibfk_1 FOREIGN KEY (encoded_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE workflow_rounds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    workflow_id INT NOT NULL,
    round_number INT NOT NULL,
    last_in DATE DEFAULT NULL,
    last_out DATE DEFAULT NULL,
    processing_days INT DEFAULT 0,
    remarks TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_workflow_round (workflow_id, round_number),
    CONSTRAINT workflow_rounds_ibfk_1 FOREIGN KEY (workflow_id) REFERENCES permit_workflows(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- PERMIT APPROVAL
-- ---------------------------------------------------------------------------
CREATE TABLE permit_approvals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    workflow_id INT DEFAULT NULL,
    application_no VARCHAR(50) NOT NULL,
    bp_no VARCHAR(50) DEFAULT NULL,
    applicant_name VARCHAR(150) NOT NULL,
    location VARCHAR(255) DEFAULT NULL,
    type_of_occupancy VARCHAR(255) DEFAULT NULL,
    contractor VARCHAR(255) DEFAULT NULL,
    land_others DECIMAL(14,2) DEFAULT NULL,
    surcharge DECIMAL(14,2) DEFAULT NULL,
    area DECIMAL(14,2) DEFAULT NULL,
    line_grade VARCHAR(255) DEFAULT NULL,
    bldg_cost DECIMAL(14,2) DEFAULT NULL,
    permit_no VARCHAR(50) DEFAULT NULL,
    incharge VARCHAR(150) DEFAULT NULL,
    or_no VARCHAR(50) DEFAULT NULL,
    fees DECIMAL(14,2) DEFAULT NULL,
    date_paid DATE DEFAULT NULL,
    received_by VARCHAR(150) DEFAULT NULL,
    date_oop DATE DEFAULT NULL,
    date_received DATE DEFAULT NULL,
    date_approved DATE DEFAULT NULL,
    permit_type VARCHAR(100) DEFAULT NULL,
    approval_date DATE NOT NULL,
    tat INT DEFAULT 0,
    approved_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY approved_by (approved_by),
    KEY workflow_id (workflow_id),
    KEY idx_ap_application_no (application_no),
    KEY idx_approval_date (approval_date),
    CONSTRAINT permit_approvals_ibfk_1 FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT permit_approvals_ibfk_2 FOREIGN KEY (workflow_id) REFERENCES permit_workflows(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- RELEASING
-- ---------------------------------------------------------------------------
CREATE TABLE releasing_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date_released DATE NOT NULL,
    permit_application_no VARCHAR(50) NOT NULL,
    applicant_name VARCHAR(150) NOT NULL,
    claimed_by VARCHAR(150) NOT NULL,
    time_released TIME NOT NULL,
    encoded_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    released_by INT DEFAULT NULL,
    KEY encoded_by (encoded_by),
    KEY idx_rp_date_released (date_released),
    KEY idx_rp_permit_app_no (permit_application_no),
    CONSTRAINT releasing_plans_ibfk_1 FOREIGN KEY (encoded_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- NOTIFICATIONS
-- ---------------------------------------------------------------------------
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    sender_id INT DEFAULT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    module_name VARCHAR(50) DEFAULT NULL,
    record_id INT DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY sender_id (sender_id),
    KEY idx_notif_user_read (user_id, is_read),
    CONSTRAINT notifications_ibfk_1 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT notifications_ibfk_2 FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- ANNOUNCEMENTS
-- ---------------------------------------------------------------------------
CREATE TABLE announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    created_by INT NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY created_by (created_by),
    CONSTRAINT announcements_ibfk_1 FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- COMMENTS (per-record discussions across modules)
-- ---------------------------------------------------------------------------
CREATE TABLE comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    module_name VARCHAR(50) NOT NULL,
    record_id INT NOT NULL,
    user_id INT NOT NULL,
    comment_text TEXT NOT NULL,
    status ENUM('Pending','Resolved') DEFAULT 'Pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY user_id (user_id),
    KEY idx_comment_module_record (module_name, record_id),
    KEY idx_comment_status (status),
    CONSTRAINT comments_ibfk_1 FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- ACTIVITY LOG
-- ---------------------------------------------------------------------------
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    module_name VARCHAR(50) NOT NULL,
    action VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_log_user (user_id),
    KEY idx_log_module (module_name),
    KEY idx_log_action (action),
    KEY idx_log_created (created_at),
    CONSTRAINT activity_logs_ibfk_1 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- SYSTEM SETTINGS
-- ---------------------------------------------------------------------------
CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT DEFAULT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- INSPECTION MANAGEMENT
-- ---------------------------------------------------------------------------
CREATE TABLE inspection_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_no VARCHAR(50) NOT NULL,
    permit_no VARCHAR(50) DEFAULT NULL,
    project_title VARCHAR(255) NOT NULL,
    project_location VARCHAR(255) DEFAULT NULL,
    applicant_name VARCHAR(150) NOT NULL,
    owner_representative VARCHAR(150) DEFAULT NULL,
    contact_number VARCHAR(50) DEFAULT NULL,
    scheduled_date DATE DEFAULT NULL,
    scheduled_time TIME DEFAULT NULL,
    inspector_id INT DEFAULT NULL,
    status ENUM('Scheduled','Ongoing','Completed','Cancelled') NOT NULL DEFAULT 'Scheduled',
    remarks TEXT DEFAULT NULL,
    encoded_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_insch_app_no (application_no),
    KEY idx_insch_date (scheduled_date),
    KEY idx_insch_status (status),
    CONSTRAINT inspection_schedules_ibfk_1 FOREIGN KEY (inspector_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT inspection_schedules_ibfk_2 FOREIGN KEY (encoded_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inspection_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inspection_no VARCHAR(50) NOT NULL,
    schedule_id INT DEFAULT NULL,
    application_no VARCHAR(50) NOT NULL,
    permit_no VARCHAR(50) DEFAULT NULL,
    project_title VARCHAR(255) NOT NULL,
    project_location VARCHAR(255) DEFAULT NULL,
    owner_representative VARCHAR(150) DEFAULT NULL,
    contact_number VARCHAR(50) DEFAULT NULL,
    project_contractor VARCHAR(150) DEFAULT NULL,
    project_engineer VARCHAR(150) DEFAULT NULL,
    inspection_team VARCHAR(255) DEFAULT NULL,
    inspection_date DATE DEFAULT NULL,
    inspection_type VARCHAR(100) DEFAULT NULL,
    inspection_result ENUM('Passed','Passed with Remarks','Ongoing','Failed','For Re-inspection') DEFAULT NULL,
    time_started TIME DEFAULT NULL,
    time_finished TIME DEFAULT NULL,
    physical_accomplishment DECIMAL(5,2) DEFAULT NULL,
    mech_accomplishment DECIMAL(5,2) DEFAULT NULL,
    extra_fields TEXT DEFAULT NULL,
    overall_findings TEXT DEFAULT NULL,
    recommendations TEXT DEFAULT NULL,
    completion_percentage DECIMAL(5,2) DEFAULT NULL,
    status ENUM('Draft','Under Review','Approved','Completed','Rejected') NOT NULL DEFAULT 'Draft',
    inspector_id INT DEFAULT NULL,
    inspector_signature VARCHAR(255) DEFAULT NULL,
    reviewed_by INT DEFAULT NULL,
    review_signature VARCHAR(255) DEFAULT NULL,
    review_date DATETIME DEFAULT NULL,
    review_remarks TEXT DEFAULT NULL,
    approved_by INT DEFAULT NULL,
    approval_signature VARCHAR(255) DEFAULT NULL,
    approval_date DATETIME DEFAULT NULL,
    approval_remarks TEXT DEFAULT NULL,
    encoded_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY inspection_no (inspection_no),
    KEY idx_insp_app_no (application_no),
    KEY idx_insp_date (inspection_date),
    KEY idx_insp_status (status),
    CONSTRAINT inspection_records_ibfk_1 FOREIGN KEY (schedule_id) REFERENCES inspection_schedules(id) ON DELETE SET NULL,
    CONSTRAINT inspection_records_ibfk_2 FOREIGN KEY (inspector_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT inspection_records_ibfk_3 FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT inspection_records_ibfk_4 FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT inspection_records_ibfk_5 FOREIGN KEY (encoded_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inspection_template_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(60) NOT NULL,
    item_text VARCHAR(255) NOT NULL,
    item_type ENUM('radio','checkbox') NOT NULL DEFAULT 'radio',
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uk_template_item (category, item_text),
    KEY idx_iti_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inspection_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inspection_id INT NOT NULL,
    template_item_id INT DEFAULT NULL,
    category VARCHAR(60) NOT NULL,
    item_text VARCHAR(255) NOT NULL,
    item_type ENUM('radio','checkbox') NOT NULL DEFAULT 'radio',
    result ENUM('Pass','Fail','N/A') NOT NULL DEFAULT 'Pass',
    remarks TEXT DEFAULT NULL,
    UNIQUE KEY uk_insp_result (inspection_id, template_item_id),
    CONSTRAINT inspection_results_ibfk_1 FOREIGN KEY (inspection_id) REFERENCES inspection_records(id) ON DELETE CASCADE,
    CONSTRAINT inspection_results_ibfk_2 FOREIGN KEY (template_item_id) REFERENCES inspection_template_items(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inspection_photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inspection_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    caption VARCHAR(255) DEFAULT NULL,
    uploaded_by INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_inphoto_insp (inspection_id),
    CONSTRAINT inspection_photos_ibfk_1 FOREIGN KEY (inspection_id) REFERENCES inspection_records(id) ON DELETE CASCADE,
    CONSTRAINT inspection_photos_ibfk_2 FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- DEFAULT SEED DATA
-- ---------------------------------------------------------------------------
INSERT INTO users (full_name, username, email, password_hash, is_active, is_admin) VALUES
('System Administrator', 'admin', 'admin@pams.gov.ph', '$2y$10$HqAong77QioVpSS0DGi/6ODJLHLnXb1qFpdY6vdOnNC0tT70YE9XG', 1, 1),
('Juan R. Dela Cruz', 'jdelacruz', 'jdelacruz@pams.gov.ph', '$2y$10$DTYZhKquXSJgYRh1WQtvMuoawRPViZ2y3MO0orl5bwKHcwLBzbMki', 1, 0);

INSERT INTO user_permissions (user_id, module_key, is_granted) VALUES
(2, 'dashboard', 1),
(2, 'inspection-checklist', 1),
(2, 'inspection-reports', 1);

-- ---------------------------------------------------------------------------
-- INSPECTION CHECKLIST TEMPLATE (standard OBO on-site ocular inspection items)
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO inspection_template_items (category, item_text, item_type, sort_order) VALUES
('General Safety', 'Provided signage and barricades are in place', 'checkbox', 1),
('General Safety', 'Personal protective equipment (PPE) is being worn by workers', 'checkbox', 2),
('General Safety', 'Presence of first-aid kits', 'checkbox', 3),
('General Safety', 'Scaffoldings and ladders are secure and in good condition', 'checkbox', 4),
('Architectural Works', 'Firewall', 'checkbox', 1),
('Architectural Works', 'Parking', 'checkbox', 2),
('Architectural Works', 'PWD Ramp/Railing', 'checkbox', 3),
('Architectural Works', 'PWD CR/Utilities', 'checkbox', 4),
('Architectural Works', 'Fire Exit', 'checkbox', 5),
('Civil / Structural Works', 'Column Footings', 'checkbox', 1),
('Civil / Structural Works', 'Wall Footings', 'checkbox', 2),
('Civil / Structural Works', 'Tie Beams', 'checkbox', 3),
('Civil / Structural Works', 'Columns', 'checkbox', 4),
('Civil / Structural Works', 'Beams', 'checkbox', 5),
('Civil / Structural Works', 'Girders', 'checkbox', 6),
('Civil / Structural Works', 'Slabs', 'checkbox', 7),
('Civil / Structural Works', 'Stairs', 'checkbox', 8),
('Civil / Structural Works', 'Roof Beams', 'checkbox', 9),
('Civil / Structural Works', 'Truss', 'checkbox', 10),
('Civil / Structural Works', 'Others', 'checkbox', 11),
('Electrical Works', 'Installed electrical devices as per the approved plan', 'checkbox', 1),
('Electrical Works', 'Sizes of the conductor as per the approved plan', 'checkbox', 2),
('Electrical Works', 'Installed protection devices as per the approved plan', 'checkbox', 3),
('Electrical Works', 'Installed equipment grounding conductor (rod)', 'checkbox', 4),
('Mechanical Works', 'Installed HVAC, ducting air conditioning as per approved plan', 'checkbox', 1),
('Mechanical Works', 'Ceiling/Wall/Floor mounted aircon as per approved plan', 'checkbox', 2),
('Sanitary / Plumbing Works', 'Roughing pipe layout as per approved plan', 'checkbox', 1),
('Sanitary / Plumbing Works', 'Installed plumbing fixtures as per approved plan', 'checkbox', 2),
('Sanitary / Plumbing Works', 'Septic Vault as per approved plan', 'checkbox', 3),
('Electronics Works', 'Layout electronics wiring as per approved plan', 'checkbox', 1),
('Electronics Works', 'Installed electronics devices as per approved plan', 'checkbox', 2);
