-- BRICK — AI Provider & Model Management Layer
-- AI infrastructure for the WONTIA ecosystem (multi-provider, multi-site aware)

CREATE TABLE IF NOT EXISTS ai_providers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL,
    description VARCHAR(500),
    adapter VARCHAR(50) NOT NULL DEFAULT 'openai-compatible',
    api_base_url VARCHAR(500),
    auth_method VARCHAR(20) DEFAULT 'bearer',
    api_key_env VARCHAR(100),
    badge VARCHAR(5) DEFAULT 'AI',
    color VARCHAR(20) DEFAULT '#9B8CDE',
    docs_url VARCHAR(500),
    status ENUM('enabled','disabled') DEFAULT 'disabled',
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY site_provider (site_id, slug),
    FOREIGN KEY (site_id) REFERENCES sites(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ai_models (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    provider_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    display_name VARCHAR(150),
    version VARCHAR(30) DEFAULT '1.0',
    model_identifier VARCHAR(150) NOT NULL,
    description VARCHAR(500),
    context_window INT DEFAULT 0,
    max_output_tokens INT DEFAULT 4096,
    input_cost DECIMAL(10,6) DEFAULT 0,
    cached_input_cost DECIMAL(10,6) DEFAULT 0,
    output_cost DECIMAL(10,6) DEFAULT 0,
    currency VARCHAR(5) DEFAULT 'USD',
    capabilities JSON,
    priority INT DEFAULT 0,
    status ENUM('active','deprecated','retired') DEFAULT 'active',
    enabled TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY site_model (site_id, provider_id, model_identifier),
    FOREIGN KEY (site_id) REFERENCES sites(id),
    FOREIGN KEY (provider_id) REFERENCES ai_providers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ai_instances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    system_id VARCHAR(50) NOT NULL,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(500),
    is_active TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY site_system (site_id, system_id),
    FOREIGN KEY (site_id) REFERENCES sites(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ai_policies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    instance_id INT,
    system_id VARCHAR(50) NOT NULL DEFAULT 'wontia',
    module VARCHAR(100) NOT NULL DEFAULT 'general',
    function_key VARCHAR(100) NOT NULL DEFAULT 'default',
    strategy ENUM('manual','auto','cost','performance','balanced') DEFAULT 'balanced',
    primary_model_id INT,
    fallback_model_id INT,
    fallback2_model_id INT,
    required_capabilities JSON,
    preferred_providers JSON,
    excluded_providers JSON,
    max_cost_per_request DECIMAL(10,4) DEFAULT 0,
    fallback_enabled TINYINT DEFAULT 1,
    monthly_budget DECIMAL(12,2) DEFAULT 0,
    budget_warning_pct INT DEFAULT 80,
    budget_hard_limit_pct INT DEFAULT 100,
    is_active TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY site_policy (site_id, system_id, module, function_key),
    FOREIGN KEY (site_id) REFERENCES sites(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ai_usage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    system_id VARCHAR(50),
    module VARCHAR(100),
    function_key VARCHAR(100),
    user_id INT,
    provider_id INT,
    model_id INT,
    model_identifier VARCHAR(150),
    input_tokens INT DEFAULT 0,
    output_tokens INT DEFAULT 0,
    total_tokens INT DEFAULT 0,
    estimated_cost DECIMAL(12,6) DEFAULT 0,
    latency_ms INT DEFAULT 0,
    status ENUM('success','error','fallback','budget_blocked') DEFAULT 'success',
    error_message VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_usage_site_date (site_id, created_at),
    KEY idx_usage_model (site_id, model_id),
    KEY idx_usage_system (site_id, system_id),
    FOREIGN KEY (site_id) REFERENCES sites(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ai_health (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    provider_id INT,
    model_id INT,
    status ENUM('healthy','degraded','offline','disabled') DEFAULT 'healthy',
    latency_ms INT DEFAULT 0,
    error_count INT DEFAULT 0,
    success_count INT DEFAULT 0,
    last_checked_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY site_model_health (site_id, model_id),
    FOREIGN KEY (site_id) REFERENCES sites(id)
) ENGINE=InnoDB;

-- ── SEED: Providers (API keys live in env vars referenced by api_key_env — never in DB) ──

INSERT IGNORE INTO ai_providers (site_id, name, slug, description, adapter, api_base_url, auth_method, api_key_env, badge, color, docs_url, status, sort_order) VALUES
(1, 'OpenAI', 'openai', 'GPT models with vision, tools and structured output.', 'openai-compatible', 'https://api.openai.com/v1', 'bearer', 'BRICK_OPENAI_API_KEY', 'OA', '#10A37F', 'https://platform.openai.com/docs', 'disabled', 1),
(1, 'DeepSeek', 'deepseek', 'High-efficiency reasoning and chat models.', 'openai-compatible', 'https://api.deepseek.com/v1', 'bearer', 'BRICK_DEEPSEEK_API_KEY', 'DS', '#4D6BFE', 'https://api-docs.deepseek.com', 'enabled', 2),
(1, 'Anthropic', 'anthropic', 'Claude models with long context and strong reasoning.', 'anthropic', 'https://api.anthropic.com', 'api-key', 'BRICK_ANTHROPIC_API_KEY', 'AN', '#D97757', 'https://docs.anthropic.com', 'disabled', 3),
(1, 'Google Gemini', 'gemini', 'Multimodal models from Google.', 'openai-compatible', 'https://generativelanguage.googleapis.com/v1beta/openai', 'bearer', 'BRICK_GEMINI_API_KEY', 'GM', '#4285F4', 'https://ai.google.dev/docs', 'disabled', 4),
(1, 'xAI', 'xai', 'Grok models by xAI.', 'openai-compatible', 'https://api.x.ai/v1', 'bearer', 'BRICK_XAI_API_KEY', 'XA', '#7C3AED', 'https://docs.x.ai', 'disabled', 5),
(1, 'Mistral', 'mistral', 'European open-weight models.', 'openai-compatible', 'https://api.mistral.ai/v1', 'bearer', 'BRICK_MISTRAL_API_KEY', 'MS', '#FF7000', 'https://docs.mistral.ai', 'disabled', 6),
(1, 'OpenRouter', 'openrouter', 'Unified gateway to many models.', 'openai-compatible', 'https://openrouter.ai/api/v1', 'bearer', 'BRICK_OPENROUTER_API_KEY', 'OR', '#6366F1', 'https://openrouter.ai/docs', 'disabled', 7);

-- ── SEED: Models (USD per 1M tokens) ──

INSERT IGNORE INTO ai_models (site_id, provider_id, name, display_name, version, model_identifier, description, context_window, max_output_tokens, input_cost, cached_input_cost, output_cost, capabilities, priority, status, enabled) VALUES
(1, (SELECT id FROM ai_providers WHERE site_id = 1 AND slug = 'deepseek'), 'DeepSeek V4', 'DeepSeek V4 Chat', 'v4', 'deepseek-chat', 'General-purpose chat and tool use.', 128000, 8192, 0.27, 0.07, 1.10, '["text","reasoning","coding","tools","structured_output","streaming","long_context"]', 100, 'active', 1),
(1, (SELECT id FROM ai_providers WHERE site_id = 1 AND slug = 'deepseek'), 'DeepSeek R1', 'DeepSeek Reasoner', 'r1', 'deepseek-reasoner', 'Deep reasoning mode for complex analysis.', 128000, 8192, 0.55, 0.14, 2.19, '["text","reasoning","coding","long_context"]', 90, 'active', 1),
(1, (SELECT id FROM ai_providers WHERE site_id = 1 AND slug = 'openai'), 'GPT-4o', 'GPT-4o', '1.0', 'gpt-4o', 'Flagship multimodal GPT model.', 128000, 16384, 2.50, 1.25, 10.00, '["text","vision","reasoning","coding","tools","structured_output","streaming","long_context"]', 100, 'active', 0),
(1, (SELECT id FROM ai_providers WHERE site_id = 1 AND slug = 'openai'), 'GPT-4o Mini', 'GPT-4o Mini', '1.0', 'gpt-4o-mini', 'Fast, low-cost GPT model.', 128000, 16384, 0.15, 0.075, 0.60, '["text","vision","coding","tools","structured_output","streaming","long_context"]', 80, 'active', 0),
(1, (SELECT id FROM ai_providers WHERE site_id = 1 AND slug = 'openai'), 'o1', 'OpenAI o1', '1.0', 'o1', 'Advanced reasoning model.', 200000, 100000, 15.00, 7.50, 60.00, '["text","reasoning","coding","long_context"]', 110, 'active', 0),
(1, (SELECT id FROM ai_providers WHERE site_id = 1 AND slug = 'anthropic'), 'Claude Sonnet', 'Claude Sonnet', '3.5', 'claude-3-5-sonnet-latest', 'Balanced Claude model with strong reasoning.', 200000, 8192, 3.00, 0.30, 15.00, '["text","vision","reasoning","coding","tools","structured_output","streaming","long_context"]', 95, 'active', 0),
(1, (SELECT id FROM ai_providers WHERE site_id = 1 AND slug = 'anthropic'), 'Claude Haiku', 'Claude Haiku', '3.5', 'claude-3-5-haiku-latest', 'Fastest Claude model.', 200000, 8192, 0.80, 0.08, 4.00, '["text","vision","coding","tools","streaming","long_context"]', 70, 'active', 0),
(1, (SELECT id FROM ai_providers WHERE site_id = 1 AND slug = 'gemini'), 'Gemini Flash', 'Gemini 2.0 Flash', '2.0', 'gemini-2.0-flash', 'Fast multimodal model.', 1000000, 8192, 0.10, 0.025, 0.40, '["text","vision","audio","image","coding","tools","structured_output","streaming","long_context","realtime"]', 75, 'active', 0),
(1, (SELECT id FROM ai_providers WHERE site_id = 1 AND slug = 'gemini'), 'Gemini Pro', 'Gemini 1.5 Pro', '1.5', 'gemini-1.5-pro', 'Large-context multimodal model.', 2000000, 8192, 1.25, 0.3125, 5.00, '["text","vision","audio","image","video","coding","tools","structured_output","streaming","long_context"]', 90, 'active', 0),
(1, (SELECT id FROM ai_providers WHERE site_id = 1 AND slug = 'xai'), 'Grok 2', 'Grok 2', '2.0', 'grok-2', 'General-purpose model by xAI.', 131072, 4096, 2.00, 0.00, 10.00, '["text","vision","reasoning","coding","tools","streaming","long_context"]', 80, 'active', 0),
(1, (SELECT id FROM ai_providers WHERE site_id = 1 AND slug = 'mistral'), 'Mistral Small', 'Mistral Small', 'latest', 'mistral-small-latest', 'Efficient open-weight model.', 32000, 4096, 0.20, 0.00, 0.60, '["text","coding","tools","structured_output","streaming"]', 60, 'active', 0),
(1, (SELECT id FROM ai_providers WHERE site_id = 1 AND slug = 'mistral'), 'Mistral Large', 'Mistral Large', 'latest', 'mistral-large-latest', 'High-capability open-weight model.', 128000, 4096, 2.00, 0.00, 6.00, '["text","reasoning","coding","tools","structured_output","streaming","long_context"]', 85, 'active', 0),
(1, (SELECT id FROM ai_providers WHERE site_id = 1 AND slug = 'openrouter'), 'OpenRouter Auto', 'OpenRouter Auto', '1.0', 'openrouter/auto', 'Automatic routing across many providers.', 128000, 4096, 1.00, 0.00, 2.00, '["text","reasoning","coding","tools","streaming","long_context"]', 50, 'active', 0);

-- ── SEED: Instances (systems that consume BRICK) ──

INSERT IGNORE INTO ai_instances (site_id, system_id, name, description, is_active) VALUES
(1, 'wontia', 'WONTIA Platform', 'Applied Intelligence System core.', 1),
(1, 'tia', 'TIA Core', 'Technology of Applied Intelligence layer.', 1),
(1, 'ia_annotation', 'IA Annotation', 'Data annotation and analysis workflows.', 1),
(1, 'website', 'Websites & CMS', 'Content generation for sites and landing pages.', 1),
(1, 'agents', 'AI Agents', 'Autonomous agent runtime.', 1),
(1, 'automations', 'Automations', 'Automated workflow engine.', 1);

-- ── SEED: Policies (system → module → function → model chain) ──

INSERT IGNORE INTO ai_policies (site_id, system_id, module, function_key, strategy, primary_model_id, fallback_model_id, fallback2_model_id, required_capabilities, fallback_enabled, monthly_budget, budget_warning_pct, budget_hard_limit_pct, is_active) VALUES
(1, 'wontia', 'general', 'default', 'balanced', (SELECT id FROM ai_models WHERE site_id = 1 AND model_identifier = 'deepseek-chat'), (SELECT id FROM ai_models WHERE site_id = 1 AND model_identifier = 'gpt-4o-mini'), NULL, '["text"]', 1, 500.00, 80, 100, 1),
(1, 'wontia', 'content', 'generation', 'cost', (SELECT id FROM ai_models WHERE site_id = 1 AND model_identifier = 'deepseek-chat'), (SELECT id FROM ai_models WHERE site_id = 1 AND model_identifier = 'gpt-4o-mini'), NULL, '["text","tools"]', 1, 300.00, 80, 100, 1),
(1, 'tia', 'orchestration', 'default', 'balanced', (SELECT id FROM ai_models WHERE site_id = 1 AND model_identifier = 'deepseek-reasoner'), (SELECT id FROM ai_models WHERE site_id = 1 AND model_identifier = 'deepseek-chat'), (SELECT id FROM ai_models WHERE site_id = 1 AND model_identifier = 'gpt-4o-mini'), '["reasoning","tools"]', 1, 800.00, 80, 100, 1),
(1, 'website', 'seo', 'metadata', 'cost', (SELECT id FROM ai_models WHERE site_id = 1 AND model_identifier = 'deepseek-chat'), (SELECT id FROM ai_models WHERE site_id = 1 AND model_identifier = 'gpt-4o-mini'), NULL, '["text"]', 1, 100.00, 80, 100, 1);
