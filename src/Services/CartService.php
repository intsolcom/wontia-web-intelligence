<?php
namespace App\Services;

use App\Core\Config;
use App\Core\Database;

/**
 * CartService — validación y RE-COTIZACIÓN server-side del carrito (seguridad).
 * NUNCA confía en el precio del cliente: resuelve precio/disponibilidad reales
 * desde store_products (DB) o el catálogo del tema (catalog.json). Multi-tenant.
 */
class CartService
{
    public const MAX_ITEMS = 50;
    public const MAX_QTY = 99;

    public function validate(array $items): array
    {
        $catalog = null;
        $lines = [];
        $subtotal = 0;
        $qtyTotal = 0;
        $issues = 0;

        foreach (array_slice($items, 0, self::MAX_ITEMS) as $it) {
            if (!is_array($it)) continue;
            $ref = trim((string)($it['ref'] ?? ($it['handle'] ?? ($it['slug'] ?? ''))));
            if ($ref === '') continue;
            $qty = max(1, min(self::MAX_QTY, (int)($it['qty'] ?? 1)));
            $r = $this->resolve($ref, $catalog);
            if ($r === null) {
                $lines[] = ['ref' => $ref, 'title' => '', 'image' => '', 'price_cents' => 0, 'qty' => $qty, 'line_total_cents' => 0, 'available' => false, 'stock' => 0, 'issue' => 'not_found'];
                $issues++;
                continue;
            }
            $clientPrice = isset($it['price_cents']) ? (int)$it['price_cents'] : 0;
            $issue = 'ok';
            if (!$r['available']) $issue = 'out_of_stock';
            elseif ($clientPrice > 0 && $clientPrice !== $r['price_cents']) $issue = 'price_changed';
            if ($issue !== 'ok') $issues++;
            $line = $r['price_cents'] * $qty;
            $subtotal += $line;
            $qtyTotal += $qty;
            $lines[] = [
                'ref' => $ref, 'title' => $r['title'], 'image' => $r['image'],
                'price_cents' => $r['price_cents'], 'qty' => $qty, 'line_total_cents' => $line,
                'available' => $r['available'], 'stock' => $r['stock'], 'issue' => $issue,
            ];
        }

        return ['items' => $lines, 'subtotal_cents' => $subtotal, 'count' => count($lines), 'qty_total' => $qtyTotal, 'issues' => $issues];
    }

    private function resolve(string $ref, ?array &$catalog): ?array
    {
        // 1) store_products (tienda nativa)
        try {
            $st = Database::instance()->prepare("SELECT name, slug, price_cents, stock, track_stock, status, images FROM store_products WHERE site_id = @site_id AND (slug = ? OR id = ?) LIMIT 1");
            $st->execute([$ref, (int)$ref]);
            $p = $st->fetch();
            if ($p) {
                $imgs = json_decode((string)($p['images'] ?? ''), true) ?: [];
                $avail = ($p['status'] === 'active') && ((int)$p['track_stock'] === 0 || (int)$p['stock'] > 0);
                return ['title' => (string)$p['name'], 'image' => (string)($imgs[0] ?? ''), 'price_cents' => (int)$p['price_cents'], 'stock' => (int)$p['stock'], 'available' => $avail];
            }
        } catch (\Throwable $e) {
        }

        // 2) catálogo del tema activo (catalog.json)
        if ($catalog === null) $catalog = $this->catalogIndex();
        if (isset($catalog[$ref])) {
            $p = $catalog[$ref];
            $v = $p['variants'][0] ?? [];
            return [
                'title' => (string)($p['title'] ?? ''),
                'image' => (string)($p['images'][0]['src'] ?? ($p['image'] ?? '')),
                'price_cents' => (int)round(((float)($v['price'] ?? 0)) * 100),
                'stock' => 0,
                'available' => !empty($v['available']),
            ];
        }
        return null;
    }

    private function catalogIndex(): array
    {
        $theme = 'default';
        try {
            $theme = (string)(Database::instance()->query("SELECT theme FROM sites WHERE id = @site_id")->fetchColumn() ?: 'default');
        } catch (\Throwable $e) {
        }
        $theme = preg_replace('/[^a-z0-9_-]/i', '', $theme) ?: 'default';
        $file = dirname(__DIR__, 2) . '/templates/themes/' . $theme . '/catalog.json';
        if (!is_file($file)) return [];
        $c = json_decode((string)@file_get_contents($file), true) ?: [];
        $idx = [];
        foreach (($c['products'] ?? []) as $p) {
            $h = (string)($p['handle'] ?? '');
            if ($h !== '') $idx[$h] = $p;
        }
        return $idx;
    }
}
