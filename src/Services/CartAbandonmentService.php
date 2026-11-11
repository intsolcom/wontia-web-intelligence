<?php
namespace App\Services;

use App\Core\Database;

/**
 * Carritos abandonados — captura desde el beacon público, métricas y recuperación.
 * Multi-tenant. No reemplaza store_carts; unifica invitados y clientes con sesión.
 */
class CartAbandonmentService
{
    private static bool $ensured = false;
    private static bool $backfilled = false;
    public const ABANDON_MINUTES = 30;

    public function ensureTables(): bool
    {
        if (self::$ensured) return true;
        $db = Database::instance();
        try {
            $db->query('SELECT 1 FROM store_abandoned_carts LIMIT 1');
            self::$ensured = true;
            return true;
        } catch (\Throwable $e) {
        }
        $path = (defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 2)) . '/install/abandoned_carts.sql';
        if (!is_file($path)) return false;
        $sql = preg_replace('/^\s*--.*$/m', '', (string)file_get_contents($path));
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            try { $db->exec($statement); } catch (\Throwable $e) {}
        }
        try {
            $db->query('SELECT 1 FROM store_abandoned_carts LIMIT 1');
            self::$ensured = true;
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function capture(string $sessionId, array $items, array $meta = []): array
    {
        if (!$this->ensureTables()) return ['ok' => false, 'message' => 'Tabla no disponible'];
        $sessionId = substr(preg_replace('/[^a-zA-Z0-9_-]/', '', $sessionId), 0, 64);
        if ($sessionId === '') return ['ok' => false, 'message' => 'Sesión vacía'];

        $clean = [];
        $qty = 0;
        $valueCents = 0;
        foreach (array_slice($items, 0, 50) as $item) {
            if (!is_array($item)) continue;
            $q = max(1, min(99, (int)($item['qty'] ?? 1)));
            $unitCents = isset($item['price_cents']) ? (int)$item['price_cents'] : (int)round(((float)($item['price'] ?? 0)) * 100);
            if ($unitCents < 0) $unitCents = 0;
            $handle = mb_substr(trim((string)($item['handle'] ?? $item['ref'] ?? '')), 0, 160);
            $title = mb_substr(trim((string)($item['title'] ?? $item['name'] ?? '')), 0, 255);
            if ($handle === '' && $title === '') continue;
            $clean[] = [
                'handle' => $handle,
                'title' => $title,
                'sku' => mb_substr(trim((string)($item['sku'] ?? '')), 0, 120),
                'product_id' => mb_substr(trim((string)($item['product_id'] ?? '')), 0, 80),
                'image' => mb_substr(trim((string)($item['img'] ?? $item['image'] ?? '')), 0, 500),
                'price' => round($unitCents / 100, 2),
                'price_cents' => $unitCents,
                'qty' => $q,
            ];
            $qty += $q;
            $valueCents += $unitCents * $q;
        }
        if (!$clean) return ['ok' => false, 'message' => 'Carrito vacío'];

        $customer = null;
        try { $customer = (new CustomerService())->current(); } catch (\Throwable $e) {}
        $customerId = (int)($meta['customer_id'] ?? ($customer['id'] ?? 0));
        $customerName = trim((string)($meta['customer_name'] ?? ($customer['name'] ?? '')));
        $customerEmail = trim((string)($meta['customer_email'] ?? ($customer['email'] ?? '')));
        if ($customerEmail !== '' && !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) $customerEmail = '';

        $utm = is_array($meta['utm'] ?? null) ? $meta['utm'] : [];
        $pick = static function (string $key, int $max = 160) use ($utm, $meta): ?string {
            $value = $utm[$key] ?? $meta[$key] ?? '';
            $value = is_scalar($value) ? trim((string)$value) : '';
            return $value === '' ? null : mb_substr($value, 0, $max);
        };
        $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        $device = trim((string)($meta['device'] ?? ''));
        if ($device === '') {
            try { $device = (new SeoTrackingService())->device($ua); } catch (\Throwable $e) { $device = ''; }
        }
        $visitorHash = null;
        try {
            $visitorHash = SeoInternalTrafficService::visitorHash(SeoInternalTrafficService::clientIp(), $ua);
        } catch (\Throwable $e) {
        }
        $currency = strtoupper(trim((string)($meta['currency'] ?? 'COP')));
        if (!preg_match('/^[A-Z]{3}$/', $currency)) $currency = 'COP';
        $lastPage = mb_substr((string)($meta['page_url'] ?? ($_SERVER['REQUEST_URI'] ?? '')), 0, 500);
        $landing = $pick('landing', 500) ?? $lastPage;

        try {
            Database::instance()->prepare(
                'INSERT INTO store_abandoned_carts (
                    site_id, session_id, visitor_hash, customer_id, customer_name, customer_email,
                    items, qty, value_cents, currency, source, medium, campaign, content, term, fbclid, gclid,
                    landing_url, last_page, device, status
                ) VALUES (
                    @site_id, :session_id, :visitor_hash, :customer_id, :customer_name, :customer_email,
                    :items, :qty, :value_cents, :currency, :source, :medium, :campaign, :content, :term, :fbclid, :gclid,
                    :landing_url, :last_page, :device, :status
                ) ON DUPLICATE KEY UPDATE
                    visitor_hash = COALESCE(VALUES(visitor_hash), visitor_hash),
                    customer_id = COALESCE(VALUES(customer_id), customer_id),
                    customer_name = COALESCE(VALUES(customer_name), customer_name),
                    customer_email = COALESCE(VALUES(customer_email), customer_email),
                    items = VALUES(items),
                    qty = VALUES(qty),
                    value_cents = VALUES(value_cents),
                    currency = VALUES(currency),
                    source = COALESCE(VALUES(source), source),
                    medium = COALESCE(VALUES(medium), medium),
                    campaign = COALESCE(VALUES(campaign), campaign),
                    content = COALESCE(VALUES(content), content),
                    term = COALESCE(VALUES(term), term),
                    fbclid = COALESCE(VALUES(fbclid), fbclid),
                    gclid = COALESCE(VALUES(gclid), gclid),
                    landing_url = COALESCE(VALUES(landing_url), landing_url),
                    last_page = VALUES(last_page),
                    device = COALESCE(VALUES(device), device),
                    status = IF(status IN (\'recovered\', \'converted\'), status, \'active\'),
                    updated_at = NOW()'
            )->execute([
                'session_id' => $sessionId,
                'visitor_hash' => $visitorHash,
                'customer_id' => $customerId > 0 ? $customerId : null,
                'customer_name' => $customerName !== '' ? mb_substr($customerName, 0, 255) : null,
                'customer_email' => $customerEmail !== '' ? $customerEmail : null,
                'items' => json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'qty' => $qty,
                'value_cents' => $valueCents,
                'currency' => $currency,
                'source' => $pick('utm_source', 120) ?? $pick('source', 120),
                'medium' => $pick('utm_medium', 120) ?? $pick('medium', 120),
                'campaign' => $pick('utm_campaign', 160) ?? $pick('campaign', 160),
                'content' => $pick('utm_content', 160) ?? $pick('content', 160),
                'term' => $pick('utm_term', 160) ?? $pick('term', 160),
                'fbclid' => $pick('fbclid', 255),
                'gclid' => $pick('gclid', 255),
                'landing_url' => $landing,
                'last_page' => $lastPage,
                'device' => $device !== '' ? mb_substr($device, 0, 20) : null,
                'status' => 'active',
            ]);
            return ['ok' => true, 'session_id' => $sessionId, 'qty' => $qty, 'value_cents' => $valueCents];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo guardar el carrito'];
        }
    }

    public function markRecovered(string $orderUuid, ?string $sessionId, int $valueCents, ?int $customerId = null): void
    {
        if (!$this->ensureTables() || $orderUuid === '') return;
        try {
            if ($sessionId !== null && $sessionId !== '') {
                $stmt = Database::instance()->prepare(
                    "UPDATE store_abandoned_carts SET status = 'recovered', recovered_order_uuid = :uuid,
                     recovered_value_cents = :value, recovered_at = NOW(), updated_at = NOW()
                     WHERE site_id = @site_id AND session_id = :sid AND status NOT IN ('converted')"
                );
                $stmt->execute(['uuid' => $orderUuid, 'value' => max(0, $valueCents), 'sid' => $sessionId]);
                if ($stmt->rowCount() > 0) return;
            }
            if ($customerId !== null && $customerId > 0) {
                Database::instance()->prepare(
                    "UPDATE store_abandoned_carts SET status = 'recovered', recovered_order_uuid = :uuid,
                     recovered_value_cents = :value, recovered_at = NOW(), updated_at = NOW()
                     WHERE site_id = @site_id AND customer_id = :cid AND status IN ('active', 'abandoned', 'contacted')"
                )->execute(['uuid' => $orderUuid, 'value' => max(0, $valueCents), 'cid' => $customerId]);
            }
        } catch (\Throwable $e) {
        }
    }

    public function markConverted(string $orderUuid): void
    {
        if (!$this->ensureTables() || $orderUuid === '') return;
        try {
            Database::instance()->prepare(
                "UPDATE store_abandoned_carts SET status = 'converted', recovered_at = COALESCE(recovered_at, NOW()), updated_at = NOW()
                 WHERE site_id = @site_id AND recovered_order_uuid = :uuid"
            )->execute(['uuid' => $orderUuid]);
        } catch (\Throwable $e) {
        }
    }

    public function list(array $filters = []): array
    {
        if (!$this->ensureTables()) return [];
        $this->backfillIfEmpty();
        $this->refreshStatuses();
        $where = ['site_id = @site_id'];
        $params = [];
        $status = trim((string)($filters['status'] ?? ''));
        if ($status === 'recovered') {
            $where[] = "status IN ('recovered', 'converted')";
        } elseif ($status !== '' && in_array($status, ['active', 'abandoned', 'contacted', 'converted', 'dismissed'], true)) {
            $where[] = 'status = :status';
            $params['status'] = $status;
        } else {
            $where[] = "status <> 'dismissed'";
        }
        $days = max(1, min(365, (int)($filters['days'] ?? 30)));
        $where[] = 'updated_at >= (NOW() - INTERVAL ' . $days . ' DAY)';
        $limit = max(1, min(300, (int)($filters['limit'] ?? 120)));
        try {
            $stmt = Database::instance()->prepare(
                'SELECT * FROM store_abandoned_carts WHERE ' . implode(' AND ', $where) . " ORDER BY updated_at DESC LIMIT $limit"
            );
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            foreach ($rows as &$row) {
                $row['items'] = json_decode((string)($row['items'] ?? '[]'), true) ?: [];
                $row['age_minutes'] = max(0, (int)floor((time() - strtotime((string)$row['updated_at'])) / 60));
                $row['is_abandoned'] = $row['status'] === 'abandoned' || ($row['status'] === 'active' && $row['age_minutes'] >= self::ABANDON_MINUTES);
            }
            unset($row);
            return is_array($rows) ? $rows : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function metrics(int $days = 30): array
    {
        if (!$this->ensureTables()) return ['ready' => false];
        $this->backfillIfEmpty();
        $this->refreshStatuses();
        $days = max(1, min(365, $days));
        $since = 'updated_at >= (NOW() - INTERVAL ' . $days . ' DAY)';
        $db = Database::instance();
        $one = function (string $sql) use ($db): int {
            try { return (int)$db->query($sql)->fetchColumn(); } catch (\Throwable $e) { return 0; }
        };
        $sum = function (string $sql) use ($db): int {
            try { return (int)$db->query($sql)->fetchColumn(); } catch (\Throwable $e) { return 0; }
        };
        $total = $one("SELECT COUNT(*) FROM store_abandoned_carts WHERE site_id = @site_id AND $since");
        $active = $one("SELECT COUNT(*) FROM store_abandoned_carts WHERE site_id = @site_id AND status = 'active' AND $since");
        $abandoned = $one("SELECT COUNT(*) FROM store_abandoned_carts WHERE site_id = @site_id AND status = 'abandoned' AND $since");
        $contacted = $one("SELECT COUNT(*) FROM store_abandoned_carts WHERE site_id = @site_id AND status = 'contacted' AND $since");
        $recovered = $one("SELECT COUNT(*) FROM store_abandoned_carts WHERE site_id = @site_id AND status IN ('recovered','converted') AND $since");
        $dismissed = $one("SELECT COUNT(*) FROM store_abandoned_carts WHERE site_id = @site_id AND status = 'dismissed' AND $since");
        $openValue = $sum("SELECT COALESCE(SUM(value_cents), 0) FROM store_abandoned_carts WHERE site_id = @site_id AND status IN ('active','abandoned','contacted') AND $since");
        $recoveredValue = $sum("SELECT COALESCE(SUM(recovered_value_cents), 0) FROM store_abandoned_carts WHERE site_id = @site_id AND status IN ('recovered','converted') AND $since");
        $avgValue = $total > 0 ? (int)round(($openValue + $recoveredValue) / $total) : 0;
        $recoveryBase = $abandoned + $contacted + $recovered;
        $rate = $recoveryBase > 0 ? round($recovered * 100 / $recoveryBase, 1) : 0;

        $top = [];
        try {
            $rows = $db->query("SELECT items FROM store_abandoned_carts WHERE site_id = @site_id AND status IN ('active','abandoned','contacted') AND $since ORDER BY updated_at DESC LIMIT 300")->fetchAll();
            $agg = [];
            foreach ($rows as $row) {
                foreach ((array)(json_decode((string)$row['items'], true) ?: []) as $item) {
                    $key = (string)($item['handle'] ?? $item['title'] ?? '');
                    if ($key === '') continue;
                    if (!isset($agg[$key])) $agg[$key] = ['name' => (string)($item['title'] ?? $key), 'handle' => (string)($item['handle'] ?? ''), 'qty' => 0, 'value_cents' => 0];
                    $agg[$key]['qty'] += (int)($item['qty'] ?? 1);
                    $agg[$key]['value_cents'] += (int)($item['price_cents'] ?? 0) * (int)($item['qty'] ?? 1);
                }
            }
            usort($agg, fn($a, $b) => $b['qty'] <=> $a['qty']);
            $top = array_slice($agg, 0, 8);
        } catch (\Throwable $e) {
        }

        return [
            'ready' => true,
            'days' => $days,
            'total' => $total,
            'active' => $active,
            'abandoned' => $abandoned,
            'contacted' => $contacted,
            'recovered' => $recovered,
            'dismissed' => $dismissed,
            'open_value_cents' => $openValue,
            'recovered_value_cents' => $recoveredValue,
            'avg_value_cents' => $avgValue,
            'recovery_rate' => $rate,
            'top_products' => $top,
            'abandon_minutes' => self::ABANDON_MINUTES,
        ];
    }

    public function updateStatus(int $id, string $status, string $channel = ''): array
    {
        if (!$this->ensureTables()) return ['ok' => false, 'message' => 'Tabla no disponible'];
        if (!in_array($status, ['contacted', 'dismissed', 'recovered'], true)) return ['ok' => false, 'message' => 'Estado inválido'];
        try {
            $sets = ['status = :status', 'updated_at = NOW()'];
            $params = ['status' => $status, 'id' => $id];
            if ($status === 'contacted') {
                $sets[] = 'recovery_channel = :channel';
                $sets[] = 'recovery_sent_at = NOW()';
                $params['channel'] = mb_substr($channel !== '' ? $channel : 'manual', 0, 20);
            }
            if ($status === 'recovered') {
                $sets[] = 'recovered_at = COALESCE(recovered_at, NOW())';
            }
            Database::instance()->prepare('UPDATE store_abandoned_carts SET ' . implode(', ', $sets) . ' WHERE id = :id AND site_id = @site_id')
                ->execute($params);
            return ['ok' => true];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo actualizar'];
        }
    }

    public function delete(int $id): array
    {
        if (!$this->ensureTables()) return ['ok' => false];
        try {
            Database::instance()->prepare('DELETE FROM store_abandoned_carts WHERE id = ? AND site_id = @site_id')->execute([$id]);
            return ['ok' => true];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo eliminar'];
        }
    }

    private function refreshStatuses(): void
    {
        try {
            Database::instance()->prepare(
                "UPDATE store_abandoned_carts SET status = 'abandoned', updated_at = updated_at
                 WHERE site_id = @site_id AND status = 'active'
                   AND updated_at <= (NOW() - INTERVAL " . self::ABANDON_MINUTES . " MINUTE)"
            )->execute();
        } catch (\Throwable $e) {
        }
    }

    private function backfillIfEmpty(): void
    {
        if (self::$backfilled) return;
        self::$backfilled = true;
        try {
            $count = (int)Database::instance()->query('SELECT COUNT(*) FROM store_abandoned_carts WHERE site_id = @site_id')->fetchColumn();
            if ($count > 0) return;
            $rows = Database::instance()->query(
                "SELECT c.id, c.customer_id, c.items, c.updated_at, c.last_reminder_at, cu.name, cu.email
                 FROM store_carts c JOIN store_customers cu ON cu.id = c.customer_id AND cu.site_id = c.site_id
                 WHERE c.site_id = @site_id AND c.items IS NOT NULL AND c.items NOT IN ('', '[]', 'null')
                 ORDER BY c.updated_at DESC LIMIT 300"
            )->fetchAll();
            foreach ($rows as $row) {
                $items = json_decode((string)$row['items'], true);
                if (!is_array($items) || !$items) continue;
                $session = 'cart-' . (int)$row['id'];
                $this->capture($session, $items, [
                    'customer_id' => (int)$row['customer_id'],
                    'customer_name' => (string)($row['name'] ?? ''),
                    'customer_email' => (string)($row['email'] ?? ''),
                    'last_page' => '/carrito',
                    'page_url' => '/carrito',
                    'currency' => 'COP',
                ]);
                $ts = strtotime((string)$row['updated_at']) ?: time();
                $status = (time() - $ts) >= self::ABANDON_MINUTES * 60 ? 'abandoned' : 'active';
                if (!empty($row['last_reminder_at'])) $status = 'contacted';
                Database::instance()->prepare(
                    'UPDATE store_abandoned_carts SET created_at = :created, updated_at = :updated, status = :status,
                     recovery_sent_at = :sent WHERE site_id = @site_id AND session_id = :sid'
                )->execute([
                    'created' => date('Y-m-d H:i:s', $ts),
                    'updated' => date('Y-m-d H:i:s', $ts),
                    'status' => $status,
                    'sent' => !empty($row['last_reminder_at']) ? (string)$row['last_reminder_at'] : null,
                    'sid' => $session,
                ]);
            }
        } catch (\Throwable $e) {
        }
    }
}
