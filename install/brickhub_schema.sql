-- BrickHub: sistema de marketplace y actualizacion automatica via GitHub
-- Ejecutar despues de schema.sql

CREATE TABLE IF NOT EXISTS brick_sources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    name VARCHAR(255) NOT NULL COMMENT 'Nombre del source (ej: WWI Core, TIA, etc.)',
    repo_url VARCHAR(500) NOT NULL COMMENT 'URL del repositorio GitHub (https://github.com/user/repo)',
    branch VARCHAR(100) DEFAULT 'main',
    install_path VARCHAR(255) DEFAULT '/src/Bricks/' COMMENT 'Ruta donde se instalan los bricks',
    auth_token VARCHAR(255) DEFAULT NULL COMMENT 'GitHub PAT opcional para rate limits',
    last_checked_at DATETIME DEFAULT NULL,
    last_version VARCHAR(50) DEFAULT NULL,
    is_active TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (site_id) REFERENCES sites(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS bricks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    source_id INT DEFAULT NULL COMMENT 'NULL = manual install',
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    version VARCHAR(50) NOT NULL DEFAULT '1.0.0',
    category VARCHAR(50) DEFAULT 'general' COMMENT 'widget, system, integration, theme',
    description TEXT,
    author VARCHAR(255),
    brick_class VARCHAR(255) COMMENT 'FQCN de la clase PHP del brick',
    installed_path VARCHAR(500) COMMENT 'Ruta donde esta instalado',
    config JSON COMMENT 'Configuracion del brick',
    status ENUM('active','inactive','error') DEFAULT 'active',
    installed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY site_slug (site_id, slug),
    FOREIGN KEY (site_id) REFERENCES sites(id),
    FOREIGN KEY (source_id) REFERENCES brick_sources(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS brick_updates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    brick_id INT NOT NULL,
    source_id INT DEFAULT NULL,
    from_version VARCHAR(50) NOT NULL,
    to_version VARCHAR(50) NOT NULL,
    release_notes TEXT,
    release_url VARCHAR(500),
    status ENUM('pending','applied','failed','rolled_back') DEFAULT 'pending',
    error_message TEXT,
    applied_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (site_id) REFERENCES sites(id),
    FOREIGN KEY (brick_id) REFERENCES bricks(id) ON DELETE CASCADE,
    FOREIGN KEY (source_id) REFERENCES brick_sources(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS brick_webhooks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    source_id INT NOT NULL,
    webhook_secret VARCHAR(128) NOT NULL,
    webhook_url VARCHAR(500) NOT NULL COMMENT 'URL para recibir push events de GitHub',
    last_triggered_at DATETIME DEFAULT NULL,
    events JSON COMMENT '["push","release"]',
    is_active TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (site_id) REFERENCES sites(id),
    FOREIGN KEY (source_id) REFERENCES brick_sources(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS brickhub_registry (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    child_url VARCHAR(500) NOT NULL COMMENT 'URL de la instalacion hija',
    child_name VARCHAR(255) DEFAULT '',
    site_key VARCHAR(128) NOT NULL UNIQUE COMMENT 'Clave unica del sitio hijo',
    is_active TINYINT DEFAULT 1,
    last_seen_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (site_id) REFERENCES sites(id)
) ENGINE=InnoDB;
