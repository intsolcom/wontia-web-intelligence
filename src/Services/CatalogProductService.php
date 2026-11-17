<?php
namespace App\Services;

use App\Core\Database;

class CatalogProductService
{
    public function syncIfNeeded(): array
    {
        $catalog = $this->catalog();
        if (!$catalog) return ['ok' => false, 'imported' => 0, 'message' => 'Catálogo no disponible.'];
        $mtime = (int)(@filemtime($this->path()) ?: 0);
        $key = 'sonika_catalog_import_mtime';
        try {
            $st = Database::instance()->prepare('SELECT `value` FROM settings WHERE site_id = @site_id AND `key` = ? LIMIT 1');
            $st->execute([$key]);
            if ((string)$st->fetchColumn() === (string)$mtime) return ['ok' => true, 'imported' => 0, 'cached' => true];
        } catch (\Throwable $e) {
        }
        $result = $this->sync();
        if (!empty($result['ok'])) {
            try {
                Database::instance()->prepare("INSERT INTO settings (site_id, `key`, `value`) VALUES (@site_id, ?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")
                    ->execute([$key, (string)$mtime]);
            } catch (\Throwable $e) {
            }
        }
        return $result;
    }

    public function sync(): array
    {
        $products = $this->catalog();
        if (!$products) return ['ok' => false, 'imported' => 0, 'message' => 'Catálogo no disponible.'];
        (new StoreService())->ensureTables();
        $db = Database::instance();
        $now = date('Y-m-d H:i:s');
        $existing = [];
        $skuIndex = [];
        try {
            $st = $db->query('SELECT id, slug, catalog_handle, sku, vendor, product_type FROM store_products WHERE site_id = @site_id');
            foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $existing[(string)$r['slug']] = $r;
                $ch = trim((string)($r['catalog_handle'] ?? ''));
                if ($ch !== '' && !isset($existing[$ch])) $existing[$ch] = $r;
                $sku = strtoupper(trim((string)($r['sku'] ?? '')));
                if ($sku !== '') $skuIndex[$sku][] = $r;
            }
        } catch (\Throwable $e) {
            return ['ok' => false, 'imported' => 0, 'message' => $e->getMessage()];
        }
        $inserted = 0;
        $db->beginTransaction();
        try {
            $insert = $db->prepare("INSERT INTO store_products (site_id, category_id, name, slug, description, short_description, price_cents, compare_price_cents, currency, sku, stock, track_stock, weight_grams, images, tags, source_tags, status, is_featured, seo_title, seo_description, sort_order, vendor, product_type, catalog_handle, source, catalog_synced_at) VALUES (@site_id, NULL, ?, ?, ?, ?, ?, ?, 'COP', ?, ?, 0, 0, ?, ?, ?, 'active', 0, ?, ?, ?, ?, ?, ?, 'catalog', ?)");
            foreach ($products as $p) {
                $handle = trim((string)($p['handle'] ?? ''));
                if ($handle === '') continue;
                $existingRow = $existing[$handle] ?? null;
                if (!$existingRow) {
                    $feedSku = strtoupper(trim((string)(($p['variants'] ?? [])[0]['sku'] ?? '')));
                    if ($feedSku !== '' && isset($skuIndex[$feedSku]) && count($skuIndex[$feedSku]) === 1) {
                        $existingRow = $skuIndex[$feedSku][0];
                        $existing[$handle] = $existingRow;
                    }
                }
                if ($existingRow) {
                    $row = $existingRow;
                    $tagsJson = json_encode(array_values(array_filter(array_map('strval', (array)($p['tags'] ?? [])))), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $db->prepare('UPDATE store_products SET catalog_handle = ?, vendor = COALESCE(NULLIF(vendor, ""), ?), product_type = COALESCE(NULLIF(product_type, ""), ?), source_tags = ?, catalog_synced_at = ? WHERE id = ? AND site_id = @site_id')
                        ->execute([$handle, (string)($p['vendor'] ?? ''), (string)($p['product_type'] ?? ''), $tagsJson, $now, (int)$row['id']]);
                    continue;
                }
                $variant = (array)(($p['variants'] ?? [])[0] ?? []);
                $price = min(2147483647, max(0, (int)round(((float)($variant['price'] ?? 0)) * 100)));
                $compare = min(2147483647, max(0, (int)round(((float)($variant['compare_at_price'] ?? 0)) * 100)));
                $images = [];
                foreach ((array)($p['images'] ?? []) as $img) {
                    $src = is_array($img) ? (string)($img['src'] ?? '') : (string)$img;
                    if ($src !== '') $images[] = $src;
                }
                $tags = array_values(array_filter(array_map('strval', (array)($p['tags'] ?? []))));
                $title = trim((string)($p['title'] ?? $handle));
                $body = (string)($p['body_html'] ?? '');
                $short = trim(strip_tags($body));
                $short = mb_substr($short, 0, 500);
                $stock = !empty($variant['available']) ? 1 : 0;
                $insert->execute([
                    $title, $handle, $body, $short, $price, $compare, (string)($variant['sku'] ?? ''),
                    $stock, json_encode($images, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    json_encode($tags, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    json_encode($tags, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    mb_substr($title, 0, 255), $short, 0,
                    (string)($p['vendor'] ?? ''), (string)($p['product_type'] ?? ''), $handle, $now,
                ]);
                $inserted++;
            }
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['ok' => false, 'imported' => $inserted, 'message' => $e->getMessage()];
        }
        return ['ok' => true, 'imported' => $inserted, 'total' => count($products)];
    }

    private function path(): string
    {
        $root = defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 2);
        return $root . '/public/assets/uploads/_catalog/catalog.json';
    }

    private function catalog(): array
    {
        $path = $this->path();
        if (!is_file($path)) return [];
        $d = json_decode((string)@file_get_contents($path), true);
        return is_array($d) && !empty($d['products']) ? (array)$d['products'] : [];
    }
}
