CREATE DATABASE IF NOT EXISTS pams_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pams_db;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) DEFAULT NULL,
    profile_photo VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    is_admin TINYINT(1) DEFAULT 0,
    last_login DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE user_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    module_key VARCHAR(50) NOT NULL,
    is_granted TINYINT(1) DEFAULT 0,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uk_user_module (user_id, module_key)
) ENGINE=InnoDB;

CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    action VARCHAR(50) NOT NULL,
    details TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO users (full_name, username, email, password_hash, is_active, is_admin) VALUES
('System Administrator', 'admin', 'admin@pams.gov.ph', '$2y$10$HqAong77QioVpSS0DGi/6ODJLHLnXb1qFpdY6vdOnNC0tT70YE9XG', 1, 1),
('Juan R. Dela Cruz', 'jdelacruz', 'jdelacruz@pams.gov.ph', '$2y$10$DTYZhKquXSJgYRh1WQtvMuoawRPViZ2y3MO0orl5bwKHcwLBzbMki', 1, 0);

INSERT INTO user_permissions (user_id, module_key, is_granted) VALUES
(2, 'dashboard', 1);
