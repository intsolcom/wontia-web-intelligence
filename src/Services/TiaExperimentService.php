<?php
namespace App\Services;

use App\Core\Database;

class TiaExperimentService
{
    private static bool $ready = false;

    public function ensureTables(): void
    {
        if (self::$ready) return;
        $db = Database::instance();
        $db->exec("CREATE TABLE IF NOT EXISTS tia_experiments (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL DEFAULT 1,
            experiment_key VARCHAR(80) NOT NULL,
            name VARCHAR(255) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'paused',
            split_b TINYINT NOT NULL DEFAULT 50,
            variant_a_label VARCHAR(120) NOT NULL DEFAULT 'A',
            variant_b_label VARCHAR(120) NOT NULL DEFAULT 'B',
            variant_b_directive TEXT NULL,
            started_at DATETIME NULL,
            ended_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uk_tia_experiment (site_id, experiment_key),
            KEY idx_tia_experiment_status (site_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS tia_experiment_assignments (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL DEFAULT 1,
            experiment_id BIGINT NOT NULL,
            visitor_id VARCHAR(128) NOT NULL,
            variant CHAR(1) NOT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uk_tia_experiment_visitor (site_id, experiment_id, visitor_id),
            KEY idx_tia_experiment_assignment (site_id, experiment_id, variant)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS tia_experiment_events (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL DEFAULT 1,
            experiment_id BIGINT NOT NULL,
            variant CHAR(1) NOT NULL,
            event_type VARCHAR(40) NOT NULL,
            interaction_uid VARCHAR(64) NULL,
            value_cents BIGINT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            KEY idx_tia_experiment_event (site_id, experiment_id, event_type, variant),
            KEY idx_tia_experiment_event_date (site_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        try {
            $db->exec("INSERT INTO tia_experiments (site_id, experiment_key, name, status, split_b, variant_a_label, variant_b_label, variant_b_directive, started_at, created_at, updated_at)
                SELECT @site_id, 'nudge_cta', 'CTA del aviso proactivo', 'running', 50, 'Hablar con TIA', 'Elegir con TIA', '', NOW(), NOW(), NOW()
                WHERE NOT EXISTS (SELECT 1 FROM tia_experiments WHERE site_id = @site_id AND experiment_key = 'nudge_cta')");
        } catch (\Throwable $e) {
        }
        try {
            $db->exec("INSERT INTO tia_experiments (site_id, experiment_key, name, status, split_b, variant_a_label, variant_b_label, variant_b_directive, started_at, created_at, updated_at)
                SELECT @site_id, 'chat_close', 'Cierre de recomendación', 'running', 50, 'Cierre consultivo', 'Cierre directo', '', NOW(), NOW(), NOW()
                WHERE NOT EXISTS (SELECT 1 FROM tia_experiments WHERE site_id = @site_id AND experiment_key = 'chat_close')");
        } catch (\Throwable $e) {
        }
        self::$ready = true;
    }

    public function experiment(string $key = 'nudge_cta'): ?array
    {
        $this->ensureTables();
        try {
            $stmt = Database::instance()->prepare('SELECT * FROM tia_experiments WHERE site_id = @site_id AND experiment_key = ? LIMIT 1');
            $stmt->execute([$key]);
            $row = $stmt->fetch();
            return $row ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function assign(string $key = 'nudge_cta'): array
    {
        $this->ensureTables();
        $exp = $this->experiment($key);
        if (!$exp || (string)$exp['status'] !== 'running') return [];
        $visitor = $this->visitorId();
        $bucket = hexdec(substr(hash('sha256', $key . '|' . $visitor), 0, 8)) % 100;
        $variant = $bucket < (int)$exp['split_b'] ? 'B' : 'A';
        try {
            Database::instance()->prepare("INSERT IGNORE INTO tia_experiment_assignments (site_id, experiment_id, visitor_id, variant, created_at) VALUES (@site_id, ?, ?, ?, NOW())")
                ->execute([(int)$exp['id'], $visitor, $variant]);
        } catch (\Throwable $e) {
        }
        return [
            'id' => (int)$exp['id'],
            'key' => (string)$exp['experiment_key'],
            'variant' => $variant,
            'label' => $variant === 'B' ? (string)$exp['variant_b_label'] : (string)$exp['variant_a_label'],
            'cta' => $variant === 'B' ? (string)$exp['variant_b_label'] : (string)$exp['variant_a_label'],
        ];
    }

    public function recordEvent(string $key, string $variant, string $type, string $interactionUid = '', int $valueCents = 0): bool
    {
        if (!in_array($type, ['impression', 'nudge_click', 'nudge_product_click'], true)) return false;
        $variant = strtoupper($variant) === 'B' ? 'B' : 'A';
        $exp = $this->experiment($key);
        if (!$exp) return false;
        try {
            Database::instance()->prepare("INSERT INTO tia_experiment_events (site_id, experiment_id, variant, event_type, interaction_uid, value_cents, created_at) VALUES (@site_id, ?, ?, ?, ?, ?, NOW())")
                ->execute([(int)$exp['id'], $variant, $type, mb_substr($interactionUid, 0, 64), max(0, $valueCents)]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function stats(int $days = 30): array
    {
        $this->ensureTables();
        $days = max(1, min(365, $days));
        $db = Database::instance();
        $out = [];
        try {
            $experiments = $db->query('SELECT * FROM tia_experiments WHERE site_id = @site_id ORDER BY id DESC LIMIT 10')->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
        foreach ($experiments as $exp) {
            $variants = [
                'A' => ['label' => (string)$exp['variant_a_label'], 'impressions' => 0, 'clicks' => 0, 'product_clicks' => 0, 'interactions' => 0, 'paid' => 0, 'revenue_cents' => 0, 'conversion_pct' => 0, 'click_rate_pct' => 0],
                'B' => ['label' => (string)$exp['variant_b_label'], 'impressions' => 0, 'clicks' => 0, 'product_clicks' => 0, 'interactions' => 0, 'paid' => 0, 'revenue_cents' => 0, 'conversion_pct' => 0, 'click_rate_pct' => 0],
            ];
            try {
                $stmt = $db->prepare("SELECT variant, SUM(event_type = 'impression') AS impressions, SUM(event_type = 'nudge_click') AS clicks, SUM(event_type = 'nudge_product_click') AS product_clicks FROM tia_experiment_events WHERE site_id = @site_id AND experiment_id = ? AND created_at >= (NOW() - INTERVAL $days DAY) GROUP BY variant");
                $stmt->execute([(int)$exp['id']]);
                foreach ($stmt->fetchAll() as $r) {
                    $v = (string)$r['variant'] === 'B' ? 'B' : 'A';
                    $variants[$v]['impressions'] = (int)$r['impressions'];
                    $variants[$v]['clicks'] = (int)$r['clicks'];
                    $variants[$v]['product_clicks'] = (int)$r['product_clicks'];
                }
            } catch (\Throwable $e) {
            }
            try {
                $stmt = $db->prepare("SELECT experiment_variant, COUNT(*) AS interactions, SUM(outcome = 'paid') AS paid, COALESCE(SUM(CASE WHEN outcome = 'paid' THEN value_cents ELSE 0 END), 0) AS revenue_cents FROM tia_interactions WHERE site_id = @site_id AND experiment_key = ? AND created_at >= (NOW() - INTERVAL $days DAY) GROUP BY experiment_variant");
                $stmt->execute([(string)$exp['experiment_key']]);
                foreach ($stmt->fetchAll() as $r) {
                    $v = (string)$r['experiment_variant'] === 'B' ? 'B' : 'A';
                    $variants[$v]['interactions'] = (int)$r['interactions'];
                    $variants[$v]['paid'] = (int)$r['paid'];
                    $variants[$v]['revenue_cents'] = (int)$r['revenue_cents'];
                }
            } catch (\Throwable $e) {
            }
            foreach ($variants as $v => $data) {
                $variants[$v]['conversion_pct'] = $data['interactions'] > 0 ? round($data['paid'] / $data['interactions'] * 100, 1) : 0;
                $variants[$v]['click_rate_pct'] = $data['impressions'] > 0 ? round($data['clicks'] / $data['impressions'] * 100, 1) : 0;
            }
            $out[] = [
                'id' => (int)$exp['id'],
                'key' => (string)$exp['experiment_key'],
                'name' => (string)$exp['name'],
                'status' => (string)$exp['status'],
                'split_b' => (int)$exp['split_b'],
                'variants' => $variants,
            ];
        }
        return $out;
    }

    public function toggle(int $id, string $status): array
    {
        $this->ensureTables();
        $status = in_array($status, ['running', 'paused', 'completed'], true) ? $status : 'paused';
        try {
            $stmt = Database::instance()->prepare('UPDATE tia_experiments SET status = ?, started_at = CASE WHEN ? = \'running\' AND started_at IS NULL THEN NOW() ELSE started_at END, ended_at = CASE WHEN ? = \'completed\' THEN NOW() ELSE ended_at END, updated_at = NOW() WHERE id = ? AND site_id = @site_id');
            $stmt->execute([$status, $status, $status, $id]);
            return ['ok' => true, 'status' => $status];
        } catch (\Throwable $e) {
            return ['ok' => false, 'status' => 'error', 'message' => 'No se pudo actualizar el experimento.'];
        }
    }

    private function visitorId(): string
    {
        $sid = session_id();
        if ($sid === '') {
            $sid = hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? '') . '|' . (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
        }
        return mb_substr($sid, 0, 128);
    }
}
