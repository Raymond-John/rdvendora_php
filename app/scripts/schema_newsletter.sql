-- Optional: create newsletter table if it was not auto-created on first subscribe.
CREATE TABLE IF NOT EXISTS newsletter_subscribers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    first_name VARCHAR(100) NULL,
    status ENUM('pending','verified','unsubscribed') NOT NULL DEFAULT 'pending',
    consent TINYINT(1) NOT NULL DEFAULT 0,
    subscribed_at DATETIME NULL,
    unsubscribed_at DATETIME NULL,
    verification_token VARCHAR(64) NULL,
    verified_at DATETIME NULL,
    ip_hash VARCHAR(64) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_email (email),
    KEY idx_status (status),
    KEY idx_token (verification_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
