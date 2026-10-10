<?php
namespace App\Services;

use App\Core\Config;
use App\Core\Database;

/**
 * SearchAnalyticsService — analítica de búsquedas por sitio.
 * Registra la consulta (texto + filtros + nº de resultados) para alimentar a TIA
 * (sinónimos, "sin resultados") y mostrar tendencias. Multi-tenant (@site_id).
 */
class SearchAnalyticsService
{
    private static bool $ensured = false;

    public function ensureTables(): array
    {
        if (self::$ensured) return ['ok' => true, 'cached' => true];
        Database::instance()->exec("CREATE TABLE IF NOT EXISTS store_search_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL DEFAULT 1,
            q VARCHAR(200) NULL,
            filters JSON NULL,
            results INT NOT NULL DEFAULT 0,
            ip_hash CHAR(32) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_site_created (site_id, created_at),
            KEY idx_q (site_id, q)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        self::$ensured = true;
        return ['ok' => true, 'cached' => false];
    }

    public function log(string $q, array $filters, int $results): void
    {
        $this->ensureTables();
        $q = trim($q);
        $filters = array_filter($filters, fn($v) => $v !== null && $v !== '' && $v !== false && $v !== 0);
        if ($q === '' && empty($filters)) return;
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        $hash = $ip !== '' ? substr(hash('sha256', $ip . '|' . (string)Config::get('JWT_SECRET', 'wwi')), 0, 32) : null;
        try {
            Database::instance()->prepare('INSERT INTO store_search_log (site_id, q, filters, results, ip_hash) VALUES (@site_id, ?, ?, ?, ?)')
                ->execute([substr($q, 0, 200), $filters ? json_encode($filters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null, $results, $hash]);
        } catch (\Throwable $e) {
        }
    }

    public function top(int $limit = 20): array
    {
        $this->ensureTables();
        $limit = max(1, min(100, $limit));
        $st = Database::instance()->prepare("SELECT q, COUNT(*) AS hits, ROUND(AVG(results)) AS avg_results FROM store_search_log WHERE site_id = @site_id AND q <> '' GROUP BY q ORDER BY hits DESC LIMIT " . $limit);
        $st->execute();
        return $st->fetchAll();
    }

    public function zeroResults(int $limit = 20): array
    {
        $this->ensureTables();
        $limit = max(1, min(100, $limit));
        $st = Database::instance()->prepare("SELECT q, COUNT(*) AS hits FROM store_search_log WHERE site_id = @site_id AND q <> '' AND results = 0 GROUP BY q ORDER BY hits DESC LIMIT " . $limit);
        $st->execute();
        return $st->fetchAll();
    }

    public function recent(int $limit = 50): array
    {
        $this->ensureTables();
        $limit = max(1, min(200, $limit));
        $st = Database::instance()->prepare("SELECT q, filters, results, created_at FROM store_search_log WHERE site_id = @site_id ORDER BY id DESC LIMIT " . $limit);
        $st->execute();
        return $st->fetchAll();
    }
}
