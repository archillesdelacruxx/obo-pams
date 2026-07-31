USE pams_db;

-- ==========================================================================
-- ORDER OF PAYMENT
-- ==========================================================================
CREATE TABLE IF NOT EXISTS order_of_payment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    or_number VARCHAR(50) NOT NULL,
    permit_number VARCHAR(50) NOT NULL,
    op_date DATE NOT NULL,
    time_in TIME DEFAULT NULL,
    time_out TIME DEFAULT NULL,
    elapsed_minutes INT DEFAULT NULL,
    encoded_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (encoded_by) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uk_or_number (or_number),
    INDEX idx_permit_number (permit_number),
    INDEX idx_op_date (op_date)
) ENGINE=InnoDB;

-- ==========================================================================
-- PERMIT WORKFLOW
-- ==========================================================================
CREATE TABLE IF NOT EXISTS permit_workflow (
    id INT AUTO_INCREMENT PRIMARY KEY,
    permit_number VARCHAR(50) NOT NULL,
    applicant VARCHAR(100) NOT NULL,
    application_no VARCHAR(50) DEFAULT NULL,
    application VARCHAR(20) NOT NULL,
    permit_type VARCHAR(50) NOT NULL,
    first_in DATE NOT NULL,
    first_out DATE DEFAULT NULL,
    no_of_days INT DEFAULT 0,
    current_round INT DEFAULT 1,
    status ENUM('pending','in-progress','completed') DEFAULT 'pending',
    created_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uk_permit_number (permit_number),
    INDEX idx_workflow_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS workflow_rounds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    workflow_id INT NOT NULL,
    round_number INT NOT NULL,
    last_in DATE DEFAULT NULL,
    last_out DATE DEFAULT NULL,
    processing_days INT DEFAULT 0,
    remarks TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (workflow_id) REFERENCES permit_workflow(id) ON DELETE CASCADE,
    UNIQUE KEY uk_workflow_round (workflow_id, round_number)
) ENGINE=InnoDB;

-- ==========================================================================
-- PERMIT APPROVAL
-- ==========================================================================
CREATE TABLE IF NOT EXISTS permit_approval (
    id INT AUTO_INCREMENT PRIMARY KEY,
    workflow_id INT DEFAULT NULL,
    permit_number VARCHAR(50) NOT NULL,
    applicant VARCHAR(100) NOT NULL,
    permit_type VARCHAR(50) DEFAULT NULL,
    approval_date DATE NOT NULL,
    tat INT DEFAULT 0,
    approved_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (workflow_id) REFERENCES permit_workflow(id) ON DELETE SET NULL,
    INDEX idx_ap_permit_number (permit_number),
    INDEX idx_approval_date (approval_date)
) ENGINE=InnoDB;

-- ==========================================================================
-- RELEASING
-- ==========================================================================
CREATE TABLE IF NOT EXISTS releasing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    permit_number VARCHAR(50) NOT NULL,
    applicant VARCHAR(100) NOT NULL,
    release_date DATE NOT NULL,
    claimed_by VARCHAR(100) DEFAULT NULL,
    time_released TIME DEFAULT NULL,
    released_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (released_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_rel_permit_number (permit_number),
    INDEX idx_release_date (release_date)
) ENGINE=InnoDB;

-- ==========================================================================
-- NOTIFICATIONS
-- ==========================================================================
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    type ENUM('announcement','workflow','approved','record','system') NOT NULL DEFAULT 'system',
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_unread (user_id, is_read)
) ENGINE=InnoDB;

-- ==========================================================================
-- ANNOUNCEMENTS
-- ==========================================================================
CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tag VARCHAR(50) NOT NULL DEFAULT 'Reminder',
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    posted_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (posted_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_ann_created_at (created_at)
) ENGINE=InnoDB;
