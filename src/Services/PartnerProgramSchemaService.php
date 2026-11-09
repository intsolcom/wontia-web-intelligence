<?php
namespace App\Services;

use App\Core\Database;

/**
 * PartnerProgramSchemaService — migración del programa único SONIKA Partners.
 *
 * - profile_category es una categoría INTERNA (artist, influencer, natural).
 * - No cambia comisión, contrato ni obligaciones.
 * - Añade los campos necesarios para Puntos SONIKA y recálculo mensual.
 */
class PartnerProgramSchemaService
{
    public const VERSION = 3;
    private const SETTING = 'sonika_partners_schema_version';
    private static bool $ensured = false;

    public static function ensure(bool $force = false): array
    {
        if (self::$ensured && !$force) return ['ok' => true, 'applied' => [], 'cached' => true];
        $db = Database::instance();
        if (!$force) {
            try {
                $st = $db->prepare('SELECT `value` FROM settings WHERE `key` = ? AND site_id = @site_id LIMIT 1');
                $st->execute([self::SETTING]);
                if ((int)$st->fetchColumn() >= self::VERSION) {
                    self::$ensured = true;
                    return ['ok' => true, 'applied' => [], 'cached' => true];
                }
            } catch (\Throwable $e) {
            }
        }
        $applied = [];
        $failed = [];
        $exec = static function (string $sql, string $label) use ($db, &$applied, &$failed): void {
            try { $db->exec($sql); $applied[] = $label; } catch (\Throwable $e) { $failed[] = $label . ': ' . $e->getMessage(); }
        };

        $profileColumns = [
            'profile_category' => "VARCHAR(20) NOT NULL DEFAULT 'natural'",
            'niche' => "VARCHAR(120) NULL",
            'audience_size' => "VARCHAR(60) NULL",
            'platforms' => "JSON NULL",
            'verification_status' => "VARCHAR(24) NOT NULL DEFAULT 'pending'",
            'public_profile_enabled' => "TINYINT NOT NULL DEFAULT 0",
            'payout_method' => "VARCHAR(40) NULL",
            'tax_id' => "VARCHAR(40) NULL",
            'risk_score' => "INT NOT NULL DEFAULT 0",
            'approved_at' => "DATETIME NULL",
            'approved_by' => "VARCHAR(190) NULL",
        ];
        foreach ($profileColumns as $col => $def) {
            $exec("ALTER TABLE affiliate_profiles ADD COLUMN IF NOT EXISTS `$col` $def", "affiliate_profiles.$col");
        }
        $exec("UPDATE affiliate_profiles SET profile_category = 'artist' WHERE profile_category IS NULL OR profile_category = ''", 'backfill:profile_category');

        $commissionColumns = [
            'points' => "INT NOT NULL DEFAULT 0",
            'month_key' => "CHAR(7) NULL",
            'formula_version' => "VARCHAR(24) NULL",
            'outlet_amount' => "DECIMAL(14,2) NOT NULL DEFAULT 0",
            'max_item_price' => "DECIMAL(14,2) NOT NULL DEFAULT 0",
        ];
        foreach ($commissionColumns as $col => $def) {
            $exec("ALTER TABLE affiliate_commissions ADD COLUMN IF NOT EXISTS `$col` $def", "affiliate_commissions.$col");
        }
        $exec("UPDATE affiliate_commissions SET month_key = DATE_FORMAT(created_at, '%Y-%m') WHERE month_key IS NULL OR month_key = ''", 'backfill:month_key');
        try {
            $db->exec('ALTER TABLE affiliate_commissions ADD INDEX idx_aff_month (site_id, affiliate_id, month_key)');
            $applied[] = 'index:aff_commissions_month';
        } catch (\Throwable $e) {
            if (stripos($e->getMessage(), 'duplicate') === false) $failed[] = 'index:aff_commissions_month: ' . $e->getMessage();
        }
        $exec("ALTER TABLE affiliate_contracts ADD COLUMN IF NOT EXISTS profile_scope VARCHAR(30) NOT NULL DEFAULT 'all'", 'affiliate_contracts.profile_scope');

        if (!$failed) {
            try {
                $db->prepare("INSERT INTO settings (site_id, `key`, `value`) VALUES (@site_id, ?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")
                    ->execute([self::SETTING, (string)self::VERSION]);
            } catch (\Throwable $e) {
            }
            self::$ensured = true;
        }
        return ['ok' => !$failed, 'applied' => $applied, 'failed' => $failed, 'cached' => false];
    }
}
