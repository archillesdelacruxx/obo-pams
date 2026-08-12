-- ============================================================================
-- PAMS — Permit Workflow: "No last out date for this round" support
-- Adds workflow_rounds.no_last_out so a round can be marked as having no
-- last out date (application not taken back out / no follow-up round).
-- Idempotent: safe to run multiple times.
-- ============================================================================
USE pams_db;

SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'pams_db' AND TABLE_NAME = 'workflow_rounds' AND COLUMN_NAME = 'no_last_out'
);
SET @ddl = IF(@col_exists = 0,
    'ALTER TABLE workflow_rounds ADD COLUMN no_last_out TINYINT(1) NOT NULL DEFAULT 0 AFTER last_out',
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
