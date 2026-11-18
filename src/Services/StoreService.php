<?php
namespace App\Services;

use App\Core\Database;

class StoreService
{
    public const TABLES = ['store_categories', 'store_products', 'store_variants', 'store_customers', 'store_orders', 'store_order_items', 'store_shipping_zones', 'store_order_events'];

    public const SETTING_KEYS = [
        'store_enabled', 'store_currency', 'store_payment_methods', 'store_wompi_public_key',
        'store_wompi_integrity_key', 'store_wompi_events_key', 'store_notify_email',
        'store_shipping_note', 'store_min_order_cents', 'store_whatsapp', 'store_terms_url',
        'store_max_orders_hour_ip', 'store_max_orders_hour_email',
        'store_reviews_auto_approve', 'store_reviews_require_purchase',
        'store_loyalty_enabled', 'store_loyalty_points_per_1000', 'store_loyalty_redeem_cents',
        'store_currencies', 'store_payment_logos',
        'store_catalog_sort', 'store_catalog_mode', 'store_catalog_brands',
        'store_catalog_hide_out_of_stock', 'store_catalog_show_tags',
        'store_oos_disable_add',
        'store_show_sales', 'store_show_sales_categories', 'store_payment_logo_methods',
        'store_recommendations', 'store_social_proof', 'store_impact_note',
        'store_recovery_enabled', 'store_recovery_hours', 'store_reservation_minutes',
        'store_auto_archive_enabled', 'store_archive_failed_days', 'store_archive_test_days',
        'partner_contract_reminder_enabled', 'partner_contract_reminder_days',
        'gift_card_enabled', 'gift_card_min_cents', 'gift_card_max_cents',
        'gift_card_expiry_days', 'gift_card_hold_hours', 'gift_card_terms_url',
        'store_checkout_methods', 'store_checkout_default_method',
        'product_content_v2',
    ];

    public const PAYMENT_METHODS = ['wompi', 'cod', 'manual'];

    public function ensureTables(): array
    {
        $db = Database::instance();
        $missing = [];
        foreach (self::TABLES as $t) {
            try {
                $db->query("SELECT 1 FROM $t LIMIT 1");
            } catch (\Exception $e) {
                $missing[] = $t;
            }
        }
        if (!$missing) return $this->migrate(['ok' => true, 'created' => 0, 'missing' => []]);

        $path = ROOT_DIR . '/install/store.sql';
        if (!file_exists($path)) return ['ok' => false, 'message' => 'Schema not found', 'missing' => $missing];

        $sql = preg_replace('/^\s*--.*$/m', '', (string)file_get_contents($path));
        $statements = array_filter(array_map('trim', explode(';', $sql)), fn($s) => $s !== '');
        $created = 0;
        foreach ($statements as $stmt) {
            try {
                $db->exec($stmt . ';');
                $created++;
            } catch (\Exception $e) {
                return ['ok' => false, 'message' => $e->getMessage(), 'missing' => $missing];
            }
        }
        return $this->migrate(['ok' => true, 'created' => $created, 'missing' => $missing]);
    }

    private function migrate(array $result): array
    {
        $db = Database::instance();
        foreach ([
            "ALTER TABLE store_orders ADD COLUMN IF NOT EXISTS ip_hash VARCHAR(64) NULL",
            "ALTER TABLE store_orders ADD COLUMN IF NOT EXISTS affiliate_ref VARCHAR(64) NULL",
            "ALTER TABLE store_orders ADD COLUMN IF NOT EXISTS stock_released TINYINT DEFAULT 0",
            "ALTER TABLE store_orders ADD COLUMN IF NOT EXISTS quote_code VARCHAR(40) COLLATE utf8mb4_unicode_ci NULL",
            "ALTER TABLE store_orders MODIFY quote_code VARCHAR(40) COLLATE utf8mb4_unicode_ci NULL",
            "ALTER TABLE store_products ADD COLUMN IF NOT EXISTS vendor VARCHAR(150) NULL",
            "ALTER TABLE store_products ADD COLUMN IF NOT EXISTS product_type VARCHAR(150) NULL",
            "ALTER TABLE store_products ADD COLUMN IF NOT EXISTS catalog_handle VARCHAR(255) NULL",
            "ALTER TABLE store_products ADD COLUMN IF NOT EXISTS source VARCHAR(20) NOT NULL DEFAULT 'local'",
            "ALTER TABLE store_products ADD COLUMN IF NOT EXISTS catalog_synced_at DATETIME NULL",
            "ALTER TABLE store_products ADD COLUMN IF NOT EXISTS free_shipping TINYINT(1) NOT NULL DEFAULT 0",
            "ALTER TABLE store_products ADD COLUMN IF NOT EXISTS tax_exempt TINYINT(1) NOT NULL DEFAULT 0",
            "ALTER TABLE store_products ADD COLUMN IF NOT EXISTS visibility VARCHAR(20) NOT NULL DEFAULT 'public'",
            "ALTER TABLE store_products ADD COLUMN IF NOT EXISTS allowed_ips TEXT NULL",
            "ALTER TABLE store_orders MODIFY quote_code VARCHAR(40) COLLATE utf8mb4_unicode_ci NULL",
            "ALTER TABLE store_orders ADD INDEX idx_store_orders_quote (site_id, quote_code)",
            "ALTER TABLE store_orders ADD COLUMN IF NOT EXISTS shipping_rate_id INT NULL",
            "ALTER TABLE store_orders ADD COLUMN IF NOT EXISTS shipping_department_code CHAR(2) NULL",
            "ALTER TABLE store_orders ADD COLUMN IF NOT EXISTS shipping_municipality_code CHAR(5) NULL",
            "ALTER TABLE store_orders ADD COLUMN IF NOT EXISTS shipping_carrier VARCHAR(100) NULL",
            "ALTER TABLE store_orders ADD COLUMN IF NOT EXISTS shipping_eta_min_days INT NULL",
            "ALTER TABLE store_orders ADD COLUMN IF NOT EXISTS shipping_eta_max_days INT NULL",
            "ALTER TABLE store_orders ADD COLUMN IF NOT EXISTS recommendation_discount_cents INT NOT NULL DEFAULT 0",
            "ALTER TABLE store_orders ADD COLUMN IF NOT EXISTS recommendation_discount_net_cents INT NOT NULL DEFAULT 0",
            "ALTER TABLE store_orders ADD COLUMN IF NOT EXISTS archived_at DATETIME NULL",
            "ALTER TABLE store_orders ADD COLUMN IF NOT EXISTS is_test TINYINT NOT NULL DEFAULT 0",
            "ALTER TABLE store_orders ADD COLUMN IF NOT EXISTS archive_reason VARCHAR(40) NULL",
            "ALTER TABLE store_orders ADD COLUMN IF NOT EXISTS archived_by INT NULL",
            "ALTER TABLE store_orders ADD COLUMN IF NOT EXISTS gift_card_cents BIGINT NOT NULL DEFAULT 0",
            "ALTER TABLE store_orders ADD COLUMN IF NOT EXISTS gift_card_code VARCHAR(32) NULL",
            "ALTER TABLE store_orders ADD INDEX idx_store_orders_archived (site_id, archived_at)",
            "ALTER TABLE store_orders ADD INDEX idx_store_orders_test (site_id, is_test)",
            "ALTER TABLE store_order_items ADD COLUMN IF NOT EXISTS discount_cents INT NOT NULL DEFAULT 0",
            "ALTER TABLE store_order_items ADD COLUMN IF NOT EXISTS discount_net_cents INT NOT NULL DEFAULT 0",
            "ALTER TABLE store_order_items ADD COLUMN IF NOT EXISTS recommendation_discount_cents INT NOT NULL DEFAULT 0",
            "ALTER TABLE store_order_items ADD COLUMN IF NOT EXISTS recommendation_discount_net_cents INT NOT NULL DEFAULT 0",
            "ALTER TABLE store_order_items ADD COLUMN IF NOT EXISTS recommendation_rate DECIMAL(6,4) NULL",
            "ALTER TABLE store_order_items ADD COLUMN IF NOT EXISTS recommendation_source VARCHAR(40) NULL",
            "ALTER TABLE store_order_items ADD COLUMN IF NOT EXISTS sku VARCHAR(120) NULL",
            "ALTER TABLE store_order_items ADD COLUMN IF NOT EXISTS product_ref VARCHAR(255) NULL",
            "ALTER TABLE store_order_items ADD COLUMN IF NOT EXISTS weight_grams INT NOT NULL DEFAULT 0",
            "ALTER TABLE store_orders MODIFY subtotal_cents BIGINT NOT NULL DEFAULT 0",
            "ALTER TABLE store_orders MODIFY subtotal_net_cents BIGINT NOT NULL DEFAULT 0",
            "ALTER TABLE store_orders MODIFY tax_cents BIGINT NOT NULL DEFAULT 0",
            "ALTER TABLE store_orders MODIFY breb_discount_cents BIGINT NOT NULL DEFAULT 0",
            "ALTER TABLE store_orders MODIFY breb_discount_net_cents BIGINT NOT NULL DEFAULT 0",
            "ALTER TABLE store_orders MODIFY shipping_cents BIGINT NOT NULL DEFAULT 0",
            "ALTER TABLE store_orders MODIFY discount_cents BIGINT NOT NULL DEFAULT 0",
            "ALTER TABLE store_orders MODIFY total_cents BIGINT NOT NULL DEFAULT 0",
            "ALTER TABLE store_orders MODIFY recommendation_discount_cents BIGINT NOT NULL DEFAULT 0",
            "ALTER TABLE store_orders MODIFY recommendation_discount_net_cents BIGINT NOT NULL DEFAULT 0",
            "ALTER TABLE store_order_items MODIFY price_cents BIGINT NOT NULL DEFAULT 0",
            "ALTER TABLE store_order_items MODIFY subtotal_cents BIGINT NOT NULL DEFAULT 0",
            "ALTER TABLE store_order_items MODIFY discount_cents BIGINT NOT NULL DEFAULT 0",
            "ALTER TABLE store_order_items MODIFY discount_net_cents BIGINT NOT NULL DEFAULT 0",
            "ALTER TABLE store_order_items MODIFY recommendation_discount_cents BIGINT NOT NULL DEFAULT 0",
            "ALTER TABLE store_order_items MODIFY recommendation_discount_net_cents BIGINT NOT NULL DEFAULT 0",
        ] as $alter) {
            try { $db->exec($alter); } catch (\Exception $e) {}
        }
        return $result;
    }

    public function ready(): bool
    {
        try {
            Database::instance()->query("SELECT 1 FROM store_products LIMIT 1");
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    // ── Settings ──

    public function settings(): array
    {
        $out = [];
        foreach (self::SETTING_KEYS as $k) $out[$k] = '';
        try {
            $db = Database::instance();
            $in = implode(',', array_fill(0, count(self::SETTING_KEYS), '?'));
            $stmt = $db->prepare("SELECT `key`, `value` FROM settings WHERE site_id = @site_id AND `key` IN ($in)");
            $stmt->execute(self::SETTING_KEYS);
            foreach ($stmt->fetchAll() as $row) $out[$row['key']] = (string)$row['value'];
        } catch (\Exception $e) {
        }
        if ($out['store_currency'] === '') $out['store_currency'] = 'COP';
        if ($out['store_payment_methods'] === '') $out['store_payment_methods'] = '["cod"]';
        if ($out['store_auto_archive_enabled'] === '') $out['store_auto_archive_enabled'] = '0';
        if ($out['store_archive_failed_days'] === '') $out['store_archive_failed_days'] = '30';
        if ($out['store_archive_test_days'] === '') $out['store_archive_test_days'] = '7';
        if ($out['partner_contract_reminder_enabled'] === '') $out['partner_contract_reminder_enabled'] = '0';
        if ($out['partner_contract_reminder_days'] === '') $out['partner_contract_reminder_days'] = '3';
        if ($out['gift_card_enabled'] === '') $out['gift_card_enabled'] = '1';
        if ($out['gift_card_min_cents'] === '') $out['gift_card_min_cents'] = '1000000';
        if ($out['gift_card_max_cents'] === '') $out['gift_card_max_cents'] = '200000000';
        if ($out['gift_card_expiry_days'] === '') $out['gift_card_expiry_days'] = '365';
        if ($out['gift_card_hold_hours'] === '') $out['gift_card_hold_hours'] = '24';
        if ($out['gift_card_terms_url'] === '') $out['gift_card_terms_url'] = '/politicas';
        if ($out['store_checkout_methods'] === '') $out['store_checkout_methods'] = '';
        if ($out['store_checkout_default_method'] === '') $out['store_checkout_default_method'] = '';
        return $out;
    }

    public function saveSettings(array $data): void
    {
        $db = Database::instance();
        $stmt = $db->prepare("INSERT INTO settings (site_id, `key`, `value`) VALUES (@site_id, :k, :v) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
        foreach (self::SETTING_KEYS as $k) {
            if (!array_key_exists($k, $data)) continue;
            $v = $data[$k];
            if (is_array($v)) $v = json_encode(array_values($v));
            $stmt->execute(['k' => $k, 'v' => (string)$v]);
        }
    }

    public function paymentMethods(): array
    {
        $s = $this->settings();
        $raw = trim((string)$s['store_payment_methods']);
        // Sin configuración guardada se conserva el comportamiento legado.
        if ($raw === '') return ['cod'];
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) return ['cod'];
        $m = array_values(array_intersect($decoded, self::PAYMENT_METHODS));
        if ($s['store_wompi_public_key'] === '' || $s['store_wompi_integrity_key'] === '') {
            $m = array_values(array_diff($m, ['wompi']));
        }
        // Un arreglo vacío es una decisión explícita: no reactivar Contra entrega.
        return $m;
    }

    public function wompiSignature(string $reference, int $amountCents, string $currency): string
    {
        $s = $this->settings();
        return hash('sha256', $reference . $amountCents . $currency . $s['store_wompi_integrity_key']);
    }

    public function verifyWompiWebhook(array $body): bool
    {
        $s = $this->settings();
        $eventsKey = $s['store_wompi_events_key'] ?: $s['store_wompi_integrity_key'];
        if ($eventsKey === '') return false;
        $props = $body['signature']['properties'] ?? [];
        $checksum = (string)($body['signature']['checksum'] ?? '');
        if (!$props || $checksum === '') return false;
        $concat = '';
        foreach ($props as $path) {
            $node = $body;
            foreach (explode('.', (string)$path) as $seg) {
                if (!is_array($node) || !array_key_exists($seg, $node)) { $node = null; break; }
                $node = $node[$seg];
            }
            $concat .= is_scalar($node) ? (string)$node : '';
        }
        $concat .= (string)($body['timestamp'] ?? '');
        $concat .= $eventsKey;
        return hash_equals(hash('sha256', $concat), strtolower($checksum));
    }

    // ── Categories ──

    public function categories(bool $activeOnly = false): array
    {
        $db = Database::instance();
        $sql = "SELECT c.*, parent.name AS parent_name,
                (SELECT COUNT(*) FROM store_products p
                    LEFT JOIN store_categories pc ON pc.id = p.category_id AND pc.site_id = p.site_id
                    WHERE p.site_id = @site_id AND p.status = 'active'
                      AND (p.category_id = c.id OR (c.path IS NOT NULL AND c.path <> '' AND pc.path LIKE CONCAT(c.path, '/%')))
                ) AS product_count
                FROM store_categories c
                LEFT JOIN store_categories parent ON parent.id = c.parent_id AND parent.site_id = c.site_id
                WHERE c.site_id = @site_id";
        if ($activeOnly) $sql .= " AND c.is_active = 1";
        $sql .= " ORDER BY COALESCE(NULLIF(c.path, ''), c.slug) ASC, c.name ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function saveCategory(array $d, ?int $id = null): int
    {
        $db = Database::instance();
        $name = trim((string)($d['name'] ?? ''));
        if ($name === '') throw new \InvalidArgumentException('Nombre requerido');
        $existing = [];
        if ($id) {
            $st = $db->prepare('SELECT * FROM store_categories WHERE id = ? AND site_id = @site_id LIMIT 1');
            $st->execute([$id]);
            $existing = $st->fetch(\PDO::FETCH_ASSOC) ?: [];
        }
        $slug = trim((string)($d['slug'] ?? ''));
        if ($slug === '') $slug = (string)($existing['slug'] ?? '') ?: $this->slugify($name);
        // Las categorías canónicas conservan su slug/URL; el panel solo puede
        // cambiar el nombre visible y el estado.
        if ($existing && (string)($existing['source'] ?? '') === 'sonika' && !empty($existing['external_key'])) {
            $slug = (string)$existing['slug'];
        }
        $hasParent = array_key_exists('parent_id', $d);
        $parentId = $hasParent && !empty($d['parent_id']) ? (int)$d['parent_id'] : null;
        if (!$hasParent && $existing) $parentId = !empty($existing['parent_id']) ? (int)$existing['parent_id'] : null;
        $parentPath = '';
        $level = 1;
        if ($parentId) {
            $st = $db->prepare('SELECT path, level FROM store_categories WHERE id = ? AND site_id = @site_id LIMIT 1');
            $st->execute([$parentId]);
            $parent = $st->fetch(\PDO::FETCH_ASSOC) ?: [];
            if (!$parent) throw new \InvalidArgumentException('Categoría padre no encontrada');
            $parentPath = trim((string)($parent['path'] ?? ''));
            $level = (int)($parent['level'] ?? 1) + 1;
            if ($parentPath === '') throw new \InvalidArgumentException('La categoría padre no tiene ruta jerárquica');
        }
        $path = $parentPath !== '' ? $parentPath . '/' . $slug : $slug;
        $st = $db->prepare('SELECT id FROM store_categories WHERE site_id = @site_id AND path = ? AND id <> ? LIMIT 1');
        $st->execute([$path, (int)($id ?? 0)]);
        if ($st->fetchColumn()) throw new \InvalidArgumentException('Ya existe una categoría con esa ruta: ' . $path);
        $params = [
            'name' => $name, 'slug' => $slug,
            'description' => (string)($d['description'] ?? ''),
            'image' => (string)($d['image'] ?? ''),
            'parent_id' => $parentId,
            'path' => $path,
            'level' => $level,
            'icon' => (string)($d['icon'] ?? ''),
            'sort_order' => (int)($d['sort_order'] ?? 0),
            'is_active' => isset($d['is_active']) ? (int)(bool)$d['is_active'] : 1,
        ];
        if ($id) {
            $params['id'] = $id;
            $db->prepare("UPDATE store_categories SET name=:name, slug=:slug, description=:description, image=:image, parent_id=:parent_id, path=:path, level=:level, icon=:icon, source='manual', sort_order=:sort_order, is_active=:is_active WHERE id=:id AND site_id=@site_id")->execute($params);
            return $id;
        }
        $db->prepare("INSERT INTO store_categories (site_id, name, slug, description, image, parent_id, path, level, icon, source, sort_order, is_active) VALUES (@site_id, :name, :slug, :description, :image, :parent_id, :path, :level, :icon, 'manual', :sort_order, :is_active)")->execute($params);
        return (int)$db->lastInsertId();
    }

    public function deleteCategory(int $id): void
    {
        $db = Database::instance();
        $db->prepare("UPDATE store_products SET category_id = NULL WHERE category_id = :id AND site_id = @site_id")->execute(['id' => $id]);
        $db->prepare("DELETE FROM store_categories WHERE id = :id AND site_id = @site_id")->execute(['id' => $id]);
    }

    // ── Products ──

    public function products(array $f = []): array
    {
        $db = Database::instance();
        $sql = "SELECT p.*, c.name AS category_name FROM store_products p LEFT JOIN store_categories c ON c.id = p.category_id WHERE p.site_id = @site_id";
        $params = [];
        if (!empty($f['status'])) { $sql .= " AND p.status = :status"; $params['status'] = $f['status']; }
        if (!empty($f['category_id'])) { $sql .= " AND p.category_id = :cat"; $params['cat'] = (int)$f['category_id']; }
        if (!empty($f['search'])) { $sql .= " AND (p.name LIKE :q1 OR p.sku LIKE :q2)"; $params['q1'] = '%' . $f['search'] . '%'; $params['q2'] = '%' . $f['search'] . '%'; }
        if (!empty($f['featured'])) $sql .= " AND p.is_featured = 1";
        if (empty($f['include_private'])) $sql .= " AND (p.visibility IS NULL OR p.visibility = '' OR p.visibility = 'public')";
        $sql .= " ORDER BY p.sort_order ASC, p.id DESC";
        $limit = (int)($f['limit'] ?? 0);
        if ($limit > 0) $sql .= " LIMIT " . min($limit, 5000);
        $offset = (int)($f['offset'] ?? 0);
        if ($limit > 0 && $offset > 0) $sql .= " OFFSET " . $offset;
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return array_map([$this, 'hydrateProduct'], $stmt->fetchAll());
    }

    public function productsCount(array $f = []): int
    {
        $db = Database::instance();
        $sql = "SELECT COUNT(*) FROM store_products p WHERE p.site_id = @site_id";
        $params = [];
        if (!empty($f['status'])) { $sql .= " AND p.status = :status"; $params['status'] = $f['status']; }
        if (!empty($f['category_id'])) { $sql .= " AND p.category_id = :cat"; $params['cat'] = (int)$f['category_id']; }
        if (!empty($f['search'])) { $sql .= " AND (p.name LIKE :q1 OR p.sku LIKE :q2)"; $params['q1'] = '%' . $f['search'] . '%'; $params['q2'] = '%' . $f['search'] . '%'; }
        if (empty($f['include_private'])) $sql .= " AND (p.visibility IS NULL OR p.visibility = '' OR p.visibility = 'public')";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function productsAdmin(array $f = []): array
    {
        $db = Database::instance();
        $sql = "SELECT p.id, p.name, p.slug, p.sku, p.price_cents, p.stock, p.track_stock, p.status, p.is_featured, p.category_id, p.free_shipping, p.tax_exempt, p.visibility, p.allowed_ips, c.name AS category_name FROM store_products p LEFT JOIN store_categories c ON c.id = p.category_id WHERE p.site_id = @site_id";
        $params = [];
        if (!empty($f['status'])) { $sql .= " AND p.status = :status"; $params['status'] = $f['status']; }
        if (!empty($f['category_id'])) { $sql .= " AND p.category_id = :cat"; $params['cat'] = (int)$f['category_id']; }
        if (!empty($f['search'])) { $sql .= " AND (p.name LIKE :q1 OR p.sku LIKE :q2 OR p.slug LIKE :q3)"; $params['q1'] = '%' . $f['search'] . '%'; $params['q2'] = '%' . $f['search'] . '%'; $params['q3'] = '%' . $f['search'] . '%'; }
        if (!empty($f['featured'])) $sql .= " AND p.is_featured = 1";
        $sql .= " ORDER BY p.sort_order ASC, p.id DESC";
        $limit = (int)($f['limit'] ?? 0);
        if ($limit > 0) $sql .= " LIMIT " . min($limit, 5000);
        $offset = (int)($f['offset'] ?? 0);
        if ($limit > 0 && $offset > 0) $sql .= " OFFSET " . $offset;
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function product(int $id): ?array
    {
        $db = Database::instance();
        $stmt = $db->prepare("SELECT * FROM store_products WHERE id = :id AND site_id = @site_id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->hydrateProduct($row) : null;
    }

    public function productBySlug(string $slug): ?array
    {
        $db = Database::instance();
        $stmt = $db->prepare("SELECT * FROM store_products WHERE (slug = :slug OR catalog_handle = :handle) AND site_id = @site_id AND status = 'active' LIMIT 1");
        $stmt->execute(['slug' => $slug, 'handle' => $slug]);
        $row = $stmt->fetch();
        if (!$row || !\App\Services\IpAccessService::canViewProduct($row)) return null;
        return $this->hydrateProduct($row);
    }

    public function saveProduct(array $d, ?int $id = null): int
    {
        $db = Database::instance();
        $name = trim((string)($d['name'] ?? ''));
        if ($name === '') throw new \InvalidArgumentException('Nombre requerido');
        $slugInput = trim((string)($d['slug'] ?? ''));
        // El slug siempre se normaliza: minúsculas, sin acentos, espacios ni símbolos.
        $slug = $slugInput !== '' ? $this->slugify($slugInput) : $this->slugify($name);
        if ($slug === '') $slug = $this->slugify($name);
        $this->assertSlugFree('store_products', $slug, $id);
        $existingProduct = [];
        if ($id) {
            $st = $db->prepare('SELECT tags, images, vendor, product_type, catalog_handle, source, free_shipping, tax_exempt, visibility, allowed_ips FROM store_products WHERE id = ? AND site_id = @site_id LIMIT 1');
            $st->execute([$id]);
            $existingProduct = $st->fetch(\PDO::FETCH_ASSOC) ?: [];
        }
        if (!array_key_exists('tags', $d) && !empty($existingProduct['tags'])) {
            $d['tags'] = json_decode((string)$existingProduct['tags'], true) ?: [];
        }
        $freeShipping = array_key_exists('free_shipping', $d)
            ? (int)(bool)$d['free_shipping']
            : (int)($existingProduct['free_shipping'] ?? 0);
        $taxExempt = array_key_exists('tax_exempt', $d)
            ? (int)(bool)$d['tax_exempt']
            : (int)($existingProduct['tax_exempt'] ?? 0);
        $visibility = strtolower(trim((string)($d['visibility'] ?? ($existingProduct['visibility'] ?? 'public'))));
        if (!in_array($visibility, ['public', 'private'], true)) $visibility = 'public';
        $allowedIpsRaw = array_key_exists('allowed_ips', $d)
            ? (string)$d['allowed_ips']
            : (string)($existingProduct['allowed_ips'] ?? '');
        $allowedIps = implode(', ', \App\Services\IpAccessService::normalizeRules($allowedIpsRaw));
        $categoryId = !empty($d['category_id']) ? (int)$d['category_id'] : null;
        $existingCategoryId = (int)($existingProduct['category_id'] ?? 0);
        $categoryChanged = $id
            ? (array_key_exists('category_id', $d) && (int)$categoryId !== $existingCategoryId)
            : !empty($categoryId);
        $categoryLocked = (int)($existingProduct['category_locked'] ?? 0);
        if ($categoryChanged) $categoryLocked = 1;
        $categorySource = $categoryLocked ? 'manual' : (string)($existingProduct['category_source'] ?? '');
        $params = [
            'category_id' => $categoryId,
            'category_locked' => $categoryLocked,
            'category_source' => $categorySource,
            'name' => $name,
            'slug' => $slug,
            'description' => (string)($d['description'] ?? ''),
            'short_description' => mb_substr((string)($d['short_description'] ?? ''), 0, 500),
            'price_cents' => (int)round((float)($d['price_cents'] ?? 0)),
            'compare_price_cents' => (int)round((float)($d['compare_price_cents'] ?? 0)),
            'currency' => (string)($d['currency'] ?? 'COP'),
            'sku' => (string)($d['sku'] ?? ''),
            'stock' => (int)($d['stock'] ?? 0),
            'track_stock' => isset($d['track_stock']) ? (int)(bool)$d['track_stock'] : 1,
            'weight_grams' => (int)($d['weight_grams'] ?? 0),
            'images' => json_encode(array_values(array_filter((array)($d['images'] ?? []), fn($v) => is_string($v) && $v !== ''))),
            'tags' => json_encode(array_values(array_filter((array)($d['tags'] ?? []), fn($v) => is_string($v) && $v !== ''))),
            'status' => in_array($d['status'] ?? '', ['draft', 'active', 'archived'], true) ? $d['status'] : 'draft',
            'is_featured' => (int)!empty($d['is_featured']),
            'seo_title' => (string)($d['seo_title'] ?? ''),
            'seo_description' => (string)($d['seo_description'] ?? ''),
            'sort_order' => (int)($d['sort_order'] ?? 0),
            'free_shipping' => $freeShipping,
            'tax_exempt' => $taxExempt,
            'visibility' => $visibility,
            'allowed_ips' => $allowedIps !== '' ? $allowedIps : null,
        ];
        if ($id) {
            $params['id'] = $id;
            $db->prepare("UPDATE store_products SET category_id=:category_id, category_locked=:category_locked, category_source=:category_source, name=:name, slug=:slug, description=:description, short_description=:short_description,
                price_cents=:price_cents, compare_price_cents=:compare_price_cents, currency=:currency, sku=:sku, stock=:stock, track_stock=:track_stock,
                weight_grams=:weight_grams, images=:images, tags=:tags, status=:status, is_featured=:is_featured, seo_title=:seo_title,
                seo_description=:seo_description, sort_order=:sort_order, free_shipping=:free_shipping, tax_exempt=:tax_exempt,
                visibility=:visibility, allowed_ips=:allowed_ips WHERE id=:id AND site_id=@site_id")->execute($params);
            $productId = $id;
        } else {
            $db->prepare("INSERT INTO store_products (site_id, category_id, category_locked, category_source, name, slug, description, short_description, price_cents, compare_price_cents,
                currency, sku, stock, track_stock, weight_grams, images, tags, status, is_featured, seo_title, seo_description, sort_order,
                free_shipping, tax_exempt, visibility, allowed_ips)
                VALUES (@site_id, :category_id, :category_locked, :category_source, :name, :slug, :description, :short_description, :price_cents, :compare_price_cents, :currency, :sku, :stock,
                :track_stock, :weight_grams, :images, :tags, :status, :is_featured, :seo_title, :seo_description, :sort_order,
                :free_shipping, :tax_exempt, :visibility, :allowed_ips)")->execute($params);
            $productId = (int)$db->lastInsertId();
        }
        if (array_key_exists('variants', $d)) $this->saveVariants($productId, (array)$d['variants']);
        try {
            $ready = $db->prepare("SELECT slug FROM store_products WHERE id = ? AND site_id = @site_id AND status = 'active' AND (track_stock = 0 OR stock > 0) LIMIT 1");
            $ready->execute([$productId]);
            $readySlug = (string)($ready->fetchColumn() ?: '');
            if ($readySlug !== '') (new StockNotificationService())->notifyForProduct($readySlug);
        } catch (\Throwable $e) {
        }
        if ($categoryChanged) {
            try {
                \App\Services\CatalogTaxonomyService::buildMap(null, true);
                \App\Services\CatalogTaxonomyService::clearCaches();
            } catch (\Throwable $e) {
            }
        }
        return $productId;
    }

    public function deleteProduct(int $id): void
    {
        $db = Database::instance();
        $db->prepare("DELETE FROM store_variants WHERE product_id = :id AND site_id = @site_id")->execute(['id' => $id]);
        $db->prepare("DELETE FROM store_products WHERE id = :id AND site_id = @site_id")->execute(['id' => $id]);
    }

    public function variants(int $productId): array
    {
        $db = Database::instance();
        $stmt = $db->prepare("SELECT * FROM store_variants WHERE product_id = :pid AND site_id = @site_id ORDER BY sort_order ASC, id ASC");
        $stmt->execute(['pid' => $productId]);
        return array_map(function ($r) {
            $r['options'] = json_decode((string)$r['options'], true) ?: [];
            return $r;
        }, $stmt->fetchAll());
    }

    public function saveVariants(int $productId, array $variants): void
    {
        $db = Database::instance();
        $db->prepare("DELETE FROM store_variants WHERE product_id = :pid AND site_id = @site_id")->execute(['pid' => $productId]);
        $stmt = $db->prepare("INSERT INTO store_variants (site_id, product_id, name, options, price_cents, stock, sku, sort_order, is_active)
            VALUES (@site_id, :pid, :name, :options, :price_cents, :stock, :sku, :sort_order, :is_active)");
        $i = 0;
        foreach ($variants as $v) {
            $nm = trim((string)($v['name'] ?? ''));
            if ($nm === '') continue;
            $stmt->execute([
                'pid' => $productId, 'name' => $nm,
                'options' => json_encode((array)($v['options'] ?? [])),
                'price_cents' => (int)round((float)($v['price_cents'] ?? 0)),
                'stock' => (int)($v['stock'] ?? 0),
                'sku' => (string)($v['sku'] ?? ''),
                'sort_order' => $i++,
                'is_active' => isset($v['is_active']) ? (int)(bool)$v['is_active'] : 1,
            ]);
        }
    }

    // ── Shipping zones ──

    public function zones(bool $activeOnly = false): array
    {
        $db = Database::instance();
        $sql = "SELECT * FROM store_shipping_zones WHERE site_id = @site_id";
        if ($activeOnly) $sql .= " AND is_active = 1";
        $sql .= " ORDER BY sort_order ASC, id ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function saveZone(array $d, ?int $id = null): int
    {
        $db = Database::instance();
        $name = trim((string)($d['name'] ?? ''));
        if ($name === '') throw new \InvalidArgumentException('Nombre requerido');
        $params = [
            'name' => $name,
            'regions' => (string)($d['regions'] ?? ''),
            'cost_cents' => (int)round((float)($d['cost_cents'] ?? 0)),
            'free_over_cents' => (int)round((float)($d['free_over_cents'] ?? 0)),
            'eta_days' => (string)($d['eta_days'] ?? ''),
            'is_active' => isset($d['is_active']) ? (int)(bool)$d['is_active'] : 1,
            'sort_order' => (int)($d['sort_order'] ?? 0),
        ];
        if ($id) {
            $params['id'] = $id;
            $db->prepare("UPDATE store_shipping_zones SET name=:name, regions=:regions, cost_cents=:cost_cents, free_over_cents=:free_over_cents,
                eta_days=:eta_days, is_active=:is_active, sort_order=:sort_order WHERE id=:id AND site_id=@site_id")->execute($params);
            return $id;
        }
        $db->prepare("INSERT INTO store_shipping_zones (site_id, name, regions, cost_cents, free_over_cents, eta_days, is_active, sort_order)
            VALUES (@site_id, :name, :regions, :cost_cents, :free_over_cents, :eta_days, :is_active, :sort_order)")->execute($params);
        return (int)$db->lastInsertId();
    }

    public function deleteZone(int $id): void
    {
        Database::instance()->prepare("DELETE FROM store_shipping_zones WHERE id = :id AND site_id = @site_id")->execute(['id' => $id]);
    }

    // ── Orders ──

    /** Base outlet (2 % fijo) y precio unitario máximo no-outlet del pedido, en pesos. */
    private function affiliateOrderMeta(array $lines, int $subtotal, int $discountCents): array
    {
        $fallbackMax = 0;
        foreach ($lines as $l) $fallbackMax = max($fallbackMax, (int)($l['price_cents'] ?? 0));
        $fallback = ['outlet' => 0.0, 'max_item' => $fallbackMax / 100];
        if ($subtotal <= 0 || !$lines) return $fallback;
        $ids = [];
        foreach ($lines as $l) {
            $pid = (int)($l['product_id'] ?? 0);
            if ($pid > 0) $ids[$pid] = true;
        }
        if (!$ids) return $fallback;
        try {
            $ids = array_keys($ids);
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $st = Database::instance()->prepare("SELECT id, tags FROM store_products WHERE site_id = @site_id AND id IN ($ph)");
            $st->execute($ids);
            $outlet = [];
            foreach ($st->fetchAll() as $row) {
                $tags = $row['tags'] ?? '';
                if (is_string($tags)) $tags = json_decode($tags, true);
                if (!is_array($tags)) continue;
                foreach ($tags as $tag) {
                    $t = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], strtolower((string)$tag));
                    if (strpos($t, 'outlet') !== false || strpos($t, 'liquidaci') !== false || strpos($t, 'margen_bajo') !== false || strpos($t, 'bajo_margen') !== false) {
                        $outlet[(int)$row['id']] = true;
                        break;
                    }
                }
            }
            $outletSubtotal = 0;
            $maxItemCents = 0;
            foreach ($lines as $l) {
                $pid = (int)($l['product_id'] ?? 0);
                if (!empty($outlet[$pid])) {
                    $outletSubtotal += (int)($l['subtotal_cents'] ?? 0);
                } else {
                    $maxItemCents = max($maxItemCents, (int)($l['price_cents'] ?? 0));
                }
            }
            $net = max(0, $subtotal - $discountCents);
            $outletBase = $outletSubtotal > 0 ? round(($net * ($outletSubtotal / $subtotal)) / 100, 2) : 0.0;
            return ['outlet' => $outletBase, 'max_item' => $maxItemCents / 100];
        } catch (\Throwable $e) {
            return $fallback;
        }
    }

    public function createOrder(array $input, string $ip = ''): array
    {
        if (!$this->ready()) throw new \RuntimeException('La tienda no esta configurada');
        $settings = $this->settings();
        if ($settings['store_enabled'] !== '1' && $settings['store_enabled'] !== '') throw new \RuntimeException('La tienda esta deshabilitada');
        $affRef = strtolower(trim((string)($input['ref'] ?? '')));
        $affRef = substr((string)preg_replace('/[^a-z0-9\-]/', '', $affRef), 0, 64);
        $this->assertRateLimit($settings, $input, $ip);
        (new CustomerService())->ensureTables();
        (new LoyaltyService())->ensureTables();
        (new StoreCouponService())->ensureTables();

        $items = (array)($input['items'] ?? []);
        if (!$items) throw new \InvalidArgumentException('El pedido no tiene productos');
        if (count($items) > 50) throw new \InvalidArgumentException('Demasiados productos en el pedido');

        $customer = (array)($input['customer'] ?? []);
        $name = trim((string)($customer['name'] ?? ''));
        $email = trim((string)($customer['email'] ?? ''));
        $phone = trim((string)($customer['phone'] ?? ''));
        if ($name === '') throw new \InvalidArgumentException('Nombre requerido');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException('Email invalido');
        if ($phone === '') throw new \InvalidArgumentException('Telefono requerido');

        $method = (string)($input['payment_method'] ?? '');
        $allowed = $this->paymentMethods();
        if (!in_array($method, $allowed, true)) throw new \InvalidArgumentException('Metodo de pago no disponible');

        $db = Database::instance();
        $db->beginTransaction();
        try {
            $lines = [];
            $subtotal = 0;
            $shippingBase = 0;
            $hasNonExempt = false;
            foreach ($items as $it) {
                $pid = (int)($it['product_id'] ?? 0);
                $qty = max(1, min(99, (int)($it['qty'] ?? 1)));
                $vid = !empty($it['variant_id']) ? (int)$it['variant_id'] : null;
                $p = $this->product($pid);
                if (!$p || $p['status'] !== 'active') throw new \RuntimeException('Producto no disponible');
                if (!\App\Services\IpAccessService::canViewProduct($p)) throw new \RuntimeException('Producto no disponible');
                $unit = (int)$p['price_cents'];
                $variantName = '';
                if ($vid) {
                    $vstmt = $db->prepare("SELECT * FROM store_variants WHERE id = :id AND product_id = :pid AND site_id = @site_id AND is_active = 1 LIMIT 1");
                    $vstmt->execute(['id' => $vid, 'pid' => $pid]);
                    $v = $vstmt->fetch();
                    if (!$v) throw new \RuntimeException('Variante no disponible');
                    if ((int)$v['price_cents'] > 0) $unit = (int)$v['price_cents'];
                    $variantName = (string)$v['name'];
                    if ((int)$p['track_stock'] === 1) {
                        $upd = $db->prepare("UPDATE store_variants SET stock = stock - :qty WHERE id = :id AND site_id = @site_id AND stock >= :minq");
                        $upd->execute(['qty' => $qty, 'id' => $vid, 'minq' => $qty]);
                        if ($upd->rowCount() < 1) throw new \RuntimeException('Sin stock: ' . $p['name']);
                    }
                } elseif ((int)$p['track_stock'] === 1) {
                    $upd = $db->prepare("UPDATE store_products SET stock = stock - :qty WHERE id = :id AND site_id = @site_id AND stock >= :minq");
                    $upd->execute(['qty' => $qty, 'id' => $pid, 'minq' => $qty]);
                    if ($upd->rowCount() < 1) throw new \RuntimeException('Sin stock: ' . $p['name']);
                }
                $lineTotal = $unit * $qty;
                $subtotal += $lineTotal;
                $lineFreeShipping = (int)($p['free_shipping'] ?? 0) === 1;
                if (!$lineFreeShipping) {
                    $shippingBase += $lineTotal;
                    $hasNonExempt = true;
                }
                $lines[] = [
                    'product_id' => $pid, 'variant_id' => $vid, 'name' => $p['name'], 'variant_name' => $variantName,
                    'price_cents' => $unit, 'qty' => $qty, 'subtotal_cents' => $lineTotal,
                    'image' => $p['images'][0] ?? '', 'free_shipping' => $lineFreeShipping,
                ];
            }

            $min = (int)$settings['store_min_order_cents'];
            if ($min > 0 && $subtotal < $min) throw new \RuntimeException('El pedido minimo es ' . $this->money($min, $settings['store_currency']));

            $shippingCents = 0;
            $zoneId = null;
            $shipIn = (array)($input['shipping'] ?? []);
            if (!empty($shipIn['zone_id'])) {
                $zstmt = $db->prepare("SELECT * FROM store_shipping_zones WHERE id = :id AND site_id = @site_id AND is_active = 1 LIMIT 1");
                $zstmt->execute(['id' => (int)$shipIn['zone_id']]);
                $zone = $zstmt->fetch();
                if (!$zone) throw new \RuntimeException('Zona de envio invalida');
                $zoneId = (int)$zone['id'];
                $shippingCents = $hasNonExempt ? (int)$zone['cost_cents'] : 0;
                if ($hasNonExempt && (int)$zone['free_over_cents'] > 0 && $shippingBase >= (int)$zone['free_over_cents']) $shippingCents = 0;
            }

            $discountCents = 0;
            $pointsUsed = 0;
            $redeemCustomerId = 0;
            $pointsReq = (int)($input['points'] ?? 0);
            if ($pointsReq > 0) {
                $loy = (new LoyaltyService())->settings();
                if (!empty($loy['enabled']) && (int)$loy['redeem_cents'] > 0) {
                    $me = (new CustomerService())->current();
                    if ($me && strcasecmp((string)$me['email'], $email) === 0) {
                        $redeemCustomerId = (int)$me['id'];
                        $bal = (new LoyaltyService())->balance($redeemCustomerId);
                        $maxByTotal = (int)floor(($subtotal + $shippingCents) / (int)$loy['redeem_cents']);
                        $pointsUsed = max(0, min($pointsReq, $bal, $maxByTotal));
                        $discountCents = $pointsUsed * (int)$loy['redeem_cents'];
                    }
                }
            }
            $couponId = 0;
            $couponIn = strtoupper(trim((string)($input['coupon'] ?? '')));
            if ($couponIn !== '') {
                $cv = (new StoreCouponService())->validate($couponIn, $subtotal);
                if (!empty($cv['ok'])) {
                    $discountCents += (int)$cv['discount_cents'];
                    $couponId = (int)($cv['id'] ?? 0);
                }
            }
            $total = $subtotal + $shippingCents - $discountCents;
            if ($total < 0) $total = 0;
            $uuid = $this->uuid4();
            $orderNumber = 'PED-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));

            $addr = (array)($shipIn['address'] ?? []);
            $addressJson = json_encode(array_filter([
                'line1' => (string)($addr['line1'] ?? ''),
                'line2' => (string)($addr['line2'] ?? ''),
                'city' => (string)($addr['city'] ?? ''),
                'region' => (string)($addr['region'] ?? ''),
                'notes' => (string)($addr['notes'] ?? ''),
            ], fn($v) => $v !== ''));

            $cust = $this->upsertCustomer($name, $email, $phone, (string)($customer['document'] ?? ''), $addressJson);
            $ipHash = $ip !== '' ? hash('sha256', $ip) : '';

            $db->prepare("INSERT INTO store_orders (site_id, uuid, order_number, customer_id, customer_name, customer_email, customer_phone, customer_document,
                shipping_address, shipping_zone_id, subtotal_cents, shipping_cents, discount_cents, total_cents, currency, payment_method, payment_status,
                fulfillment_status, notes, ip_hash, affiliate_ref) VALUES (@site_id, :uuid, :num, :cid, :name, :email, :phone, :doc, :addr, :zone, :sub, :ship, :disc, :total, :cur, :pm,
                'pending', 'new', :notes, :iph, :affref)")
                ->execute([
                    'uuid' => $uuid, 'num' => $orderNumber, 'cid' => $cust, 'name' => $name, 'email' => $email, 'phone' => $phone,
                    'doc' => (string)($customer['document'] ?? ''), 'addr' => $addressJson, 'zone' => $zoneId,
                    'sub' => $subtotal, 'ship' => $shippingCents, 'disc' => $discountCents, 'total' => $total, 'cur' => $settings['store_currency'],
                    'pm' => $method, 'notes' => (string)($input['notes'] ?? ''), 'iph' => $ipHash, 'affref' => $affRef !== '' ? $affRef : null,
                ]);
            $orderId = (int)$db->lastInsertId();
            $this->markOrderInternal($orderId);

            $istmt = $db->prepare("INSERT INTO store_order_items (site_id, order_id, product_id, variant_id, sku, product_ref, name, variant_name, price_cents, qty, subtotal_cents, weight_grams, image)
                VALUES (@site_id, :oid, :pid, :vid, :sku, :pref, :name, :vname, :price, :qty, :sub, :weight, :img)");
            foreach ($lines as $l) {
                $istmt->execute([
                    'oid' => $orderId, 'pid' => (int)($l['product_id'] ?? 0), 'vid' => ($l['variant_id'] ?? null) ?: null,
                    'sku' => mb_substr((string)($l['sku'] ?? ''), 0, 120), 'pref' => mb_substr((string)($l['product_ref'] ?? $l['ref'] ?? ''), 0, 255),
                    'name' => $l['name'], 'vname' => $l['variant_name'], 'price' => $l['price_cents'], 'qty' => $l['qty'],
                    'sub' => $l['subtotal_cents'], 'weight' => (int)($l['weight_grams'] ?? 0), 'img' => $l['image'],
                ]);
            }

            if ($pointsUsed > 0 && $redeemCustomerId > 0) {
                $db->prepare("INSERT INTO store_loyalty_ledger (site_id, customer_id, points, reason, order_id, note) VALUES (@site_id, ?, ?, 'redeem', ?, 'Puntos usados en el pedido')")
                   ->execute([$redeemCustomerId, -$pointsUsed, $orderId]);
                $db->prepare("UPDATE store_customers SET points_balance = points_balance - ? WHERE id = ? AND site_id = @site_id")
                   ->execute([$pointsUsed, $redeemCustomerId]);
            }

            $db->commit();

            // Atribución de Partner SONIKA — nunca bloquea el pedido.
            try {
                $__aff = $affRef !== '' ? $affRef : (string)($_COOKIE['sonika_aff'] ?? '');
                $__internal = 0;
                if ($__aff !== '') {
                    $st = $db->prepare('SELECT is_internal FROM store_orders WHERE id = ? AND site_id = @site_id LIMIT 1');
                    $st->execute([$orderId]);
                    $__internal = (int)$st->fetchColumn();
                }
                if ($__aff !== '' && $__internal === 0) {
                    $__base = max(0.0, ((float)$subtotal - (float)$discountCents) / 100);
                    $__metaAff = $this->affiliateOrderMeta($lines, (int)$subtotal, (int)$discountCents);
                    (new \App\Services\AffiliateService())->attributeOrder((string)$uuid, $__base, null, $__aff, $__metaAff['outlet'], $__metaAff['max_item']);
                }
            } catch (\Throwable $e) {
            }

            // Tracking & Conversion — nunca bloquea el pedido.
            try {
                TrackingConversionService::onOrderCreated(
                    (string)$uuid,
                    $lines,
                    (int)$total,
                    (string)$settings['store_currency'],
                    (string)$method,
                    (string)$orderNumber
                );
            } catch (\Throwable $e) {
            }
            try {
                (new CartAbandonmentService())->markRecovered(
                    (string)$uuid,
                    SeoInternalTrafficService::sessionId(),
                    (int)$total,
                    (int)$cust
                );
            } catch (\Throwable $e) {
            }

            if ($couponId > 0) {
                try {
                    (new StoreCouponService())->redeem($couponId);
                } catch (\Throwable $e) {
                }
            }
            $order = $this->orderById($orderId);
            $payment = ['method' => $method, 'reference' => $uuid, 'amount_in_cents' => $total, 'currency' => $settings['store_currency']];
            if ($method === 'wompi') {
                $payment['public_key'] = $settings['store_wompi_public_key'];
                $payment['signature'] = $this->wompiSignature($uuid, $total, $settings['store_currency']);
            }

            $this->notifyOrder($order);

            return ['order' => $order, 'payment' => $payment];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    /**
     * Pedido para tiendas basadas en CATALOGO (catalog.json del tema), no en
     * store_products. Re-cotiza server-side (CartService) y registra el pedido
     * como solicitud (payment_method=request) avisando al comercio por email.
     */
    public function createCatalogOrder(array $input, string $ip = ''): array
    {
        $this->ensureTables();
        (new CustomerService())->ensureTables();
        (new BrebService())->ensureTables();
        (new StoreCouponService())->ensureTables();
        (new LoyaltyService())->ensureTables();

        $itemsIn = (array)($input['items'] ?? []);
        if (!$itemsIn) throw new \InvalidArgumentException('El pedido no tiene productos');
        if (count($itemsIn) > 50) throw new \InvalidArgumentException('Demasiados productos en el pedido');

        $customer = (array)($input['customer'] ?? []);
        $name = trim((string)($customer['name'] ?? ''));
        $email = trim((string)($customer['email'] ?? ''));
        $phone = trim((string)($customer['phone'] ?? ''));
        $document = trim((string)($customer['document'] ?? ''));
        $shipIn = (array)($input['shipping'] ?? []);
        $addr = (array)($shipIn['address'] ?? []);
        $line1 = trim((string)($addr['line1'] ?? ''));
        $city = trim((string)($addr['city'] ?? ''));
        $region = trim((string)($addr['region'] ?? ''));
        $departmentCode = preg_replace('/\D+/', '', (string)($addr['department_code'] ?? ($shipIn['department_code'] ?? '')));
        $municipalityCode = preg_replace('/\D+/', '', (string)($addr['municipality_code'] ?? ($shipIn['municipality_code'] ?? '')));
        $notes = trim((string)($input['notes'] ?? ($addr['notes'] ?? '')));
        if ($name === '' || mb_strlen($name) < 2) throw new \InvalidArgumentException('Escribe tu nombre completo.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException('Escribe un correo electronico valido.');
        $phoneDigits = preg_replace('/\D+/', '', $phone);
        if ($phoneDigits === '' || strlen($phoneDigits) < 7 || strlen($phoneDigits) > 15) throw new \InvalidArgumentException('Escribe un telefono valido (7 a 15 digitos).');
        if ($document === '' || mb_strlen($document) < 5) throw new \InvalidArgumentException('Escribe tu documento (CC, NIT o CE).');
        if ($line1 === '' || mb_strlen($line1) < 5) throw new \InvalidArgumentException('Escribe la direccion de entrega.');
        if (strlen($departmentCode) !== 2 || strlen($municipalityCode) !== 5) throw new \InvalidArgumentException('Selecciona el departamento y el municipio de entrega.');
        if ($city === '' || mb_strlen($city) < 2) throw new \InvalidArgumentException('Escribe la ciudad o municipio.');
        if ($notes === '' || mb_strlen($notes) < 30) throw new \InvalidArgumentException('Escribe al menos 30 caracteres con indicaciones para la entrega.');
        if (mb_strlen($notes) > 500) throw new \InvalidArgumentException('Las indicaciones de entrega no pueden superar 500 caracteres.');
        if (empty($input['terms'])) throw new \InvalidArgumentException('Debes aceptar los terminos y condiciones.');

        $settings = $this->settings();
        if (($settings['store_enabled'] ?? '') === '0') throw new \RuntimeException('La tienda esta deshabilitada');
        $this->assertRateLimit($settings, $input, $ip);

        $method = (string)($input['payment_method'] ?? 'request');
        if (!in_array($method, BrebService::METHODS, true)) throw new \InvalidArgumentException('Metodo de pago no disponible');

        $breb = new BrebService();
        $brebSettings = $breb->settings();
        if ($method === 'breb' && !$breb->available()) throw new \RuntimeException('Bre-B no esta disponible en este momento.');
        if ($method === 'mercadopago' && !(new MercadoPagoService())->isAvailable() && ($brebSettings['mp_link'] === '' || !$brebSettings['mp_enabled'])) throw new \RuntimeException('Mercado Pago no esta disponible en este momento.');
        if ($method === 'epayco' && ($brebSettings['epayco_link'] === '' || !$brebSettings['epayco_enabled'])) throw new \RuntimeException('ePayco no esta disponible en este momento.');
        if ($method === 'sistecredito' && !(new SistecreditoService())->isAvailable()) throw new \RuntimeException('Sistecrédito no está disponible en este momento.');

        $quoteCode = strtoupper(trim((string)($input['quote_code'] ?? '')));
        if ($quoteCode !== '' && !preg_match('/^COT-[A-Z0-9-]{4,36}$/', $quoteCode)) $quoteCode = '';
        $couponIn = strtoupper(trim((string)($input['coupon'] ?? '')));
        $pointsReq = max(0, (int)($input['points'] ?? 0));
        $giftCardIn = (array)($input['gift_card'] ?? []);
        $quote = $breb->quote($itemsIn, $method, $couponIn, $pointsReq, [
            'department_code' => $departmentCode,
            'municipality_code' => $municipalityCode,
        ], $giftCardIn);
        if (empty($quote['ok'])) throw new \RuntimeException((string)($quote['message'] ?? 'El pedido no es valido'));
        $shippingMeta = (array)($quote['shipping'] ?? []);
        if (empty($shippingMeta['ok'])) throw new \RuntimeException((string)($shippingMeta['message'] ?? 'No hay cobertura para este municipio.'));
        if (!empty($shippingMeta['municipality'])) $city = (string)$shippingMeta['municipality'];
        if (!empty($shippingMeta['department'])) $region = (string)$shippingMeta['department'];
        if ($couponIn !== '' && ($quote['coupon_ok'] ?? null) === false) {
            throw new \RuntimeException((string)($quote['coupon_message'] ?? 'El cupón no es válido.'));
        }
        if ($pointsReq > 0 && (int)($quote['points_used'] ?? 0) <= 0) {
            throw new \RuntimeException((string)($quote['points_message'] ?? 'No se pudieron aplicar los puntos.'));
        }
        if (trim((string)($giftCardIn['code'] ?? '')) !== '' && ($quote['gift_card_ok'] ?? null) === false) {
            throw new \RuntimeException((string)($quote['gift_card_message'] ?? 'La tarjeta de regalo no es válida.'));
        }
        if ((int)$quote['issue_count'] > 0) {
            $names = [];
            foreach (array_slice((array)$quote['issues'], 0, 3) as $issue) {
                $label = trim((string)($issue['title'] ?? $issue['ref'] ?? ''));
                if ($label !== '') $names[] = $label;
            }
            throw new \RuntimeException('Revisa la disponibilidad del pedido: ' . ($names ? implode(', ', $names) : 'hay productos no disponibles'));
        }
        if ($method === 'addi') {
            $addi = new AddiService();
            if (!$addi->isConfigured() || $addi->publicMethod() === null) {
                throw new \RuntimeException('Addi no está disponible en este momento.');
            }
            $addiTotal = (int)($quote['total_cents'] ?? 0);
            $addiCfg = $addi->config($addiTotal);
            if (empty($addiCfg['ok'])) {
                throw new \RuntimeException((string)($addiCfg['message'] ?? 'No se pudo validar Addi.'));
            }
            $addiData = (array)($addiCfg['data'] ?? []);
            $addiMin = (int)($addiData['minAmount'] ?? 0);
            $addiMax = (int)($addiData['maxAmount'] ?? 0);
            if (empty($addiData['isActiveAlly'])) {
                throw new \RuntimeException('Addi no está activo para este comercio todavía.');
            }
            if ($addiMin > 0 && $addiTotal < $addiMin * 100) {
                throw new \RuntimeException('Addi aplica para compras desde ' . $this->money($addiMin * 100, (string)($settings['store_currency'] ?? 'COP')) . '.');
            }
            if ($addiMax > 0 && $addiTotal > $addiMax * 100) {
                throw new \RuntimeException('Addi aplica para compras hasta ' . $this->money($addiMax * 100, (string)($settings['store_currency'] ?? 'COP')) . '.');
            }
            if (empty($addiData['policy'])) {
                throw new \RuntimeException('Addi no está disponible para este monto.');
            }
        }

        $lines = (array)$quote['items'];
        $subtotal = (int)$quote['subtotal_before_cents'];
        $subtotalNet = (int)$quote['net_after_cents'];
        $tax = (int)$quote['tax_after_cents'];
        $brebDiscount = (int)($quote['breb_discount_cents'] ?? 0);
        $brebDiscountNet = (int)($quote['breb_discount_net_cents'] ?? 0);
        $recommendationDiscount = (int)($quote['recommendation_discount_cents'] ?? 0);
        $recommendationDiscountNet = (int)($quote['recommendation_discount_net_cents'] ?? 0);
        $couponDiscount = (int)($quote['coupon_discount_cents'] ?? 0);
        $couponId = (int)($quote['coupon_id'] ?? 0);
        $pointsUsed = (int)($quote['points_used'] ?? 0);
        $pointsDiscount = (int)($quote['points_discount_cents'] ?? 0);
        $giftCardDiscount = (int)($quote['gift_card_discount_cents'] ?? 0);
        $giftCardCode = (string)($quote['gift_card_code'] ?? '');
        $giftCardCodeMasked = (string)($quote['gift_card_code_masked'] ?? '');
        $pointsCustomerId = 0;
        if ($pointsUsed > 0) {
            $me = (new CustomerService())->current();
            if (!$me || strcasecmp((string)$me['email'], $email) !== 0) {
                throw new \RuntimeException('Inicia sesión con el correo de tu cuenta para usar puntos.');
            }
            $pointsCustomerId = (int)$me['id'];
        }
        $discount = (int)$quote['discount_cents'];
        $discountNet = (int)$quote['discount_net_cents'];
        $shipping = (int)$quote['shipping_cents'];
        $total = (int)$quote['total_cents'];
        if ($total > 0 && !(new CheckoutPaymentMethodService())->isVisible($method)) {
            throw new \RuntimeException('El método de pago seleccionado no está disponible en este momento.');
        }

        $currency = ($settings['store_currency'] ?? '') !== '' ? (string)$settings['store_currency'] : 'COP';
        $uuid = $this->uuid4();
        $orderNumber = 'PED-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        $addressJson = json_encode(array_filter([
            'line1' => $line1,
            'city' => $city,
            'region' => $region,
            'department' => (string)($shippingMeta['department'] ?? ''),
            'municipality' => (string)($shippingMeta['municipality'] ?? ''),
            'department_code' => $departmentCode,
            'municipality_code' => $municipalityCode,
            'notes' => $notes,
        ], fn($v) => $v !== ''));

        $paymentLink = null;
        if ($method === 'mercadopago') $paymentLink = $brebSettings['mp_link'];
        if ($method === 'epayco') $paymentLink = $brebSettings['epayco_link'];
        $paymentReference = in_array($method, ['breb', 'mercadopago', 'epayco'], true)
            ? strtoupper($method === 'breb' ? 'BREB-' : ($method === 'mercadopago' ? 'MP-' : 'EPAYCO-')) . $orderNumber
            : null;
        $expiresAt = $method === 'breb'
            ? date('Y-m-d H:i:s', time() + ((int)$brebSettings['payment_minutes'] * 60))
            : null;
        $paymentMeta = json_encode([
            'quote_method' => $method,
            'tax_rate' => $brebSettings['tax_rate'],
            'prices_include_tax' => $brebSettings['prices_include_tax'],
            'free_shipping' => (bool)$quote['free_shipping'],
            'shipping_rate_id' => (int)($shippingMeta['rate_id'] ?? 0),
            'shipping_source' => (string)($shippingMeta['source'] ?? ''),
            'shipping_eta_min_days' => (int)($shippingMeta['eta_min_days'] ?? 0),
            'shipping_eta_max_days' => (int)($shippingMeta['eta_max_days'] ?? 0),
            'discount_pct' => $method === 'breb' ? $brebSettings['discount_pct'] : 0,
            'coupon_code' => (string)($quote['coupon_code'] ?? ''),
            'coupon_discount_cents' => $couponDiscount,
            'recommendation_discount_cents' => $recommendationDiscount,
            'recommendation_discount_net_cents' => $recommendationDiscountNet,
            'recommendation_items' => (array)($quote['recommendation_items'] ?? []),
            'points_used' => $pointsUsed,
            'points_discount_cents' => $pointsDiscount,
            'gift_card_cents' => $giftCardDiscount,
            'gift_card_code_masked' => $giftCardCodeMasked,
        ]);

        $db = Database::instance();
        $db->beginTransaction();
        try {
            $cust = $this->upsertCustomer($name, $email, $phone, $document, $addressJson);
            $db->prepare("INSERT INTO store_orders (site_id, uuid, order_number, customer_id, customer_name, customer_email, customer_phone, customer_document, quote_code,
                shipping_address, shipping_rate_id, shipping_department_code, shipping_municipality_code, shipping_carrier,
                shipping_eta_min_days, shipping_eta_max_days,
                subtotal_cents, subtotal_net_cents, tax_cents, breb_discount_cents, breb_discount_net_cents,
                recommendation_discount_cents, recommendation_discount_net_cents,
                shipping_cents, discount_cents, total_cents, currency, payment_method, payment_status, payment_reference, payment_link,
                payment_expires_at, payment_proof_status, payment_meta, fulfillment_status, notes, ip_hash, gift_card_cents, gift_card_code)
                VALUES (@site_id, :uuid, :num, :cid, :name, :email, :phone, :doc, :quote, :addr, :rate, :dept, :mun, :carrier,
                :etamin, :etamax, :sub, :subnet, :tax, :bdis, :bdisnet, :rdis, :rdisnet,
                :ship, :disc, :total, :cur, :pm, 'pending', :pref, :link, :expires, 'none', :meta, 'new', :notes, :iph, :gcc, :gcode)")
                ->execute([
                    'uuid' => $uuid, 'num' => $orderNumber, 'cid' => $cust, 'name' => $name, 'email' => $email, 'phone' => $phone,
                    'doc' => $document, 'quote' => $quoteCode !== '' ? $quoteCode : null, 'addr' => $addressJson,
                    'rate' => (int)($shippingMeta['rate_id'] ?? 0) ?: null, 'dept' => $departmentCode, 'mun' => $municipalityCode,
                    'carrier' => (string)($shippingMeta['carrier'] ?? '') ?: null,
                    'etamin' => (int)($shippingMeta['eta_min_days'] ?? 0) ?: null,
                    'etamax' => (int)($shippingMeta['eta_max_days'] ?? 0) ?: null,
                    'sub' => $subtotal, 'subnet' => $subtotalNet, 'tax' => $tax, 'bdis' => $brebDiscount, 'bdisnet' => $brebDiscountNet,
                    'rdis' => $recommendationDiscount, 'rdisnet' => $recommendationDiscountNet,
                    'ship' => $shipping, 'disc' => $discount, 'total' => $total, 'cur' => $currency, 'pm' => $method,
                    'pref' => $paymentReference, 'link' => $paymentLink, 'expires' => $expiresAt, 'meta' => $paymentMeta,
                    'notes' => $notes, 'iph' => $ip !== '' ? hash('sha256', $ip) : '',
                    'gcc' => $giftCardDiscount, 'gcode' => $giftCardCode !== '' ? $giftCardCode : null,
                ]);
            $orderId = (int)$db->lastInsertId();
            $this->markOrderInternal($orderId);

            $istmt = $db->prepare("INSERT INTO store_order_items (site_id, order_id, product_id, variant_id, sku, product_ref, name, variant_name, price_cents, qty, subtotal_cents, discount_cents, discount_net_cents, recommendation_discount_cents, recommendation_discount_net_cents, recommendation_rate, recommendation_source, weight_grams, image)
                VALUES (@site_id, :oid, :pid, :vid, :sku, :pref, :name, '', :price, :qty, :sub, :disc, :discnet, :recdisc, :recdiscnet, :recrate, :recsource, :weight, :img)");
            foreach ($lines as $l) {
                $istmt->execute([
                    'oid' => $orderId, 'pid' => (int)($l['product_id'] ?? 0), 'vid' => ($l['variant_id'] ?? null) ?: null,
                    'sku' => mb_substr((string)($l['sku'] ?? ''), 0, 120), 'pref' => mb_substr((string)($l['product_ref'] ?? $l['ref'] ?? ''), 0, 255),
                    'name' => (string)($l['title'] ?? $l['ref']), 'price' => (int)$l['original_unit_cents'],
                    'qty' => (int)$l['qty'], 'sub' => (int)$l['original_line_cents'], 'img' => (string)($l['image'] ?? ''),
                    'disc' => (int)($l['discount_cents'] ?? 0), 'discnet' => (int)($l['discount_net_cents'] ?? 0),
                    'recdisc' => (int)($l['recommendation_discount_cents'] ?? 0),
                    'recdiscnet' => (int)($l['recommendation_discount_net_cents'] ?? 0),
                    'recrate' => (float)($l['recommendation_rate'] ?? 0) ?: null,
                    'recsource' => (string)($l['rec']['source'] ?? '') ?: null,
                    'weight' => (int)($l['weight_grams'] ?? 0),
                ]);
            }
            if ($pointsUsed > 0 && $pointsCustomerId > 0) {
                $db->prepare("INSERT INTO store_loyalty_ledger (site_id, customer_id, points, reason, order_id, note) VALUES (@site_id, ?, ?, 'redeem', ?, 'Puntos usados en el pedido')")
                    ->execute([$pointsCustomerId, -$pointsUsed, $orderId]);
                $db->prepare("UPDATE store_customers SET points_balance = points_balance - ? WHERE id = ? AND site_id = @site_id")
                    ->execute([$pointsUsed, $pointsCustomerId]);
            }
            if ($giftCardDiscount > 0) {
                $reserve = (new GiftCardService())->reserveForOrder(
                    $orderId,
                    $giftCardCode,
                    (string)($giftCardIn['pin'] ?? ''),
                    $giftCardDiscount
                );
                if (empty($reserve['ok'])) {
                    throw new \RuntimeException((string)($reserve['message'] ?? 'No se pudo aplicar la tarjeta de regalo.'));
                }
            }
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }

        // Tracking & Conversion — nunca bloquea el pedido.
        try {
            TrackingConversionService::onOrderCreated(
                (string)$uuid,
                $lines,
                (int)$total,
                (string)$currency,
                (string)$method,
                (string)$orderNumber
            );
        } catch (\Throwable $e) {
        }
        try {
            (new CartAbandonmentService())->markRecovered(
                (string)$uuid,
                SeoInternalTrafficService::sessionId(),
                (int)$total,
                (int)$cust
            );
        } catch (\Throwable $e) {
        }

        if ($couponId > 0) {
            try {
                (new StoreCouponService())->redeem($couponId);
            } catch (\Throwable $e) {
            }
        }
        if ($quoteCode !== '') {
            try {
                (new QuoteService())->attributeOrder($quoteCode, $uuid, $orderNumber, $total);
            } catch (\Throwable $e) {
            }
        }
        $order = $this->orderById($orderId);
        $addiPayment = null;
        $sistecreditoPayment = null;
        $mercadopagoPayment = null;
        $giftCardPayment = null;
        if ($total === 0 && $giftCardDiscount > 0) {
            $this->markPaid($uuid, 'GIFTCARD-' . $orderNumber, 'gift_card');
            $order = $this->orderById($orderId);
            $giftCardPayment = [
                'method' => 'gift_card',
                'status' => 'paid',
                'redirect_url' => '',
                'paid_with_gift_card' => true,
            ];
        }
        if ($giftCardPayment === null && $method === 'mercadopago' && (new MercadoPagoService())->isAvailable()) {
            $mp = new MercadoPagoService();
            $mpMode = $mp->mode();
            $mpResult = $mpMode === 'checkout_pro' ? $mp->createPreference($order) : ['ok' => true, 'redirect_url' => '', 'status' => 'pending'];
            if (empty($mpResult['ok'])) {
                $this->updateOrder($orderId, ['payment_status' => 'failed']);
                throw new \RuntimeException((string)($mpResult['message'] ?? 'No se pudo iniciar el pago con Mercado Pago.'));
            }
            $mercadopagoPayment = [
                'method' => 'mercadopago',
                'redirect_url' => (string)($mpResult['redirect_url'] ?? ''),
                'status' => (string)($mpResult['status'] ?? 'pending'),
                'mode' => $mpMode,
            ];
            $meta = json_decode((string)($order['payment_meta'] ?? ''), true) ?: [];
            $meta['mercadopago_mode'] = $mpMode;
            $meta['mercadopago_redirect_url'] = $mercadopagoPayment['redirect_url'];
            $meta['mercadopago_status'] = 'pending';
            $db->prepare('UPDATE store_orders SET payment_reference = ?, payment_link = ?, payment_meta = ?, payment_expires_at = ? WHERE id = ? AND site_id = @site_id')
                ->execute([
                    'MP-' . $orderNumber,
                    $mercadopagoPayment['redirect_url'] !== '' ? $mercadopagoPayment['redirect_url'] : null,
                    json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    date('Y-m-d H:i:s', time() + 3600),
                    $orderId,
                ]);
            $order = $this->orderById($orderId);
        }
        if ($giftCardPayment === null && $method === 'sistecredito') {
            $scResult = (new SistecreditoService())->createApplication($order);
            if (empty($scResult['ok'])) {
                $this->updateOrder($orderId, ['payment_status' => 'failed']);
                throw new \RuntimeException((string)($scResult['message'] ?? 'No se pudo iniciar el pago con Sistecrédito.'));
            }
            $sistecreditoPayment = [
                'method' => 'sistecredito',
                'redirect_url' => (string)($scResult['redirect_url'] ?? ''),
                'status' => (string)($scResult['status'] ?? 'pending'),
                'pending' => !empty($scResult['pending']),
            ];
            $meta = json_decode((string)($order['payment_meta'] ?? ''), true) ?: [];
            $meta['sistecredito_redirect_url'] = $sistecreditoPayment['redirect_url'];
            $meta['sistecredito_status'] = $sistecreditoPayment['status'];
            $meta['sistecredito_provider_payment_id'] = (string)($scResult['provider_payment_id'] ?? '');
            $db->prepare('UPDATE store_orders SET payment_reference = ?, payment_meta = ?, payment_expires_at = ? WHERE id = ? AND site_id = @site_id')
                ->execute(['SISTE-' . $orderNumber, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), date('Y-m-d H:i:s', time() + 900), $orderId]);
            $order = $this->orderById($orderId);
        }
        if ($giftCardPayment === null && $method === 'addi') {
            $addiResult = (new AddiService())->createApplication($order);
            if (empty($addiResult['ok'])) {
                $this->updateOrder($orderId, ['payment_status' => 'failed']);
                throw new \RuntimeException((string)($addiResult['message'] ?? 'No se pudo iniciar el pago con Addi.'));
            }
            $addiOrderId = (string)($addiResult['addi_order_id'] ?? '');
            $addiPayment = [
                'method' => 'addi',
                'redirect_url' => (string)$addiResult['redirect_url'],
                'status' => 'REDIRECT',
                'addi_order_id' => $addiOrderId,
            ];
            $meta = json_decode((string)($order['payment_meta'] ?? ''), true) ?: [];
            $meta['addi_order_id'] = $addiOrderId;
            $meta['addi_redirect_url'] = $addiPayment['redirect_url'];
            $meta['addi_status'] = 'REDIRECT';
            $db->prepare('UPDATE store_orders SET payment_reference = ?, payment_link = ?, payment_meta = ?, payment_expires_at = ? WHERE id = ? AND site_id = @site_id')
                ->execute([
                    'ADDI-' . $orderNumber,
                    $addiPayment['redirect_url'] !== '' ? $addiPayment['redirect_url'] : null,
                    json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    date('Y-m-d H:i:s', time() + 7200),
                    $orderId,
                ]);
            $order = $this->orderById($orderId);
        }
        try {
            $this->notifyOrder($order);
        } catch (\Throwable $e) {
        }
        return ['order' => $order, 'payment' => $giftCardPayment ?: ($mercadopagoPayment ?: ($sistecreditoPayment ?: ($addiPayment ?: $breb->paymentInfo($order))))];
    }

    public function orderById(int $id): ?array
    {
        $db = Database::instance();
        $stmt = $db->prepare("SELECT o.*, u.username AS archived_by_name
            FROM store_orders o
            LEFT JOIN users u ON u.id = o.archived_by AND u.site_id = o.site_id
            WHERE o.id = :id AND o.site_id = @site_id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->hydrateOrder($row) : null;
    }

    public function orderByUuid(string $uuid): ?array
    {
        $db = Database::instance();
        $stmt = $db->prepare("SELECT * FROM store_orders WHERE uuid = :uuid AND site_id = @site_id LIMIT 1");
        $stmt->execute(['uuid' => $uuid]);
        $row = $stmt->fetch();
        return $row ? $this->hydrateOrder($row) : null;
    }

    private function orderWhere(array $f, array &$params): string
    {
        $sql = '';
        $archived = (string)($f['archived'] ?? '');
        if ($archived === '1' || $archived === 'archived') {
            $sql .= " AND o.archived_at IS NOT NULL";
        } elseif ($archived !== 'all') {
            $sql .= " AND o.archived_at IS NULL";
        }
        if (isset($f['is_test']) && $f['is_test'] !== '') {
            $sql .= " AND o.is_test = :istest";
            $params['istest'] = ((string)$f['is_test'] === '1') ? 1 : 0;
        }
        if (!empty($f['payment_status'])) { $sql .= " AND o.payment_status = :ps"; $params['ps'] = $f['payment_status']; }
        if (!empty($f['fulfillment_status'])) { $sql .= " AND o.fulfillment_status = :fs"; $params['fs'] = $f['fulfillment_status']; }
        if (!empty($f['payment_method'])) { $sql .= " AND o.payment_method = :pm"; $params['pm'] = $f['payment_method']; }
        if (!empty($f['date_from'])) {
            $ts = strtotime((string)$f['date_from']);
            if ($ts !== false) { $sql .= " AND o.created_at >= :df"; $params['df'] = date('Y-m-d 00:00:00', $ts); }
        }
        if (!empty($f['date_to'])) {
            $ts = strtotime((string)$f['date_to']);
            if ($ts !== false) { $sql .= " AND o.created_at <= :dt"; $params['dt'] = date('Y-m-d 23:59:59', $ts); }
        }
        if (isset($f['min_cents']) && $f['min_cents'] !== '') { $sql .= " AND o.total_cents >= :minc"; $params['minc'] = (int)$f['min_cents']; }
        if (isset($f['max_cents']) && $f['max_cents'] !== '') { $sql .= " AND o.total_cents <= :maxc"; $params['maxc'] = (int)$f['max_cents']; }
        if (!empty($f['search'])) {
            $sql .= " AND (o.customer_name LIKE :q1 OR o.customer_email LIKE :q2 OR o.order_number LIKE :q3 OR o.uuid LIKE :q4)";
            $params['q1'] = '%' . $f['search'] . '%';
            $params['q2'] = '%' . $f['search'] . '%';
            $params['q3'] = '%' . $f['search'] . '%';
            $params['q4'] = '%' . $f['search'] . '%';
        }
        return $sql;
    }

    public function orders(array $f = []): array
    {
        $db = Database::instance();
        $sql = "SELECT o.*, u.username AS archived_by_name
                FROM store_orders o
                LEFT JOIN users u ON u.id = o.archived_by AND u.site_id = o.site_id
                WHERE o.site_id = @site_id";
        $params = [];
        $sql .= $this->orderWhere($f, $params);
        $sql .= " ORDER BY o.id DESC LIMIT " . min((int)($f['limit'] ?? 50), 200);
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return array_map([$this, 'hydrateOrder'], $stmt->fetchAll());
    }

    public function ordersExport(array $f = []): array
    {
        $db = Database::instance();
        $sql = "SELECT o.*, u.username AS archived_by_name
                FROM store_orders o
                LEFT JOIN users u ON u.id = o.archived_by AND u.site_id = o.site_id
                WHERE o.site_id = @site_id";
        $params = [];
        $sql .= $this->orderWhere($f, $params);
        $sql .= " ORDER BY o.id DESC LIMIT 5000";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function orderCounts(): array
    {
        $db = Database::instance();
        try {
            $row = $db->query("SELECT
                SUM(CASE WHEN archived_at IS NULL THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN archived_at IS NOT NULL THEN 1 ELSE 0 END) AS archived,
                SUM(CASE WHEN is_test = 1 THEN 1 ELSE 0 END) AS test_count,
                SUM(CASE WHEN is_test = 0 THEN 1 ELSE 0 END) AS real_count,
                COUNT(*) AS total
                FROM store_orders WHERE site_id = @site_id")->fetch();
        } catch (\Throwable $e) {
            $row = [];
        }
        return [
            'active' => (int)($row['active'] ?? 0),
            'archived' => (int)($row['archived'] ?? 0),
            'tests' => (int)($row['test_count'] ?? 0),
            'real' => (int)($row['real_count'] ?? 0),
            'total' => (int)($row['total'] ?? 0),
        ];
    }

    public function logOrderEvent(int $orderId, string $event, string $from = '', string $to = '', string $reason = '', ?int $userId = null): void
    {
        if ($orderId <= 0) return;
        try {
            $db = Database::instance();
            $db->prepare("INSERT INTO store_order_events (site_id, order_id, event, from_value, to_value, reason, user_id)
                VALUES (@site_id, ?, ?, ?, ?, ?, ?)")->execute([
                $orderId,
                mb_substr($event, 0, 40),
                mb_substr($from, 0, 80),
                mb_substr($to, 0, 80),
                mb_substr($reason, 0, 120),
                $userId ?? \App\Core\Session::userId(),
            ]);
        } catch (\Throwable $e) {
        }
    }

    public function orderEvents(int $orderId, int $limit = 50): array
    {
        $db = Database::instance();
        try {
            $stmt = $db->prepare("SELECT e.*, u.username
                FROM store_order_events e
                LEFT JOIN users u ON u.id = e.user_id AND u.site_id = e.site_id
                WHERE e.site_id = @site_id AND e.order_id = ?
                ORDER BY e.id DESC LIMIT " . max(1, min(200, $limit)));
            $stmt->execute([$orderId]);
            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function archiveOrders(array $ids, bool $archived, string $reason = '', ?int $userId = null): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), function ($id) { return $id > 0; })));
        if (!$ids) return 0;
        $db = Database::instance();
        $in = implode(',', array_fill(0, count($ids), '?'));
        if ($archived) {
            $sql = "UPDATE store_orders SET archived_at = NOW(), archive_reason = ?, archived_by = ?
                    WHERE site_id = @site_id AND id IN ($in)";
            $params = array_merge([mb_substr(trim($reason) ?: 'manual', 0, 40), $userId], $ids);
        } else {
            $sql = "UPDATE store_orders SET archived_at = NULL, archive_reason = NULL, archived_by = NULL
                    WHERE site_id = @site_id AND id IN ($in)";
            $params = $ids;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $count = $stmt->rowCount();
        $event = $archived ? 'archived' : 'restored';
        foreach ($ids as $orderId) {
            $this->logOrderEvent($orderId, $event, $archived ? 'active' : 'archived', $archived ? 'archived' : 'active', $reason, $userId);
        }
        return $count;
    }

    public function archiveOrdersByFilters(array $f, bool $archived, string $reason = '', ?int $userId = null): int
    {
        $db = Database::instance();
        $params = [];
        $where = $this->orderWhere($f, $params);
        $sql = "SELECT o.id FROM store_orders o WHERE o.site_id = @site_id $where ORDER BY o.id DESC LIMIT 500";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return $this->archiveOrders($ids, $archived, $reason, $userId);
    }

    public function markOrdersTest(array $ids, bool $isTest): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), function ($id) { return $id > 0; })));
        if (!$ids) return 0;
        $db = Database::instance();
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare("UPDATE store_orders SET is_test = ? WHERE site_id = @site_id AND id IN ($in)");
        $stmt->execute(array_merge([$isTest ? 1 : 0], $ids));
        $count = $stmt->rowCount();
        foreach ($ids as $orderId) {
            $this->logOrderEvent($orderId, $isTest ? 'test_marked' : 'test_unmarked', $isTest ? '0' : '1', $isTest ? '1' : '0');
        }
        return $count;
    }

    public function detectTestOrders(int $limit = 200): array
    {
        $db = Database::instance();
        $limit = max(1, min(500, $limit));
        $sql = "SELECT o.*, u.username AS archived_by_name
                FROM store_orders o
                LEFT JOIN users u ON u.id = o.archived_by AND u.site_id = o.site_id
                WHERE o.site_id = @site_id AND o.archived_at IS NULL AND o.is_test = 0 AND (
                    o.order_number LIKE 'TEST-%'
                    OR LOWER(o.customer_email) LIKE '%@test%'
                    OR LOWER(o.customer_email) LIKE '%@example.com%'
                    OR LOWER(o.customer_email) LIKE '%testuser%'
                    OR LOWER(o.customer_name) LIKE '%test%'
                    OR LOWER(o.customer_name) LIKE '%prueba%'
                    OR LOWER(o.customer_name) LIKE '%alerta%'
                    OR o.total_cents <= 100000
                )
                ORDER BY o.id DESC LIMIT $limit";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $reasons = [];
            $number = strtoupper((string)($row['order_number'] ?? ''));
            $email = strtolower((string)($row['customer_email'] ?? ''));
            $name = strtolower((string)($row['customer_name'] ?? ''));
            if (str_starts_with($number, 'TEST-')) $reasons[] = 'Número TEST-';
            if (str_contains($email, '@test') || str_contains($email, '@example.com') || str_contains($email, 'testuser')) $reasons[] = 'Correo de prueba';
            if (str_contains($name, 'test') || str_contains($name, 'prueba') || str_contains($name, 'alerta')) $reasons[] = 'Nombre de prueba';
            if ((int)($row['total_cents'] ?? 0) <= 100000) $reasons[] = 'Monto mínimo';
            $row['test_reasons'] = array_values(array_unique($reasons));
        }
        unset($row);
        return $rows;
    }

    public function autoArchiveOrders(): array
    {
        $s = $this->settings();
        if (($s['store_auto_archive_enabled'] ?? '0') !== '1') {
            return ['ok' => true, 'enabled' => false, 'failed' => 0, 'tests' => 0];
        }
        $failedDays = max(1, (int)($s['store_archive_failed_days'] ?: 30));
        $testDays = max(1, (int)($s['store_archive_test_days'] ?: 7));
        $failedCutoff = date('Y-m-d H:i:s', time() - ($failedDays * 86400));
        $testCutoff = date('Y-m-d H:i:s', time() - ($testDays * 86400));
        $db = Database::instance();
        $failed = 0;
        $tests = 0;
        try {
            $stmt = $db->prepare("UPDATE store_orders
                SET archived_at = NOW(), archive_reason = 'auto_failed', archived_by = NULL
                WHERE site_id = @site_id AND archived_at IS NULL AND is_test = 0
                  AND (payment_status = 'failed' OR fulfillment_status = 'cancelled')
                  AND created_at < :cutoff");
            $stmt->execute(['cutoff' => $failedCutoff]);
            $failed = $stmt->rowCount();
        } catch (\Throwable $e) {
        }
        try {
            $stmt = $db->prepare("UPDATE store_orders
                SET archived_at = NOW(), archive_reason = 'auto_test', archived_by = NULL
                WHERE site_id = @site_id AND archived_at IS NULL AND is_test = 1
                  AND created_at < :cutoff");
            $stmt->execute(['cutoff' => $testCutoff]);
            $tests = $stmt->rowCount();
        } catch (\Throwable $e) {
        }
        return ['ok' => true, 'enabled' => true, 'failed' => $failed, 'tests' => $tests];
    }

    public function updateOrder(int $id, array $d): void
    {
        $db = Database::instance();
        $oldStmt = $db->prepare("SELECT payment_status, fulfillment_status FROM store_orders WHERE id = :id AND site_id = @site_id LIMIT 1");
        $oldStmt->execute(['id' => $id]);
        $oldRow = $oldStmt->fetch() ?: [];
        $sets = [];
        $params = ['id' => $id];
        if (!empty($d['fulfillment_status']) && in_array($d['fulfillment_status'], ['new', 'confirmed', 'preparing', 'shipped', 'delivered', 'cancelled'], true)) {
            $sets[] = "fulfillment_status = :fs";
            $params['fs'] = $d['fulfillment_status'];
        }
        if (!empty($d['payment_status']) && in_array($d['payment_status'], ['pending', 'paid', 'failed', 'refunded'], true)) {
            $sets[] = "payment_status = :ps";
            $params['ps'] = $d['payment_status'];
            if ($d['payment_status'] === 'paid') $sets[] = "paid_at = NOW()";
        }
        if (array_key_exists('admin_notes', $d)) { $sets[] = "admin_notes = :an"; $params['an'] = (string)$d['admin_notes']; }
        if (!$sets) return;
        $db->prepare("UPDATE store_orders SET " . implode(', ', $sets) . " WHERE id = :id AND site_id = @site_id")->execute($params);
        try {
            $newStmt = $db->prepare("SELECT payment_status, fulfillment_status FROM store_orders WHERE id = :id AND site_id = @site_id LIMIT 1");
            $newStmt->execute(['id' => $id]);
            $newRow = $newStmt->fetch() ?: [];
            $changes = [];
            if ((string)($oldRow['payment_status'] ?? '') !== (string)($newRow['payment_status'] ?? '')) {
                $changes[] = 'pago: ' . ($oldRow['payment_status'] ?? '—') . ' → ' . ($newRow['payment_status'] ?? '—');
            }
            if ((string)($oldRow['fulfillment_status'] ?? '') !== (string)($newRow['fulfillment_status'] ?? '')) {
                $changes[] = 'estado: ' . ($oldRow['fulfillment_status'] ?? '—') . ' → ' . ($newRow['fulfillment_status'] ?? '—');
            }
            if ($changes) $this->logOrderEvent($id, 'status_update', '', implode('; ', $changes));
        } catch (\Throwable $e) {
        }
        $reverse = in_array((string)($d['payment_status'] ?? ''), ['failed', 'refunded'], true) || (string)($d['fulfillment_status'] ?? '') === 'cancelled';
        if ($reverse) {
            try { (new GiftCardService())->onOrderFailed($id, 'Pedido ' . (string)($d['payment_status'] ?? $d['fulfillment_status'] ?? 'anulado')); } catch (\Throwable $e) {}
        }
        if ($reverse || (string)($d['payment_status'] ?? '') === 'paid') {
            try {
                $st = $db->prepare('SELECT uuid FROM store_orders WHERE id = ? AND site_id = @site_id LIMIT 1');
                $st->execute([$id]);
                $uuid = (string)$st->fetchColumn();
                if ($uuid !== '') {
                    if ($reverse) {
                        (new \App\Services\AffiliateService())->reverseOrder($uuid, 'Pedido ' . (string)($d['payment_status'] ?? $d['fulfillment_status'] ?? 'anulado'));
                        TrackingConversionService::onOrderCancelled($uuid, 'Pedido ' . (string)($d['payment_status'] ?? $d['fulfillment_status'] ?? 'anulado'));
                    } elseif ((string)($d['payment_status'] ?? '') === 'paid') {
                        try { (new GiftCardService())->onOrderPaid($uuid); } catch (\Throwable $e) {}
                        TrackingConversionService::onOrderPaid($uuid);
                        (new CartAbandonmentService())->markConverted($uuid);
                    }
                }
            } catch (\Throwable $e) {
            }
        }
    }

    public function markPaid(string $uuid, string $providerRef, string $method): bool
    {
        $db = Database::instance();
        $stmt = $db->prepare("UPDATE store_orders SET payment_status = 'paid', paid_at = NOW(), provider_ref = :ref, payment_method = :pm,
            archived_at = CASE WHEN is_test = 1 THEN archived_at ELSE NULL END,
            archive_reason = CASE WHEN is_test = 1 THEN archive_reason ELSE NULL END,
            archived_by = CASE WHEN is_test = 1 THEN archived_by ELSE NULL END
            WHERE uuid = :uuid AND site_id = @site_id AND payment_status <> 'paid'");
        $stmt->execute(['ref' => $providerRef, 'pm' => $method, 'uuid' => $uuid]);
        $changed = $stmt->rowCount() >= 1;
        if ($changed) {
            $db->prepare("UPDATE store_customers c JOIN store_orders o ON o.customer_id = c.id AND o.site_id = c.site_id
                SET c.orders_count = c.orders_count + 1, c.total_spent_cents = c.total_spent_cents + o.total_cents
                WHERE o.uuid = :uuid AND o.site_id = @site_id")->execute(['uuid' => $uuid]);
            try {
                (new LoyaltyService())->awardForOrder($uuid);
            } catch (\Throwable $e) {
            }
        }
        try {
            (new GiftCardService())->onOrderPaid($uuid);
        } catch (\Throwable $e) {
        }
        try {
            TrackingConversionService::onOrderPaid($uuid);
        } catch (\Throwable $e) {
        }
        try {
            (new CartAbandonmentService())->markConverted($uuid);
        } catch (\Throwable $e) {
        }
        return $changed;
    }

    public function stats(bool $includeTests = false): array
    {
        $db = Database::instance();
        $where = $includeTests ? '' : ' AND is_test = 0';
        $stmt = $db->prepare("SELECT
            COUNT(*) AS orders_total,
            SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) AS orders_paid,
            SUM(CASE WHEN payment_status = 'paid' THEN total_cents ELSE 0 END) AS revenue_cents,
            SUM(CASE WHEN fulfillment_status = 'new' THEN 1 ELSE 0 END) AS orders_new,
            SUM(CASE WHEN fulfillment_status = 'shipped' THEN 1 ELSE 0 END) AS orders_shipped
            FROM store_orders WHERE site_id = @site_id$where");
        $stmt->execute();
        $o = $stmt->fetch() ?: [];
        $p = $db->prepare("SELECT COUNT(*) AS products_total, SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS products_active,
            SUM(CASE WHEN track_stock = 1 AND stock <= 3 THEN 1 ELSE 0 END) AS products_low_stock FROM store_products WHERE site_id = @site_id");
        $p->execute();
        $pr = $p->fetch() ?: [];
        return array_merge(
            array_map(fn($v) => (int)$v, array_intersect_key($o, array_flip(['orders_total', 'orders_paid', 'revenue_cents', 'orders_new', 'orders_shipped']))),
            array_map(fn($v) => (int)$v, array_intersect_key($pr, array_flip(['products_total', 'products_active', 'products_low_stock'])))
        );
    }

    // ── Helpers ──

    /** Marca el pedido como interno si se creó desde un equipo del negocio. */
    private function markOrderInternal(int $orderId): void
    {
        try {
            $internal = SeoInternalTrafficService::detect();
            if (empty($internal['internal'])) return;
            Database::instance()->prepare('UPDATE store_orders SET is_internal = 1, internal_reason = ? WHERE id = ? AND site_id = @site_id')
                ->execute([mb_substr((string)($internal['reason'] ?? 'internal'), 0, 40), $orderId]);
        } catch (\Throwable $e) {
        }
    }

    private function assertRateLimit(array $settings, array $input, string $ip): void
    {
        $maxIp = (int)($settings['store_max_orders_hour_ip'] ?? 0) ?: 8;
        $maxEmail = (int)($settings['store_max_orders_hour_email'] ?? 0) ?: 4;
        $db = Database::instance();
        if ($ip !== '') {
            $stmt = $db->prepare("SELECT COUNT(*) FROM store_orders WHERE site_id = @site_id AND ip_hash = :h AND created_at >= (NOW() - INTERVAL 1 HOUR)");
            $stmt->execute(['h' => hash('sha256', $ip)]);
            if ((int)$stmt->fetchColumn() >= $maxIp) {
                throw new StoreRateLimitException('Demasiados pedidos desde tu conexion. Intenta de nuevo mas tarde o escribenos por WhatsApp.');
            }
        }
        $email = trim((string)($input['customer']['email'] ?? ''));
        if ($email !== '') {
            $stmt = $db->prepare("SELECT COUNT(*) FROM store_orders WHERE site_id = @site_id AND customer_email = :e AND created_at >= (NOW() - INTERVAL 1 HOUR)");
            $stmt->execute(['e' => $email]);
            if ((int)$stmt->fetchColumn() >= $maxEmail) {
                throw new StoreRateLimitException('Ya registramos varios pedidos con este correo. Espera unos minutos o contactanos.');
            }
        }
    }

    public function releaseExpiredOrders(?int $siteId = null, int $minutes = 60): array
    {
        if (!$this->ready()) return ['released' => 0, 'orders' => []];
        $db = Database::instance();
        $sql = "SELECT id, site_id, uuid, order_number FROM store_orders
                WHERE payment_status = 'pending' AND payment_method NOT IN ('cod', 'breb', 'mercadopago', 'epayco', 'addi', 'request') AND stock_released = 0
                AND created_at <= (NOW() - INTERVAL :m MINUTE)";
        $params = ['m' => max(5, $minutes)];
        if ($siteId !== null) {
            $sql .= " AND site_id = :sid";
            $params['sid'] = $siteId;
        }
        $sql .= " ORDER BY id ASC LIMIT 50";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll();
        $released = [];
        foreach ($orders as $o) {
            $items = $db->prepare("SELECT product_id, variant_id, qty FROM store_order_items WHERE order_id = :oid AND site_id = :sid");
            $items->execute(['oid' => (int)$o['id'], 'sid' => (int)$o['site_id']]);
            foreach ($items->fetchAll() as $it) {
                if (!empty($it['variant_id'])) {
                    $db->prepare("UPDATE store_variants SET stock = stock + :q WHERE id = :id AND site_id = :sid")
                        ->execute(['q' => (int)$it['qty'], 'id' => (int)$it['variant_id'], 'sid' => (int)$o['site_id']]);
                } elseif (!empty($it['product_id'])) {
                    $db->prepare("UPDATE store_products SET stock = stock + :q WHERE id = :id AND site_id = :sid AND track_stock = 1")
                        ->execute(['q' => (int)$it['qty'], 'id' => (int)$it['product_id'], 'sid' => (int)$o['site_id']]);
                }
            }
            $db->prepare("UPDATE store_orders SET payment_status = 'failed', fulfillment_status = 'cancelled', stock_released = 1,
                admin_notes = CONCAT(COALESCE(admin_notes, ''), '\n[auto] Stock liberado por pago no completado') WHERE id = :id AND site_id = :sid")
                ->execute(['id' => (int)$o['id'], 'sid' => (int)$o['site_id']]);
            try {
                (new LoyaltyService())->reverseOrderPoints((int)$o['id'], 'release');
            } catch (\Throwable $e) {
            }
            try {
                (new GiftCardService())->onOrderFailed((int)$o['id'], 'Pago no completado');
            } catch (\Throwable $e) {
            }
            $released[] = $o['order_number'] ?: $o['uuid'];
        }
        return ['released' => count($released), 'orders' => $released];
    }

    private function upsertCustomer(string $name, string $email, string $phone, string $doc, string $addressJson): int
    {
        $db = Database::instance();
        $stmt = $db->prepare("SELECT id FROM store_customers WHERE site_id = @site_id AND email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $id = $stmt->fetchColumn();
        if ($id) {
            $db->prepare("UPDATE store_customers SET name = :name, phone = :phone, document = :doc, address = :addr WHERE id = :id AND site_id = @site_id")
                ->execute(['name' => $name, 'phone' => $phone, 'doc' => $doc, 'addr' => $addressJson, 'id' => (int)$id]);
            return (int)$id;
        }
        $db->prepare("INSERT INTO store_customers (site_id, name, email, phone, document, address) VALUES (@site_id, :name, :email, :phone, :doc, :addr)")
            ->execute(['name' => $name, 'email' => $email, 'phone' => $phone, 'doc' => $doc, 'addr' => $addressJson]);
        return (int)$db->lastInsertId();
    }

    private function notifyOrder(array $order): void
    {
        try {
            $s = $this->settings();
            $svc = new EmailService();
            $currency = $order['currency'] ?? 'COP';
            $lines = '';
            foreach (($order['items'] ?? []) as $it) {
                $lines .= '- ' . $it['qty'] . ' x ' . $it['name'] . ($it['variant_name'] ? ' (' . $it['variant_name'] . ')' : '') . ' = ' . $this->money((int)$it['subtotal_cents'], $currency) . "\n";
            }
            $baseUrl = rtrim((string)\App\Core\Config::get('APP_URL', ''), '/');
            $orderUrl = $baseUrl . '/pedido/' . (string)$order['uuid'];
            $methodLabel = match ((string)$order['payment_method']) {
                'breb' => 'Bre-B',
                'mercadopago' => 'Mercado Pago',
                'epayco' => 'ePayco',
                'addi' => 'Addi (crédito)',
                'sistecredito' => 'Sistecrédito',
                'wompi' => 'Wompi',
                'cod' => 'Contra entrega',
                'gift_card' => 'Tarjeta de regalo',
                default => 'Pago asistido',
            };
            $body = "Pedido " . $order['order_number'] . "\n\n" . $lines . "\nSubtotal: " . $this->money((int)$order['subtotal_cents'], $currency)
                . "\nDescuento: " . $this->money((int)($order['discount_cents'] ?? 0), $currency)
                . "\nIVA incluido: " . $this->money((int)($order['tax_cents'] ?? 0), $currency)
                . "\nEnvio: " . $this->money((int)$order['shipping_cents'], $currency)
                . "\nTotal: " . $this->money((int)$order['total_cents'], $currency)
                . "\n\nMetodo de pago: " . $methodLabel;
            $addr = (array)($order['shipping_address'] ?? []);
            $delivery = trim(implode(', ', array_filter([
                (string)($addr['line1'] ?? ''),
                (string)($addr['city'] ?? ''),
                (string)($addr['region'] ?? ''),
            ], fn($v) => trim($v) !== '')));
            if ($delivery !== '') $body .= "\nEntrega: " . $delivery;
            if (!empty($addr['notes'])) $body .= "\nIndicaciones: " . (string)$addr['notes'];
            $body .= "\n\nPuedes ver el estado y continuar el pago aqui:\n" . $orderUrl;
            if ((string)$order['payment_method'] === 'breb') {
                $body .= "\n\nEscanea el QR de Bre-B y adjunta tu comprobante en la pagina del pedido. El pedido queda pendiente de verificacion.";
            } elseif (in_array((string)$order['payment_method'], ['mercadopago', 'epayco'], true) && !empty($order['payment_link'])) {
                $body .= "\n\nCompleta el pago aqui:\n" . (string)$order['payment_link'];
            } elseif ((string)$order['payment_method'] === 'addi') {
                $addiMeta = json_decode((string)($order['payment_meta'] ?? ''), true) ?: [];
                if (!empty($addiMeta['addi_redirect_url'])) $body .= "\n\nCompleta tu solicitud de crédito Addi aquí:\n" . (string)$addiMeta['addi_redirect_url'];
            } elseif ((string)$order['payment_method'] === 'sistecredito') {
                $scMeta = json_decode((string)($order['payment_meta'] ?? ''), true) ?: [];
                if (!empty($scMeta['sistecredito_redirect_url'])) $body .= "\n\nCompleta tu solicitud de crédito Sistecrédito aquí:\n" . (string)$scMeta['sistecredito_redirect_url'];
            }
            $body .= "\n\nTu pedido queda sujeto a confirmacion del pago y disponibilidad de los productos.";
            $svc->send($order['customer_email'], 'Pedido recibido ' . $order['order_number'], $body);
            $notify = $s['store_notify_email'];
            if ($notify !== '' && filter_var($notify, FILTER_VALIDATE_EMAIL)) {
                $svc->send($notify, 'Nuevo pedido ' . $order['order_number'], $body);
            }
        } catch (\Throwable $e) {
        }
    }

    private function hydrateProduct(array $row): array
    {
        $row['images'] = json_decode((string)($row['images'] ?? ''), true) ?: [];
        $row['tags'] = json_decode((string)($row['tags'] ?? ''), true) ?: [];
        $row['price_cents'] = (int)$row['price_cents'];
        $row['compare_price_cents'] = (int)$row['compare_price_cents'];
        $row['stock'] = (int)$row['stock'];
        $row['track_stock'] = (int)$row['track_stock'];
        $row['free_shipping'] = (int)($row['free_shipping'] ?? 0);
        $row['tax_exempt'] = (int)($row['tax_exempt'] ?? 0);
        $row['visibility'] = (string)($row['visibility'] ?? 'public');
        $row['allowed_ips'] = (string)($row['allowed_ips'] ?? '');
        return $row;
    }

    private function hydrateOrder(array $row): array
    {
        $db = Database::instance();
        $stmt = $db->prepare("SELECT * FROM store_order_items WHERE order_id = :oid AND site_id = @site_id ORDER BY id ASC");
        $stmt->execute(['oid' => (int)$row['id']]);
        $row['items'] = $stmt->fetchAll();
        $row['shipping_address'] = json_decode((string)($row['shipping_address'] ?? ''), true) ?: [];
        return $row;
    }

    private function assertSlugFree(string $table, string $slug, ?int $id): void
    {
        if (!in_array($table, ['store_products', 'store_categories'], true)) return;
        $stmt = Database::instance()->prepare("SELECT id FROM $table WHERE slug = :slug AND site_id = @site_id LIMIT 1");
        $stmt->execute(['slug' => $slug]);
        $found = $stmt->fetchColumn();
        if ($found && (int)$found !== (int)$id) {
            throw new \InvalidArgumentException('Ya existe un registro con ese slug: ' . $slug);
        }
    }

    private function slugify(string $s): string    {
        $s = strtolower(trim($s));
        $s = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ñ', 'ü'], ['a', 'e', 'i', 'o', 'u', 'n', 'u'], $s);
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?: '';
        $s = trim($s, '-');
        return $s !== '' ? $s : 'item-' . substr(bin2hex(random_bytes(3)), 0, 5);
    }

    private function uuid4(): string
    {
        $d = random_bytes(16);
        $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
        $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
    }

    public function money(int $cents, string $currency = 'COP'): string
    {
        $v = $cents / 100;
        $sym = $currency === 'USD' ? 'US$' : ($currency === 'COP' ? '$' : $currency . ' ');
        return $sym . number_format($v, $currency === 'COP' ? 0 : 2, ',', '.');
    }
}
