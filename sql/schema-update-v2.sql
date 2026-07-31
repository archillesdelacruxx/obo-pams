USE pams_db;

CREATE TABLE IF NOT EXISTS permit_approvals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    workflow_id INT DEFAULT NULL,
    application_no VARCHAR(50) NOT NULL,
    applicant_name VARCHAR(150) NOT NULL,
    permit_type VARCHAR(100) DEFAULT NULL,
    approval_date DATE NOT NULL,
    tat INT DEFAULT 0,
    approved_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (workflow_id) REFERENCES permit_workflows(id) ON DELETE SET NULL,
    INDEX idx_ap_application_no (application_no),
    INDEX idx_approval_date (approval_date)
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
    FOREIGN KEY (workflow_id) REFERENCES permit_workflows(id) ON DELETE CASCADE,
    UNIQUE KEY uk_workflow_round (workflow_id, round_number)
) ENGINE=InnoDB;

ALTER TABLE order_of_payments ADD COLUMN elapsed_minutes INT DEFAULT NULL;
ALTER TABLE order_of_payments ADD COLUMN time_in TIME DEFAULT NULL;
ALTER TABLE order_of_payments ADD COLUMN time_out TIME DEFAULT NULL;

ALTER TABLE permit_workflows ADD COLUMN first_in DATE DEFAULT NULL;
ALTER TABLE permit_workflows ADD COLUMN first_out DATE DEFAULT NULL;
ALTER TABLE permit_workflows ADD COLUMN no_of_days INT DEFAULT 0;
ALTER TABLE permit_workflows ADD COLUMN current_round INT DEFAULT 1;

ALTER TABLE permit_workflows ADD COLUMN permit_no VARCHAR(50) DEFAULT NULL;
ALTER TABLE permit_workflows ADD COLUMN application_number VARCHAR(50) DEFAULT NULL;
ALTER TABLE permit_workflows ADD COLUMN permit_type VARCHAR(100) DEFAULT NULL;
ALTER TABLE permit_workflows ADD COLUMN assessment_approval TEXT DEFAULT NULL;
ALTER TABLE permit_workflows ADD COLUMN date_paid DATE DEFAULT NULL;
ALTER TABLE permit_workflows ADD COLUMN released DATE DEFAULT NULL;

ALTER TABLE releasing_plans ADD COLUMN released_by INT DEFAULT NULL;
