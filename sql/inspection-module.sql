-- ============================================================================
-- PAMS — Inspection Management module (incremental migration)
-- Applies the new Inspection Management schema on top of an existing database.
-- Idempotent: safe to run multiple times.
-- ============================================================================
USE pams_db;

CREATE TABLE IF NOT EXISTS inspection_schedules (
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

CREATE TABLE IF NOT EXISTS inspection_records (
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
    date_reinspected DATE DEFAULT NULL,
    status ENUM('Draft','Under Review','Approved','Completed','Rejected') NOT NULL DEFAULT 'Draft',
    inspector_id INT DEFAULT NULL,
    reviewed_by INT DEFAULT NULL,
    review_date DATETIME DEFAULT NULL,
    review_remarks TEXT DEFAULT NULL,
    approved_by INT DEFAULT NULL,
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

CREATE TABLE IF NOT EXISTS inspection_template_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(60) NOT NULL,
    item_text VARCHAR(255) NOT NULL,
    item_type ENUM('radio','checkbox') NOT NULL DEFAULT 'radio',
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uk_template_item (category, item_text),
    KEY idx_iti_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inspection_results (
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

CREATE TABLE IF NOT EXISTS inspection_photos (
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

-- Checklist template seed (official on-site ocular inspection form);
-- item_type defaults to 'radio' here and is corrected to 'checkbox' by the sync block below.
INSERT IGNORE INTO inspection_template_items (category, item_text, sort_order) VALUES
('General Safety', 'Provided signage and barricades are in place', 1),
('General Safety', 'Personal protective equipment (PPE) is being worn by workers', 2),
('General Safety', 'Presence of first-aid kits', 3),
('General Safety', 'Scaffoldings and ladders are secure and in good condition', 4),
('Architectural Works', 'Firewall', 1),
('Architectural Works', 'Parking', 2),
('Architectural Works', 'PWD Ramp/Railing', 3),
('Architectural Works', 'PWD CR/Utilities', 4),
('Architectural Works', 'Fire Exit', 5),
('Civil / Structural Works', 'Column Footings', 1),
('Civil / Structural Works', 'Wall Footings', 2),
('Civil / Structural Works', 'Tie Beams', 3),
('Civil / Structural Works', 'Columns', 4),
('Civil / Structural Works', 'Beams', 5),
('Civil / Structural Works', 'Girders', 6),
('Civil / Structural Works', 'Slabs', 7),
('Civil / Structural Works', 'Stairs', 8),
('Civil / Structural Works', 'Roof Beams', 9),
('Civil / Structural Works', 'Truss', 10),
('Civil / Structural Works', 'Others', 11),
('Electrical Works', 'Installed electrical devices as per the approved plan', 1),
('Electrical Works', 'Sizes of the conductor as per the approved plan', 2),
('Electrical Works', 'Installed protection devices as per the approved plan', 3),
('Electrical Works', 'Installed equipment grounding conductor (rod)', 4),
('Mechanical Works', 'Installed HVAC, ducting air conditioning as per approved plan', 1),
('Mechanical Works', 'Ceiling/Wall/Floor mounted aircon as per approved plan', 2),
('Sanitary / Plumbing Works', 'Roughing pipe layout as per approved plan', 1),
('Sanitary / Plumbing Works', 'Installed plumbing fixtures as per approved plan', 2),
('Sanitary / Plumbing Works', 'Septic Vault as per approved plan', 3),
('Electronics Works', 'Layout electronics wiring as per approved plan', 1),
('Electronics Works', 'Installed electronics devices as per approved plan', 2);

-- Default inspection module grants for all active non-admin users
INSERT INTO user_permissions (user_id, module_key, is_granted)
SELECT u.id, k.module_key, 1
FROM users u
CROSS JOIN (
    SELECT 'inspection-checklist' AS module_key
    UNION ALL SELECT 'inspection-reports'
) k
WHERE u.is_active = 1 AND u.is_admin = 0
ON DUPLICATE KEY UPDATE is_granted = 1;

-- Revoke grants for removed modules (schedule / history)
DELETE FROM user_permissions WHERE module_key IN ('inspection-schedule', 'inspection-history');

-- inspection_records: permit issue date (inspection-checklist "Date Issued")
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'pams_db' AND TABLE_NAME = 'inspection_records' AND COLUMN_NAME = 'permit_date_issued'
);
SET @ddl = IF(@col_exists = 0,
    'ALTER TABLE inspection_records ADD COLUMN permit_date_issued DATE DEFAULT NULL AFTER permit_no',
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- inspection_records: monitoring report fields (contractor, engineer, inspection type, result)
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'pams_db' AND TABLE_NAME = 'inspection_records' AND COLUMN_NAME = 'project_contractor'
);
SET @ddl = IF(@col_exists = 0,
    'ALTER TABLE inspection_records ADD COLUMN project_contractor VARCHAR(150) DEFAULT NULL AFTER contact_number',
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'pams_db' AND TABLE_NAME = 'inspection_records' AND COLUMN_NAME = 'project_engineer'
);
SET @ddl = IF(@col_exists = 0,
    'ALTER TABLE inspection_records ADD COLUMN project_engineer VARCHAR(150) DEFAULT NULL AFTER project_contractor',
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'pams_db' AND TABLE_NAME = 'inspection_records' AND COLUMN_NAME = 'inspection_type'
);
SET @ddl = IF(@col_exists = 0,
    'ALTER TABLE inspection_records ADD COLUMN inspection_type VARCHAR(100) DEFAULT NULL AFTER inspection_date',
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'pams_db' AND TABLE_NAME = 'inspection_records' AND COLUMN_NAME = 'inspection_result'
);
SET @ddl = IF(@col_exists = 0,
    "ALTER TABLE inspection_records ADD COLUMN inspection_result ENUM('Passed','Passed with Remarks','Ongoing','Failed','For Re-inspection') DEFAULT NULL AFTER inspection_type",
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- item_type support (checkbox vs radio): add columns idempotently
SET @ti_col = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'pams_db' AND TABLE_NAME = 'inspection_template_items' AND COLUMN_NAME = 'item_type'
);
SET @ddl = IF(@ti_col = 0,
    "ALTER TABLE inspection_template_items ADD COLUMN item_type ENUM('radio','checkbox') NOT NULL DEFAULT 'radio' AFTER item_text",
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @rs_col = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'pams_db' AND TABLE_NAME = 'inspection_results' AND COLUMN_NAME = 'item_type'
);
SET @ddl = IF(@rs_col = 0,
    "ALTER TABLE inspection_results ADD COLUMN item_type ENUM('radio','checkbox') NOT NULL DEFAULT 'radio' AFTER item_text",
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- inspection_records: mechanical works accomplishment percentage (checklist "% Mechanical")
SET @mech_col = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'pams_db' AND TABLE_NAME = 'inspection_records' AND COLUMN_NAME = 'mech_accomplishment'
);
SET @ddl = IF(@mech_col = 0,
    'ALTER TABLE inspection_records ADD COLUMN mech_accomplishment DECIMAL(5,2) DEFAULT NULL AFTER physical_accomplishment',
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- inspection_records: extra per-category fields (setbacks, floor level, percents, remarks)
SET @ex_col = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'pams_db' AND TABLE_NAME = 'inspection_records' AND COLUMN_NAME = 'extra_fields'
);
SET @ddl = IF(@ex_col = 0,
    'ALTER TABLE inspection_records ADD COLUMN extra_fields TEXT DEFAULT NULL AFTER mech_accomplishment',
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- inspection_records: date_reinspected
SET @reinsp_col = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'pams_db' AND TABLE_NAME = 'inspection_records' AND COLUMN_NAME = 'date_reinspected'
);
SET @ddl = IF(@reinsp_col = 0,
    'ALTER TABLE inspection_records ADD COLUMN date_reinspected DATE DEFAULT NULL AFTER team_leader_2',
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Official on-site ocular inspection checklist template (all checkbox items)
DELETE FROM inspection_template_items;
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
