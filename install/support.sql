SET NAMES utf8mb4;

INSERT IGNORE INTO settings (site_id, `key`, `value`) VALUES
    (12, 'support_notify_email', 'clientes@sonika.com.co'),
    (12, 'tia_embed_enabled', '1');

CREATE TABLE IF NOT EXISTS wwi_leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    company VARCHAR(190) DEFAULT '',
    phone VARCHAR(40) DEFAULT '',
    service VARCHAR(120) DEFAULT '',
    message TEXT,
    source VARCHAR(60) DEFAULT 'contact',
    status VARCHAR(20) DEFAULT 'new',
    ip_hash CHAR(40) DEFAULT '',
    order_ref VARCHAR(60) NULL,
    reply_preference VARCHAR(20) NULL,
    auth_accepted TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_site (site_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE wwi_leads ADD COLUMN IF NOT EXISTS order_ref VARCHAR(60) NULL;
ALTER TABLE wwi_leads ADD COLUMN IF NOT EXISTS reply_preference VARCHAR(20) NULL;
ALTER TABLE wwi_leads ADD COLUMN IF NOT EXISTS auth_accepted TINYINT(1) NOT NULL DEFAULT 0;

INSERT INTO ai_policies
    (site_id, system_id, module, function_key, strategy, primary_model_id, fallback_model_id, fallback2_model_id, max_cost_per_request, fallback_enabled, monthly_budget, budget_warning_pct, budget_hard_limit_pct, is_active)
SELECT 12, 'tia', 'support', 'chat', 'manual',
    (SELECT id FROM ai_models WHERE site_id = 12 AND model_identifier = 'deepseek-chat' ORDER BY id LIMIT 1),
    (SELECT id FROM ai_models WHERE site_id = 12 AND model_identifier = 'deepseek-reasoner' ORDER BY id LIMIT 1),
    NULL, 0.0500, 1, 100.00, 80, 100, 1
WHERE NOT EXISTS (
    SELECT 1 FROM ai_policies
    WHERE site_id = 12 AND system_id = 'tia' AND module = 'support' AND function_key = 'chat'
);
