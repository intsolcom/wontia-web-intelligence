<?php
namespace App\Core\AiBrick;

use App\Core\Config;
use App\Core\Database;

class AiBrickService
{
    public const CAPABILITIES = [
        'text' => 'Text',
        'reasoning' => 'Reasoning',
        'vision' => 'Vision',
        'audio' => 'Audio',
        'image' => 'Image',
        'video' => 'Video',
        'coding' => 'Coding',
        'tools' => 'Tool Use',
        'structured_output' => 'Structured Output',
        'streaming' => 'Streaming',
        'embeddings' => 'Embeddings',
        'long_context' => 'Long Context',
        'realtime' => 'Realtime',
        'agents' => 'Agents',
    ];

    public const ADAPTERS = [
        'openai-compatible' => 'OpenAI Compatible',
        'anthropic' => 'Anthropic',
    ];

    public const STRATEGIES = [
        'manual' => 'Manual',
        'auto' => 'Automatic',
        'cost' => 'Cost Optimized',
        'performance' => 'Performance Optimized',
        'balanced' => 'Balanced',
    ];

    public function providers(): array
    {
        $this->provision();
        $db = Database::instance();
        $rows = $db->query("SELECT p.*, (SELECT COUNT(*) FROM ai_models m WHERE m.provider_id = p.id AND m.enabled = 1) AS enabled_models FROM ai_providers p WHERE p.site_id = @site_id ORDER BY p.sort_order ASC")->fetchAll();
        foreach ($rows as &$row) {
            $row['has_key'] = $this->resolveApiKey($row) !== null;
        }
        return $rows;
    }

    public function findProvider(int $id): ?array
    {
        $stmt = Database::instance()->prepare("SELECT * FROM ai_providers WHERE id = :id AND site_id = @site_id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findProviderBySlug(string $slug): ?array
    {
        $stmt = Database::instance()->prepare("SELECT * FROM ai_providers WHERE slug = :slug AND site_id = @site_id");
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function models(?int $providerId = null): array
    {
        $this->provision();
        $db = Database::instance();
        if ($providerId) {
            $stmt = $db->prepare("SELECT m.*, p.name AS provider_name, p.slug AS provider_slug, p.badge, p.color FROM ai_models m JOIN ai_providers p ON p.id = m.provider_id WHERE m.site_id = @site_id AND m.provider_id = :pid ORDER BY m.priority DESC, m.name ASC");
            $stmt->execute(['pid' => $providerId]);
            return $this->decorateModels($stmt->fetchAll());
        }
        $rows = $db->query("SELECT m.*, p.name AS provider_name, p.slug AS provider_slug, p.badge, p.color FROM ai_models m JOIN ai_providers p ON p.id = m.provider_id WHERE m.site_id = @site_id ORDER BY p.sort_order ASC, m.priority DESC")->fetchAll();
        return $this->decorateModels($rows);
    }

    private function decorateModels(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['capabilities'] = $this->safeJson($row['capabilities'] ?? '');
            $row['input_cost'] = (float)$row['input_cost'];
            $row['cached_input_cost'] = (float)$row['cached_input_cost'];
            $row['output_cost'] = (float)$row['output_cost'];
        }
        return $rows;
    }

    public function findModel(int $id): ?array
    {
        $stmt = Database::instance()->prepare("SELECT m.*, p.name AS provider_name, p.slug AS provider_slug FROM ai_models m JOIN ai_providers p ON p.id = m.provider_id WHERE m.id = :id AND m.site_id = @site_id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) return null;
        $row['capabilities'] = $this->safeJson($row['capabilities'] ?? '');
        return $row;
    }

    public function instances(): array
    {
        $this->provision();
        $db = Database::instance();
        return $db->query("SELECT i.*, (SELECT COUNT(*) FROM ai_policies p WHERE p.system_id = i.system_id AND p.site_id = i.site_id AND p.is_active = 1) AS policy_count FROM ai_instances i WHERE i.site_id = @site_id ORDER BY i.id ASC")->fetchAll();
    }

    public function policies(): array
    {
        $this->provision();
        $db = Database::instance();
        $rows = $db->query("SELECT p.*, pm.name AS primary_name, pm.model_identifier AS primary_identifier, pr.name AS primary_provider,
            fm.name AS fallback_name, fm.model_identifier AS fallback_identifier,
            f2m.name AS fallback2_name
            FROM ai_policies p
            LEFT JOIN ai_models pm ON pm.id = p.primary_model_id
            LEFT JOIN ai_providers pr ON pr.id = pm.provider_id
            LEFT JOIN ai_models fm ON fm.id = p.fallback_model_id
            LEFT JOIN ai_models f2m ON f2m.id = p.fallback2_model_id
            WHERE p.site_id = @site_id ORDER BY p.system_id ASC, p.module ASC, p.function_key ASC")->fetchAll();
        foreach ($rows as &$row) {
            $row['required_capabilities'] = $this->safeJson($row['required_capabilities'] ?? '');
            $row['preferred_providers'] = $this->safeJson($row['preferred_providers'] ?? '');
            $row['excluded_providers'] = $this->safeJson($row['excluded_providers'] ?? '');
            $row['monthly_budget'] = (float)$row['monthly_budget'];
        }
        return $rows;
    }

    public function findPolicy(string $systemId, string $module, string $function): ?array
    {
        $this->provision();
        $db = Database::instance();
        $stmt = $db->prepare("SELECT p.*, pm.model_identifier AS primary_identifier, fm.model_identifier AS fallback_identifier, f2m.model_identifier AS fallback2_identifier
            FROM ai_policies p
            LEFT JOIN ai_models pm ON pm.id = p.primary_model_id
            LEFT JOIN ai_models fm ON fm.id = p.fallback_model_id
            LEFT JOIN ai_models f2m ON f2m.id = p.fallback2_model_id
            WHERE p.site_id = @site_id AND p.system_id = :sys AND p.module = :mod AND p.function_key = :fn AND p.is_active = 1 LIMIT 1");
        $stmt->execute(['sys' => $systemId, 'mod' => $module, 'fn' => $function]);
        $row = $stmt->fetch();
        if (!$row) return null;
        $row['required_capabilities'] = $this->safeJson($row['required_capabilities'] ?? '');
        $row['preferred_providers'] = $this->safeJson($row['preferred_providers'] ?? '');
        $row['excluded_providers'] = $this->safeJson($row['excluded_providers'] ?? '');
        return $row;
    }

    public function saveProvider(array $d, ?int $id = null): array
    {
        $db = Database::instance();
        $name = trim((string)($d['name'] ?? ''));
        $slug = trim((string)($d['slug'] ?? ''));
        if ($name === '' || $slug === '') return ['ok' => false, 'message' => 'Name and slug are required'];
        $data = [
            'name' => $name,
            'slug' => $slug,
            'description' => (string)($d['description'] ?? ''),
            'adapter' => in_array($d['adapter'] ?? '', array_keys(self::ADAPTERS), true) ? $d['adapter'] : 'openai-compatible',
            'api_base_url' => (string)($d['api_base_url'] ?? ''),
            'auth_method' => in_array($d['auth_method'] ?? '', ['bearer', 'api-key', 'azure'], true) ? $d['auth_method'] : 'bearer',
            'api_key_env' => (string)($d['api_key_env'] ?? ''),
            'badge' => mb_substr((string)($d['badge'] ?? 'AI'), 0, 5),
            'color' => (string)($d['color'] ?? '#9B8CDE'),
            'docs_url' => (string)($d['docs_url'] ?? ''),
            'status' => ($d['status'] ?? 'disabled') === 'enabled' ? 'enabled' : 'disabled',
            'sort_order' => (int)($d['sort_order'] ?? 0),
        ];
        try {
            if ($id) {
                $sets = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($data)));
                $stmt = $db->prepare("UPDATE ai_providers SET $sets WHERE id = :id AND site_id = @site_id");
                $stmt->execute($data + ['id' => $id]);
                return ['ok' => true, 'message' => 'Provider updated', 'id' => $id];
            }
            $cols = implode(', ', array_keys($data));
            $ph = implode(', ', array_map(fn($k) => ":$k", array_keys($data)));
            $stmt = $db->prepare("INSERT INTO ai_providers (site_id, $cols) VALUES (@site_id, $ph)");
            $stmt->execute($data);
            return ['ok' => true, 'message' => 'Provider created', 'id' => (int)$db->lastInsertId()];
        } catch (\PDOException $e) {
            return ['ok' => false, 'message' => $e->getCode() === '23000' ? 'A provider with this slug already exists' : 'Database error'];
        }
    }

    public function deleteProvider(int $id): array
    {
        $db = Database::instance();
        $stmt = $db->prepare("DELETE FROM ai_providers WHERE id = :id AND site_id = @site_id");
        $stmt->execute(['id' => $id]);
        return ['ok' => true, 'message' => 'Provider removed'];
    }

    public function saveModel(array $d, ?int $id = null): array
    {
        $db = Database::instance();
        $providerId = (int)($d['provider_id'] ?? 0);
        $identifier = trim((string)($d['model_identifier'] ?? ''));
        $name = trim((string)($d['name'] ?? ''));
        if (!$providerId || $identifier === '' || $name === '') return ['ok' => false, 'message' => 'Provider, name and model identifier are required'];
        $capabilities = is_array($d['capabilities'] ?? null) ? array_values(array_intersect($d['capabilities'], array_keys(self::CAPABILITIES))) : [];
        $data = [
            'provider_id' => $providerId,
            'name' => $name,
            'display_name' => (string)($d['display_name'] ?? $name),
            'version' => (string)($d['version'] ?? '1.0'),
            'model_identifier' => $identifier,
            'description' => (string)($d['description'] ?? ''),
            'context_window' => (int)($d['context_window'] ?? 0),
            'max_output_tokens' => (int)($d['max_output_tokens'] ?? 4096),
            'input_cost' => (float)($d['input_cost'] ?? 0),
            'cached_input_cost' => (float)($d['cached_input_cost'] ?? 0),
            'output_cost' => (float)($d['output_cost'] ?? 0),
            'currency' => (string)($d['currency'] ?? 'USD'),
            'capabilities' => json_encode($capabilities),
            'priority' => (int)($d['priority'] ?? 0),
            'status' => in_array($d['status'] ?? '', ['active', 'deprecated', 'retired'], true) ? $d['status'] : 'active',
            'enabled' => !empty($d['enabled']) ? 1 : 0,
        ];
        try {
            if ($id) {
                $sets = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($data)));
                $stmt = $db->prepare("UPDATE ai_models SET $sets WHERE id = :id AND site_id = @site_id");
                $stmt->execute($data + ['id' => $id]);
                return ['ok' => true, 'message' => 'Model updated', 'id' => $id];
            }
            $cols = implode(', ', array_keys($data));
            $ph = implode(', ', array_map(fn($k) => ":$k", array_keys($data)));
            $stmt = $db->prepare("INSERT INTO ai_models (site_id, $cols) VALUES (@site_id, $ph)");
            $stmt->execute($data);
            return ['ok' => true, 'message' => 'Model created', 'id' => (int)$db->lastInsertId()];
        } catch (\PDOException $e) {
            return ['ok' => false, 'message' => $e->getCode() === '23000' ? 'This model identifier already exists for the provider' : 'Database error'];
        }
    }

    public function deleteModel(int $id): array
    {
        $db = Database::instance();
        $db->prepare("DELETE FROM ai_health WHERE model_id = :id AND site_id = @site_id")->execute(['id' => $id]);
        $db->prepare("DELETE FROM ai_models WHERE id = :id AND site_id = @site_id")->execute(['id' => $id]);
        return ['ok' => true, 'message' => 'Model removed'];
    }

    public function savePolicy(array $d, ?int $id = null): array
    {
        $db = Database::instance();
        $systemId = trim((string)($d['system_id'] ?? 'wontia'));
        if ($systemId === '') return ['ok' => false, 'message' => 'System is required'];
        $data = [
            'system_id' => $systemId,
            'module' => trim((string)($d['module'] ?? 'general')) ?: 'general',
            'function_key' => trim((string)($d['function'] ?? 'default')) ?: 'default',
            'strategy' => in_array($d['strategy'] ?? '', array_keys(self::STRATEGIES), true) ? $d['strategy'] : 'balanced',
            'primary_model_id' => (int)($d['primary_model_id'] ?? 0) ?: null,
            'fallback_model_id' => (int)($d['fallback_model_id'] ?? 0) ?: null,
            'fallback2_model_id' => (int)($d['fallback2_model_id'] ?? 0) ?: null,
            'required_capabilities' => json_encode(is_array($d['required_capabilities'] ?? null) ? array_values(array_intersect($d['required_capabilities'], array_keys(self::CAPABILITIES))) : []),
            'preferred_providers' => json_encode(is_array($d['preferred_providers'] ?? null) ? $d['preferred_providers'] : []),
            'excluded_providers' => json_encode(is_array($d['excluded_providers'] ?? null) ? $d['excluded_providers'] : []),
            'max_cost_per_request' => (float)($d['max_cost_per_request'] ?? 0),
            'fallback_enabled' => !empty($d['fallback_enabled']) ? 1 : 0,
            'monthly_budget' => (float)($d['monthly_budget'] ?? 0),
            'budget_warning_pct' => (int)($d['budget_warning_pct'] ?? 80),
            'budget_hard_limit_pct' => (int)($d['budget_hard_limit_pct'] ?? 100),
            'is_active' => !empty($d['is_active']) ? 1 : 0,
        ];
        try {
            if ($id) {
                $sets = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($data)));
                $stmt = $db->prepare("UPDATE ai_policies SET $sets WHERE id = :id AND site_id = @site_id");
                $stmt->execute($data + ['id' => $id]);
                return ['ok' => true, 'message' => 'Policy updated', 'id' => $id];
            }
            $cols = implode(', ', array_keys($data));
            $ph = implode(', ', array_map(fn($k) => ":$k", array_keys($data)));
            $stmt = $db->prepare("INSERT INTO ai_policies (site_id, $cols) VALUES (@site_id, $ph)");
            $stmt->execute($data);
            return ['ok' => true, 'message' => 'Policy created', 'id' => (int)$db->lastInsertId()];
        } catch (\PDOException $e) {
            return ['ok' => false, 'message' => $e->getCode() === '23000' ? 'A policy for this system/module/function already exists' : 'Database error'];
        }
    }

    public function deletePolicy(int $id): array
    {
        Database::instance()->prepare("DELETE FROM ai_policies WHERE id = :id AND site_id = @site_id")->execute(['id' => $id]);
        return ['ok' => true, 'message' => 'Policy removed'];
    }

    public function resolveApiKey(array $provider): ?string
    {
        $env = $provider['api_key_env'] ?? '';
        if ($env) {
            $key = Config::get($env);
            if ($key) return $key;
        }
        if (($provider['slug'] ?? '') === 'deepseek') {
            $legacy = Config::get('DEEPSEEK_API_KEY');
            if ($legacy) return $legacy;
        }
        return null;
    }

    public function adapterFor(array $provider): AiProviderAdapter
    {
        return ($provider['adapter'] ?? '') === 'anthropic' ? new AnthropicAdapter() : new OpenAiCompatibleAdapter();
    }

    public function recordUsage(AiResponse $response, string $status, ?string $errorMessage = null): void
    {
        $db = Database::instance();
        $stmt = $db->prepare("INSERT INTO ai_usage (site_id, system_id, module, function_key, user_id, provider_id, model_id, model_identifier, input_tokens, output_tokens, total_tokens, estimated_cost, latency_ms, status, error_message)
            VALUES (@site_id, :sys, :mod, :fn, :uid, :pid, :mid, :ident, :it, :ot, :tt, :cost, :lat, :status, :err)");
        $stmt->execute([
            'sys' => $response->systemId ?: 'wontia',
            'mod' => $response->module ?: 'general',
            'fn' => $response->function ?: 'default',
            'uid' => null,
            'pid' => $response->providerId,
            'mid' => $response->modelId,
            'ident' => $response->model,
            'it' => $response->inputTokens,
            'ot' => $response->outputTokens,
            'tt' => $response->totalTokens,
            'cost' => $response->cost,
            'lat' => $response->latencyMs,
            'status' => $status,
            'err' => $errorMessage ? mb_substr($errorMessage, 0, 490) : null,
        ]);
    }

    public function updateHealth(?int $modelId, ?int $providerId, bool $success, int $latencyMs, ?string $error = null): void
    {
        if (!$modelId) return;
        $db = Database::instance();
        $successCount = $success ? 1 : 0;
        $errorCount = $success ? 0 : 1;
        $total = max(1, $errorCount + $successCount);
        $errorRate = $errorCount / $total;
        $status = $success ? ($errorRate > 0.2 ? 'degraded' : 'healthy') : ($latencyMs > 30000 ? 'offline' : 'degraded');
        $stmt = $db->prepare("INSERT INTO ai_health (site_id, provider_id, model_id, status, latency_ms, error_count, success_count, last_checked_at)
            VALUES (@site_id, :pid, :mid, :status, :lat, :ec, :sc, NOW())
            ON DUPLICATE KEY UPDATE status = :status2, latency_ms = :lat2, error_count = error_count + :ec2, success_count = success_count + :sc2, last_checked_at = NOW()");
        $stmt->execute([
            'pid' => $providerId,
            'mid' => $modelId,
            'status' => $status,
            'lat' => $latencyMs,
            'ec' => $errorCount,
            'sc' => $successCount,
            'status2' => $status,
            'lat2' => $latencyMs,
            'ec2' => $errorCount,
            'sc2' => $successCount,
        ]);
    }

    public function healthRows(): array
    {
        $db = Database::instance();
        return $db->query("SELECT h.*, m.name AS model_name, m.model_identifier, m.enabled, p.name AS provider_name, p.slug AS provider_slug
            FROM ai_health h
            JOIN ai_models m ON m.id = h.model_id
            JOIN ai_providers p ON p.id = m.provider_id
            WHERE h.site_id = @site_id ORDER BY p.sort_order ASC, m.priority DESC")->fetchAll();
    }

    public function monthCost(): float
    {
        $db = Database::instance();
        return (float)$db->query("SELECT COALESCE(SUM(estimated_cost), 0) FROM ai_usage WHERE site_id = @site_id AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())")->fetchColumn();
    }

    public function policyMonthCost(string $systemId, string $module, string $function): float
    {
        $db = Database::instance();
        $stmt = $db->prepare("SELECT COALESCE(SUM(estimated_cost), 0) FROM ai_usage WHERE site_id = @site_id AND system_id = :sys AND module = :mod AND function_key = :fn AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())");
        $stmt->execute(['sys' => $systemId, 'mod' => $module, 'fn' => $function]);
        return (float)$stmt->fetchColumn();
    }

    public function overview(string $range = '30d'): array
    {
        $this->provision();
        $db = Database::instance();
        [$from, $fromToday] = $this->rangeBounds($range);

        $stmt = $db->prepare("SELECT COUNT(*) c, COALESCE(SUM(total_tokens),0) t, COALESCE(SUM(estimated_cost),0) cost, COALESCE(AVG(latency_ms),0) lat,
            SUM(status = 'error') err, SUM(status = 'fallback') fb
            FROM ai_usage WHERE site_id = @site_id AND created_at >= :from");
        $stmt->execute(['from' => $from]);
        $agg = $stmt->fetch();

        $stmt = $db->prepare("SELECT COUNT(*) c, COALESCE(SUM(total_tokens),0) t, COALESCE(SUM(estimated_cost),0) cost
            FROM ai_usage WHERE site_id = @site_id AND created_at >= :from");
        $stmt->execute(['from' => $fromToday]);
        $today = $stmt->fetch();

        $activeProviders = (int)$db->query("SELECT COUNT(DISTINCT p.id) FROM ai_providers p JOIN ai_models m ON m.provider_id = p.id AND m.enabled = 1 WHERE p.site_id = @site_id AND p.status = 'enabled'")->fetchColumn();
        $enabledModels = (int)$db->query("SELECT COUNT(*) FROM ai_models WHERE site_id = @site_id AND enabled = 1")->fetchColumn();
        $totalModels = (int)$db->query("SELECT COUNT(*) FROM ai_models WHERE site_id = @site_id")->fetchColumn();

        $totalBudget = (float)$db->query("SELECT COALESCE(SUM(monthly_budget),0) FROM ai_policies WHERE site_id = @site_id AND is_active = 1")->fetchColumn();
        $monthCost = $this->monthCost();
        $budgetPct = $totalBudget > 0 ? round($monthCost / $totalBudget * 100, 1) : 0;

        $requests = (int)($agg['c'] ?? 0);
        $errors = (int)($agg['err'] ?? 0);

        return [
            'stats' => [
                'active_providers' => $activeProviders,
                'active_models' => $enabledModels,
                'total_models' => $totalModels,
                'requests_today' => (int)($today['c'] ?? 0),
                'tokens_today' => (int)($today['t'] ?? 0),
                'cost_today' => round((float)($today['cost'] ?? 0), 4),
                'requests_range' => $requests,
                'tokens_range' => (int)($agg['t'] ?? 0),
                'cost_range' => round((float)($agg['cost'] ?? 0), 4),
                'monthly_cost' => round($monthCost, 4),
                'error_rate' => $requests > 0 ? round($errors / $requests * 100, 1) : 0,
                'avg_latency_ms' => (int)round((float)($agg['lat'] ?? 0)),
                'failover_count' => (int)($agg['fb'] ?? 0),
                'range' => $range,
            ],
            'budget' => [
                'monthly_budget' => round($totalBudget, 2),
                'monthly_spent' => round($monthCost, 2),
                'pct_used' => $budgetPct,
                'status' => $budgetPct >= 100 ? 'limit' : ($budgetPct >= 80 ? 'warning' : 'ok'),
            ],
            'cost_by_provider' => $this->usageGroup('provider', $from),
            'cost_by_model' => $this->usageGroup('model', $from),
            'usage_by_system' => $this->usageGroup('system', $from),
            'suggestions' => $this->suggestions(),
            'health' => $this->healthRows(),
            'recent' => $this->recentUsage(10),
        ];
    }

    public function usageGroup(string $group, string $from): array
    {
        $db = Database::instance();
        $columns = [
            'provider' => 'p.name AS label, p.slug, p.color',
            'model' => 'm.display_name AS label, m.model_identifier AS slug, p.color',
            'system' => 'u.system_id AS label, u.system_id AS slug, "#9B8CDE" AS color',
            'function' => 'CONCAT(u.system_id, " / ", u.module, " / ", u.function_key) AS label, u.function_key AS slug, "#9B8CDE" AS color',
        ];
        if (!isset($columns[$group])) $group = 'model';
        $select = $columns[$group];
        $joins = $group === 'provider'
            ? ' JOIN ai_providers p ON p.id = u.provider_id'
            : ' JOIN ai_models m ON m.id = u.model_id JOIN ai_providers p ON p.id = m.provider_id';
        $sql = "SELECT $select, COUNT(*) AS requests, COALESCE(SUM(u.total_tokens),0) AS tokens, COALESCE(SUM(u.estimated_cost),0) AS cost, COALESCE(AVG(u.latency_ms),0) AS avg_latency, SUM(u.status = 'error') AS errors
            FROM ai_usage u$joins
            WHERE u.site_id = @site_id AND u.created_at >= :from
            GROUP BY label, slug, color ORDER BY cost DESC LIMIT 20";
        $stmt = $db->prepare($sql);
        $stmt->execute(['from' => $from]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['cost'] = round((float)$row['cost'], 4);
            $row['requests'] = (int)$row['requests'];
            $row['tokens'] = (int)$row['tokens'];
            $row['errors'] = (int)$row['errors'];
        }
        return $rows;
    }

    public function recentUsage(int $limit = 10): array
    {
        $db = Database::instance();
        return $db->query("SELECT u.*, p.name AS provider_name, p.color, m.display_name AS model_name
            FROM ai_usage u
            LEFT JOIN ai_providers p ON p.id = u.provider_id
            LEFT JOIN ai_models m ON m.id = u.model_id
            WHERE u.site_id = @site_id ORDER BY u.id DESC LIMIT $limit")->fetchAll();
    }

    public function suggestions(): array
    {
        $out = [];
        $models = $this->models();
        $policies = $this->policies();
        $enabled = array_values(array_filter($models, fn($m) => (int)$m['enabled'] === 1 && $m['status'] === 'active' && $this->providerEnabled($m)));

        foreach ($policies as $policy) {
            if ((float)$policy['monthly_budget'] > 0) {
                $spent = $this->policyMonthCost($policy['system_id'], $policy['module'], $policy['function_key']);
                $pct = $spent / $policy['monthly_budget'] * 100;
                if ($pct >= (int)$policy['budget_hard_limit_pct']) {
                    $out[] = $this->sugg('budget', 'critical', 'Budget limit reached', "Policy {$policy['system_id']}/{$policy['module']}/{$policy['function_key']} has spent " . round($pct, 1) . "% of its monthly budget ($" . number_format($spent, 2) . " of $" . number_format((float)$policy['monthly_budget'], 2) . "). Requests will be blocked.", 'Review the policy budget or switch to a cheaper model');
                } elseif ($pct >= (int)$policy['budget_warning_pct']) {
                    $out[] = $this->sugg('budget', 'warning', 'Budget warning', "Policy {$policy['system_id']}/{$policy['module']} is at " . round($pct, 1) . "% of its monthly budget.", 'Consider a cost-optimized strategy');
                }
            }
            if ($policy['fallback_enabled'] && !$policy['fallback_model_id']) {
                $out[] = $this->sugg('fallback', 'warning', 'No fallback configured', "Policy {$policy['system_id']}/{$policy['module']}/{$policy['function_key']} has fallback enabled but no fallback model.", 'Assign a fallback model');
            }
        }

        $from = date('Y-m-d H:i:s', strtotime('-30 days'));
        $health = $this->healthRows();
        foreach ($health as $h) {
            $total = (int)$h['error_count'] + (int)$h['success_count'];
            if ($total >= 5) {
                $rate = (int)$h['error_count'] / $total * 100;
                if ($rate >= 25) {
                    $out[] = $this->sugg('error', 'critical', 'High error rate', "{$h['model_name']} ({$h['provider_name']}) shows " . round($rate) . "% errors over recent calls.", 'Run a health check or switch primary model');
                }
            }
        }

        foreach ($policies as $policy) {
            $caps = $policy['required_capabilities'];
            $primary = $policy['primary_model_id'];
            $candidates = array_values(array_filter($enabled, function ($m) use ($caps) {
                return !array_diff($caps, $m['capabilities'] ?? []);
            }));
            if (!$candidates) continue;
            usort($candidates, fn($a, $b) => $this->blendedCost($a) <=> $this->blendedCost($b));
            $current = null;
            foreach ($enabled as $m) {
                if ((int)$m['id'] === (int)$primary) $current = $m;
            }
            $cheapest = $candidates[0];
            if ($current && $cheapest && (int)$cheapest['id'] !== (int)$current['id']) {
                $saving = $this->blendedCost($current) - $this->blendedCost($cheapest);
                if ($saving > 0.1) {
                    $out[] = $this->sugg('cost', 'info', 'Cheaper model available', "For {$policy['system_id']}/{$policy['module']}, {$cheapest['display_name']} covers the same capabilities and costs $" . number_format($this->blendedCost($cheapest), 2) . "/M tokens vs $" . number_format($this->blendedCost($current), 2) . "/M for {$current['display_name']}.", 'Switch primary model');
                }
            }
        }

        $usageRows = $this->recentUsage(500);
        $usedModelIds = [];
        foreach ($usageRows as $row) $usedModelIds[(int)$row['model_id']] = true;
        foreach ($enabled as $m) {
            if ((int)($m['context_window'] ?? 0) > 0 && empty($usedModelIds[(int)$m['id']]) && count($out) < 12) {
                $out[] = $this->sugg('idle', 'info', 'Unused model enabled', "{$m['display_name']} ({$m['provider_name']}) is enabled but has no requests in the last 30 days.", 'Disable it or route a function to it');
            }
        }

        $priority = ['critical' => 0, 'warning' => 1, 'info' => 2];
        usort($out, fn($a, $b) => $priority[$a['severity']] <=> $priority[$b['severity']]);
        return array_slice($out, 0, 15);
    }

    private function sugg(string $type, string $severity, string $title, string $detail, string $action): array
    {
        return ['type' => $type, 'severity' => $severity, 'title' => $title, 'detail' => $detail, 'action' => $action];
    }

    private function providerEnabled(array $model): bool
    {
        $provider = $this->findProvider((int)$model['provider_id']);
        return $provider && $provider['status'] === 'enabled';
    }

    private function blendedCost(array $model): float
    {
        return (float)($model['input_cost'] ?? 0) + (float)($model['output_cost'] ?? 0);
    }

    private function rangeBounds(string $range): array
    {
        return match ($range) {
            'today' => [date('Y-m-d 00:00:00'), date('Y-m-d 00:00:00')],
            '7d' => [date('Y-m-d H:i:s', strtotime('-7 days')), date('Y-m-d 00:00:00')],
            'all' => ['2000-01-01 00:00:00', date('Y-m-d 00:00:00')],
            default => [date('Y-m-d H:i:s', strtotime('-30 days')), date('Y-m-d 00:00:00')],
        };
    }

    private function safeJson($value): array
    {
        if (is_array($value)) return $value;
        if (is_string($value)) return json_decode($value, true) ?: [];
        return [];
    }

    public function ensureTables(): array
    {
        $db = Database::instance();
        $statements = [
            "CREATE TABLE IF NOT EXISTS ai_providers (
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
                UNIQUE KEY site_provider (site_id, slug)
            ) ENGINE=InnoDB",
            "CREATE TABLE IF NOT EXISTS ai_models (
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
                UNIQUE KEY site_model (site_id, provider_id, model_identifier)
            ) ENGINE=InnoDB",
            "CREATE TABLE IF NOT EXISTS ai_instances (
                id INT AUTO_INCREMENT PRIMARY KEY,
                site_id INT NOT NULL DEFAULT 1,
                system_id VARCHAR(50) NOT NULL,
                name VARCHAR(100) NOT NULL,
                description VARCHAR(500),
                is_active TINYINT DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY site_system (site_id, system_id)
            ) ENGINE=InnoDB",
            "CREATE TABLE IF NOT EXISTS ai_policies (
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
                UNIQUE KEY site_policy (site_id, system_id, module, function_key)
            ) ENGINE=InnoDB",
            "CREATE TABLE IF NOT EXISTS ai_usage (
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
                KEY idx_usage_system (site_id, system_id)
            ) ENGINE=InnoDB",
            "CREATE TABLE IF NOT EXISTS ai_health (
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
                UNIQUE KEY site_model_health (site_id, model_id)
            ) ENGINE=InnoDB",
        ];
        foreach ($statements as $sql) $db->exec($sql);

        $seeded = $this->seedIfEmpty();
        return ['ok' => true, 'message' => $seeded ? 'BRICK tables created and seeded' : 'BRICK tables already exist', 'data' => ['seeded' => $seeded]];
    }

    public function provision(): void
    {
        try {
            $this->seedIfEmpty();
        } catch (\Exception $e) {}
    }

    private function seedIfEmpty(): bool
    {
        $db = Database::instance();
        $seeded = false;

        $seedCount = (int)$db->query("SELECT COUNT(*) FROM ai_providers WHERE site_id = @site_id")->fetchColumn();
        if ($seedCount === 0) {
            $db->exec("INSERT IGNORE INTO ai_providers (site_id, name, slug, description, adapter, api_base_url, auth_method, api_key_env, badge, color, docs_url, status, sort_order) VALUES
                (@site_id, 'OpenAI', 'openai', 'GPT models with vision, tools and structured output.', 'openai-compatible', 'https://api.openai.com/v1', 'bearer', 'BRICK_OPENAI_API_KEY', 'OA', '#10A37F', 'https://platform.openai.com/docs', 'disabled', 1),
                (@site_id, 'DeepSeek', 'deepseek', 'High-efficiency reasoning and chat models.', 'openai-compatible', 'https://api.deepseek.com/v1', 'bearer', 'BRICK_DEEPSEEK_API_KEY', 'DS', '#4D6BFE', 'https://api-docs.deepseek.com', 'enabled', 2),
                (@site_id, 'Anthropic', 'anthropic', 'Claude models with long context and strong reasoning.', 'anthropic', 'https://api.anthropic.com', 'api-key', 'BRICK_ANTHROPIC_API_KEY', 'AN', '#D97757', 'https://docs.anthropic.com', 'disabled', 3),
                (@site_id, 'Google Gemini', 'gemini', 'Multimodal models from Google.', 'openai-compatible', 'https://generativelanguage.googleapis.com/v1beta/openai', 'bearer', 'BRICK_GEMINI_API_KEY', 'GM', '#4285F4', 'https://ai.google.dev/docs', 'disabled', 4),
                (@site_id, 'xAI', 'xai', 'Grok models by xAI.', 'openai-compatible', 'https://api.x.ai/v1', 'bearer', 'BRICK_XAI_API_KEY', 'XA', '#7C3AED', 'https://docs.x.ai', 'disabled', 5),
                (@site_id, 'Mistral', 'mistral', 'European open-weight models.', 'openai-compatible', 'https://api.mistral.ai/v1', 'bearer', 'BRICK_MISTRAL_API_KEY', 'MS', '#FF7000', 'https://docs.mistral.ai', 'disabled', 6),
                (@site_id, 'OpenRouter', 'openrouter', 'Unified gateway to many models.', 'openai-compatible', 'https://openrouter.ai/api/v1', 'bearer', 'BRICK_OPENROUTER_API_KEY', 'OR', '#6366F1', 'https://openrouter.ai/docs', 'disabled', 7)");
            $seeded = true;
        }

        $modelCount = (int)$db->query("SELECT COUNT(*) FROM ai_models WHERE site_id = @site_id")->fetchColumn();
        if ($modelCount === 0) {
            $db->exec("INSERT IGNORE INTO ai_models (site_id, provider_id, name, display_name, version, model_identifier, description, context_window, max_output_tokens, input_cost, cached_input_cost, output_cost, capabilities, priority, status, enabled) VALUES
                (@site_id, (SELECT id FROM ai_providers WHERE site_id = @site_id AND slug = 'deepseek'), 'DeepSeek V4', 'DeepSeek V4 Chat', 'v4', 'deepseek-chat', 'General-purpose chat and tool use.', 128000, 8192, 0.27, 0.07, 1.10, '[\"text\",\"reasoning\",\"coding\",\"tools\",\"structured_output\",\"streaming\",\"long_context\"]', 100, 'active', 1),
                (@site_id, (SELECT id FROM ai_providers WHERE site_id = @site_id AND slug = 'deepseek'), 'DeepSeek R1', 'DeepSeek Reasoner', 'r1', 'deepseek-reasoner', 'Deep reasoning mode for complex analysis.', 128000, 8192, 0.55, 0.14, 2.19, '[\"text\",\"reasoning\",\"coding\",\"long_context\"]', 90, 'active', 1),
                (@site_id, (SELECT id FROM ai_providers WHERE site_id = @site_id AND slug = 'openai'), 'GPT-4o', 'GPT-4o', '1.0', 'gpt-4o', 'Flagship multimodal GPT model.', 128000, 16384, 2.50, 1.25, 10.00, '[\"text\",\"vision\",\"reasoning\",\"coding\",\"tools\",\"structured_output\",\"streaming\",\"long_context\"]', 100, 'active', 0),
                (@site_id, (SELECT id FROM ai_providers WHERE site_id = @site_id AND slug = 'openai'), 'GPT-4o Mini', 'GPT-4o Mini', '1.0', 'gpt-4o-mini', 'Fast, low-cost GPT model.', 128000, 16384, 0.15, 0.075, 0.60, '[\"text\",\"vision\",\"coding\",\"tools\",\"structured_output\",\"streaming\",\"long_context\"]', 80, 'active', 0),
                (@site_id, (SELECT id FROM ai_providers WHERE site_id = @site_id AND slug = 'openai'), 'o1', 'OpenAI o1', '1.0', 'o1', 'Advanced reasoning model.', 200000, 100000, 15.00, 7.50, 60.00, '[\"text\",\"reasoning\",\"coding\",\"long_context\"]', 110, 'active', 0),
                (@site_id, (SELECT id FROM ai_providers WHERE site_id = @site_id AND slug = 'anthropic'), 'Claude Sonnet', 'Claude Sonnet', '3.5', 'claude-3-5-sonnet-latest', 'Balanced Claude model with strong reasoning.', 200000, 8192, 3.00, 0.30, 15.00, '[\"text\",\"vision\",\"reasoning\",\"coding\",\"tools\",\"structured_output\",\"streaming\",\"long_context\"]', 95, 'active', 0),
                (@site_id, (SELECT id FROM ai_providers WHERE site_id = @site_id AND slug = 'anthropic'), 'Claude Haiku', 'Claude Haiku', '3.5', 'claude-3-5-haiku-latest', 'Fastest Claude model.', 200000, 8192, 0.80, 0.08, 4.00, '[\"text\",\"vision\",\"coding\",\"tools\",\"streaming\",\"long_context\"]', 70, 'active', 0),
                (@site_id, (SELECT id FROM ai_providers WHERE site_id = @site_id AND slug = 'gemini'), 'Gemini Flash', 'Gemini 2.0 Flash', '2.0', 'gemini-2.0-flash', 'Fast multimodal model.', 1000000, 8192, 0.10, 0.025, 0.40, '[\"text\",\"vision\",\"audio\",\"image\",\"coding\",\"tools\",\"structured_output\",\"streaming\",\"long_context\",\"realtime\"]', 75, 'active', 0),
                (@site_id, (SELECT id FROM ai_providers WHERE site_id = @site_id AND slug = 'gemini'), 'Gemini Pro', 'Gemini 1.5 Pro', '1.5', 'gemini-1.5-pro', 'Large-context multimodal model.', 2000000, 8192, 1.25, 0.3125, 5.00, '[\"text\",\"vision\",\"audio\",\"image\",\"video\",\"coding\",\"tools\",\"structured_output\",\"streaming\",\"long_context\"]', 90, 'active', 0),
                (@site_id, (SELECT id FROM ai_providers WHERE site_id = @site_id AND slug = 'xai'), 'Grok 2', 'Grok 2', '2.0', 'grok-2', 'General-purpose model by xAI.', 131072, 4096, 2.00, 0.00, 10.00, '[\"text\",\"vision\",\"reasoning\",\"coding\",\"tools\",\"streaming\",\"long_context\"]', 80, 'active', 0),
                (@site_id, (SELECT id FROM ai_providers WHERE site_id = @site_id AND slug = 'mistral'), 'Mistral Small', 'Mistral Small', 'latest', 'mistral-small-latest', 'Efficient open-weight model.', 32000, 4096, 0.20, 0.00, 0.60, '[\"text\",\"coding\",\"tools\",\"structured_output\",\"streaming\"]', 60, 'active', 0),
                (@site_id, (SELECT id FROM ai_providers WHERE site_id = @site_id AND slug = 'mistral'), 'Mistral Large', 'Mistral Large', 'latest', 'mistral-large-latest', 'High-capability open-weight model.', 128000, 4096, 2.00, 0.00, 6.00, '[\"text\",\"reasoning\",\"coding\",\"tools\",\"structured_output\",\"streaming\",\"long_context\"]', 85, 'active', 0),
                (@site_id, (SELECT id FROM ai_providers WHERE site_id = @site_id AND slug = 'openrouter'), 'OpenRouter Auto', 'OpenRouter Auto', '1.0', 'openrouter/auto', 'Automatic routing across many providers.', 128000, 4096, 1.00, 0.00, 2.00, '[\"text\",\"reasoning\",\"coding\",\"tools\",\"streaming\",\"long_context\"]', 50, 'active', 0)");
            $seeded = true;
        }

        $instanceCount = (int)$db->query("SELECT COUNT(*) FROM ai_instances WHERE site_id = @site_id")->fetchColumn();
        if ($instanceCount === 0) {
            $db->exec("INSERT IGNORE INTO ai_instances (site_id, system_id, name, description, is_active) VALUES
                (@site_id, 'wontia', 'WONTIA Platform', 'Applied Intelligence System core.', 1),
                (@site_id, 'tia', 'TIA Core', 'Technology of Applied Intelligence layer.', 1),
                (@site_id, 'ia_annotation', 'IA Annotation', 'Data annotation and analysis workflows.', 1),
                (@site_id, 'website', 'Websites & CMS', 'Content generation for sites and landing pages.', 1),
                (@site_id, 'agents', 'AI Agents', 'Autonomous agent runtime.', 1),
                (@site_id, 'automations', 'Automations', 'Automated workflow engine.', 1)");
            $seeded = true;
        }

        $policyCount = (int)$db->query("SELECT COUNT(*) FROM ai_policies WHERE site_id = @site_id")->fetchColumn();
        if ($policyCount === 0) {
            $db->exec("INSERT IGNORE INTO ai_policies (site_id, system_id, module, function_key, strategy, primary_model_id, fallback_model_id, fallback2_model_id, required_capabilities, fallback_enabled, monthly_budget, budget_warning_pct, budget_hard_limit_pct, is_active) VALUES
                (@site_id, 'wontia', 'general', 'default', 'balanced', (SELECT id FROM ai_models WHERE site_id = @site_id AND model_identifier = 'deepseek-chat'), (SELECT id FROM ai_models WHERE site_id = @site_id AND model_identifier = 'gpt-4o-mini'), NULL, '[\"text\"]', 1, 500.00, 80, 100, 1),
                (@site_id, 'wontia', 'content', 'generation', 'cost', (SELECT id FROM ai_models WHERE site_id = @site_id AND model_identifier = 'deepseek-chat'), (SELECT id FROM ai_models WHERE site_id = @site_id AND model_identifier = 'gpt-4o-mini'), NULL, '[\"text\",\"tools\"]', 1, 300.00, 80, 100, 1),
                (@site_id, 'tia', 'orchestration', 'default', 'balanced', (SELECT id FROM ai_models WHERE site_id = @site_id AND model_identifier = 'deepseek-reasoner'), (SELECT id FROM ai_models WHERE site_id = @site_id AND model_identifier = 'deepseek-chat'), (SELECT id FROM ai_models WHERE site_id = @site_id AND model_identifier = 'gpt-4o-mini'), '[\"reasoning\",\"tools\"]', 1, 800.00, 80, 100, 1),
                (@site_id, 'website', 'seo', 'metadata', 'cost', (SELECT id FROM ai_models WHERE site_id = @site_id AND model_identifier = 'deepseek-chat'), (SELECT id FROM ai_models WHERE site_id = @site_id AND model_identifier = 'gpt-4o-mini'), NULL, '[\"text\"]', 1, 100.00, 80, 100, 1)");
            $seeded = true;
        }

        return $seeded;
    }
}
