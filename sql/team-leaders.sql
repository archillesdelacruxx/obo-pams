-- ============================================================================
-- PAMS — Team Leaders registry (incremental migration)
-- Idempotent: safe to run multiple times.
-- ============================================================================
USE pams_db;

CREATE TABLE IF NOT EXISTS team_leaders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    position VARCHAR(150) DEFAULT NULL,
    team_no TINYINT NOT NULL DEFAULT 1 COMMENT 'Assigned team: 1 or 2',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_tl_team (team_no),
    KEY idx_tl_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- inspection_records: selected team leaders for the INSPECTED BY block
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'pams_db' AND TABLE_NAME = 'inspection_records' AND COLUMN_NAME = 'team_leader_1'
);
SET @ddl = IF(@col_exists = 0,
    'ALTER TABLE inspection_records ADD COLUMN team_leader_1 INT DEFAULT NULL AFTER inspector_id',
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'pams_db' AND TABLE_NAME = 'inspection_records' AND COLUMN_NAME = 'team_leader_2'
);
SET @ddl = IF(@col_exists = 0,
    'ALTER TABLE inspection_records ADD COLUMN team_leader_2 INT DEFAULT NULL AFTER team_leader_1',
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add FK constraints (idempotent via information_schema check)
SET @fk1 = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = 'pams_db' AND TABLE_NAME = 'inspection_records' AND CONSTRAINT_NAME = 'fk_inspection_team_leader_1'
);
SET @ddl = IF(@fk1 = 0,
    'ALTER TABLE inspection_records ADD CONSTRAINT fk_inspection_team_leader_1 FOREIGN KEY (team_leader_1) REFERENCES team_leaders(id) ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk2 = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = 'pams_db' AND TABLE_NAME = 'inspection_records' AND CONSTRAINT_NAME = 'fk_inspection_team_leader_2'
);
SET @ddl = IF(@fk2 = 0,
    'ALTER TABLE inspection_records ADD CONSTRAINT fk_inspection_team_leader_2 FOREIGN KEY (team_leader_2) REFERENCES team_leaders(id) ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
