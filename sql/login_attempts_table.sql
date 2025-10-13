-- Login attempts table for brute force protection
-- Note: Import this after creating the database through cPanel

CREATE TABLE login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(255) NOT NULL, -- IP address or username
    success TINYINT(1) NOT NULL DEFAULT 0, -- 0 for failed, 1 for successful
    username VARCHAR(50),
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_identifier (identifier),
    INDEX idx_created_at (created_at),
    INDEX idx_success (success)
);