<?php
namespace App\Services;

use App\Core\Config;
use App\Core\Database;

class QuoteService
{
    private static bool $ensured = false;
    public const ROLES = ['superadmin', 'admin', 'editor'];
    private const MAX_HOUR_IP = 30;
    public const EVENT_VIEW = 'view';
    public const EVENT_UNLOCK = 'unlock';
    public const EVENT_UNLOCK_FAILED = 'unlock_failed';
    public const EVENT_PDF = 'pdf';
    public const EVENT_WHATSAPP = 'whatsapp';
    public const EVENT_CHECKOUT = 'checkout';
    public const EVENT_SENT = 'sent';
    public const EVENT_ORDER = 'order_created';
    public const EVENT_LINK_COPY = 'link_copy';
    private const EVENTS = [
        self::EVENT_VIEW, self::EVENT_UNLOCK, self::EVENT_UNLOCK_FAILED, self::EVENT_PDF,
        self::EVENT_WHATSAPP, self::EVENT_CHECKOUT, self::EVENT_SENT, self::EVENT_ORDER,
        self::EVENT_LINK_COPY,
    ];
    private const SETTINGS = [
        'quote_public_enabled' => '1',
        'quote_public_days' => '90',
        'quote_valid_days' => '15',
        'quote_require_verification' => '1',
        'quote_access_minutes' => '30',
        'quote_company_city' => 'Barranquilla, Colombia',
        'quote_company_nit' => '',
        'quote_pdf_enabled' => '1',
        'quote_whatsapp_enabled' => '1',
        'quote_whatsapp_api_token' => '',
        'quote_whatsapp_phone_id' => '',
        'quote_whatsapp_to' => '',
        'quote_public_base_url' => 'https://sonika.com.co',
    ];

    public function ensureTables(): array
    {
        if (self::$ensured) return ['ok' => true, 'cached' => true];
        $db = Database::instance();
        $db->exec("CREATE TABLE IF NOT EXISTS store_quotes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL,
            code VARCHAR(40) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            currency VARCHAR(5) NOT NULL DEFAULT 'COP',
            payment_method VARCHAR(30) NOT NULL DEFAULT 'request',
            customer_type VARCHAR(20) NULL,
            customer_name VARCHAR(255) NULL,
            customer_json LONGTEXT NULL,
            subtotal_before_cents BIGINT NOT NULL DEFAULT 0,
            net_before_cents BIGINT NOT NULL DEFAULT 0,
            tax_before_cents BIGINT NOT NULL DEFAULT 0,
            breb_discount_cents BIGINT NOT NULL DEFAULT 0,
            coupon_discount_cents BIGINT NOT NULL DEFAULT 0,
            shipping_cents BIGINT NOT NULL DEFAULT 0,
            net_after_cents BIGINT NOT NULL DEFAULT 0,
            tax_after_cents BIGINT NOT NULL DEFAULT 0,
            total_cents BIGINT NOT NULL DEFAULT 0,
            item_count INT NOT NULL DEFAULT 0,
            items_json LONGTEXT NULL,
            meta_json LONGTEXT NULL,
            ip_hash VARCHAR(64) NULL,
            viewed_count INT NOT NULL DEFAULT 0,
            last_viewed_at DATETIME NULL,
            sent_at DATETIME NULL,
            converted_at DATETIME NULL,
            expires_at DATETIME NULL,
            public_token VARCHAR(64) NULL,
            public_created_at DATETIME NULL,
            public_expires_at DATETIME NULL,
            public_revoked_at DATETIME NULL,
            order_uuid VARCHAR(64) NULL,
            order_number VARCHAR(64) NULL,
            order_total_cents BIGINT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_quote_code (site_id, code),
            UNIQUE KEY uk_quote_public_token (public_token),
            KEY idx_quote_status (site_id, status, created_at),
            KEY idx_quote_ip (site_id, ip_hash, created_at),
            KEY idx_quote_public_expiry (site_id, public_expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $this->ensureColumns('store_quotes', [
            'customer_type' => 'VARCHAR(20) NULL',
            'customer_name' => 'VARCHAR(255) NULL',
            'customer_json' => 'LONGTEXT NULL',
            'public_token' => 'VARCHAR(64) NULL',
            'public_created_at' => 'DATETIME NULL',
            'public_expires_at' => 'DATETIME NULL',
            'public_revoked_at' => 'DATETIME NULL',
            'order_uuid' => 'VARCHAR(64) NULL',
            'order_number' => 'VARCHAR(64) NULL',
            'order_total_cents' => 'BIGINT NOT NULL DEFAULT 0',
        ]);
        $this->ensureQuoteMoneyColumns();
        $db->exec("CREATE TABLE IF NOT EXISTS quote_events (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL,
            quote_id INT NOT NULL,
            event VARCHAR(40) NOT NULL,
            ip_hash VARCHAR(64) NULL,
            ua_hash VARCHAR(64) NULL,
            meta_json LONGTEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_quote_event (site_id, quote_id, created_at),
            KEY idx_quote_event_type (site_id, event, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        try { $db->exec("ALTER TABLE store_orders ADD COLUMN IF NOT EXISTS quote_code VARCHAR(40) NULL"); } catch (\Throwable $e) {}
        try { $db->exec("ALTER TABLE store_orders ADD INDEX idx_store_orders_quote (site_id, quote_code)"); } catch (\Throwable $e) {}
        try { $db->exec("ALTER TABLE store_orders MODIFY quote_code VARCHAR(40) COLLATE utf8mb4_unicode_ci NULL"); } catch (\Throwable $e) {}
        $this->backfillPublicTokens();
        self::$ensured = true;
        return ['ok' => true, 'cached' => false];
    }

    private function ensureQuoteMoneyColumns(): void
    {
        $columns = [
            'subtotal_before_cents', 'net_before_cents', 'tax_before_cents',
            'breb_discount_cents', 'coupon_discount_cents', 'shipping_cents',
            'net_after_cents', 'tax_after_cents', 'total_cents', 'order_total_cents',
        ];
        try {
            $stmt = Database::instance()->query('SHOW COLUMNS FROM store_quotes');
            $types = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $types[(string)$row['Field']] = strtolower((string)$row['Type']);
            }
        } catch (\Throwable $e) {
            return;
        }
        foreach ($columns as $column) {
            if (isset($types[$column]) && str_starts_with($types[$column], 'bigint')) continue;
            try {
                Database::instance()->exec("ALTER TABLE store_quotes MODIFY $column BIGINT NOT NULL DEFAULT 0");
            } catch (\Throwable $e) {
            }
        }
    }

    private function ensureColumns(string $table, array $columns): void
    {
        $existing = [];
        try {
            $stmt = Database::instance()->query('SHOW COLUMNS FROM ' . $table);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $existing[(string)$row['Field']] = true;
            }
        } catch (\Throwable $e) {
            return;
        }
        foreach ($columns as $name => $definition) {
            if (isset($existing[$name])) continue;
            try { Database::instance()->exec("ALTER TABLE $table ADD COLUMN $name $definition"); } catch (\Throwable $e) {}
        }
    }

    private function backfillPublicTokens(): void
    {
        try {
            $db = Database::instance();
            $stmt = $db->prepare('SELECT id, code, created_at FROM store_quotes WHERE site_id = @site_id AND (public_token IS NULL OR public_token = "") ORDER BY id ASC LIMIT 100');
            $stmt->execute();
            foreach ($stmt->fetchAll() as $row) {
                $token = bin2hex(random_bytes(32));
                $created = (string)($row['created_at'] ?: date('Y-m-d H:i:s'));
                $publicExpires = date('Y-m-d H:i:s', strtotime($created) + (90 * 86400));
                $db->prepare('UPDATE store_quotes SET public_token = ?, public_created_at = COALESCE(public_created_at, ?), public_expires_at = COALESCE(public_expires_at, ?) WHERE id = ? AND site_id = @site_id')
                    ->execute([$token, $created, $publicExpires, (int)$row['id']]);
            }
        } catch (\Throwable $e) {
        }
    }

    public function settings(): array
    {
        $out = self::SETTINGS;
        try {
            $keys = array_keys($out);
            $in = implode(',', array_fill(0, count($keys), '?'));
            $stmt = Database::instance()->prepare("SELECT `key`, `value` FROM settings WHERE site_id = @site_id AND `key` IN ($in)");
            $stmt->execute($keys);
            foreach ($stmt->fetchAll() as $row) {
                $out[(string)$row['key']] = (string)$row['value'];
            }
        } catch (\Throwable $e) {
        }
        $out['quote_public_enabled'] = $out['quote_public_enabled'] !== '0';
        $out['quote_require_verification'] = $out['quote_require_verification'] !== '0';
        $out['quote_pdf_enabled'] = $out['quote_pdf_enabled'] !== '0';
        $out['quote_whatsapp_enabled'] = $out['quote_whatsapp_enabled'] !== '0';
        $out['quote_public_days'] = max(1, min(3650, (int)$out['quote_public_days']));
        $out['quote_valid_days'] = max(1, min(90, (int)$out['quote_valid_days']));
        $out['quote_access_minutes'] = max(5, min(1440, (int)$out['quote_access_minutes']));
        $out['quote_company_city'] = trim((string)$out['quote_company_city']) ?: 'Barranquilla, Colombia';
        return $out;
    }

    public function saveSettings(array $data): array
    {
        $settings = $this->settings();
        $map = [
            'quote_public_enabled' => !empty($data['quote_public_enabled']) ? '1' : '0',
            'quote_require_verification' => !empty($data['quote_require_verification']) ? '1' : '0',
            'quote_pdf_enabled' => !empty($data['quote_pdf_enabled']) ? '1' : '0',
            'quote_whatsapp_enabled' => !empty($data['quote_whatsapp_enabled']) ? '1' : '0',
            'quote_public_days' => (string)max(1, min(3650, (int)($data['quote_public_days'] ?? $settings['quote_public_days']))),
            'quote_valid_days' => (string)max(1, min(90, (int)($data['quote_valid_days'] ?? $settings['quote_valid_days']))),
            'quote_access_minutes' => (string)max(5, min(1440, (int)($data['quote_access_minutes'] ?? $settings['quote_access_minutes']))),
            'quote_company_city' => mb_substr(trim((string)($data['quote_company_city'] ?? $settings['quote_company_city'])), 0, 120),
            'quote_company_nit' => mb_substr(trim((string)($data['quote_company_nit'] ?? $settings['quote_company_nit'])), 0, 40),
            'quote_whatsapp_api_token' => trim((string)($data['quote_whatsapp_api_token'] ?? '')) !== '' ? trim((string)$data['quote_whatsapp_api_token']) : (string)$settings['quote_whatsapp_api_token'],
            'quote_whatsapp_phone_id' => trim((string)($data['quote_whatsapp_phone_id'] ?? $settings['quote_whatsapp_phone_id'])),
            'quote_whatsapp_to' => preg_replace('/\D+/', '', (string)($data['quote_whatsapp_to'] ?? $settings['quote_whatsapp_to'])) ?: '',
            'quote_public_base_url' => preg_match('~^https?://~i', trim((string)($data['quote_public_base_url'] ?? ''))) ? rtrim(trim((string)$data['quote_public_base_url']), '/') : (string)$settings['quote_public_base_url'],
        ];
        $stmt = Database::instance()->prepare("INSERT INTO settings (site_id, `key`, `value`) VALUES (@site_id, ?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
        foreach ($map as $key => $value) $stmt->execute([$key, (string)$value]);
        return $this->settings();
    }

    public function save(array $input, string $ip = ''): array
    {
        $this->ensureTables();
        $settings = $this->settings();
        $itemsIn = (array)($input['items'] ?? []);
        if (!$itemsIn) throw new \InvalidArgumentException('La cotización no tiene productos.');
        if (count($itemsIn) > 50) throw new \InvalidArgumentException('Demasiados productos en la cotización.');
        $method = (string)($input['payment_method'] ?? 'request');
        if (!in_array($method, BrebService::METHODS, true)) throw new \InvalidArgumentException('Método de pago no disponible.');
        $customerIn = (array)($input['customer'] ?? []);
        if (!$customerIn) throw new \InvalidArgumentException('Completa los datos del cliente para preparar la cotización.');
        $customer = (new CustomerDataValidator())->validate($customerIn);
        $customerJson = QuoteCrypto::encrypt(json_encode($customer, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');
        $customerName = mb_substr((string)($customer['display_name'] ?? $customer['name'] ?? ''), 0, 255);
        $customerType = (string)($customer['type'] ?? 'natural');
        $validDays = max(1, min(90, (int)($input['valid_days'] ?? $settings['quote_valid_days'])));
        $codeIn = strtoupper(trim((string)($input['code'] ?? '')));
        if ($codeIn !== '' && !preg_match('/^COT-[A-Z0-9-]{4,36}$/', $codeIn)) $codeIn = '';

        $db = Database::instance();
        $existing = null;
        if ($codeIn !== '') {
            $st = $db->prepare('SELECT * FROM store_quotes WHERE code = ? AND site_id = @site_id LIMIT 1');
            $st->execute([$codeIn]);
            $existing = $st->fetch() ?: null;
        }

        $ipHash = $ip !== '' ? hash('sha256', $ip) : '';
        if (!$existing && $ipHash !== '') {
            $st = $db->prepare('SELECT COUNT(*) FROM store_quotes WHERE site_id = @site_id AND ip_hash = ? AND created_at >= (NOW() - INTERVAL 1 HOUR)');
            $st->execute([$ipHash]);
            if ((int)$st->fetchColumn() >= self::MAX_HOUR_IP) {
                throw new StoreRateLimitException('Demasiadas cotizaciones desde esta conexión. Intenta más tarde o escríbenos por WhatsApp.');
            }
        }

        $quoteItems = [];
        $categoryByRef = [];
        foreach ($itemsIn as $item) {
            if (!is_array($item)) continue;
            $ref = trim((string)($item['ref'] ?? ($item['handle'] ?? '')));
            if ($ref === '') continue;
            $qty = max(1, min(99, (int)($item['qty'] ?? 1)));
            $category = trim((string)($item['category'] ?? 'Productos')) ?: 'Productos';
            $quoteItems[] = ['ref' => $ref, 'qty' => $qty];
            $categoryByRef[$ref] = $category;
        }
        if (!$quoteItems) throw new \InvalidArgumentException('La cotización no tiene productos.');

        $breb = new BrebService();
        $quote = $breb->quote($quoteItems, $method);
        if (empty($quote['ok']) || empty($quote['items'])) throw new \InvalidArgumentException((string)($quote['message'] ?? 'No se pudo cotizar el pedido.'));
        $lines = [];
        foreach ((array)$quote['items'] as $line) {
            $ref = (string)($line['ref'] ?? '');
            $line['category'] = $categoryByRef[$ref] ?? 'Productos';
            $line['url'] = '/producto/' . rawurlencode($ref);
            $lines[] = $line;
        }
        $quote['items'] = $lines;
        $itemsJson = json_encode($lines, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $metaJson = json_encode([
            'tax_rate' => $quote['tax_rate'] ?? 19,
            'prices_include_tax' => $quote['prices_include_tax'] ?? true,
            'free_shipping' => (bool)($quote['free_shipping'] ?? false),
            'valid_days' => $validDays,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $expiresAt = date('Y-m-d H:i:s', time() + $validDays * 86400);
        $currency = (string)($quote['currency'] ?? 'COP');
        $token = $existing && !empty($existing['public_token']) ? (string)$existing['public_token'] : bin2hex(random_bytes(32));
        $publicCreated = $existing && !empty($existing['public_created_at']) ? (string)$existing['public_created_at'] : date('Y-m-d H:i:s');
        $publicExpires = $existing && !empty($existing['public_expires_at']) ? (string)$existing['public_expires_at'] : date('Y-m-d H:i:s', time() + $settings['quote_public_days'] * 86400);
        $values = [
            $currency, $method, $customerType, $customerName, $customerJson,
            (int)($quote['subtotal_before_cents'] ?? 0), (int)($quote['net_before_cents'] ?? 0),
            (int)($quote['tax_before_cents'] ?? 0), (int)($quote['breb_discount_cents'] ?? 0),
            (int)($quote['coupon_discount_cents'] ?? 0), (int)($quote['shipping_cents'] ?? 0),
            (int)($quote['net_after_cents'] ?? 0), (int)($quote['tax_after_cents'] ?? 0),
            (int)($quote['total_cents'] ?? 0), count($lines), $itemsJson, $metaJson, $expiresAt,
            $token, $publicCreated, $publicExpires,
        ];

        if ($existing) {
            $code = (string)$existing['code'];
            $sql = "UPDATE store_quotes SET status = CASE WHEN order_uuid IS NULL THEN 'draft' ELSE status END, currency = ?, payment_method = ?, customer_type = ?, customer_name = ?, customer_json = ?, subtotal_before_cents = ?, net_before_cents = ?, tax_before_cents = ?, breb_discount_cents = ?, coupon_discount_cents = ?, shipping_cents = ?, net_after_cents = ?, tax_after_cents = ?, total_cents = ?, item_count = ?, items_json = ?, meta_json = ?, expires_at = ?, public_token = ?, public_created_at = ?, public_expires_at = ?, updated_at = NOW() WHERE id = ? AND site_id = @site_id";
            $db->prepare($sql)->execute(array_merge($values, [(int)$existing['id']]));
        } else {
            $code = $this->uniqueCode();
            $sql = "INSERT INTO store_quotes (site_id, code, status, currency, payment_method, customer_type, customer_name, customer_json, subtotal_before_cents, net_before_cents, tax_before_cents, breb_discount_cents, coupon_discount_cents, shipping_cents, net_after_cents, tax_after_cents, total_cents, item_count, items_json, meta_json, expires_at, public_token, public_created_at, public_expires_at, ip_hash) VALUES (@site_id, ?, 'draft', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $db->prepare($sql)->execute(array_merge([$code], $values, [$ipHash]));
        }

        $row = $this->findByCode($code, false);
        return [
            'code' => $code,
            'url' => $this->publicUrl($row ?: []),
            'pdf_url' => $row ? $this->signedPath($row, 'pdf', 900) : '',
            'expires_at' => $row['expires_at'] ?? $expiresAt,
            'status' => $row['status'] ?? 'draft',
            'quote' => $this->publicQuote($row ?: []),
        ];
    }

    public function findByCode(string $code, bool $increment = true): ?array
    {
        $this->ensureTables();
        $code = strtoupper(trim($code));
        if ($code === '' || !preg_match('/^COT-[A-Z0-9-]{4,36}$/', $code)) return null;
        $st = Database::instance()->prepare('SELECT * FROM store_quotes WHERE code = ? AND site_id = @site_id LIMIT 1');
        $st->execute([$code]);
        $row = $st->fetch();
        return $row ? $this->hydrateRow($row, $increment) : null;
    }

    public function findByPublicToken(string $token, bool $increment = false): ?array
    {
        $this->ensureTables();
        $token = strtolower(trim($token));
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
        $st = Database::instance()->prepare('SELECT * FROM store_quotes WHERE public_token = ? AND site_id = @site_id LIMIT 1');
        $st->execute([$token]);
        $row = $st->fetch();
        return $row ? $this->hydrateRow($row, $increment) : null;
    }

    private function hydrateRow(array $row, bool $increment): array
    {
        $row['items'] = json_decode((string)($row['items_json'] ?? '[]'), true) ?: [];
        $row['meta'] = json_decode((string)($row['meta_json'] ?? '{}'), true) ?: [];
        $rawCustomer = (string)($row['customer_json'] ?? '');
        if (QuoteCrypto::isEncrypted($rawCustomer)) {
            $rawCustomer = QuoteCrypto::decrypt($rawCustomer);
        } elseif ($rawCustomer !== '' && function_exists('openssl_encrypt')) {
            try {
                Database::instance()->prepare('UPDATE store_quotes SET customer_json = ? WHERE id = ? AND site_id = @site_id')
                    ->execute([QuoteCrypto::encrypt($rawCustomer), (int)$row['id']]);
                $row['customer_json'] = QuoteCrypto::encrypt($rawCustomer);
            } catch (\Throwable $e) {}
        }
        $row['customer'] = json_decode($rawCustomer, true) ?: [];
        if (empty($row['public_token'])) {
            $row['public_token'] = bin2hex(random_bytes(32));
            $row['public_created_at'] = (string)($row['created_at'] ?: date('Y-m-d H:i:s'));
            $row['public_expires_at'] = date('Y-m-d H:i:s', strtotime((string)$row['public_created_at']) + 90 * 86400);
            try {
                Database::instance()->prepare('UPDATE store_quotes SET public_token = ?, public_created_at = ?, public_expires_at = ? WHERE id = ? AND site_id = @site_id')
                    ->execute([$row['public_token'], $row['public_created_at'], $row['public_expires_at'], (int)$row['id']]);
            } catch (\Throwable $e) {}
        }
        $row['public_url'] = $this->publicUrl($row);
        $row['url'] = $row['public_url'];
        $row['expired'] = !empty($row['expires_at']) && strtotime((string)$row['expires_at']) < time();
        $row['public_expired'] = !empty($row['public_expires_at']) && strtotime((string)$row['public_expires_at']) < time();
        $row['public_revoked'] = !empty($row['public_revoked_at']);
        $row['effective_status'] = $row['expired'] && in_array((string)$row['status'], ['draft', 'sent'], true) ? 'expired' : (string)$row['status'];
        if ($increment && !$row['expired'] && !$row['public_expired'] && !$row['public_revoked']) {
            try {
                Database::instance()->prepare('UPDATE store_quotes SET viewed_count = viewed_count + 1, last_viewed_at = NOW() WHERE id = ? AND site_id = @site_id')
                    ->execute([(int)$row['id']]);
                $row['viewed_count'] = (int)$row['viewed_count'] + 1;
                $this->recordEvent((int)$row['id'], self::EVENT_VIEW);
            } catch (\Throwable $e) {
            }
        }
        return $row;
    }

    public function publicUrl(array $row): string
    {
        $token = (string)($row['public_token'] ?? '');
        return $token !== '' ? '/cotizacion/' . rawurlencode($token) : '';
    }

    public function signedPath(array $row, string $kind = 'pdf', int $ttl = 900): string
    {
        $token = (string)($row['public_token'] ?? '');
        if ($token === '') return '';
        $exp = time() + max(60, min(86400, $ttl));
        $sig = hash_hmac('sha256', $token . '|' . $kind . '|' . $exp, $this->securityKey());
        return $kind === 'print'
            ? '/cotizacion/' . rawurlencode($token) . '?print=1&exp=' . $exp . '&sig=' . $sig
            : '/cotizacion/' . rawurlencode($token) . '/pdf?exp=' . $exp . '&sig=' . $sig;
    }

    public function verifySigned(array $row, string $kind, string $exp, string $sig): bool
    {
        if (!ctype_digit($exp) || (int)$exp < time()) return false;
        $token = (string)($row['public_token'] ?? '');
        $want = hash_hmac('sha256', $token . '|' . $kind . '|' . $exp, $this->securityKey());
        return $token !== '' && hash_equals($want, $sig);
    }

    public function isPublicAvailable(array $row): bool
    {
        return (bool)$row
            && (bool)$this->settings()['quote_public_enabled']
            && empty($row['public_expired'])
            && empty($row['public_revoked']);
    }

    public function accessCookieName(array $row): string
    {
        return 'sq_a_' . (int)($row['id'] ?? 0);
    }

    public function hasAccess(array $row): bool
    {
        $name = $this->accessCookieName($row);
        $raw = (string)($_COOKIE[$name] ?? '');
        if ($raw === '') return false;
        $parts = explode('.', $raw, 2);
        if (count($parts) !== 2 || !ctype_digit($parts[0]) || (int)$parts[0] < time()) return false;
        return hash_equals($this->accessSignature($row, (int)$parts[0]), $parts[1]);
    }

    public function grantAccess(array $row): void
    {
        $exp = time() + (int)$this->settings()['quote_access_minutes'] * 60;
        $cookie = $exp . '.' . $this->accessSignature($row, $exp);
        setcookie($this->accessCookieName($row), $cookie, [
            'expires' => $exp,
            'path' => '/',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function accessSignature(array $row, int $exp): string
    {
        return hash_hmac('sha256', (int)$row['id'] . '|' . (string)$row['public_token'] . '|' . $exp, $this->securityKey());
    }

    public function verifyAccess(array $row, string $identity, string $ip = ''): array
    {
        $settings = $this->settings();
        if (!$settings['quote_require_verification']) return ['ok' => true];
        $ipHash = $ip !== '' ? hash('sha256', $ip) : '';
        if ($ipHash !== '') {
            try {
                $st = Database::instance()->prepare("SELECT COUNT(*) FROM quote_events WHERE site_id = @site_id AND quote_id = ? AND event = ? AND ip_hash = ? AND created_at >= (NOW() - INTERVAL 15 MINUTE)");
                $st->execute([(int)$row['id'], self::EVENT_UNLOCK_FAILED, $ipHash]);
                if ((int)$st->fetchColumn() >= 5) return ['ok' => false, 'message' => 'Demasiados intentos. Espera unos minutos antes de volver a intentar.'];
            } catch (\Throwable $e) {}
        }
        $identity = trim($identity);
        $customer = (array)($row['customer'] ?? []);
        $email = mb_strtolower(trim((string)($customer['email'] ?? '')));
        $doc = preg_replace('/\D+/', '', (string)($customer['document'] ?? $customer['nit'] ?? '')) ?? '';
        $phone = preg_replace('/\D+/', '', (string)($customer['phone'] ?? '')) ?? '';
        $digits = preg_replace('/\D+/', '', $identity) ?? '';
        $checkedEmail = $email !== '' && hash_equals($email, mb_strtolower($identity));
        $checkedDoc = strlen($digits) >= 4 && (str_ends_with($doc, substr($digits, -4)) || hash_equals($doc, $digits) || (strlen($phone) >= 4 && str_ends_with($phone, substr($digits, -4))));
        if ($checkedEmail || $checkedDoc) {
            $this->recordEvent((int)$row['id'], self::EVENT_UNLOCK, $ip);
            return ['ok' => true];
        }
        $this->recordEvent((int)$row['id'], self::EVENT_UNLOCK_FAILED, $ip);
        return ['ok' => false, 'message' => 'No pudimos verificar el acceso. Usa el correo o los últimos 4 dígitos del documento de esta cotización.'];
    }

    public function recordEvent(int $quoteId, string $event, string $ip = '', string $ua = '', array $meta = []): void
    {
        if (!in_array($event, self::EVENTS, true) || $quoteId <= 0) return;
        try {
            $db = Database::instance();
            $db->prepare('INSERT INTO quote_events (site_id, quote_id, event, ip_hash, ua_hash, meta_json) VALUES (@site_id, ?, ?, ?, ?, ?)')
                ->execute([$quoteId, $event, $ip !== '' ? hash('sha256', $ip) : null, $ua !== '' ? hash('sha256', $ua) : null, $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null]);
            if (in_array($event, [self::EVENT_PDF, self::EVENT_WHATSAPP], true)) {
                $db->prepare("UPDATE store_quotes SET sent_at = COALESCE(sent_at, NOW()), status = CASE WHEN status = 'converted' THEN status ELSE 'sent' END WHERE id = ? AND site_id = @site_id")->execute([$quoteId]);
            }
        } catch (\Throwable $e) {
        }
    }

    public function markView(array $row, string $ip = ''): void
    {
        if (empty($row['id'])) return;
        try {
            Database::instance()->prepare('UPDATE store_quotes SET viewed_count = viewed_count + 1, last_viewed_at = NOW(), updated_at = NOW() WHERE id = ? AND site_id = @site_id')
                ->execute([(int)$row['id']]);
            $this->recordEvent((int)$row['id'], self::EVENT_VIEW, $ip, (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
        } catch (\Throwable $e) {
        }
    }

    public function markSent(string $code): bool
    {
        $this->ensureTables();
        $row = $this->findByCode($code, false);
        if (!$row) return false;
        $st = Database::instance()->prepare("UPDATE store_quotes SET status = CASE WHEN status = 'converted' THEN status ELSE 'sent' END, sent_at = NOW(), updated_at = NOW() WHERE id = ? AND site_id = @site_id");
        $st->execute([(int)$row['id']]);
        $this->recordEvent((int)$row['id'], self::EVENT_SENT);
        return $st->rowCount() > 0;
    }

    public function markConverted(string $code): bool
    {
        $this->ensureTables();
        $row = $this->findByCode($code, false);
        if (!$row) return false;
        $st = Database::instance()->prepare("UPDATE store_quotes SET status = 'converted', converted_at = NOW(), updated_at = NOW() WHERE id = ? AND site_id = @site_id");
        $st->execute([(int)$row['id']]);
        return $st->rowCount() > 0;
    }

    public function attributeOrder(string $quoteCode, string $orderUuid, string $orderNumber, int $totalCents): void
    {
        $quoteCode = strtoupper(trim($quoteCode));
        if (!preg_match('/^COT-[A-Z0-9-]{4,36}$/', $quoteCode)) return;
        $this->ensureTables();
        try {
            $row = $this->findByCode($quoteCode, false);
            if (!$row) return;
            Database::instance()->prepare("UPDATE store_quotes SET status = 'converted', converted_at = NOW(), order_uuid = ?, order_number = ?, order_total_cents = ?, updated_at = NOW() WHERE id = ? AND site_id = @site_id")
                ->execute([$orderUuid, $orderNumber, $totalCents, (int)$row['id']]);
            $this->recordEvent((int)$row['id'], self::EVENT_ORDER, '', '', ['order_number' => $orderNumber]);
        } catch (\Throwable $e) {
        }
    }

    public function markCheckout(array $row, string $ip = ''): void
    {
        $this->recordEvent((int)$row['id'], self::EVENT_CHECKOUT, $ip);
    }

    public function adminList(int $limit = 200, array $filters = []): array
    {
        $this->ensureTables();
        $limit = max(1, min(500, (int)$limit));
        $where = 'q.site_id = @site_id';
        $params = [];
        $search = trim((string)($filters['q'] ?? ''));
        if ($search !== '') {
            $where .= ' AND (q.code LIKE :q OR q.customer_name LIKE :q OR q.order_number LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }
        $status = (string)($filters['status'] ?? '');
        if (in_array($status, ['draft', 'sent', 'converted', 'expired'], true)) {
            $where .= ' AND q.status = :status';
            $params['status'] = $status;
        }
        $sql = "SELECT q.*,
            (SELECT COUNT(*) FROM quote_events e WHERE e.site_id=q.site_id AND e.quote_id=q.id AND e.event='pdf') AS pdf_count,
            (SELECT COUNT(*) FROM quote_events e WHERE e.site_id=q.site_id AND e.quote_id=q.id AND e.event='whatsapp') AS whatsapp_count,
            (SELECT COUNT(*) FROM quote_events e WHERE e.site_id=q.site_id AND e.quote_id=q.id AND e.event='unlock') AS unlock_count,
            (SELECT COUNT(*) FROM store_orders o WHERE o.site_id=q.site_id AND o.quote_code = q.code COLLATE utf8mb4_unicode_ci) AS orders_count,
            (SELECT COUNT(*) FROM store_orders o WHERE o.site_id=q.site_id AND o.quote_code = q.code COLLATE utf8mb4_unicode_ci AND o.payment_status='paid') AS paid_orders,
            (SELECT MAX(e.created_at) FROM quote_events e WHERE e.site_id=q.site_id AND e.quote_id=q.id) AS last_event_at
            FROM store_quotes q WHERE $where ORDER BY q.id DESC LIMIT $limit";
        $st = Database::instance()->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll();
        foreach ($rows as &$row) {
            $row['url'] = $this->publicUrl($row);
            $row['expired'] = !empty($row['expires_at']) && strtotime((string)$row['expires_at']) < time();
            $row['public_expired'] = !empty($row['public_expires_at']) && strtotime((string)$row['public_expires_at']) < time();
            $row['public_revoked'] = !empty($row['public_revoked_at']);
            $row['effective_status'] = $row['expired'] && in_array((string)$row['status'], ['draft', 'sent'], true) ? 'expired' : (string)$row['status'];
        }
        return $rows;
    }

    public function adminQuote(string $code): ?array
    {
        $row = $this->findByCode($code, false);
        if ($row) $row['pdf_url'] = $this->signedPath($row, 'pdf', 900);
        return $row;
    }

    public function revokePublic(string $code): bool
    {
        $this->ensureTables();
        $st = Database::instance()->prepare('UPDATE store_quotes SET public_revoked_at = NOW(), updated_at = NOW() WHERE code = ? AND site_id = @site_id');
        $st->execute([strtoupper(trim($code))]);
        return $st->rowCount() > 0;
    }

    public function extendPublic(string $code, int $days = 90): bool
    {
        $this->ensureTables();
        $days = max(1, min(3650, $days));
        $st = Database::instance()->prepare('UPDATE store_quotes SET public_revoked_at = NULL, public_expires_at = DATE_ADD(NOW(), INTERVAL ? DAY), updated_at = NOW() WHERE code = ? AND site_id = @site_id');
        $st->execute([$days, strtoupper(trim($code))]);
        return $st->rowCount() > 0;
    }

    public function regeneratePublic(string $code): bool
    {
        $this->ensureTables();
        $token = bin2hex(random_bytes(32));
        $settings = $this->settings();
        $st = Database::instance()->prepare('UPDATE store_quotes SET public_token = ?, public_created_at = NOW(), public_expires_at = DATE_ADD(NOW(), INTERVAL ? DAY), public_revoked_at = NULL, updated_at = NOW() WHERE code = ? AND site_id = @site_id');
        $st->execute([$token, $settings['quote_public_days'], strtoupper(trim($code))]);
        return $st->rowCount() > 0;
    }

    public function deleteQuote(string $code): bool
    {
        $this->ensureTables();
        $row = $this->findByCode($code, false);
        if (!$row) return false;
        $db = Database::instance();
        $db->prepare('DELETE FROM quote_events WHERE quote_id = ? AND site_id = @site_id')->execute([(int)$row['id']]);
        $db->prepare('DELETE FROM store_quotes WHERE id = ? AND site_id = @site_id')->execute([(int)$row['id']]);
        try { (new QuotePdfService())->purge((int)$row['id']); } catch (\Throwable $e) {}
        return true;
    }

    public function stats(): array
    {
        $this->ensureTables();
        $st = Database::instance()->prepare("SELECT COUNT(*) AS total,
            SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) AS converted,
            SUM(total_cents) AS value_cents,
            SUM(viewed_count) AS views,
            SUM(CASE WHEN sent_at IS NOT NULL THEN 1 ELSE 0 END) AS sent,
            SUM(CASE WHEN public_expires_at IS NOT NULL AND public_expires_at < NOW() THEN 1 ELSE 0 END) AS public_expired,
            SUM(CASE WHEN public_revoked_at IS NOT NULL THEN 1 ELSE 0 END) AS revoked
            FROM store_quotes WHERE site_id = @site_id");
        $st->execute();
        $q = $st->fetch() ?: [];
        $orders = ['orders' => 0, 'paid' => 0, 'paid_value_cents' => 0];
        try {
            $ost = Database::instance()->prepare("SELECT COUNT(*) AS orders,
                SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) AS paid,
                SUM(CASE WHEN payment_status = 'paid' THEN total_cents ELSE 0 END) AS paid_value_cents
                FROM store_orders WHERE site_id = @site_id AND quote_code IS NOT NULL AND quote_code <> ''");
            $ost->execute();
            $orders = array_merge($orders, $ost->fetch() ?: []);
        } catch (\Throwable $e) {}
        $total = (int)($q['total'] ?? 0);
        $converted = (int)($q['converted'] ?? 0);
        $paid = (int)($orders['paid'] ?? 0);
        $out = [
            'total' => $total,
            'converted' => $converted,
            'value_cents' => (int)($q['value_cents'] ?? 0),
            'views' => (int)($q['views'] ?? 0),
            'sent' => (int)($q['sent'] ?? 0),
            'public_expired' => (int)($q['public_expired'] ?? 0),
            'revoked' => (int)($q['revoked'] ?? 0),
            'orders' => (int)($orders['orders'] ?? 0),
            'paid' => $paid,
            'paid_value_cents' => (int)($orders['paid_value_cents'] ?? 0),
            'conversion_rate' => $total > 0 ? round($converted / $total * 100, 1) : 0,
            'paid_rate' => $total > 0 ? round($paid / $total * 100, 1) : 0,
            'ticket_cents' => $paid > 0 ? (int)round(((int)$orders['paid_value_cents']) / $paid) : 0,
            'daily' => [],
        ];
        try {
            $dst = Database::instance()->prepare("SELECT DATE(created_at) AS day, COUNT(*) AS quotes, SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) AS converted FROM store_quotes WHERE site_id = @site_id AND created_at >= (NOW() - INTERVAL 13 DAY) GROUP BY DATE(created_at) ORDER BY day ASC");
            $dst->execute();
            $out['daily'] = $dst->fetchAll();
        } catch (\Throwable $e) {}
        return $out;
    }

    public function validateCustomer(array $input): array
    {
        return (new CustomerDataValidator())->validate($input);
    }

    public function publicQuote(array $row): array
    {
        if (!$row) return [];
        $url = $this->publicUrl($row);
        unset($row['meta'], $row['ip_hash'], $row['items_json'], $row['meta_json'], $row['customer_json'], $row['public_token']);
        if (isset($row['customer']) && is_array($row['customer'])) {
            $customer = $row['customer'];
            $row['customer'] = array_intersect_key($customer, array_flip([
                'type', 'name', 'full_name', 'company_name', 'document', 'nit', 'display_name',
            ]));
        }
        $row['url'] = $url;
        return $row;
    }

    public function securityKey(): string
    {
        $secret = (string)(Config::get('QUOTE_ENCRYPTION_KEY', '') ?: Config::get('JWT_SECRET', '') ?: 'sonika-quote-local-key');
        return hash('sha256', 'sonika-quote-link|' . $secret, true);
    }

    private function uniqueCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        for ($i = 0; $i < 20; $i++) {
            $suffix = '';
            for ($j = 0; $j < 5; $j++) $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            $code = 'COT-' . date('ymd') . '-' . $suffix;
            $st = Database::instance()->prepare('SELECT id FROM store_quotes WHERE code = ? AND site_id = @site_id LIMIT 1');
            $st->execute([$code]);
            if (!$st->fetchColumn()) return $code;
        }
        return 'COT-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
    }
}
