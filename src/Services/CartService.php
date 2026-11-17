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
                $lines[] = ['ref' => $ref, 'title' => '', 'image' => '', 'price_cents' => 0, 'qty' => $qty, 'line_total_cents' => 0, 'available' => false, 'stock' => 0, 'weight_grams' => 0, 'issue' => 'not_found'];
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
            $rec = is_array($it['rec'] ?? null) ? [
                'source' => (string)($it['rec']['source'] ?? ''),
                'campaign' => (string)($it['rec']['campaign'] ?? ''),
                'slot' => (string)($it['rec']['slot'] ?? ''),
                'reason' => mb_substr((string)($it['rec']['reason'] ?? ''), 0, 160),
            ] : null;
            $lines[] = [
                'ref' => $ref, 'title' => $r['title'], 'image' => $r['image'],
                'price_cents' => $r['price_cents'], 'qty' => $qty, 'line_total_cents' => $line,
                'available' => $r['available'], 'stock' => $r['stock'], 'weight_grams' => (int)($r['weight_grams'] ?? 0), 'issue' => $issue,
                'free_shipping' => !empty($r['free_shipping']), 'tax_exempt' => !empty($r['tax_exempt']),
                'visibility' => (string)($r['visibility'] ?? 'public'),
                'product_id' => (int)($r['product_id'] ?? 0), 'variant_id' => $r['variant_id'] ?? null,
                'sku' => (string)($r['sku'] ?? ''), 'product_ref' => (string)($r['product_ref'] ?? $ref),
                'brand' => (string)($r['brand'] ?? ''), 'category' => (string)($r['category'] ?? ''),
                'rec' => $rec,
            ];
        }

        return ['items' => $lines, 'subtotal_cents' => $subtotal, 'count' => count($lines), 'qty_total' => $qtyTotal, 'issues' => $issues];
    }

    private function resolve(string $ref, ?array &$catalog): ?array
    {
        // 1) store_products (tienda nativa)
        try {
            $st = Database::instance()->prepare("SELECT id, name, slug, sku, vendor, product_type, catalog_handle, price_cents, stock, track_stock, status, images, weight_grams, free_shipping, tax_exempt, visibility, allowed_ips FROM store_products WHERE site_id = @site_id AND (slug = ? OR catalog_handle = ? OR id = ?) LIMIT 1");
            $st->execute([$ref, $ref, (int)$ref]);
            $p = $st->fetch();
            if ($p) {
                if (!IpAccessService::canViewProduct($p)) return null;
                $imgs = json_decode((string)($p['images'] ?? ''), true) ?: [];
                $avail = ($p['status'] === 'active') && ((int)$p['track_stock'] === 0 || (int)$p['stock'] > 0);
                return [
                    'title' => (string)$p['name'], 'image' => (string)($imgs[0] ?? ''), 'price_cents' => (int)$p['price_cents'],
                    'stock' => (int)$p['stock'], 'available' => $avail, 'weight_grams' => (int)($p['weight_grams'] ?? 0),
                    'free_shipping' => (int)($p['free_shipping'] ?? 0) === 1, 'tax_exempt' => (int)($p['tax_exempt'] ?? 0) === 1,
                    'visibility' => (string)($p['visibility'] ?? 'public'),
                    'product_id' => (int)($p['id'] ?? 0), 'variant_id' => null,
                    'sku' => (string)($p['sku'] ?? ''), 'product_ref' => (string)($p['slug'] ?? ''),
                    'brand' => (string)($p['vendor'] ?? ''), 'category' => (string)($p['product_type'] ?? ''),
                ];
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
                'weight_grams' => (int)($p['weight_grams'] ?? ($v['weight_grams'] ?? 0)),
                'free_shipping' => false, 'tax_exempt' => false, 'visibility' => 'public',
                'product_id' => 0, 'variant_id' => isset($v['id']) ? (int)$v['id'] : null,
                'sku' => (string)($v['sku'] ?? ($p['sku'] ?? '')), 'product_ref' => $ref,
                'brand' => (string)($p['vendor'] ?? ($p['brand'] ?? '')), 'category' => (string)($p['product_type'] ?? ($p['type'] ?? '')),
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
