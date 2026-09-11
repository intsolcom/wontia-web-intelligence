CREATE TABLE IF NOT EXISTS brick_ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    brick_slug VARCHAR(120) NOT NULL,
    site_id INT NOT NULL DEFAULT 1,
    user_id INT NOT NULL,
    rating TINYINT NOT NULL,
    comment VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_brick (brick_slug, user_id),
    INDEX idx_brick (brick_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS brick_metrics (
    brick_slug VARCHAR(120) NOT NULL PRIMARY KEY,
    installs INT NOT NULL DEFAULT 0,
    views INT NOT NULL DEFAULT 0,
    previews INT NOT NULL DEFAULT 0,
    uninstalls INT NOT NULL DEFAULT 0,
    add_to_page INT NOT NULL DEFAULT 0,
    updates INT NOT NULL DEFAULT 0,
    last_event_at TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS brick_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    brick_slug VARCHAR(120) NOT NULL,
    event VARCHAR(30) NOT NULL,
    site_id INT NOT NULL DEFAULT 1,
    user_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_brick_event (brick_slug, event),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
