<?php
namespace App\Services;

use App\Core\Database;

/**
 * CartRecoveryService — recuperación de carritos abandonados (multi-tenant).
 * Envía un email al cliente con su carrito guardado si no ha comprado en X horas.
 */
class CartRecoveryService
{
    private static bool $ensured = false;

    public function ensureTables(): void
    {
        if (self::$ensured) return;
        try {
            $st = Database::instance()->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_carts' AND COLUMN_NAME = 'last_reminder_at'");
            $st->execute();
            if ((int)$st->fetchColumn() === 0) Database::instance()->exec("ALTER TABLE store_carts ADD COLUMN last_reminder_at DATETIME NULL");
        } catch (\Throwable $e) {
        }
        self::$ensured = true;
    }

    private function setting(string $k, string $d = ''): string
    {
        try {
            $st = Database::instance()->prepare("SELECT `value` FROM settings WHERE site_id = @site_id AND `key` = ? LIMIT 1");
            $st->execute([$k]);
            $v = $st->fetchColumn();
            return ($v === false || $v === null) ? $d : (string)$v;
        } catch (\Throwable $e) {
            return $d;
        }
    }

    public function runIfDue(int $minutes = 60): array
    {
        $dir = dirname(__DIR__, 2) . '/cache/state';
        if (!is_dir($dir)) @mkdir($dir, 0770, true);
        $f = $dir . '/cart_recovery_' . (int)\App\Core\Config::get('SITE_ID', '1') . '.json';
        $now = time();
        if (is_file($f)) {
            $p = json_decode((string)@file_get_contents($f), true);
            if (is_array($p) && $now - (int)($p['t'] ?? 0) < $minutes * 60) return ['ok' => true, 'skipped' => true];
        }
        @file_put_contents($f, json_encode(['t' => $now]), LOCK_EX);
        return $this->run();
    }

    public function run(): array
    {
        $this->ensureTables();
        if ($this->setting('store_recovery_enabled', '0') !== '1') return ['ok' => true, 'skipped' => 'disabled'];
        $hours = max(1, min(720, (int)$this->setting('store_recovery_hours', '24')));
        $db = Database::instance();
        $sql = "SELECT c.id, c.customer_id, c.items, c.updated_at, cu.email, cu.name, s.`value` AS site_name
                FROM store_carts c
                JOIN store_customers cu ON cu.id = c.customer_id AND cu.site_id = c.site_id
                LEFT JOIN settings s ON s.site_id = c.site_id AND s.`key` = 'site_name'
                WHERE c.site_id = @site_id AND c.items IS NOT NULL AND c.items NOT IN ('', '[]', 'null')
                  AND c.updated_at <= (NOW() - INTERVAL :h HOUR)
                  AND (c.last_reminder_at IS NULL OR c.last_reminder_at <= (NOW() - INTERVAL :h2 HOUR))
                ORDER BY c.updated_at ASC LIMIT 30";
        $st = $db->prepare($sql);
        $st->execute(['h' => $hours, 'h2' => $hours]);
        $rows = $st->fetchAll();
        $mail = new EmailService();
        $sent = 0;
        foreach ($rows as $r) {
            $items = json_decode((string)$r['items'], true);
            if (!is_array($items) || !$items) continue;
            $email = trim((string)$r['email']);
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
            $site = trim((string)($r['site_name'] ?? '')) ?: 'Tu tienda';
            $base = rtrim((string)\App\Core\Config::get('APP_URL', ''), '/');
            $lines = '';
            foreach (array_slice($items, 0, 20) as $it) {
                if (!is_array($it)) continue;
                $lines .= '<tr><td style="padding:6px 0">' . htmlspecialchars((string)($it['title'] ?? $it['handle'] ?? '')) . ' × ' . (int)($it['qty'] ?? 1)
                    . '</td><td style="text-align:right">$' . number_format(((float)($it['price'] ?? 0)) * ((int)($it['qty'] ?? 1)), 0, ',', '.') . '</td></tr>';
            }
            $html = '<div style="font-family:Arial,sans-serif;color:#222;max-width:520px"><h2 style="margin:0 0 8px">' . htmlspecialchars($site) . '</h2>'
                . '<p>Hola' . ($r['name'] ? ' ' . htmlspecialchars((string)$r['name']) : '') . ', dejaste productos en tu carrito. ¡Todavía están disponibles!</p>'
                . '<table style="width:100%;font-size:13px;border-collapse:collapse">' . $lines . '</table>'
                . '<p style="margin-top:16px"><a href="' . htmlspecialchars($base . '/carrito') . '" style="background:#111;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none">Volver a mi carrito</a></p>'
                . '<p style="color:#999;font-size:12px">Si ya compraste, ignora este correo.</p></div>';
            if ($mail->sendHtml($email, 'Tu carrito te espera', $html)) {
                $db->prepare("UPDATE store_carts SET last_reminder_at = NOW() WHERE id = ?")->execute([(int)$r['id']]);
                $sent++;
            }
        }
        return ['ok' => true, 'considered' => count($rows), 'sent' => $sent];
    }
}
