<?php
namespace App\Services;

use App\Core\Database;

/**
 * BannerStatsService — métricas del brick Banner (impresiones/clics) y A/B.
 * Multi-tenant (@site_id). Idempotente por (site, uid, variante).
 */
class BannerStatsService
{
    private static bool $ensured = false;

    public function ensureTables(): array
    {
        if (self::$ensured) return ['ok' => true, 'cached' => true];
        Database::instance()->exec("CREATE TABLE IF NOT EXISTS wwi_banner_stats (
            id INT AUTO_INCREMENT PRIMARY KEY, site_id INT NOT NULL DEFAULT 1,
            banner_uid VARCHAR(64) NOT NULL, variant VARCHAR(8) NOT NULL DEFAULT 'a',
            impressions INT NOT NULL DEFAULT 0, clicks INT NOT NULL DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_bn (site_id, banner_uid, variant)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        self::$ensured = true;
        return ['ok' => true, 'cached' => false];
    }

    public function track(string $uid, string $type = 'impression', string $variant = 'a'): array
    {
        $this->ensureTables();
        $uid = substr((string)preg_replace('/[^a-zA-Z0-9_-]/', '', $uid), 0, 64);
        if ($uid === '') return ['ok' => false];
        $variant = in_array($variant, ['a', 'b'], true) ? $variant : 'a';
        $col = $type === 'click' ? 'clicks' : 'impressions';
        Database::instance()->prepare("INSERT INTO wwi_banner_stats (site_id, banner_uid, variant, `$col`) VALUES (@site_id, ?, ?, 1) ON DUPLICATE KEY UPDATE `$col` = `$col` + 1")
            ->execute([$uid, $variant]);
        return ['ok' => true];
    }

    public function list(int $limit = 300): array
    {
        $this->ensureTables();
        $limit = max(1, min(500, $limit));
        $st = Database::instance()->prepare("SELECT banner_uid, variant, impressions, clicks, updated_at FROM wwi_banner_stats WHERE site_id = @site_id ORDER BY impressions DESC LIMIT $limit");
        $st->execute();
        return $st->fetchAll();
    }

    public function summary(): array
    {
        $this->ensureTables();
        $st = Database::instance()->prepare("SELECT COUNT(*) c, COALESCE(SUM(impressions), 0) imp, COALESCE(SUM(clicks), 0) clk FROM wwi_banner_stats WHERE site_id = @site_id");
        $st->execute();
        $r = $st->fetch() ?: [];
        $imp = (int)($r['imp'] ?? 0);
        $clk = (int)($r['clk'] ?? 0);
        return ['rows' => (int)($r['c'] ?? 0), 'impressions' => $imp, 'clicks' => $clk, 'ctr' => $imp ? round($clk * 100 / $imp, 2) : 0];
    }

    public function clear(): array
    {
        $this->ensureTables();
        Database::instance()->prepare("DELETE FROM wwi_banner_stats WHERE site_id = @site_id")->execute();
        return ['ok' => true];
    }
}
