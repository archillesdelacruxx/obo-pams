-- Extend inspector_admin (user id 3) with delete, review queue, and team leader management.
INSERT IGNORE INTO user_permissions (user_id, module_key, is_granted)
SELECT u.id, p.module_key, 1
FROM users u
JOIN (
    SELECT 'inspection-delete' AS module_key
    UNION ALL SELECT 'inspection-review'
    UNION ALL SELECT 'team-leaders'
) p
WHERE u.username = 'inspector_admin'
  AND NOT EXISTS (
      SELECT 1 FROM user_permissions up
      WHERE up.user_id = u.id AND up.module_key = p.module_key
  );
