<?php
namespace App\Services;

use App\Core\Database;

class TiaProfitService
{
    private static bool $ready = false;

    public function ensureTables(): void
    {
        if (self::$ready) return;
        $db = Database::instance();
        $db->exec("CREATE TABLE IF NOT EXISTS tia_operating_costs (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL DEFAULT 1,
            period CHAR(7) NOT NULL,
            category VARCHAR(40) NOT NULL,
            label VARCHAR(160) NOT NULL,
            amount_cents BIGINT NOT NULL DEFAULT 0,
            notes TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            KEY idx_tia_cost_period (site_id, period),
            KEY idx_tia_cost_category (site_id, period, category)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS tia_product_costs (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL DEFAULT 1,
            product_ref VARCHAR(120) NOT NULL,
            product_title VARCHAR(255) NULL,
            cost_cents BIGINT NOT NULL DEFAULT 0,
            notes TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uk_tia_product_cost (site_id, product_ref),
            KEY idx_tia_product_cost_ref (site_id, product_ref)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS tia_marketing_campaigns (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL DEFAULT 1,
            period CHAR(7) NOT NULL,
            name VARCHAR(160) NOT NULL,
            channel VARCHAR(80) NULL,
            spend_cents BIGINT NOT NULL DEFAULT 0,
            notes TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            KEY idx_tia_campaign_period (site_id, period)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        self::$ready = true;
    }

    public function policy(): array
    {
        $defaults = [
            'gross_margin_pct' => TiaMarginService::GROSS_MARGIN_PCT,
            'net_target_pct' => TiaMarginService::NET_TARGET_PCT,
            'breb_included' => true,
            'accordion_excluded' => true,
        ];
        try {
            $stmt = Database::instance()->prepare('SELECT value FROM settings WHERE site_id = @site_id AND `key` = ? LIMIT 1');
            $stmt->execute(['tia_profit_policy']);
            $row = $stmt->fetch();
            $saved = $row ? json_decode((string)$row['value'], true) : null;
            if (is_array($saved)) $defaults = array_merge($defaults, $saved);
        } catch (\Throwable $e) {
        }
        $defaults['gross_margin_pct'] = max(0, min(100, (float)$defaults['gross_margin_pct']));
        $defaults['net_target_pct'] = max(0, min(100, (float)$defaults['net_target_pct']));
        return $defaults;
    }

    public function savePolicy(array $data): array
    {
        $gross = max(0, min(100, (float)($data['gross_margin_pct'] ?? 20)));
        $net = max(0, min(100, (float)($data['net_target_pct'] ?? 5)));
        $policy = [
            'gross_margin_pct' => $gross,
            'net_target_pct' => $net,
            'breb_included' => !empty($data['breb_included']),
            'accordion_excluded' => !empty($data['accordion_excluded']),
        ];
        try {
            Database::instance()->prepare("INSERT INTO settings (site_id, `key`, value, created_at, updated_at) VALUES (@site_id, 'tia_profit_policy', ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()")
                ->execute([json_encode($policy, JSON_UNESCAPED_UNICODE)]);
            return ['ok' => true, 'policy' => $policy];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo guardar la política.'];
        }
    }

    public function overview(string $period = ''): array
    {
        $this->ensureTables();
        $period = $this->period($period);
        $policy = $this->policy();
        $db = Database::instance();
        $paid = [];
        try {
            $stmt = $db->prepare("SELECT value_cents, primary_title FROM tia_interactions WHERE site_id = @site_id AND outcome = 'paid' AND DATE_FORMAT(created_at, '%Y-%m') = ?");
            $stmt->execute([$period]);
            $paid = $stmt->fetchAll();
        } catch (\Throwable $e) {
        }
        $marginService = new TiaMarginService();
        $eligible = 0;
        $excluded = 0;
        $unknown = 0;
        foreach ($paid as $row) {
            $value = max(0, (int)$row['value_cents']);
            $title = trim((string)($row['primary_title'] ?? ''));
            if ($title === '') $unknown += $value;
            elseif (!empty($policy['accordion_excluded']) && $marginService->isAccordion($title)) $excluded += $value;
            else $eligible += $value;
        }
        $grossPct = (float)$policy['gross_margin_pct'];
        $gross = (int)round($eligible * $grossPct / 100);
        $costs = [];
        $costTotal = 0;
        try {
            $stmt = $db->prepare("SELECT id, category, label, amount_cents, notes, created_at FROM tia_operating_costs WHERE site_id = @site_id AND period = ? ORDER BY id DESC");
            $stmt->execute([$period]);
            $costs = $stmt->fetchAll();
            foreach ($costs as $c) $costTotal += max(0, (int)$c['amount_cents']);
        } catch (\Throwable $e) {
        }
        $campaigns = [];
        $campaignTotal = 0;
        try {
            $stmt = $db->prepare("SELECT id, name, channel, spend_cents, notes, created_at FROM tia_marketing_campaigns WHERE site_id = @site_id AND period = ? ORDER BY id DESC");
            $stmt->execute([$period]);
            $campaigns = $stmt->fetchAll();
            foreach ($campaigns as $c) $campaignTotal += max(0, (int)$c['spend_cents']);
        } catch (\Throwable $e) {
        }
        $productCosts = [];
        try {
            $productCosts = $db->query("SELECT id, product_ref, product_title, cost_cents, notes FROM tia_product_costs WHERE site_id = @site_id ORDER BY product_title ASC, product_ref ASC LIMIT 200")->fetchAll();
        } catch (\Throwable $e) {
        }
        $revenue = $eligible + $excluded + $unknown;
        $net = $gross - $costTotal - $campaignTotal;
        $netTarget = (int)round($revenue * (float)$policy['net_target_pct'] / 100);
        return [
            'ok' => true,
            'period' => $period,
            'policy' => $policy,
            'revenue' => [
                'total_cents' => $revenue,
                'eligible_cents' => $eligible,
                'excluded_accordion_cents' => $excluded,
                'unknown_cents' => $unknown,
            ],
            'gross_margin_cents' => $gross,
            'operating_costs_cents' => $costTotal,
            'campaign_spend_cents' => $campaignTotal,
            'net_profit_cents' => $net,
            'net_target_cents' => $netTarget,
            'net_gap_cents' => $net - $netTarget,
            'net_margin_pct' => $revenue > 0 ? round($net / $revenue * 100, 1) : 0,
            'costs' => array_map(fn($c) => ['id' => (int)$c['id'], 'category' => (string)$c['category'], 'label' => (string)$c['label'], 'amount_cents' => (int)$c['amount_cents'], 'notes' => (string)($c['notes'] ?? ''), 'created_at' => (string)$c['created_at']], $costs),
            'campaigns' => array_map(fn($c) => ['id' => (int)$c['id'], 'name' => (string)$c['name'], 'channel' => (string)($c['channel'] ?? ''), 'spend_cents' => (int)$c['spend_cents'], 'notes' => (string)($c['notes'] ?? ''), 'created_at' => (string)$c['created_at']], $campaigns),
            'product_costs' => array_map(fn($c) => ['id' => (int)$c['id'], 'product_ref' => (string)$c['product_ref'], 'product_title' => (string)($c['product_title'] ?? ''), 'cost_cents' => (int)$c['cost_cents'], 'notes' => (string)($c['notes'] ?? '')], $productCosts),
        ];
    }

    public function addCost(array $data): array
    {
        $this->ensureTables();
        $period = $this->period((string)($data['period'] ?? ''));
        $category = (string)($data['category'] ?? 'other');
        if (!in_array($category, ['financial', 'operational', 'tax', 'administrative', 'marketing', 'returns', 'logistics', 'other'], true)) $category = 'other';
        $label = trim((string)($data['label'] ?? ''));
        if ($label === '') return ['ok' => false, 'message' => 'Escribe una descripción del costo.'];
        try {
            Database::instance()->prepare("INSERT INTO tia_operating_costs (site_id, period, category, label, amount_cents, notes, created_at, updated_at) VALUES (@site_id, ?, ?, ?, ?, ?, NOW(), NOW())")
                ->execute([$period, $category, mb_substr($label, 0, 160), max(0, (int)($data['amount_cents'] ?? 0)), (string)($data['notes'] ?? '')]);
            return ['ok' => true];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo guardar el costo.'];
        }
    }

    public function deleteCost(int $id): array
    {
        try {
            Database::instance()->prepare('DELETE FROM tia_operating_costs WHERE id = ? AND site_id = @site_id')->execute([$id]);
            return ['ok' => true];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo eliminar el costo.'];
        }
    }

    public function addProductCost(array $data): array
    {
        $this->ensureTables();
        $ref = trim((string)($data['product_ref'] ?? ''));
        if ($ref === '') return ['ok' => false, 'message' => 'Escribe el SKU o referencia.'];
        try {
            Database::instance()->prepare("INSERT INTO tia_product_costs (site_id, product_ref, product_title, cost_cents, notes, created_at, updated_at) VALUES (@site_id, ?, ?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE product_title = VALUES(product_title), cost_cents = VALUES(cost_cents), notes = VALUES(notes), updated_at = NOW()")
                ->execute([mb_substr($ref, 0, 120), mb_substr((string)($data['product_title'] ?? ''), 0, 255), max(0, (int)($data['cost_cents'] ?? 0)), (string)($data['notes'] ?? '')]);
            return ['ok' => true];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo guardar el costo del producto.'];
        }
    }

    public function deleteProductCost(int $id): array
    {
        try {
            Database::instance()->prepare('DELETE FROM tia_product_costs WHERE id = ? AND site_id = @site_id')->execute([$id]);
            return ['ok' => true];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo eliminar el costo.'];
        }
    }

    public function addCampaign(array $data): array
    {
        $this->ensureTables();
        $period = $this->period((string)($data['period'] ?? ''));
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') return ['ok' => false, 'message' => 'Escribe el nombre de la campaña.'];
        try {
            Database::instance()->prepare("INSERT INTO tia_marketing_campaigns (site_id, period, name, channel, spend_cents, notes, created_at, updated_at) VALUES (@site_id, ?, ?, ?, ?, ?, NOW(), NOW())")
                ->execute([$period, mb_substr($name, 0, 160), mb_substr((string)($data['channel'] ?? ''), 0, 80), max(0, (int)($data['spend_cents'] ?? 0)), (string)($data['notes'] ?? '')]);
            return ['ok' => true];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo guardar la campaña.'];
        }
    }

    public function deleteCampaign(int $id): array
    {
        try {
            Database::instance()->prepare('DELETE FROM tia_marketing_campaigns WHERE id = ? AND site_id = @site_id')->execute([$id]);
            return ['ok' => true];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo eliminar la campaña.'];
        }
    }

    private function period(string $period): string
    {
        return preg_match('/^\d{4}-\d{2}$/', $period) ? $period : date('Y-m');
    }
}
