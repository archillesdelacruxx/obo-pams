-- ============================================================================
-- PAMS — Inspector Admin account (web reviewer)
-- Idempotent: safe to run multiple times.
--
-- Creates an inspection-only reviewer account that can see ALL synced
-- inspection records (from every mobile inspector) and review / approve /
-- reject them on the web. It is NOT a full system admin.
--
-- Default credentials (change in User Management after first login):
--   username: inspector_admin
--   password: inspectoradmin123
-- ============================================================================
USE pams_db;

INSERT INTO users (username, password_hash, full_name, email, role, is_admin, is_active)
SELECT 'inspector_admin', '$2y$10$aL0GfHJIUdVYtKYFwbpySe/Mhf5jtCU3x6CRMIyxCEADe.eEI6yxa', 'Inspector Admin', 'inspector_admin@local.test', 'inspector', 0, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'inspector_admin');

SET @inspector_admin_id = (SELECT id FROM users WHERE username = 'inspector_admin');

-- Inspection-only grants (view all records + review/approve/reject + checklist access)
INSERT INTO user_permissions (user_id, module_key, is_granted)
SELECT @inspector_admin_id, k.module_key, 1
FROM (
    SELECT 'inspection-checklist' AS module_key
    UNION ALL SELECT 'inspection-reports'
    UNION ALL SELECT 'inspection-edit'
) k
WHERE @inspector_admin_id IS NOT NULL
ON DUPLICATE KEY UPDATE is_granted = 1;
