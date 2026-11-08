<?php
namespace App\Services;

use App\Core\Database;

/**
 * ProductContentLabSchemaService — esquema del Content Fix Lab.
 *
 * Guarda trabajos por producto, revisiones (para rollback) y actividad
 * auditable. No modifica descripciones por sí solo: el servicio del laboratorio
 * decide y el admin confirma.
 */
class ProductContentLabSchemaService
{
    public const VERSION = 3;
    private const SETTING = 'product_content_lab_schema_version';
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
            try {
                $db->exec($sql);
                $applied[] = $label;
            } catch (\Throwable $e) {
                $failed[] = $label . ': ' . $e->getMessage();
            }
        };

        $exec("CREATE TABLE IF NOT EXISTS product_content_jobs (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL DEFAULT 1,
            product_id INT NULL,
            handle VARCHAR(255) NOT NULL,
            title VARCHAR(500) NOT NULL DEFAULT '',
            vendor VARCHAR(190) NULL,
            product_type VARCHAR(190) NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'new',
            stage VARCHAR(40) NOT NULL DEFAULT 'pending',
            confidence INT NOT NULL DEFAULT 0,
            source_url VARCHAR(1000) NULL,
            source_domain VARCHAR(190) NULL,
            source_title VARCHAR(500) NULL,
            source_text LONGTEXT NULL,
            source_html LONGTEXT NULL,
            source_specs LONGTEXT NULL,
            source_features LONGTEXT NULL,
            source_videos LONGTEXT NULL,
            candidates LONGTEXT NULL,
            evidence LONGTEXT NULL,
            generated_json LONGTEXT NULL,
            generated_html LONGTEXT NULL,
            generated_preview LONGTEXT NULL,
            generated_score INT NOT NULL DEFAULT 0,
            previous_description LONGTEXT NULL,
            previous_short VARCHAR(500) NULL,
            miche_data LONGTEXT NULL,
            miche_score INT NOT NULL DEFAULT 0,
            miche_recommendation VARCHAR(20) NULL,
            video_status VARCHAR(32) NOT NULL DEFAULT 'none',
            video_source_url VARCHAR(1000) NULL,
            video_file VARCHAR(500) NULL,
            youtube_id VARCHAR(32) NULL,
            youtube_url VARCHAR(500) NULL,
            rights_confirmed TINYINT NOT NULL DEFAULT 0,
            ai_model VARCHAR(120) NULL,
            ai_provider VARCHAR(120) NULL,
            tokens_used INT NOT NULL DEFAULT 0,
            error TEXT NULL,
            created_by VARCHAR(190) NULL,
            applied_by VARCHAR(190) NULL,
            applied_at DATETIME NULL,
            live_url VARCHAR(500) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uk_site_handle (site_id, handle),
            KEY idx_site_status (site_id, status),
            KEY idx_site_product (site_id, product_id),
            KEY idx_site_updated (site_id, updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'table:product_content_jobs');

        $exec("CREATE TABLE IF NOT EXISTS product_content_revisions (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL DEFAULT 1,
            job_id BIGINT NULL,
            product_id INT NULL,
            handle VARCHAR(255) NOT NULL,
            previous_description LONGTEXT NULL,
            previous_short VARCHAR(500) NULL,
            new_description LONGTEXT NULL,
            new_short VARCHAR(500) NULL,
            source VARCHAR(40) NOT NULL DEFAULT 'lab',
            created_by VARCHAR(190) NULL,
            created_at DATETIME NOT NULL,
            KEY idx_site_handle (site_id, handle),
            KEY idx_site_job (site_id, job_id),
            KEY idx_site_created (site_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'table:product_content_revisions');

        $exec("CREATE TABLE IF NOT EXISTS product_content_activity (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL DEFAULT 1,
            job_id BIGINT NULL,
            product_id INT NULL,
            handle VARCHAR(255) NULL,
            action VARCHAR(64) NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'ok',
            detail TEXT NULL,
            actor VARCHAR(190) NULL,
            created_at DATETIME NOT NULL,
            KEY idx_site_created (site_id, created_at),
            KEY idx_site_job (site_id, job_id),
            KEY idx_site_handle (site_id, handle)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'table:product_content_activity');

        // Alinear la colación con store_products para que los JOIN por handle no fallen.
        $exec("ALTER TABLE product_content_jobs CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci", 'collation:product_content_jobs');
        $exec("ALTER TABLE product_content_revisions CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci", 'collation:product_content_revisions');
        $exec("ALTER TABLE product_content_activity CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci", 'collation:product_content_activity');

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
