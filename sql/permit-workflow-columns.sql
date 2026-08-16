-- ============================================================================
-- PAMS — Permit Workflow: add columns the API + web UI already use
-- The workflow create/update endpoints and the web form submit permit_no,
-- permit_type, assessment_approval, date_paid and released, but the
-- permit_workflows table was created without them — causing the workflow
-- module to fail at runtime. Idempotent: safe to run multiple times.
-- ============================================================================
USE pams_db;

SET @cols = JSON_ARRAY(
    'permit_no', 'permit_type', 'assessment_approval', 'date_paid', 'released'
);

DROP PROCEDURE IF EXISTS add_permit_workflow_cols;
DELIMITER //
CREATE PROCEDURE add_permit_workflow_cols()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = 'pams_db' AND TABLE_NAME = 'permit_workflows' AND COLUMN_NAME = 'permit_no') THEN
        ALTER TABLE permit_workflows ADD COLUMN permit_no VARCHAR(50) DEFAULT NULL AFTER application_no;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = 'pams_db' AND TABLE_NAME = 'permit_workflows' AND COLUMN_NAME = 'permit_type') THEN
        ALTER TABLE permit_workflows ADD COLUMN permit_type VARCHAR(100) DEFAULT NULL AFTER project_type;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = 'pams_db' AND TABLE_NAME = 'permit_workflows' AND COLUMN_NAME = 'assessment_approval') THEN
        ALTER TABLE permit_workflows ADD COLUMN assessment_approval VARCHAR(255) DEFAULT NULL AFTER permit_type;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = 'pams_db' AND TABLE_NAME = 'permit_workflows' AND COLUMN_NAME = 'date_paid') THEN
        ALTER TABLE permit_workflows ADD COLUMN date_paid DATE DEFAULT NULL AFTER assessment_approval;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = 'pams_db' AND TABLE_NAME = 'permit_workflows' AND COLUMN_NAME = 'released') THEN
        ALTER TABLE permit_workflows ADD COLUMN released DATE DEFAULT NULL AFTER date_paid;
    END IF;
END//
DELIMITER ;

CALL add_permit_workflow_cols();
DROP PROCEDURE IF EXISTS add_permit_workflow_cols;