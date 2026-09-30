-- WWI Brick Lifecycle (Incubadora -> Marketplace)
-- Catálogo GLOBAL de bloques (el código de los bricks Core es el mismo para todos los sitios).
-- Idempotente: se puede ejecutar varias veces.

CREATE TABLE IF NOT EXISTS brick_lifecycle (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(120) NOT NULL,
    brick_type VARCHAR(20) NOT NULL DEFAULT 'core',
    state ENUM('incubator','launched') NOT NULL DEFAULT 'incubator',
    maturity VARCHAR(20) NOT NULL DEFAULT 'alpha',
    hidden TINYINT NOT NULL DEFAULT 0,
    target_launch_at DATETIME NULL,
    launched_at DATETIME NULL,
    launched_by VARCHAR(120) NULL,
    release_notes TEXT NULL,
    interest INT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_brick_slug (slug),
    KEY idx_state (state),
    KEY idx_target (target_launch_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS brick_lifecycle_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(120) NOT NULL,
    event VARCHAR(40) NOT NULL,
    from_state VARCHAR(20) NULL,
    to_state VARCHAR(20) NULL,
    actor VARCHAR(120) NULL,
    reason VARCHAR(255) NULL,
    meta JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ev_slug (slug),
    KEY idx_ev_created (created_at)
) ENGINE=InnoDB;
