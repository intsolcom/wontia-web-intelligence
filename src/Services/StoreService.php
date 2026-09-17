<?php
namespace App\Services;

use App\Core\Database;

class StoreService
{
    public const TABLES = ['store_categories', 'store_products', 'store_variants', 'store_customers', 'store_orders', 'store_order_items', 'store_shipping_zones'];

    public const SETTING_KEYS = [
        'store_enabled', 'store_currency', 'store_payment_methods', 'store_wompi_public_key',
        'store_wompi_integrity_key', 'store_wompi_events_key', 'store_notify_email',
        'store_shipping_note', 'store_min_order_cents', 'store_whatsapp', 'store_terms_url',
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
        if (!$missing) return ['ok' => true, 'created' => 0, 'missing' => []];

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
        return ['ok' => true, 'created' => $created, 'missing' => $missing];
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
        $m = json_decode($s['store_payment_methods'] ?: '[]', true) ?: [];
        $m = array_values(array_intersect($m, self::PAYMENT_METHODS));
        if ($s['store_wompi_public_key'] === '' || $s['store_wompi_integrity_key'] === '') {
            $m = array_values(array_diff($m, ['wompi']));
        }
        return $m ?: ['cod'];
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
        $sql = "SELECT c.*, (SELECT COUNT(*) FROM store_products p WHERE p.category_id = c.id AND p.site_id = @site_id AND p.status = 'active') AS product_count
                FROM store_categories c WHERE c.site_id = @site_id";
        if ($activeOnly) $sql .= " AND c.is_active = 1";
        $sql .= " ORDER BY c.sort_order ASC, c.name ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function saveCategory(array $d, ?int $id = null): int
    {
        $db = Database::instance();
        $name = trim((string)($d['name'] ?? ''));
        if ($name === '') throw new \InvalidArgumentException('Nombre requerido');
        $slug = trim((string)($d['slug'] ?? '')) ?: $this->slugify($name);
        $this->assertSlugFree('store_categories', $slug, $id);
        $params = [
            'name' => $name, 'slug' => $slug,
            'description' => (string)($d['description'] ?? ''),
            'image' => (string)($d['image'] ?? ''),
            'sort_order' => (int)($d['sort_order'] ?? 0),
            'is_active' => isset($d['is_active']) ? (int)(bool)$d['is_active'] : 1,
        ];
        if ($id) {
            $params['id'] = $id;
            $db->prepare("UPDATE store_categories SET name=:name, slug=:slug, description=:description, image=:image, sort_order=:sort_order, is_active=:is_active WHERE id=:id AND site_id=@site_id")->execute($params);
            return $id;
        }
        $db->prepare("INSERT INTO store_categories (site_id, name, slug, description, image, sort_order, is_active) VALUES (@site_id, :name, :slug, :description, :image, :sort_order, :is_active)")->execute($params);
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
        $sql .= " ORDER BY p.sort_order ASC, p.id DESC";
        $limit = (int)($f['limit'] ?? 0);
        if ($limit > 0) $sql .= " LIMIT " . min($limit, 200);
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
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
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
        $stmt = $db->prepare("SELECT * FROM store_products WHERE slug = :slug AND site_id = @site_id AND status = 'active' LIMIT 1");
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();
        return $row ? $this->hydrateProduct($row) : null;
    }

    public function saveProduct(array $d, ?int $id = null): int
    {
        $db = Database::instance();
        $name = trim((string)($d['name'] ?? ''));
        if ($name === '') throw new \InvalidArgumentException('Nombre requerido');
        $slug = trim((string)($d['slug'] ?? '')) ?: $this->slugify($name);
        $this->assertSlugFree('store_products', $slug, $id);
        $params = [
            'category_id' => !empty($d['category_id']) ? (int)$d['category_id'] : null,
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
        ];
        if ($id) {
            $params['id'] = $id;
            $db->prepare("UPDATE store_products SET category_id=:category_id, name=:name, slug=:slug, description=:description, short_description=:short_description,
                price_cents=:price_cents, compare_price_cents=:compare_price_cents, currency=:currency, sku=:sku, stock=:stock, track_stock=:track_stock,
                weight_grams=:weight_grams, images=:images, tags=:tags, status=:status, is_featured=:is_featured, seo_title=:seo_title,
                seo_description=:seo_description, sort_order=:sort_order WHERE id=:id AND site_id=@site_id")->execute($params);
            $productId = $id;
        } else {
            $db->prepare("INSERT INTO store_products (site_id, category_id, name, slug, description, short_description, price_cents, compare_price_cents,
                currency, sku, stock, track_stock, weight_grams, images, tags, status, is_featured, seo_title, seo_description, sort_order)
                VALUES (@site_id, :category_id, :name, :slug, :description, :short_description, :price_cents, :compare_price_cents, :currency, :sku, :stock,
                :track_stock, :weight_grams, :images, :tags, :status, :is_featured, :seo_title, :seo_description, :sort_order)")->execute($params);
            $productId = (int)$db->lastInsertId();
        }
        if (array_key_exists('variants', $d)) $this->saveVariants($productId, (array)$d['variants']);
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

    public function createOrder(array $input): array
    {
        if (!$this->ready()) throw new \RuntimeException('La tienda no esta configurada');
        $settings = $this->settings();
        if ($settings['store_enabled'] !== '1' && $settings['store_enabled'] !== '') throw new \RuntimeException('La tienda esta deshabilitada');

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
            foreach ($items as $it) {
                $pid = (int)($it['product_id'] ?? 0);
                $qty = max(1, min(99, (int)($it['qty'] ?? 1)));
                $vid = !empty($it['variant_id']) ? (int)$it['variant_id'] : null;
                $p = $this->product($pid);
                if (!$p || $p['status'] !== 'active') throw new \RuntimeException('Producto no disponible');
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
                $lines[] = [
                    'product_id' => $pid, 'variant_id' => $vid, 'name' => $p['name'], 'variant_name' => $variantName,
                    'price_cents' => $unit, 'qty' => $qty, 'subtotal_cents' => $lineTotal,
                    'image' => $p['images'][0] ?? '',
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
                $shippingCents = (int)$zone['cost_cents'];
                if ((int)$zone['free_over_cents'] > 0 && $subtotal >= (int)$zone['free_over_cents']) $shippingCents = 0;
            }

            $total = $subtotal + $shippingCents;
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

            $db->prepare("INSERT INTO store_orders (site_id, uuid, order_number, customer_id, customer_name, customer_email, customer_phone, customer_document,
                shipping_address, shipping_zone_id, subtotal_cents, shipping_cents, discount_cents, total_cents, currency, payment_method, payment_status,
                fulfillment_status, notes) VALUES (@site_id, :uuid, :num, :cid, :name, :email, :phone, :doc, :addr, :zone, :sub, :ship, 0, :total, :cur, :pm,
                'pending', 'new', :notes)")
                ->execute([
                    'uuid' => $uuid, 'num' => $orderNumber, 'cid' => $cust, 'name' => $name, 'email' => $email, 'phone' => $phone,
                    'doc' => (string)($customer['document'] ?? ''), 'addr' => $addressJson, 'zone' => $zoneId,
                    'sub' => $subtotal, 'ship' => $shippingCents, 'total' => $total, 'cur' => $settings['store_currency'],
                    'pm' => $method, 'notes' => (string)($input['notes'] ?? ''),
                ]);
            $orderId = (int)$db->lastInsertId();

            $istmt = $db->prepare("INSERT INTO store_order_items (site_id, order_id, product_id, variant_id, name, variant_name, price_cents, qty, subtotal_cents, image)
                VALUES (@site_id, :oid, :pid, :vid, :name, :vname, :price, :qty, :sub, :img)");
            foreach ($lines as $l) {
                $istmt->execute([
                    'oid' => $orderId, 'pid' => $l['product_id'], 'vid' => $l['variant_id'], 'name' => $l['name'],
                    'vname' => $l['variant_name'], 'price' => $l['price_cents'], 'qty' => $l['qty'], 'sub' => $l['subtotal_cents'], 'img' => $l['image'],
                ]);
            }

            $db->commit();

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

    public function orderById(int $id): ?array
    {
        $db = Database::instance();
        $stmt = $db->prepare("SELECT * FROM store_orders WHERE id = :id AND site_id = @site_id LIMIT 1");
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

    public function orders(array $f = []): array
    {
        $db = Database::instance();
        $sql = "SELECT * FROM store_orders WHERE site_id = @site_id";
        $params = [];
        if (!empty($f['payment_status'])) { $sql .= " AND payment_status = :ps"; $params['ps'] = $f['payment_status']; }
        if (!empty($f['fulfillment_status'])) { $sql .= " AND fulfillment_status = :fs"; $params['fs'] = $f['fulfillment_status']; }
        if (!empty($f['search'])) {
            $sql .= " AND (customer_name LIKE :q1 OR customer_email LIKE :q2 OR order_number LIKE :q3 OR uuid LIKE :q4)";
            $params['q1'] = '%' . $f['search'] . '%';
            $params['q2'] = '%' . $f['search'] . '%';
            $params['q3'] = '%' . $f['search'] . '%';
            $params['q4'] = '%' . $f['search'] . '%';
        }
        $sql .= " ORDER BY id DESC LIMIT " . min((int)($f['limit'] ?? 50), 200);
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return array_map([$this, 'hydrateOrder'], $stmt->fetchAll());
    }

    public function updateOrder(int $id, array $d): void
    {
        $db = Database::instance();
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
    }

    public function markPaid(string $uuid, string $providerRef, string $method): bool
    {
        $db = Database::instance();
        $stmt = $db->prepare("UPDATE store_orders SET payment_status = 'paid', paid_at = NOW(), provider_ref = :ref, payment_method = :pm
            WHERE uuid = :uuid AND site_id = @site_id AND payment_status <> 'paid'");
        $stmt->execute(['ref' => $providerRef, 'pm' => $method, 'uuid' => $uuid]);
        if ($stmt->rowCount() < 1) return false;
        $db->prepare("UPDATE store_customers c JOIN store_orders o ON o.customer_id = c.id AND o.site_id = c.site_id
            SET c.orders_count = c.orders_count + 1, c.total_spent_cents = c.total_spent_cents + o.total_cents
            WHERE o.uuid = :uuid AND o.site_id = @site_id")->execute(['uuid' => $uuid]);
        return true;
    }

    public function stats(): array
    {
        $db = Database::instance();
        $stmt = $db->prepare("SELECT
            COUNT(*) AS orders_total,
            SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) AS orders_paid,
            SUM(CASE WHEN payment_status = 'paid' THEN total_cents ELSE 0 END) AS revenue_cents,
            SUM(CASE WHEN fulfillment_status = 'new' THEN 1 ELSE 0 END) AS orders_new,
            SUM(CASE WHEN fulfillment_status = 'shipped' THEN 1 ELSE 0 END) AS orders_shipped
            FROM store_orders WHERE site_id = @site_id");
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
            $body = "Pedido " . $order['order_number'] . "\n\n" . $lines . "\nSubtotal: " . $this->money((int)$order['subtotal_cents'], $currency)
                . "\nEnvio: " . $this->money((int)$order['shipping_cents'], $currency)
                . "\nTotal: " . $this->money((int)$order['total_cents'], $currency)
                . "\n\nMetodo de pago: " . $order['payment_method'];
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
