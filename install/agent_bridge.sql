-- ============================================================
-- WWI AGENT BRIDGE HUB — canal de coordinación de agentes (núcleo)
-- Idempotente. Auto-migrado por App\Services\AgentBridgeService::ensureTables().
-- ============================================================

CREATE TABLE IF NOT EXISTS agent_bridge_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NULL,
    site_slug VARCHAR(80) NULL,
    from_role VARCHAR(80) NOT NULL,
    to_role VARCHAR(80) NOT NULL DEFAULT 'head-team-leader',
    type ENUM('SOLICITUD','ORDEN','RESULTADO','RESPUESTA','ACLARACION','NOTA') NOT NULL DEFAULT 'NOTA',
    subject VARCHAR(200) NULL,
    body TEXT NOT NULL,
    status ENUM('abierto','en_curso','resuelto') NOT NULL DEFAULT 'abierto',
    meta JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_site (site_id, created_at),
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS agent_bridge_agents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(80) NOT NULL,
    site_id INT NULL,
    name VARCHAR(160) NULL,
    api_key_hash VARCHAR(255) NOT NULL,
    is_active TINYINT NOT NULL DEFAULT 1,
    last_seen DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cola de tareas de plataforma (modo 1): las ORDEN "PLATAFORMA" se convierten en tickets.
CREATE TABLE IF NOT EXISTS bridge_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NULL,
    site_slug VARCHAR(80) NULL,
    message_id INT NULL,
    title VARCHAR(200) NULL,
    detail TEXT NULL,
    status ENUM('pending','in_progress','done') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_status (status),
    KEY idx_site (site_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
