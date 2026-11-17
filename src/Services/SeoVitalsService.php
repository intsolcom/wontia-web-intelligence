<?php
namespace App\Services;

use App\Core\Database;

/**
 * SeoVitalsService — Core Web Vitals de usuarios reales (RUM).
 *
 * No guarda IP, user-agent ni session_id: solo métrica, valor, ruta,
 * dispositivo y tipo de conexión. Solo se recoge con consentimiento de
 * analítica y se excluye el tráfico de bots y las pruebas (is_test=1).
 *
 * Umbrales oficiales de Google:
 *   LCP  2.5 s / 4.0 s
 *   INP  200 ms / 500 ms
 *   CLS  0.1 / 0.25
 *   TTFB 0.8 s / 1.8 s
 *   FCP  1.8 s / 3.0 s
 */
class SeoVitalsService
{
    public const METRICS = ['lcp', 'inp', 'cls', 'ttfb', 'fcp'];
    private const SETTING_LAST_RUN = 'seo_vitals_last_run';

    public function ensureTable(): void
    {
        Database::instance()->exec("CREATE TABLE IF NOT EXISTS seo_vitals (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL,
            page_url VARCHAR(500) NOT NULL,
            metric VARCHAR(16) NOT NULL,
            value DECIMAL(12,4) NOT NULL,
            rating VARCHAR(20) NOT NULL DEFAULT 'good',
            device VARCHAR(16) NULL,
            connection VARCHAR(16) NULL,
            is_test TINYINT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_site_metric_created (site_id, metric, created_at),
            KEY idx_site_page_created (site_id, page_url(191), created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    /** Recibe un lote del beacon del navegador. Devuelve filas guardadas. */
    public function record(array $items): int
    {
        if (!class_exists('\\App\\Services\\TrackingConsentService') || !\App\Services\TrackingConsentService::allows('analytics')) return 0;
        $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        try {
            if ((new SeoTrackingService())->isBot($ua)) return 0;
        } catch (\Throwable $e) {
        }
        $this->ensureTable();
        $db = Database::instance();
        $stmt = $db->prepare("INSERT INTO seo_vitals (site_id, page_url, metric, value, rating, device, connection, is_test, created_at)
            VALUES (@site_id, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $saved = 0;
        foreach (array_slice($items, 0, 10) as $item) {
            if (!is_array($item)) continue;
            $metric = strtolower(trim((string)($item['metric'] ?? '')));
            if (!in_array($metric, self::METRICS, true)) continue;
            $value = $item['value'] ?? null;
            if (!is_numeric($value)) continue;
            $value = (float)$value;
            if (!is_finite($value) || $value < 0) continue;
            if ($metric === 'cls') {
                if ($value > 10) continue;
            } else {
                if ($value > 120000) continue;
            }
            $page = (string)($item['page_url'] ?? ($_SERVER['REQUEST_URI'] ?? '/'));
            $page = (string)parse_url($page, PHP_URL_PATH);
            $page = preg_replace('/[\x00-\x1F\x7F]/', '', $page) ?? '/';
            if ($page === '' || $page[0] !== '/') $page = '/';
            $page = mb_substr($page, 0, 500);
            $device = strtolower(trim((string)($item['device'] ?? '')));
            if (!in_array($device, ['mobile', 'tablet', 'desktop'], true)) $device = null;
            $connection = strtolower(trim((string)($item['connection'] ?? '')));
            $connection = $connection !== '' ? mb_substr($connection, 0, 16) : null;
            $isTest = !empty($item['is_test']) ? 1 : 0;
            try {
                $stmt->execute([
                    $page,
                    $metric,
                    $metric === 'cls' ? round($value, 4) : round($value, 2),
                    $this->rating($metric, $value),
                    $device,
                    $connection,
                    $isTest,
                ]);
                $saved++;
            } catch (\Throwable $e) {
            }
        }
        return $saved;
    }

    /** Resumen p75 para el panel. */
    public function summary(int $days = 30): array
    {
        $days = max(1, min(365, $days));
        $this->ensureTable();
        $db = Database::instance();
        $rows = [];
        try {
            $rows = $db->query("SELECT metric, value, device, page_url, created_at
                FROM seo_vitals
                WHERE site_id = @site_id AND is_test = 0 AND created_at >= (NOW() - INTERVAL $days DAY)
                ORDER BY metric, value
                LIMIT 100000")->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $rows = [];
        }

        $byMetric = [];
        $byDevice = [];
        $byPage = [];
        $lastSeen = null;
        $pages = [];
        foreach ($rows as $r) {
            $metric = (string)$r['metric'];
            $value = (float)$r['value'];
            $device = (string)($r['device'] ?? '');
            $page = (string)($r['page_url'] ?? '/');
            $byMetric[$metric][] = $value;
            if (in_array($metric, ['lcp', 'inp', 'cls'], true) && $device !== '') {
                $byDevice[$device][$metric][] = $value;
            }
            if ($metric === 'lcp') {
                $byPage[$page][] = $value;
            }
            $pages[$page] = true;
            if ($lastSeen === null || (string)$r['created_at'] > $lastSeen) $lastSeen = (string)$r['created_at'];
        }

        $metrics = [];
        foreach (self::METRICS as $metric) {
            if (empty($byMetric[$metric])) continue;
            $p75 = $this->p75($byMetric[$metric]);
            $metrics[$metric] = [
                'p75' => $metric === 'cls' ? round($p75, 3) : (int)round($p75),
                'rating' => $this->rating($metric, $p75),
                'samples' => count($byMetric[$metric]),
                'unit' => $metric === 'cls' ? 'score' : 'ms',
            ];
        }

        $devices = [];
        foreach ($byDevice as $device => $values) {
            $entry = ['samples' => 0];
            foreach (['lcp', 'inp', 'cls'] as $metric) {
                if (empty($values[$metric])) continue;
                $p75 = $this->p75($values[$metric]);
                $entry[$metric] = $metric === 'cls' ? round($p75, 3) : (int)round($p75);
                $entry[$metric . '_rating'] = $this->rating($metric, $p75);
                $entry['samples'] += count($values[$metric]);
            }
            $devices[$device] = $entry;
        }

        $slow = [];
        foreach ($byPage as $page => $values) {
            if (count($values) < 3) continue;
            $p75 = $this->p75($values);
            $slow[] = ['page' => $page, 'lcp_p75' => (int)round($p75), 'rating' => $this->rating('lcp', $p75), 'samples' => count($values)];
        }
        usort($slow, static fn($a, $b) => $b['lcp_p75'] <=> $a['lcp_p75']);
        $slow = array_slice($slow, 0, 5);

        return [
            'days' => $days,
            'samples' => count($rows),
            'pages' => count($pages),
            'last_seen' => $lastSeen,
            'metrics' => $metrics,
            'by_device' => $devices,
            'top_slow' => $slow,
            'thresholds' => [
                'lcp' => [2500, 4000],
                'inp' => [200, 500],
                'cls' => [0.1, 0.25],
                'ttfb' => [800, 1800],
                'fcp' => [1800, 3000],
            ],
            'note' => 'Datos de usuarios reales con consentimiento. INP y CLS son aproximaciones del navegador; no sustituyen CrUX.',
        ];
    }

    private function p75(array $values): float
    {
        if (!$values) return 0.0;
        sort($values, SORT_NUMERIC);
        $n = count($values);
        $index = (int)ceil(0.75 * $n) - 1;
        $index = max(0, min($n - 1, $index));
        return (float)$values[$index];
    }

    private function rating(string $metric, float $value): string
    {
        $thresholds = [
            'lcp' => [2500, 4000],
            'inp' => [200, 500],
            'cls' => [0.1, 0.25],
            'ttfb' => [800, 1800],
            'fcp' => [1800, 3000],
        ];
        [$good, $poor] = $thresholds[$metric] ?? [0, 0];
        if ($value <= $good) return 'good';
        if ($value <= $poor) return 'needs-improvement';
        return 'poor';
    }
}
