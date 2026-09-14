CREATE TABLE IF NOT EXISTS wwi_section_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    section_id INT NOT NULL,
    user_id INT NOT NULL,
    username VARCHAR(100) DEFAULT '',
    body VARCHAR(1000) NOT NULL,
    status ENUM('open','resolved') DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_section (section_id),
    INDEX idx_site_status (site_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS wwi_section_versions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    section_id INT NOT NULL,
    user_id INT DEFAULT NULL,
    username VARCHAR(100) DEFAULT '',
    snapshot JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_section (section_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS wwi_edit_presence (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    user_id INT NOT NULL,
    username VARCHAR(100) DEFAULT '',
    section_id INT DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user (site_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
