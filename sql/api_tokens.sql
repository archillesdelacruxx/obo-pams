-- ============================================================================
-- PAMS — Mobile API tokens
-- Token-based auth para sa React Native (Expo) mobile app.
-- Ang PHP web app ay nananatiling session/cookie-based.
-- ============================================================================

USE pams_db;

CREATE TABLE IF NOT EXISTS api_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token CHAR(64) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    last_used_at DATETIME DEFAULT NULL,
    UNIQUE KEY uniq_token (token),
    KEY idx_api_tokens_user (user_id),
    CONSTRAINT api_tokens_ibfk_1 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
