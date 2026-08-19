-- Optional: create news table if it was not auto-created on first News page load.
CREATE TABLE IF NOT EXISTS blog_posts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(160) NOT NULL,
    title VARCHAR(220) NOT NULL,
    excerpt TEXT NULL,
    body MEDIUMTEXT NOT NULL,
    category VARCHAR(40) NOT NULL DEFAULT 'platform',
    author VARCHAR(120) NOT NULL DEFAULT 'RD Vendora team',
    image_url VARCHAR(500) NULL,
    status ENUM('draft','published') NOT NULL DEFAULT 'draft',
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    published_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_slug (slug),
    KEY idx_status_pub (status, published_at),
    KEY idx_category (category),
    KEY idx_featured (is_featured)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
