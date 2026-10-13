<?php
namespace App\Services;

use App\Core\Config;
use App\Core\Database;

/**
 * FavoritesAlertService — avisa al cliente cuando un favorito baja de precio
 * o vuelve a estar disponible. Multi-tenant (@site_id).
 *
 * Resuelve el valor actual del producto por, en este orden:
 *   1) catálogo del tema activo (templates/themes/<theme>/catalog.json) por handle
 *   2) tabla store_products (tiendas nativas) por slug/id
 */
class FavoritesAlertService
{
    public function runIfDue(int $minutes = 360): array
    {
        $dir = ROOT_DIR . '/cache/state';
        if (!is_dir($dir)) @mkdir($dir, 0770, true);
        $f = $dir . '/fav_alerts_' . (int)Config::get('SITE_ID', '1') . '.json';
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
        (new CustomerService())->ensureTables();
        $db = Database::instance();
        $st = $db->prepare('SELECT * FROM store_customer_favorites WHERE site_id = @site_id AND (alert_price = 1 OR alert_stock = 1)');
        $st->execute();
        $favs = $st->fetchAll();
        if (!$favs) return ['ok' => true, 'checked' => 0, 'notified' => 0];

        $catalog = $this->catalogIndex();
        $mail = new EmailService();
        $checked = 0;
        $notified = 0;

        foreach ($favs as $fav) {
            $cur = $this->resolve((string)$fav['product_ref'], $catalog, $db);
            if ($cur === null) continue;
            $checked++;

            $stored = (int)$fav['price_cents'];

            if (!empty($fav['alert_price']) && $cur['price_cents'] > 0) {
                if ($stored > 0 && $cur['price_cents'] < $stored) {
                    if ($this->notifyPriceDrop($mail, $fav, $cur, $stored)) {
                        $db->prepare('UPDATE store_customer_favorites SET price_cents = ?, notified_price_at = NOW() WHERE id = ?')
                           ->execute([$cur['price_cents'], $fav['id']]);
                        $notified++;
                    }
                }
                $db->prepare('UPDATE store_customer_favorites SET last_price_cents = ? WHERE id = ?')->execute([$cur['price_cents'], $fav['id']]);
            }

            if (!empty($fav['alert_stock']) && $cur['available'] !== null) {
                $was = ($fav['in_stock'] === null) ? null : (int)$fav['in_stock'];
                if ($was === 0 && $cur['available']) {
                    if ($this->notifyBackInStock($mail, $fav, $cur)) {
                        $db->prepare('UPDATE store_customer_favorites SET notified_stock_at = NOW() WHERE id = ?')->execute([$fav['id']]);
                        $notified++;
                    }
                }
                $db->prepare('UPDATE store_customer_favorites SET in_stock = ? WHERE id = ?')->execute([$cur['available'] ? 1 : 0, $fav['id']]);
            }
        }

        return ['ok' => true, 'checked' => $checked, 'notified' => $notified];
    }

    private function activeTheme(): string
    {
        try {
            $st = Database::instance()->prepare('SELECT theme FROM sites WHERE id = @site_id LIMIT 1');
            $st->execute();
            return (string)($st->fetchColumn() ?: '');
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function catalogIndex(): array
    {
        $theme = $this->activeTheme();
        if ($theme === '') return [];
        $file = ROOT_DIR . '/templates/themes/' . $theme . '/catalog.json';
        if (!is_file($file)) return [];
        $c = json_decode((string)@file_get_contents($file), true) ?: [];
        $idx = [];
        foreach (($c['products'] ?? []) as $p) {
            $h = (string)($p['handle'] ?? '');
            if ($h !== '') $idx[$h] = $p;
        }
        return $idx;
    }

    private function resolve(string $ref, array $catalog, $db): ?array
    {
        if (isset($catalog[$ref])) {
            $p = $catalog[$ref];
            $v = $p['variants'][0] ?? [];
            return [
                'price_cents' => (int)round(((float)($v['price'] ?? 0)) * 100),
                'available' => !empty($v['available']),
                'title' => (string)($p['title'] ?? ''),
                'url' => '/producto/' . rawurlencode($ref),
            ];
        }
        try {
            $st = $db->prepare('SELECT price_cents, stock, name, slug FROM store_products WHERE site_id = @site_id AND (slug = ? OR id = ?) LIMIT 1');
            $st->execute([$ref, (int)$ref]);
            $row = $st->fetch();
            if ($row) {
                return [
                    'price_cents' => (int)$row['price_cents'],
                    'available' => ((int)$row['stock'] > 0),
                    'title' => (string)$row['name'],
                    'url' => '/producto/' . rawurlencode((string)$row['slug']),
                ];
            }
        } catch (\Throwable $e) {
        }
        return null;
    }

    private function resolveCustomerEmail(array $fav): string
    {
        try {
            $st = Database::instance()->prepare('SELECT email FROM store_customers WHERE id = ? AND site_id = @site_id LIMIT 1');
            $st->execute([(int)$fav['customer_id']]);
            return (string)($st->fetchColumn() ?: '');
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function notifyPriceDrop(EmailService $mail, array $fav, array $cur, int $oldCents): bool
    {
        $to = $this->resolveCustomerEmail($fav);
        if ($to === '' || !$mail->configured()) return false;
        $name = (string)($fav['title'] ?: $cur['title'] ?: 'tu favorito');
        $site = $this->siteName();
        $old = '$' . number_format($oldCents / 100, 0, ',', '.');
        $now = '$' . number_format($cur['price_cents'] / 100, 0, ',', '.');
        $url = $this->absUrl($cur['url']);
        $html = $this->wrap($site,
            '<p>Buenas noticias: el precio de <strong>' . htmlspecialchars($name) . '</strong> bajó.</p>'
            . '<p style="font-size:20px"><s style="color:#999">' . $old . '</s> &nbsp;<strong style="color:#16A34A">' . $now . '</strong></p>'
            . '<p><a href="' . htmlspecialchars($url) . '" style="background:#111;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none">Ver producto</a></p>'
        );
        return $mail->sendHtml($to, 'Bajó de precio: ' . $name, $html);
    }

    private function notifyBackInStock(EmailService $mail, array $fav, array $cur): bool
    {
        $to = $this->resolveCustomerEmail($fav);
        if ($to === '' || !$mail->configured()) return false;
        $name = (string)($fav['title'] ?: $cur['title'] ?: 'tu favorito');
        $site = $this->siteName();
        $url = $this->absUrl($cur['url']);
        $html = $this->wrap($site,
            '<p><strong>' . htmlspecialchars($name) . '</strong> volvió a estar disponible.</p>'
            . '<p><a href="' . htmlspecialchars($url) . '" style="background:#111;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none">Comprar ahora</a></p>'
        );
        return $mail->sendHtml($to, 'Volvió a estar disponible: ' . $name, $html);
    }

    private function absUrl(string $path): string
    {
        $base = rtrim((string)Config::get('APP_URL', ''), '/');
        return $base . '/' . ltrim($path, '/');
    }

    private function siteName(): string
    {
        try {
            $st = Database::instance()->prepare('SELECT `value` FROM settings WHERE site_id = @site_id AND `key` = ? LIMIT 1');
            $st->execute(['site_name']);
            $v = $st->fetchColumn();
            if ($v) return (string)$v;
        } catch (\Throwable $e) {
        }
        return 'SONIKA';
    }

    private function wrap(string $site, string $body): string
    {
        return '<div style="font-family:Arial,Helvetica,sans-serif;color:#222;max-width:520px">'
            . '<h2 style="margin:0 0 12px">' . htmlspecialchars($site) . '</h2>'
            . $body
            . '<p style="color:#999;font-size:12px;margin-top:18px">Recibes este correo porque tienes este producto en tus favoritos.</p>'
            . '</div>';
    }
}
