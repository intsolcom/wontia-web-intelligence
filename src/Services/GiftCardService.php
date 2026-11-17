<?php
namespace App\Services;

use App\Core\Config;
use App\Core\Database;

/**
 * Tarjetas de regalo SONIKA.
 *
 * - Compra digital con pago en línea (Mercado Pago / Bre-B / ePayco / asistido).
 * - Código + PIN canjeable total o parcialmente en el checkout.
 * - Reserva de saldo al crear el pedido y liberación si el pago falla.
 * - Entrega por correo inmediata o programada.
 * - Gestión administrativa de emisión, anulación, reenvío y ocasiones.
 */
class GiftCardService
{
    private static bool $ensured = false;

    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_VOID = 'void';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_DEPLETED = 'depleted';

    public function ensureTables(): void
    {
        if (self::$ensured) return;
        $db = Database::instance();

        $db->exec("CREATE TABLE IF NOT EXISTS gift_card_occasions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL DEFAULT 1,
            slug VARCHAR(80) NOT NULL,
            name VARCHAR(120) NOT NULL,
            icon VARCHAR(16) NOT NULL DEFAULT '🎁',
            tagline VARCHAR(190) NOT NULL DEFAULT '',
            description TEXT NULL,
            color_from VARCHAR(16) NOT NULL DEFAULT '#F20D16',
            color_to VARCHAR(16) NOT NULL DEFAULT '#101d3a',
            suggested_amounts VARCHAR(255) NOT NULL DEFAULT '5000000,10000000,20000000,30000000,50000000',
            default_message VARCHAR(255) NOT NULL DEFAULT '',
            image_url VARCHAR(500) NOT NULL DEFAULT '',
            active TINYINT NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_gc_occasion_slug (site_id, slug),
            KEY idx_gc_occasions_active (site_id, active, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS gift_cards (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL DEFAULT 1,
            code VARCHAR(32) NOT NULL,
            pin_hash VARCHAR(255) NOT NULL,
            pin_enc TEXT NOT NULL,
            occasion_id INT UNSIGNED NULL,
            amount_cents BIGINT NOT NULL DEFAULT 0,
            balance_cents BIGINT NOT NULL DEFAULT 0,
            currency VARCHAR(5) NOT NULL DEFAULT 'COP',
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            purchaser_name VARCHAR(190) NOT NULL DEFAULT '',
            purchaser_email VARCHAR(190) NOT NULL DEFAULT '',
            recipient_name VARCHAR(190) NOT NULL DEFAULT '',
            recipient_email VARCHAR(190) NOT NULL DEFAULT '',
            message VARCHAR(500) NOT NULL DEFAULT '',
            delivery_at DATETIME NULL,
            expires_at DATETIME NULL,
            order_id INT NULL,
            issued_by VARCHAR(80) NOT NULL DEFAULT '',
            issued_at DATETIME NULL,
            delivered_at DATETIME NULL,
            last_used_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_gc_code (site_id, code),
            KEY idx_gc_status (site_id, status, created_at),
            KEY idx_gc_order (site_id, order_id),
            KEY idx_gc_recipient (site_id, recipient_email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS gift_card_transactions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL DEFAULT 1,
            gift_card_id BIGINT UNSIGNED NOT NULL,
            order_id INT NULL,
            type VARCHAR(20) NOT NULL,
            amount_cents BIGINT NOT NULL DEFAULT 0,
            balance_after_cents BIGINT NOT NULL DEFAULT 0,
            note VARCHAR(255) NOT NULL DEFAULT '',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_gc_tx_card (site_id, gift_card_id, id),
            KEY idx_gc_tx_order (site_id, order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS gift_card_redemptions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL DEFAULT 1,
            gift_card_id BIGINT UNSIGNED NOT NULL,
            order_id INT NOT NULL,
            amount_cents BIGINT NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'reserved',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_gc_redemption (site_id, gift_card_id, order_id),
            KEY idx_gc_redemption_order (site_id, order_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS gift_card_purchases (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL DEFAULT 1,
            order_id INT NOT NULL,
            occasion_id INT UNSIGNED NULL,
            amount_cents BIGINT NOT NULL DEFAULT 0,
            quantity INT NOT NULL DEFAULT 1,
            sender_name VARCHAR(190) NOT NULL DEFAULT '',
            recipient_name VARCHAR(190) NOT NULL DEFAULT '',
            recipient_email VARCHAR(190) NOT NULL DEFAULT '',
            message VARCHAR(500) NOT NULL DEFAULT '',
            delivery_at DATETIME NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            issued_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_gc_purchase_order (site_id, order_id),
            KEY idx_gc_purchase_status (site_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS gift_card_attempts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL DEFAULT 1,
            ip_hash VARCHAR(64) NOT NULL DEFAULT '',
            code VARCHAR(32) NOT NULL DEFAULT '',
            success TINYINT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_gc_attempts_ip (site_id, ip_hash, created_at),
            KEY idx_gc_attempts_code (site_id, code, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->seedOccasions();
        self::$ensured = true;
    }

    private function seedOccasions(): void
    {
        $db = Database::instance();
        try {
            $st = $db->prepare("SELECT COUNT(*) FROM gift_card_occasions WHERE site_id = @site_id");
            $st->execute();
            if ((int)$st->fetchColumn() > 0) return;
        } catch (\Throwable $e) {
            return;
        }

        $seed = [
            ['cumpleanos', 'Cumpleaños', '🎂', 'Celebra otro año de música y buenos momentos.', '#F20D16', '#7C0A12', '5000000,10000000,20000000,30000000,50000000', '¡Feliz cumpleaños! Que este regalo suene a todo lo que te gusta.', 10],
            ['grado', 'Grado / Graduación', '🎓', 'Un logro que merece celebrarse con el regalo perfecto.', '#2563EB', '#0B1B3A', '10000000,20000000,30000000,50000000,80000000', '¡Felicitaciones por tu grado! Este es un regalo para celebrar tu esfuerzo.', 20],
            ['navidad', 'Navidad', '🎄', 'Comparte la magia de la música en esta Navidad.', '#0D7A58', '#062A20', '5000000,10000000,20000000,30000000,50000000', '¡Feliz Navidad! Que la música te acompañe siempre.', 30],
            ['dia-de-la-madre', 'Día de la Madre', '💐', 'Un detalle para la persona que siempre te acompaña.', '#DB2777', '#4A0B2A', '10000000,20000000,30000000,50000000,80000000', 'Feliz Día de la Madre. Gracias por tanto, hoy te consentimos.', 40],
            ['dia-del-padre', 'Día del Padre', '🎸', 'Para el que te enseñó a amar la música.', '#1D4ED8', '#0B1220', '10000000,20000000,30000000,50000000,80000000', 'Feliz Día del Padre. Este regalo lo eligió alguien que te admira.', 50],
            ['amor-y-amistad', 'Amor y Amistad', '❤️', 'Dile a alguien cuánto significa con un regalo que suena.', '#E11D48', '#3B0716', '5000000,10000000,20000000,30000000,50000000', 'Feliz Amor y Amistad. Gracias por estar siempre.', 60],
            ['dia-del-musico', 'Día del Músico', '🎶', '22 de noviembre, Santa Cecilia. Celebra a quien vive la música.', '#7C3AED', '#1A0B33', '10000000,20000000,30000000,50000000,80000000', '¡Feliz Día del Músico! Que nunca te falten canciones ni escenarios.', 70],
            ['patrocinio-al-talento', 'Patrocinio al talento', '🌟', 'Apoya a un músico, una banda o un proyecto musical.', '#F59E0B', '#3A2403', '30000000,50000000,100000000,200000000,300000000', 'Este aporte es para que tu talento siga creciendo. ¡Cree en tu música!', 80],
            ['regalo-libre', 'Regalo libre', '🎁', 'Sin fecha especial: solo porque sí.', '#101D3A', '#F20D16', '5000000,10000000,20000000,30000000,50000000', 'Un regalo para ti, porque la música siempre es una buena idea.', 90],
            ['quinceanera', 'Quinceañera', '👑', 'Quince años, una vida entera por sonar.', '#C026D3', '#3A0A44', '20000000,30000000,50000000,80000000,100000000', '¡Felices 15! Que esta nueva etapa venga llena de música y sueños.', 100],
            ['aniversario', 'Aniversario', '💍', 'Celebra el tiempo juntos con un regalo que emociona.', '#B45309', '#2A1602', '20000000,30000000,50000000,80000000,100000000', 'Feliz aniversario. Que sigan sumando canciones y momentos.', 110],
            ['baby-shower', 'Baby shower', '🍼', 'Un nuevo comienzo merece una melodía especial.', '#0EA5E9', '#082F49', '10000000,20000000,30000000,50000000,80000000', '¡Bienvenido, pequeño! Aquí empieza tu primera playlist.', 120],
            ['agradecimiento', 'Agradecimiento', '🙏', 'Gracias es más que una palabra. Hazlo sonar.', '#0D9488', '#042F2E', '5000000,10000000,20000000,30000000,50000000', 'Gracias por todo. Este detalle es para ti.', 130],
            ['condolencias', 'Condolencias', '🕊️', 'Un gesto de acompañamiento en un momento difícil.', '#475569', '#0F172A', '5000000,10000000,20000000,30000000,50000000', 'Con cariño y respeto, en memoria de quien amamos.', 140],
            ['corporativo', 'Regalo corporativo', '💼', 'Reconoce a tu equipo, clientes o aliados.', '#0F766E', '#042F2E', '50000000,100000000,200000000,300000000,500000000', 'Gracias por ser parte de nuestra historia. Este regalo es para ti.', 150],
            ['dia-del-nino', 'Día del Niño', '🧸', 'La música también es cosa de niños.', '#EA580C', '#3A1602', '5000000,10000000,20000000,30000000,50000000', '¡Feliz Día del Niño! Que la música te haga feliz siempre.', 160],
            ['dia-de-las-velitas', 'Día de las Velitas', '🕯️', 'Enciende la Navidad con un regalo que suena.', '#CA8A04', '#3A2602', '5000000,10000000,20000000,30000000,50000000', 'Feliz Día de las Velitas. Que la luz y la música iluminen tu casa.', 170],
            ['ano-nuevo', 'Año Nuevo', '🎆', 'Empieza el año con nuevos sonidos.', '#1E40AF', '#0B1220', '10000000,20000000,30000000,50000000,80000000', '¡Feliz Año Nuevo! Que este año venga con la mejor música.', 180],
        ];

        $ins = $db->prepare("INSERT INTO gift_card_occasions
            (site_id, slug, name, icon, tagline, description, color_from, color_to, suggested_amounts, default_message, active, sort_order)
            VALUES (@site_id, :slug, :name, :icon, :tagline, :description, :cf, :ct, :amounts, :message, 1, :sort)");
        foreach ($seed as $row) {
            try {
                $ins->execute([
                    'slug' => $row[0], 'name' => $row[1], 'icon' => $row[2], 'tagline' => $row[3],
                    'description' => $row[3], 'cf' => $row[4], 'ct' => $row[5],
                    'amounts' => $row[6], 'message' => $row[7], 'sort' => $row[8],
                ]);
            } catch (\Throwable $e) {
            }
        }
    }

    private function secret(): string
    {
        $db = Database::instance();
        try {
            $st = $db->prepare("SELECT `value` FROM settings WHERE site_id = @site_id AND `key` = 'gift_card_secret' LIMIT 1");
            $st->execute();
            $found = (string)$st->fetchColumn();
            if ($found !== '') return $found;
        } catch (\Throwable $e) {
        }
        $secret = bin2hex(random_bytes(32));
        try {
            $st = $db->prepare("INSERT INTO settings (site_id, `key`, `value`) VALUES (@site_id, 'gift_card_secret', ?)
                ON DUPLICATE KEY UPDATE `value` = `value`");
            $st->execute([$secret]);
            $st = $db->prepare("SELECT `value` FROM settings WHERE site_id = @site_id AND `key` = 'gift_card_secret' LIMIT 1");
            $st->execute();
            $found = (string)$st->fetchColumn();
            if ($found !== '') return $found;
        } catch (\Throwable $e) {
        }
        return $secret;
    }

    public function settings(): array
    {
        $this->ensureTables();
        $keys = [
            'gift_card_enabled', 'gift_card_min_cents', 'gift_card_max_cents',
            'gift_card_expiry_days', 'gift_card_hold_hours', 'gift_card_terms_url',
        ];
        $map = [];
        try {
            $db = Database::instance();
            $ph = implode(',', array_fill(0, count($keys), '?'));
            $st = $db->prepare("SELECT `key`, `value` FROM settings WHERE site_id = @site_id AND `key` IN ($ph)");
            $st->execute($keys);
            foreach ($st->fetchAll() as $row) $map[(string)$row['key']] = (string)$row['value'];
        } catch (\Throwable $e) {
        }
        $out = [
            'enabled' => ($map['gift_card_enabled'] ?? '1') !== '0',
            'min_cents' => max(10000, (int)($map['gift_card_min_cents'] ?? 1000000)),
            'max_cents' => max(100000, (int)($map['gift_card_max_cents'] ?? 200000000)),
            'expiry_days' => max(30, min(3650, (int)($map['gift_card_expiry_days'] ?? 365))),
            'hold_hours' => max(1, min(240, (int)($map['gift_card_hold_hours'] ?? 24))),
            'terms_url' => trim((string)($map['gift_card_terms_url'] ?? '/politicas')) ?: '/politicas',
            'currency' => 'COP',
            'secret' => $this->secret(),
        ];
        try {
            $store = (new StoreService())->settings();
            $out['currency'] = (string)($store['store_currency'] ?? 'COP') ?: 'COP';
        } catch (\Throwable $e) {
        }
        if ($out['max_cents'] < $out['min_cents']) $out['max_cents'] = $out['min_cents'];
        return $out;
    }

    public function publicConfig(): array
    {
        $s = $this->settings();
        return [
            'enabled' => $s['enabled'],
            'currency' => $s['currency'],
            'min_cents' => $s['min_cents'],
            'max_cents' => $s['max_cents'],
            'expiry_days' => $s['expiry_days'],
            'terms_url' => $s['terms_url'],
            'occasions' => $this->occasions(true),
            'methods' => $this->paymentMethods(),
        ];
    }

    public function occasions(bool $activeOnly = true): array
    {
        $this->ensureTables();
        try {
            $sql = "SELECT * FROM gift_card_occasions WHERE site_id = @site_id"
                . ($activeOnly ? " AND active = 1" : "")
                . " ORDER BY sort_order ASC, id ASC";
            $st = Database::instance()->query($sql);
            $rows = $st->fetchAll();
            foreach ($rows as &$row) {
                $row['suggested_amounts'] = array_values(array_filter(array_map(
                    fn($v) => max(0, (int)trim((string)$v)),
                    explode(',', (string)($row['suggested_amounts'] ?? ''))
                )));
            }
            unset($row);
            return $rows;
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function occasionBySlug(string $slug): ?array
    {
        $this->ensureTables();
        $slug = strtolower(trim($slug));
        if ($slug === '') return null;
        try {
            $st = Database::instance()->prepare("SELECT * FROM gift_card_occasions WHERE site_id = @site_id AND slug = ? LIMIT 1");
            $st->execute([$slug]);
            $row = $st->fetch() ?: null;
            if ($row) {
                $row['suggested_amounts'] = array_values(array_filter(array_map(
                    fn($v) => max(0, (int)trim((string)$v)),
                    explode(',', (string)($row['suggested_amounts'] ?? ''))
                )));
            }
            return $row;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function occasionById(int $id): ?array
    {
        $this->ensureTables();
        try {
            $st = Database::instance()->prepare("SELECT * FROM gift_card_occasions WHERE site_id = @site_id AND id = ? LIMIT 1");
            $st->execute([$id]);
            return $st->fetch() ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function paymentMethods(): array
    {
        $allowed = ['mercadopago', 'breb', 'epayco', 'request'];
        try {
            $visible = (new CheckoutPaymentMethodService())->visible();
            $out = [];
            foreach ($visible as $m) {
                $id = (string)($m['id'] ?? '');
                if ($id === '' || !in_array($id, $allowed, true)) continue;
                if (empty($m['available'])) continue;
                $out[] = [
                    'id' => $id,
                    'label' => (string)($m['label'] ?? $id),
                    'description' => (string)($m['description'] ?? ''),
                    'badge' => (string)($m['badge'] ?? ''),
                ];
            }
            return $out;
        } catch (\Throwable $e) {
        }

        // Respaldo si el servicio central no está disponible.
        $out = [];
        try {
            $mp = new MercadoPagoService();
            if ($mp->isAvailable()) {
                $settings = $mp->settings();
                $out[] = [
                    'id' => 'mercadopago',
                    'label' => (string)($settings['label'] ?? 'Mercado Pago'),
                    'description' => 'Tarjeta, PSE, efectivo y cuotas con Mercado Pago.',
                ];
            }
        } catch (\Throwable $e) {
        }
        try {
            $breb = new BrebService();
            $bs = $breb->settings();
            if ($breb->available()) {
                $out[] = [
                    'id' => 'breb',
                    'label' => (string)($bs['label'] ?? 'Bre-B'),
                    'description' => 'Escanea el QR y paga desde tu app bancaria.',
                ];
            }
            if (!empty($bs['epayco_enabled']) && (string)($bs['epayco_link'] ?? '') !== '') {
                $out[] = [
                    'id' => 'epayco',
                    'label' => (string)($bs['epayco_label'] ?? 'ePayco'),
                    'description' => 'Paga en el entorno seguro de ePayco.',
                ];
            }
            if (!empty($bs['request_enabled'])) {
                $out[] = [
                    'id' => 'request',
                    'label' => 'Coordinar pago por WhatsApp',
                    'description' => 'Te ayudamos a completar el pago de forma asistida.',
                ];
            }
        } catch (\Throwable $e) {
        }
        return $out;
    }

    public function money(int $cents, string $currency = 'COP'): string
    {
        $sym = $currency === 'USD' ? 'US$' : ($currency === 'COP' ? '$' : $currency . ' ');
        return $sym . number_format($cents / 100, $currency === 'COP' ? 0 : 2, ',', '.');
    }

    public function maskCode(string $code): string
    {
        $code = strtoupper(trim($code));
        if (strlen($code) <= 8) return $code;
        return substr($code, 0, 8) . '••••' . substr($code, -4);
    }

    public function pinFor(array $card): string
    {
        $enc = (string)($card['pin_enc'] ?? '');
        if ($enc === '') return '';
        try {
            $raw = base64_decode($enc, true);
            if ($raw === false || strlen($raw) < 29) return '';
            $iv = substr($raw, 0, 12);
            $tag = substr($raw, 12, 16);
            $cipher = substr($raw, 28);
            $key = hash('sha256', $this->secret() . '|sonika-gift-card', true);
            $pin = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
            return $pin === false ? '' : (string)$pin;
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function encryptPin(string $pin): string
    {
        $key = hash('sha256', $this->secret() . '|sonika-gift-card', true);
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($pin, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) throw new \RuntimeException('No se pudo proteger el PIN de la tarjeta.');
        return base64_encode($iv . $tag . $cipher);
    }

    private function generateCode(): string
    {
        $alphabet = self::CODE_ALPHABET;
        $groups = [];
        for ($g = 0; $g < 3; $g++) {
            $part = '';
            for ($i = 0; $i < 4; $i++) {
                $part .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $groups[] = $part;
        }
        return 'SNK-' . implode('-', $groups);
    }

    private function generatePin(): string
    {
        return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function normalizeCode(string $code): string
    {
        $code = strtoupper(trim($code));
        $code = preg_replace('/[^A-Z0-9]/', '', $code);
        if (str_starts_with($code, 'SNK')) $code = substr($code, 3);
        $code = substr($code, 0, 12);
        if (strlen($code) !== 12) return '';
        return 'SNK-' . substr($code, 0, 4) . '-' . substr($code, 4, 4) . '-' . substr($code, 8, 4);
    }

    private function pinValid(string $pin, array $card): bool
    {
        $pin = preg_replace('/\D+/', '', $pin);
        if (strlen($pin) !== 6) return false;
        return password_verify($pin, (string)($card['pin_hash'] ?? ''));
    }

    private function registerAttempt(string $code, bool $success, string $ip = ''): void
    {
        try {
            $st = Database::instance()->prepare("INSERT INTO gift_card_attempts (site_id, ip_hash, code, success) VALUES (@site_id, ?, ?, ?)");
            $st->execute([$ip !== '' ? hash('sha256', $ip) : '', substr($code, 0, 32), $success ? 1 : 0]);
            if (random_int(1, 20) === 1) {
                Database::instance()->exec("DELETE FROM gift_card_attempts WHERE site_id = @site_id AND created_at < (NOW() - INTERVAL 2 DAY)");
            }
        } catch (\Throwable $e) {
        }
    }

    private function attemptsExceeded(string $code, string $ip = ''): bool
    {
        try {
            $db = Database::instance();
            $ipHash = $ip !== '' ? hash('sha256', $ip) : '';
            if ($ipHash !== '') {
                $st = $db->prepare("SELECT COUNT(*) FROM gift_card_attempts WHERE site_id = @site_id AND ip_hash = ? AND success = 0 AND created_at >= (NOW() - INTERVAL 15 MINUTE)");
                $st->execute([$ipHash]);
                if ((int)$st->fetchColumn() >= 12) return true;
            }
            $st = $db->prepare("SELECT COUNT(*) FROM gift_card_attempts WHERE site_id = @site_id AND code = ? AND success = 0 AND created_at >= (NOW() - INTERVAL 15 MINUTE)");
            $st->execute([substr($code, 0, 32)]);
            return (int)$st->fetchColumn() >= 5;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Valida una tarjeta para mostrarla o para aplicarla al checkout.
     * No modifica saldo.
     */
    public function validateCode(string $code, string $pin, int $amountCents = 0, bool $record = true, string $ip = ''): array
    {
        $this->ensureTables();
        $normalized = $this->normalizeCode($code);
        if ($normalized === '') {
            if ($record) $this->registerAttempt($normalized, false, $ip);
            return ['ok' => false, 'message' => 'Escribe el código completo de la tarjeta.'];
        }
        if ($this->attemptsExceeded($normalized, $ip)) {
            return ['ok' => false, 'message' => 'Demasiados intentos. Espera 15 minutos e intenta de nuevo.'];
        }
        try {
            $st = Database::instance()->prepare("SELECT * FROM gift_cards WHERE site_id = @site_id AND code = ? LIMIT 1");
            $st->execute([$normalized]);
            $card = $st->fetch() ?: null;
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No pudimos consultar la tarjeta.'];
        }
        if (!$card || !$this->pinValid($pin, $card)) {
            if ($record) $this->registerAttempt($normalized, false, $ip);
            return ['ok' => false, 'message' => 'Código o PIN incorrecto. Revisa los datos e intenta de nuevo.'];
        }
        if ($record) $this->registerAttempt($normalized, true, $ip);

        $status = (string)$card['status'];
        $balance = (int)$card['balance_cents'];
        $expires = (string)($card['expires_at'] ?? '');
        if ($status === self::STATUS_ACTIVE && $expires !== '' && strtotime($expires) !== false && strtotime($expires) < time()) {
            $status = self::STATUS_EXPIRED;
            try {
                Database::instance()->prepare("UPDATE gift_cards SET status = 'expired' WHERE id = ? AND site_id = @site_id")
                    ->execute([(int)$card['id']]);
            } catch (\Throwable $e) {
            }
        }
        if ($status === self::STATUS_VOID) return ['ok' => false, 'message' => 'Esta tarjeta fue anulada.'];
        if ($status === self::STATUS_EXPIRED) return ['ok' => false, 'message' => 'Esta tarjeta está vencida.'];
        if ($status === self::STATUS_DEPLETED || $balance <= 0) return ['ok' => false, 'message' => 'Esta tarjeta ya no tiene saldo disponible.'];

        $apply = $amountCents > 0 ? min($balance, max(0, $amountCents)) : $balance;
        $occasion = null;
        if (!empty($card['occasion_id'])) {
            $occasion = $this->occasionById((int)$card['occasion_id']);
        }
        return [
            'ok' => true,
            'card_id' => (int)$card['id'],
            'code' => (string)$card['code'],
            'code_masked' => $this->maskCode((string)$card['code']),
            'balance_cents' => $balance,
            'amount_cents' => (int)$card['amount_cents'],
            'apply_cents' => $apply,
            'currency' => (string)$card['currency'],
            'status' => $status,
            'expires_at' => $expires,
            'occasion' => $occasion ? [
                'slug' => (string)$occasion['slug'],
                'name' => (string)$occasion['name'],
                'icon' => (string)$occasion['icon'],
            ] : null,
            'recipient_name' => (string)($card['recipient_name'] ?? ''),
            'message' => 'Tarjeta aplicada. Saldo disponible: ' . $this->money($balance, (string)$card['currency']) . '.',
        ];
    }

    /**
     * Consulta pública de saldo. Devuelve movimientos sin exponer el PIN.
     */
    public function balanceInquiry(string $code, string $pin, string $ip = ''): array
    {
        $v = $this->validateCode($code, $pin, 0, true, $ip);
        if (empty($v['ok'])) return $v;
        $tx = [];
        try {
            $st = Database::instance()->prepare("SELECT type, amount_cents, balance_after_cents, note, created_at
                FROM gift_card_transactions WHERE site_id = @site_id AND gift_card_id = ? ORDER BY id DESC LIMIT 20");
            $st->execute([(int)$v['card_id']]);
            $tx = $st->fetchAll();
        } catch (\Throwable $e) {
        }
        $v['transactions'] = array_map(function ($row) {
            return [
                'type' => (string)$row['type'],
                'amount_cents' => (int)$row['amount_cents'],
                'balance_after_cents' => (int)$row['balance_after_cents'],
                'note' => (string)$row['note'],
                'created_at' => (string)$row['created_at'],
            ];
        }, $tx);
        return $v;
    }

    /**
     * Reserva saldo dentro de la transacción del pedido.
     * Decrementa el saldo de inmediato y registra el movimiento.
     */
    public function reserveForOrder(int $orderId, string $code, string $pin, int $amountCents): array
    {
        $this->ensureTables();
        $amountCents = max(0, $amountCents);
        if ($amountCents <= 0) return ['ok' => false, 'message' => 'Monto de tarjeta inválido.'];
        $normalized = $this->normalizeCode($code);
        if ($normalized === '') return ['ok' => false, 'message' => 'Código de tarjeta inválido.'];
        $db = Database::instance();
        try {
            $st = $db->prepare("SELECT * FROM gift_cards WHERE site_id = @site_id AND code = ? LIMIT 1 FOR UPDATE");
            $st->execute([$normalized]);
            $card = $st->fetch() ?: null;
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No pudimos consultar la tarjeta.'];
        }
        if (!$card || !$this->pinValid($pin, $card)) return ['ok' => false, 'message' => 'Código o PIN incorrecto.'];
        if ((string)$card['status'] !== self::STATUS_ACTIVE) return ['ok' => false, 'message' => 'La tarjeta no está activa.'];
        $expires = (string)($card['expires_at'] ?? '');
        if ($expires !== '' && strtotime($expires) !== false && strtotime($expires) < time()) {
            return ['ok' => false, 'message' => 'La tarjeta está vencida.'];
        }
        $balance = (int)$card['balance_cents'];
        if ($balance < $amountCents) return ['ok' => false, 'message' => 'El saldo de la tarjeta no alcanza para este pedido.'];
        $newBalance = $balance - $amountCents;
        try {
            $db->prepare("UPDATE gift_cards SET balance_cents = ?, status = ?, last_used_at = NOW() WHERE id = ? AND site_id = @site_id")
                ->execute([$newBalance, $newBalance <= 0 ? self::STATUS_DEPLETED : self::STATUS_ACTIVE, (int)$card['id']]);
            $db->prepare("INSERT INTO gift_card_redemptions (site_id, gift_card_id, order_id, amount_cents, status)
                VALUES (@site_id, ?, ?, ?, 'reserved')
                ON DUPLICATE KEY UPDATE amount_cents = VALUES(amount_cents), status = 'reserved', updated_at = NOW()")
                ->execute([(int)$card['id'], $orderId, $amountCents]);
            $db->prepare("INSERT INTO gift_card_transactions (site_id, gift_card_id, order_id, type, amount_cents, balance_after_cents, note)
                VALUES (@site_id, ?, ?, 'redeem', ?, ?, 'Saldo reservado para el pedido')")
                ->execute([(int)$card['id'], $orderId, -$amountCents, $newBalance]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo reservar el saldo de la tarjeta.'];
        }
        return [
            'ok' => true,
            'card_id' => (int)$card['id'],
            'code' => (string)$card['code'],
            'code_masked' => $this->maskCode((string)$card['code']),
            'amount_cents' => $amountCents,
            'balance_after_cents' => $newBalance,
        ];
    }

    public function completeForOrder(int $orderId): void
    {
        try {
            $db = Database::instance();
            $db->prepare("UPDATE gift_card_redemptions SET status = 'completed', updated_at = NOW()
                WHERE site_id = @site_id AND order_id = ? AND status = 'reserved'")->execute([$orderId]);
        } catch (\Throwable $e) {
        }
    }

    public function releaseForOrder(int $orderId, string $reason = ''): void
    {
        $db = null;
        $ownTx = false;
        try {
            $db = Database::instance();
            $ownTx = !$db->inTransaction();
            if ($ownTx) $db->beginTransaction();
            $st = $db->prepare("SELECT * FROM gift_card_redemptions WHERE site_id = @site_id AND order_id = ? AND status = 'reserved'");
            $st->execute([$orderId]);
            $rows = $st->fetchAll();
            foreach ($rows as $row) {
                $stCard = $db->prepare("SELECT * FROM gift_cards WHERE site_id = @site_id AND id = ? LIMIT 1 FOR UPDATE");
                $stCard->execute([(int)$row['gift_card_id']]);
                $card = $stCard->fetch() ?: null;
                if (!$card) continue;
                $amount = (int)$row['amount_cents'];
                $newBalance = (int)$card['balance_cents'] + $amount;
                $status = (string)$card['status'];
                if ($status === self::STATUS_DEPLETED && $newBalance > 0) $status = self::STATUS_ACTIVE;
                $db->prepare("UPDATE gift_cards SET balance_cents = ?, status = ? WHERE id = ? AND site_id = @site_id")
                    ->execute([$newBalance, $status, (int)$card['id']]);
                $db->prepare("UPDATE gift_card_redemptions SET status = 'released', updated_at = NOW() WHERE id = ? AND site_id = @site_id")
                    ->execute([(int)$row['id']]);
                $db->prepare("INSERT INTO gift_card_transactions (site_id, gift_card_id, order_id, type, amount_cents, balance_after_cents, note)
                    VALUES (@site_id, ?, ?, 'release', ?, ?, ?)")
                    ->execute([(int)$card['id'], $orderId, $amount, $newBalance, mb_substr($reason !== '' ? $reason : 'Reserva liberada', 0, 255)]);
            }
            if ($ownTx) $db->commit();
        } catch (\Throwable $e) {
            if ($ownTx && $db && $db->inTransaction()) $db->rollBack();
        }
    }

    /**
     * Compra una tarjeta de regalo. Crea un pedido digital sin envío.
     */
    public function createPurchase(array $input, string $ip = ''): array
    {
        $this->ensureTables();
        (new StoreService())->ensureTables();
        (new BrebService())->ensureTables();
        (new CustomerService())->ensureTables();
        $settings = $this->settings();
        if (!$settings['enabled']) throw new \RuntimeException('Las tarjetas de regalo no están disponibles en este momento.');

        $occasion = null;
        if (!empty($input['occasion_slug'])) $occasion = $this->occasionBySlug((string)$input['occasion_slug']);
        elseif (!empty($input['occasion_id'])) $occasion = $this->occasionById((int)$input['occasion_id']);
        if (!$occasion || (int)$occasion['active'] !== 1) throw new \InvalidArgumentException('Selecciona una ocasión válida.');

        $amount = (int)($input['amount_cents'] ?? 0);
        if ($amount <= 0 && isset($input['amount'])) $amount = (int)round(((float)$input['amount']) * 100);
        $amount = (int)round($amount / 100) * 100;
        if ($amount < $settings['min_cents']) throw new \InvalidArgumentException('El monto mínimo de la tarjeta es ' . $this->money($settings['min_cents'], $settings['currency']) . '.');
        if ($amount > $settings['max_cents']) throw new \InvalidArgumentException('El monto máximo de la tarjeta es ' . $this->money($settings['max_cents'], $settings['currency']) . '.');

        $senderName = trim((string)($input['sender_name'] ?? ''));
        $senderEmail = trim((string)($input['sender_email'] ?? ''));
        $recipientName = trim((string)($input['recipient_name'] ?? ''));
        $recipientEmail = trim((string)($input['recipient_email'] ?? ''));
        $message = trim((string)($input['message'] ?? ''));
        if ($message === '') $message = (string)($occasion['default_message'] ?? '');
        $message = mb_substr(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $message) ?? '', 0, 300);

        if ($senderName === '' || mb_strlen($senderName) < 2) throw new \InvalidArgumentException('Escribe tu nombre.');
        if (!filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException('Escribe un correo válido para enviarte la copia.');
        if ($recipientName === '' || mb_strlen($recipientName) < 2) throw new \InvalidArgumentException('Escribe el nombre de quien recibe la tarjeta.');
        if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException('Escribe un correo válido para enviar la tarjeta.');
        if (empty($input['terms'])) throw new \InvalidArgumentException('Debes aceptar los términos y condiciones.');

        $deliveryAt = null;
        $deliveryRaw = trim((string)($input['delivery_at'] ?? ''));
        if ($deliveryRaw !== '') {
            $deliveryAt = $this->parseDeliveryAt($deliveryRaw);
        }

        $methods = $this->paymentMethods();
        $allowed = array_column($methods, 'id');
        if (!$allowed) throw new \RuntimeException('No hay métodos de pago disponibles para tarjetas de regalo.');
        $method = (string)($input['payment_method'] ?? $allowed[0]);
        if (!in_array($method, $allowed, true)) $method = (string)$allowed[0];

        $db = Database::instance();
        $this->assertPurchaseRateLimit($db, $senderEmail, $ip);

        $currency = $settings['currency'];
        $uuid = $this->uuid4();
        $orderNumber = 'GC-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        $customerId = $this->upsertCustomer($senderName, $senderEmail, '', '', json_encode(['digital' => true]));
        $addressJson = json_encode(['digital' => true, 'delivery' => 'email', 'recipient_email' => $recipientEmail]);
        $expiresAt = date('Y-m-d H:i:s', time() + $settings['expiry_days'] * 86400);
        $paymentMeta = json_encode([
            'gift_card_purchase' => true,
            'occasion' => (string)$occasion['slug'],
            'recipient_email' => $recipientEmail,
            'delivery_at' => $deliveryAt,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $db->beginTransaction();
        try {
            $db->prepare("INSERT INTO store_orders (site_id, uuid, order_number, customer_id, customer_name, customer_email, customer_phone, customer_document,
                shipping_address, subtotal_cents, subtotal_net_cents, tax_cents, breb_discount_cents, breb_discount_net_cents,
                recommendation_discount_cents, recommendation_discount_net_cents, shipping_cents, discount_cents, total_cents, currency,
                payment_method, payment_status, payment_reference, payment_expires_at, payment_proof_status, payment_meta, fulfillment_status, notes, ip_hash,
                gift_card_cents, gift_card_code)
                VALUES (@site_id, :uuid, :num, :cid, :name, :email, '', '',
                :addr, :sub, 0, 0, 0, 0, 0, 0, 0, 0, :total, :cur,
                :pm, 'pending', :ref, :expires, 'none', :meta, 'new', :notes, :iph, 0, NULL)")
                ->execute([
                    'uuid' => $uuid, 'num' => $orderNumber, 'cid' => $customerId, 'name' => $senderName, 'email' => $senderEmail,
                    'addr' => $addressJson, 'sub' => $amount, 'total' => $amount, 'cur' => $currency,
                    'pm' => $method, 'ref' => strtoupper($method) . '-' . $orderNumber,
                    'expires' => $method === 'breb' ? date('Y-m-d H:i:s', time() + 7200) : null,
                    'meta' => $paymentMeta, 'notes' => 'Tarjeta de regalo para ' . $recipientName . ' <' . $recipientEmail . '>',
                    'iph' => $ip !== '' ? hash('sha256', $ip) : '',
                ]);
            $orderId = (int)$db->lastInsertId();

            $db->prepare("INSERT INTO store_order_items (site_id, order_id, product_id, variant_id, name, variant_name, price_cents, qty, subtotal_cents, discount_cents, discount_net_cents, recommendation_discount_cents, recommendation_discount_net_cents, image)
                VALUES (@site_id, ?, 0, NULL, ?, '', ?, 1, ?, 0, 0, 0, 0, '')")
                ->execute([$orderId, 'Tarjeta de regalo SONIKA — ' . (string)$occasion['name'], $amount, $amount]);

            $db->prepare("INSERT INTO gift_card_purchases (site_id, order_id, occasion_id, amount_cents, quantity, sender_name, recipient_name, recipient_email, message, delivery_at, status)
                VALUES (@site_id, ?, ?, ?, 1, ?, ?, ?, ?, ?, 'pending')")
                ->execute([$orderId, (int)$occasion['id'], $amount, $senderName, $recipientName, $recipientEmail, $message, $deliveryAt]);

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }

        $order = (new StoreService())->orderById($orderId);
        if (!$order) throw new \RuntimeException('No se pudo crear el pedido de la tarjeta.');

        $payment = ['method' => $method, 'status' => 'pending'];
        try {
            if ($method === 'mercadopago' && (new MercadoPagoService())->isAvailable()) {
                $mp = new MercadoPagoService();
                $result = $mp->mode() === 'checkout_pro'
                    ? $mp->createPreference($order)
                    : ['ok' => true, 'redirect_url' => '', 'status' => 'pending'];
                if (empty($result['ok'])) {
                    (new StoreService())->updateOrder($orderId, ['payment_status' => 'failed']);
                    throw new \RuntimeException((string)($result['message'] ?? 'No se pudo iniciar el pago con Mercado Pago.'));
                }
                $meta = json_decode((string)($order['payment_meta'] ?? ''), true) ?: [];
                $meta['mercadopago_mode'] = $mp->mode();
                $meta['mercadopago_redirect_url'] = (string)($result['redirect_url'] ?? '');
                $meta['mercadopago_status'] = 'pending';
                $db->prepare("UPDATE store_orders SET payment_reference = ?, payment_link = ?, payment_meta = ?, payment_expires_at = ? WHERE id = ? AND site_id = @site_id")
                    ->execute([
                        'MP-' . $orderNumber,
                        (string)($result['redirect_url'] ?? '') ?: null,
                        json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        date('Y-m-d H:i:s', time() + 3600),
                        $orderId,
                    ]);
                $payment = [
                    'method' => 'mercadopago',
                    'redirect_url' => (string)($result['redirect_url'] ?? ''),
                    'status' => 'pending',
                ];
            } elseif ($method === 'epayco') {
                $bs = (new BrebService())->settings();
                $db->prepare("UPDATE store_orders SET payment_link = ? WHERE id = ? AND site_id = @site_id")
                    ->execute([(string)($bs['epayco_link'] ?? ''), $orderId]);
                $payment = ['method' => 'epayco', 'redirect_url' => (string)($bs['epayco_link'] ?? ''), 'status' => 'pending', 'external' => true];
            } elseif ($method === 'breb') {
                $payment = (new BrebService())->paymentInfo((new StoreService())->orderById($orderId));
            } else {
                $payment = ['method' => 'request', 'status' => 'pending', 'assisted' => true];
            }
        } catch (\Throwable $e) {
            try { (new StoreService())->updateOrder($orderId, ['payment_status' => 'failed']); } catch (\Throwable $x) {}
            throw $e;
        }

        $order = (new StoreService())->orderById($orderId);
        try { $this->notifyPurchase($order); } catch (\Throwable $e) {}
        return ['order' => $order, 'payment' => $payment];
    }

    private function assertPurchaseRateLimit(\PDO $db, string $email, string $ip): void
    {
        try {
            if ($ip !== '') {
                $st = $db->prepare("SELECT COUNT(*) FROM store_orders WHERE site_id = @site_id AND ip_hash = ? AND created_at >= (NOW() - INTERVAL 1 HOUR)");
                $st->execute([hash('sha256', $ip)]);
                if ((int)$st->fetchColumn() >= 8) throw new \RuntimeException('Demasiados pedidos desde tu conexión. Intenta más tarde.');
            }
            $st = $db->prepare("SELECT COUNT(*) FROM store_orders WHERE site_id = @site_id AND customer_email = ? AND created_at >= (NOW() - INTERVAL 1 HOUR)");
            $st->execute([$email]);
            if ((int)$st->fetchColumn() >= 4) throw new \RuntimeException('Ya registramos varios pedidos con este correo. Espera unos minutos.');
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
        }
    }

    private function upsertCustomer(string $name, string $email, string $phone, string $doc, string $addressJson): int
    {
        $db = Database::instance();
        $st = $db->prepare("SELECT id FROM store_customers WHERE site_id = @site_id AND email = ? LIMIT 1");
        $st->execute([$email]);
        $id = $st->fetchColumn();
        if ($id) {
            $db->prepare("UPDATE store_customers SET name = ?, phone = ?, document = ?, address = ? WHERE id = ? AND site_id = @site_id")
                ->execute([$name, $phone, $doc, $addressJson, (int)$id]);
            return (int)$id;
        }
        $db->prepare("INSERT INTO store_customers (site_id, name, email, phone, document, address) VALUES (@site_id, ?, ?, ?, ?, ?)")
            ->execute([$name, $email, $phone, $doc, $addressJson]);
        return (int)$db->lastInsertId();
    }

    private function uuid4(): string
    {
        $d = random_bytes(16);
        $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
        $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
    }

    public function purchaseForOrder(int $orderId): ?array
    {
        $this->ensureTables();
        try {
            $st = Database::instance()->prepare("SELECT * FROM gift_card_purchases WHERE site_id = @site_id AND order_id = ? LIMIT 1");
            $st->execute([$orderId]);
            return $st->fetch() ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function cardsForOrder(int $orderId): array
    {
        $this->ensureTables();
        try {
            $st = Database::instance()->prepare("SELECT * FROM gift_cards WHERE site_id = @site_id AND order_id = ? ORDER BY id ASC");
            $st->execute([$orderId]);
            return $st->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Se ejecuta al confirmar el pago de cualquier pedido.
     * - Completa reservas de tarjetas usadas como medio de pago.
     * - Emite la tarjeta cuando el pedido es una compra de tarjeta de regalo.
     */
    public function onOrderPaid(string $uuid): void
    {
        $this->ensureTables();
        try {
            $st = Database::instance()->prepare("SELECT * FROM store_orders WHERE uuid = ? AND site_id = @site_id LIMIT 1");
            $st->execute([$uuid]);
            $order = $st->fetch() ?: null;
        } catch (\Throwable $e) {
            return;
        }
        if (!$order) return;
        $orderId = (int)$order['id'];

        $this->completeForOrder($orderId);

        $purchase = $this->purchaseForOrder($orderId);
        if (!$purchase) return;
        if ((string)$purchase['status'] === 'issued') return;

        $db = Database::instance();
        try {
            $existing = $this->cardsForOrder($orderId);
            if (!$existing) {
                $occasion = !empty($purchase['occasion_id']) ? $this->occasionById((int)$purchase['occasion_id']) : null;
                $card = $this->issueCard([
                    'occasion_id' => (int)($purchase['occasion_id'] ?? 0),
                    'occasion_name' => (string)($occasion['name'] ?? 'Regalo'),
                    'amount_cents' => (int)$purchase['amount_cents'],
                    'currency' => (string)$order['currency'],
                    'purchaser_name' => (string)$purchase['sender_name'],
                    'purchaser_email' => (string)$order['customer_email'],
                    'recipient_name' => (string)$purchase['recipient_name'],
                    'recipient_email' => (string)$purchase['recipient_email'],
                    'message' => (string)$purchase['message'],
                    'delivery_at' => $purchase['delivery_at'] ?: null,
                    'order_id' => $orderId,
                    'issued_by' => 'order',
                ]);
                if (empty($card['ok'])) throw new \RuntimeException((string)($card['message'] ?? 'No se pudo emitir la tarjeta.'));
            }
            $db->prepare("UPDATE gift_card_purchases SET status = 'issued', issued_at = NOW() WHERE id = ? AND site_id = @site_id")
                ->execute([(int)$purchase['id']]);
        } catch (\Throwable $e) {
            try {
                $db->prepare("INSERT INTO store_order_events (site_id, order_id, event, to_value, reason)
                    VALUES (@site_id, ?, 'gift_card_issue_failed', 'error', ?)")
                    ->execute([$orderId, mb_substr($e->getMessage(), 0, 120)]);
            } catch (\Throwable $x) {
            }
            return;
        }

        try { $this->deliverScheduled(20); } catch (\Throwable $e) {}
    }

    public function onOrderFailed(int $orderId, string $reason = ''): void
    {
        $this->releaseForOrder($orderId, $reason !== '' ? $reason : 'Pago no completado');
        $purchase = $this->purchaseForOrder($orderId);
        if ($purchase && (string)$purchase['status'] === 'issued') {
            $isRefund = stripos($reason, 'reembols') !== false || stripos($reason, 'refund') !== false;
            foreach ($this->cardsForOrder($orderId) as $card) {
                $balance = (int)$card['balance_cents'];
                if ((string)$card['status'] === self::STATUS_ACTIVE && $balance > 0 && ($isRefund || $balance === (int)$card['amount_cents'])) {
                    try {
                        $db = Database::instance();
                        $db->prepare("UPDATE gift_cards SET status = 'void', balance_cents = 0 WHERE id = ? AND site_id = @site_id")
                            ->execute([(int)$card['id']]);
                        $db->prepare("INSERT INTO gift_card_transactions (site_id, gift_card_id, order_id, type, amount_cents, balance_after_cents, note)
                            VALUES (@site_id, ?, ?, 'void', ?, 0, ?)")
                            ->execute([(int)$card['id'], $orderId, -$balance, mb_substr('Anulación por reverso del pedido: ' . $reason, 0, 255)]);
                    } catch (\Throwable $e) {
                    }
                }
            }
        }
    }

    private function issueCard(array $d): array
    {
        $this->ensureTables();
        $db = Database::instance();
        $amount = max(0, (int)($d['amount_cents'] ?? 0));
        if ($amount <= 0) return ['ok' => false, 'message' => 'Monto inválido.'];
        $currency = (string)($d['currency'] ?? 'COP') ?: 'COP';
        $settings = $this->settings();
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $candidate = $this->generateCode();
            try {
                $st = $db->prepare("SELECT COUNT(*) FROM gift_cards WHERE site_id = @site_id AND code = ?");
                $st->execute([$candidate]);
                if ((int)$st->fetchColumn() === 0) { $code = $candidate; break; }
            } catch (\Throwable $e) {
                break;
            }
        }
        if ($code === '') $code = $this->generateCode();
        $pin = $this->generatePin();
        $expiresAt = !empty($d['expires_at']) ? (string)$d['expires_at'] : date('Y-m-d H:i:s', time() + $settings['expiry_days'] * 86400);
        $deliveryAt = !empty($d['delivery_at']) ? (string)$d['delivery_at'] : date('Y-m-d H:i:s');
        try {
            $db->prepare("INSERT INTO gift_cards (site_id, code, pin_hash, pin_enc, occasion_id, amount_cents, balance_cents, currency, status,
                purchaser_name, purchaser_email, recipient_name, recipient_email, message, delivery_at, expires_at, order_id, issued_by, issued_at)
                VALUES (@site_id, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())")
                ->execute([
                    $code, password_hash($pin, PASSWORD_DEFAULT), $this->encryptPin($pin),
                    !empty($d['occasion_id']) ? (int)$d['occasion_id'] : null,
                    $amount, $amount, $currency,
                    (string)($d['purchaser_name'] ?? ''), (string)($d['purchaser_email'] ?? ''),
                    (string)($d['recipient_name'] ?? ''), (string)($d['recipient_email'] ?? ''),
                    mb_substr((string)($d['message'] ?? ''), 0, 500),
                    $deliveryAt, $expiresAt,
                    !empty($d['order_id']) ? (int)$d['order_id'] : null,
                    (string)($d['issued_by'] ?? 'admin'),
                ]);
            $id = (int)$db->lastInsertId();
            $db->prepare("INSERT INTO gift_card_transactions (site_id, gift_card_id, order_id, type, amount_cents, balance_after_cents, note)
                VALUES (@site_id, ?, ?, 'issue', ?, ?, 'Emisión de tarjeta de regalo')")
                ->execute([$id, !empty($d['order_id']) ? (int)$d['order_id'] : null, $amount, $amount]);
            $st = $db->prepare("SELECT * FROM gift_cards WHERE site_id = @site_id AND id = ? LIMIT 1");
            $st->execute([$id]);
            return ['ok' => true, 'card' => $st->fetch() ?: null, 'pin' => $pin];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo emitir la tarjeta: ' . $e->getMessage()];
        }
    }

    /**
     * Entrega por correo las tarjetas emitidas cuya fecha de entrega ya llegó.
     */
    public function deliverScheduled(int $limit = 50): array
    {
        $this->ensureTables();
        $limit = max(1, min(200, $limit));
        try {
            $st = Database::instance()->query("SELECT * FROM gift_cards
                WHERE site_id = @site_id AND status = 'active' AND delivered_at IS NULL
                  AND (delivery_at IS NULL OR delivery_at <= NOW())
                ORDER BY id ASC LIMIT $limit");
            $cards = $st->fetchAll();
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudieron consultar las tarjetas.', 'sent' => 0];
        }
        $sent = 0;
        $failed = 0;
        foreach ($cards as $card) {
            if ($this->deliverCard($card)) $sent++; else $failed++;
        }
        return ['ok' => true, 'candidates' => count($cards), 'sent' => $sent, 'failed' => $failed];
    }

    private function deliverCard(array $card): bool
    {
        $email = trim((string)($card['recipient_email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;
        $pin = $this->pinFor($card);
        if ($pin === '') return false;
        $settings = $this->settings();
        $currency = (string)($card['currency'] ?? $settings['currency']);
        $amount = $this->money((int)$card['amount_cents'], $currency);
        $balance = $this->money((int)$card['balance_cents'], $currency);
        $occasion = !empty($card['occasion_id']) ? $this->occasionById((int)$card['occasion_id']) : null;
        $occasionName = (string)($occasion['name'] ?? 'Regalo');
        $icon = (string)($occasion['icon'] ?? '🎁');
        $code = (string)$card['code'];
        $expires = (string)($card['expires_at'] ?? '');
        $expiresLabel = $expires !== '' ? date('d/m/Y', strtotime($expires)) : 'Sin vencimiento';
        $base = rtrim((string)Config::get('APP_URL', 'https://sonika.com.co'), '/');
        $link = $base . '/tarjetas-de-regalo/consultar?code=' . rawurlencode($code);
        $html = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:620px;margin:0 auto;background:#f7f7f5;padding:26px">'
            . '<div style="background:linear-gradient(135deg,' . htmlspecialchars((string)($occasion['color_from'] ?? '#F20D16'), ENT_QUOTES, 'UTF-8') . ',' . htmlspecialchars((string)($occasion['color_to'] ?? '#101d3a'), ENT_QUOTES, 'UTF-8') . ');border-radius:22px;padding:30px;color:#fff">'
            . '<div style="font-size:12px;letter-spacing:.16em;text-transform:uppercase;opacity:.85">SONIKA · Tarjeta de regalo</div>'
            . '<div style="font-size:44px;line-height:1;margin:10px 0 4px">' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . '</div>'
            . '<h1 style="font-size:26px;line-height:1.15;margin:0 0 8px">' . htmlspecialchars($occasionName, ENT_QUOTES, 'UTF-8') . '</h1>'
            . '<div style="font-size:34px;font-weight:800;margin:12px 0">' . htmlspecialchars($amount, ENT_QUOTES, 'UTF-8') . '</div>'
            . '<p style="margin:0;opacity:.9">Para ' . htmlspecialchars((string)$card['recipient_name'], ENT_QUOTES, 'UTF-8') . '</p>'
            . '</div>'
            . '<div style="background:#fff;border:1px solid #e7e5df;border-radius:18px;padding:24px;margin-top:14px">'
            . '<p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#222">' . nl2br(htmlspecialchars((string)$card['message'], ENT_QUOTES, 'UTF-8')) . '</p>'
            . '<p style="margin:0 0 6px;font-size:11px;color:#777;text-transform:uppercase;letter-spacing:.08em">Código</p>'
            . '<div style="font-family:ui-monospace,Menlo,Consolas,monospace;font-size:21px;font-weight:800;letter-spacing:.08em;background:#101d3a;color:#fff;border-radius:12px;padding:14px 16px;text-align:center">' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</div>'
            . '<p style="margin:14px 0 6px;font-size:11px;color:#777;text-transform:uppercase;letter-spacing:.08em">PIN</p>'
            . '<div style="font-family:ui-monospace,Menlo,Consolas,monospace;font-size:26px;font-weight:800;letter-spacing:.3em;background:#f2f4f7;border:1px dashed #c9ced8;border-radius:12px;padding:12px 16px;text-align:center">' . htmlspecialchars($pin, ENT_QUOTES, 'UTF-8') . '</div>'
            . '<p style="font-size:12px;color:#555;line-height:1.6;margin:16px 0 0">Saldo disponible: <b>' . htmlspecialchars($balance, ENT_QUOTES, 'UTF-8') . '</b><br>Vence: <b>' . htmlspecialchars($expiresLabel, ENT_QUOTES, 'UTF-8') . '</b></p>'
            . '<p style="margin:18px 0 0"><a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;background:#F20D16;color:#fff;text-decoration:none;font-weight:800;padding:13px 20px;border-radius:12px">Consultar saldo y usar mi tarjeta</a></p>'
            . '<p style="font-size:11px;color:#777;line-height:1.6;margin:16px 0 0">Puedes usar la tarjeta total o parcialmente en la tienda. Si el saldo no cubre todo, paga la diferencia con otro método. No compartas tu PIN con nadie.</p>'
            . '</div>'
            . '<p style="text-align:center;font-size:11px;color:#888;margin:18px 0 0">SONIKA · Instrumentos, audio e iluminación · ' . htmlspecialchars($base, ENT_QUOTES, 'UTF-8') . '</p>'
            . '</div>';
        try {
            $svc = new EmailService();
            $sent = $svc->sendHtml($email, 'Tu tarjeta de regalo SONIKA — ' . $occasionName, $html);
            $purchaser = trim((string)($card['purchaser_email'] ?? ''));
            if ($purchaser !== '' && strcasecmp($purchaser, $email) !== 0 && filter_var($purchaser, FILTER_VALIDATE_EMAIL)) {
                $copy = '<p style="font-family:Arial,sans-serif;font-size:13px;color:#444">Enviaste una tarjeta de regalo SONIKA a <b>' . htmlspecialchars((string)$card['recipient_name'], ENT_QUOTES, 'UTF-8') . '</b> (' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . ').</p>'
                    . '<p style="font-family:Arial,sans-serif;font-size:12px;color:#666">Código: <b>' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</b><br>PIN: <b>' . htmlspecialchars($pin, ENT_QUOTES, 'UTF-8') . '</b><br>Monto: <b>' . htmlspecialchars($amount, ENT_QUOTES, 'UTF-8') . '</b></p>'
                    . '<p style="font-family:Arial,sans-serif;font-size:12px;color:#666">Guarda esta copia. El destinatario también recibió su tarjeta por correo.</p>';
                try { $svc->sendHtml($purchaser, 'Copia: tarjeta de regalo SONIKA enviada', $copy); } catch (\Throwable $e) {}
            }
            if ($sent) {
                try {
                    Database::instance()->prepare("UPDATE gift_cards SET delivered_at = NOW() WHERE id = ? AND site_id = @site_id")
                        ->execute([(int)$card['id']]);
                } catch (\Throwable $e) {
                }
            }
            return $sent;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function notifyPurchase(?array $order): void
    {
        if (!$order) return;
        $email = trim((string)($order['customer_email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return;
        $purchase = $this->purchaseForOrder((int)$order['id']);
        if (!$purchase) return;
        $occasion = !empty($purchase['occasion_id']) ? $this->occasionById((int)$purchase['occasion_id']) : null;
        $name = (string)($occasion['name'] ?? 'Tarjeta de regalo');
        $base = rtrim((string)Config::get('APP_URL', 'https://sonika.com.co'), '/');
        $orderUrl = $base . '/pedido/' . rawurlencode((string)$order['uuid']);
        $html = '<div style="font-family:Arial,sans-serif;max-width:580px;margin:0 auto;color:#222;line-height:1.65">'
            . '<h1 style="font-size:22px">Recibimos tu pedido de tarjeta de regalo</h1>'
            . '<p>Hola ' . htmlspecialchars((string)$purchase['sender_name'], ENT_QUOTES, 'UTF-8') . ',</p>'
            . '<p>Tu tarjeta <b>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</b> por <b>' . htmlspecialchars($this->money((int)$purchase['amount_cents'], (string)$order['currency']), ENT_QUOTES, 'UTF-8') . '</b> quedó registrada.</p>'
            . '<p>Cuando confirmemos el pago, enviaremos el código y el PIN a <b>' . htmlspecialchars((string)$purchase['recipient_email'], ENT_QUOTES, 'UTF-8') . '</b>' . (!empty($purchase['delivery_at']) ? ' el ' . htmlspecialchars(date('d/m/Y', strtotime((string)$purchase['delivery_at'])), ENT_QUOTES, 'UTF-8') : '') . '.</p>'
            . '<p><a href="' . htmlspecialchars($orderUrl, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;background:#F20D16;color:#fff;text-decoration:none;font-weight:800;padding:12px 18px;border-radius:10px">Ver mi pedido</a></p>'
            . '<p style="font-size:12px;color:#666">Pedido ' . htmlspecialchars((string)$order['order_number'], ENT_QUOTES, 'UTF-8') . '</p>'
            . '</div>';
        try { (new EmailService())->sendHtml($email, 'Pedido de tarjeta de regalo recibido — ' . $order['order_number'], $html); } catch (\Throwable $e) {}
    }

    /**
     * Libera reservas antiguas de pedidos que no se pagaron.
     * También marca el pedido como fallido/cancelado para evitar cobros posteriores.
     */
    public function expireReservations(int $hours = 24, int $limit = 50): array
    {
        $this->ensureTables();
        $settings = $this->settings();
        $hours = max(1, min(240, $hours > 0 ? $hours : $settings['hold_hours']));
        $limit = max(1, min(200, $limit));
        try {
            $st = Database::instance()->query("SELECT DISTINCT r.order_id
                FROM gift_card_redemptions r
                JOIN store_orders o ON o.id = r.order_id AND o.site_id = r.site_id
                WHERE r.site_id = @site_id AND r.status = 'reserved'
                  AND o.payment_status = 'pending'
                  AND r.created_at < (NOW() - INTERVAL $hours HOUR)
                ORDER BY r.order_id ASC LIMIT $limit");
            $ids = array_map('intval', array_column($st->fetchAll(), 'order_id'));
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudieron consultar las reservas.', 'released' => 0];
        }
        $released = 0;
        foreach ($ids as $id) {
            try {
                $this->releaseForOrder($id, 'Reserva vencida sin pago');
                Database::instance()->prepare("UPDATE store_orders SET payment_status = 'failed', fulfillment_status = 'cancelled',
                    admin_notes = CONCAT(COALESCE(admin_notes, ''), '\n[auto] Reserva de tarjeta vencida sin pago')
                    WHERE id = ? AND site_id = @site_id AND payment_status = 'pending'")->execute([$id]);
                $released++;
            } catch (\Throwable $e) {
            }
        }
        return ['ok' => true, 'hours' => $hours, 'released' => $released, 'orders' => $ids];
    }

    // ── Administración ──

    public function adminStats(): array
    {
        $this->ensureTables();
        try {
            $db = Database::instance();
            $st = $db->query("SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN status = 'depleted' THEN 1 ELSE 0 END) AS depleted,
                SUM(CASE WHEN status = 'void' THEN 1 ELSE 0 END) AS voided,
                COALESCE(SUM(balance_cents), 0) AS balance_cents,
                COALESCE(SUM(amount_cents), 0) AS issued_cents
                FROM gift_cards WHERE site_id = @site_id");
            $row = $st->fetch() ?: [];
            $p = $db->query("SELECT COUNT(*) AS orders, COALESCE(SUM(amount_cents),0) AS amount_cents
                FROM gift_card_purchases WHERE site_id = @site_id AND status = 'issued'");
            $purchase = $p->fetch() ?: [];
            $r = $db->query("SELECT COALESCE(SUM(amount_cents),0) AS redeemed_cents
                FROM gift_card_redemptions WHERE site_id = @site_id AND status = 'completed'");
            $redeemed = $r->fetch() ?: [];
            return [
                'total' => (int)($row['total'] ?? 0),
                'active' => (int)($row['active'] ?? 0),
                'depleted' => (int)($row['depleted'] ?? 0),
                'voided' => (int)($row['voided'] ?? 0),
                'balance_cents' => (int)($row['balance_cents'] ?? 0),
                'issued_cents' => (int)($row['issued_cents'] ?? 0),
                'redeemed_cents' => (int)($redeemed['redeemed_cents'] ?? 0),
                'orders' => (int)($purchase['orders'] ?? 0),
                'sales_cents' => (int)($purchase['amount_cents'] ?? 0),
            ];
        } catch (\Throwable $e) {
            return ['total' => 0, 'active' => 0, 'depleted' => 0, 'voided' => 0, 'balance_cents' => 0, 'issued_cents' => 0, 'redeemed_cents' => 0, 'orders' => 0, 'sales_cents' => 0];
        }
    }

    public function adminList(array $f = []): array
    {
        $this->ensureTables();
        $where = '';
        $params = [];
        $status = trim((string)($f['status'] ?? ''));
        if ($status !== '' && in_array($status, ['active', 'depleted', 'void', 'expired'], true)) {
            $where .= ' AND c.status = :status';
            $params['status'] = $status;
        }
        $search = trim((string)($f['search'] ?? ''));
        if ($search !== '') {
            $where .= ' AND (c.code LIKE :q1 OR c.recipient_email LIKE :q2 OR c.purchaser_email LIKE :q3 OR c.recipient_name LIKE :q4)';
            foreach (['q1', 'q2', 'q3', 'q4'] as $k) $params[$k] = '%' . $search . '%';
        }
        try {
            $sql = "SELECT c.*, o.order_number, o.payment_status AS order_payment_status,
                    oc.name AS occasion_name, oc.icon AS occasion_icon
                FROM gift_cards c
                LEFT JOIN store_orders o ON o.id = c.order_id AND o.site_id = c.site_id
                LEFT JOIN gift_card_occasions oc ON oc.id = c.occasion_id AND oc.site_id = c.site_id
                WHERE c.site_id = @site_id" . $where . "
                ORDER BY c.id DESC LIMIT " . max(1, min(500, (int)($f['limit'] ?? 100)));
            $st = Database::instance()->prepare($sql);
            $st->execute($params);
            $rows = $st->fetchAll();
            foreach ($rows as &$row) {
                $row['code_masked'] = $this->maskCode((string)$row['code']);
                unset($row['pin_hash'], $row['pin_enc']);
            }
            unset($row);
            return $rows;
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function adminIssue(array $d, string $by = ''): array
    {
        $this->ensureTables();
        $amount = (int)($d['amount_cents'] ?? 0);
        if ($amount <= 0 && isset($d['amount'])) $amount = (int)round(((float)$d['amount']) * 100);
        $amount = (int)round($amount / 100) * 100;
        $settings = $this->settings();
        if ($amount <= 0) return ['ok' => false, 'message' => 'Escribe un monto válido.'];
        if ($amount < $settings['min_cents']) return ['ok' => false, 'message' => 'El monto mínimo es ' . $this->money($settings['min_cents'], $settings['currency']) . '.'];
        if ($amount > $settings['max_cents']) return ['ok' => false, 'message' => 'El monto máximo es ' . $this->money($settings['max_cents'], $settings['currency']) . '.'];
        $recipientEmail = trim((string)($d['recipient_email'] ?? ''));
        if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'message' => 'Escribe un correo válido para el destinatario.'];
        $recipientName = trim((string)($d['recipient_name'] ?? ''));
        if ($recipientName === '') return ['ok' => false, 'message' => 'Escribe el nombre del destinatario.'];
        $occasion = !empty($d['occasion_id']) ? $this->occasionById((int)$d['occasion_id']) : null;
        $expiresDays = max(1, min(3650, (int)($d['expiry_days'] ?? $settings['expiry_days'])));
        $deliveryAt = trim((string)($d['delivery_at'] ?? ''));
        $deliveryAt = $deliveryAt !== '' ? $this->parseDeliveryAt($deliveryAt) : date('Y-m-d H:i:s');
        $result = $this->issueCard([
            'occasion_id' => $occasion ? (int)$occasion['id'] : 0,
            'amount_cents' => $amount,
            'currency' => $settings['currency'],
            'purchaser_name' => trim((string)($d['purchaser_name'] ?? 'Emisión administrativa')),
            'purchaser_email' => trim((string)($d['purchaser_email'] ?? '')),
            'recipient_name' => $recipientName,
            'recipient_email' => $recipientEmail,
            'message' => mb_substr(trim((string)($d['message'] ?? '')), 0, 300),
            'delivery_at' => $deliveryAt,
            'expires_at' => date('Y-m-d H:i:s', time() + $expiresDays * 86400),
            'issued_by' => $by !== '' ? $by : 'admin',
        ]);
        if (empty($result['ok'])) return $result;
        $card = (array)$result['card'];
        $card['pin'] = (string)$result['pin'];
        try { $this->deliverScheduled(20); } catch (\Throwable $e) {}
        try {
            $st = Database::instance()->prepare("SELECT * FROM gift_cards WHERE site_id = @site_id AND id = ? LIMIT 1");
            $st->execute([(int)$card['id']]);
            $fresh = $st->fetch();
            if ($fresh) $card = $fresh;
        } catch (\Throwable $e) {
        }
        $card['pin'] = (string)$result['pin'];
        $card['code_masked'] = $this->maskCode((string)$card['code']);
        $delivered = !empty($card['delivered_at']);
        unset($card['pin_hash'], $card['pin_enc']);
        return ['ok' => true, 'card' => $card, 'delivered' => $delivered, 'message' => $delivered ? 'Tarjeta emitida y enviada.' : 'Tarjeta emitida. El envío quedó programado.'];
    }

    public function adminVoid(int $id, string $reason = '', string $by = ''): array
    {
        $this->ensureTables();
        $db = Database::instance();
        try {
            $st = $db->prepare("SELECT * FROM gift_cards WHERE site_id = @site_id AND id = ? LIMIT 1 FOR UPDATE");
            $st->execute([$id]);
            $card = $st->fetch() ?: null;
            if (!$card) return ['ok' => false, 'message' => 'Tarjeta no encontrada.'];
            if ((string)$card['status'] === self::STATUS_VOID) return ['ok' => true, 'message' => 'La tarjeta ya estaba anulada.'];
            $stReserved = $db->prepare("SELECT COUNT(*) FROM gift_card_redemptions WHERE site_id = @site_id AND gift_card_id = ? AND status = 'reserved'");
            $stReserved->execute([$id]);
            if ((int)$stReserved->fetchColumn() > 0) {
                return ['ok' => false, 'message' => 'La tarjeta tiene saldo reservado en pedidos pendientes. Cancela esos pedidos antes de anularla.'];
            }
            $balance = (int)$card['balance_cents'];
            $db->prepare("UPDATE gift_cards SET status = 'void', balance_cents = 0 WHERE id = ? AND site_id = @site_id")->execute([$id]);
            $db->prepare("INSERT INTO gift_card_transactions (site_id, gift_card_id, type, amount_cents, balance_after_cents, note)
                VALUES (@site_id, ?, 'void', ?, 0, ?)")->execute([$id, -$balance, mb_substr('Anulación: ' . ($reason !== '' ? $reason : 'sin motivo'), 0, 255)]);
            return ['ok' => true, 'message' => 'Tarjeta anulada.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo anular la tarjeta.'];
        }
    }

    public function adminResend(int $id): array
    {
        $this->ensureTables();
        try {
            $st = Database::instance()->prepare("SELECT * FROM gift_cards WHERE site_id = @site_id AND id = ? LIMIT 1");
            $st->execute([$id]);
            $card = $st->fetch() ?: null;
            if (!$card) return ['ok' => false, 'message' => 'Tarjeta no encontrada.'];
            if ((string)$card['status'] === self::STATUS_VOID) return ['ok' => false, 'message' => 'No se puede reenviar una tarjeta anulada.'];
            $db = Database::instance();
            $db->prepare("UPDATE gift_cards SET delivery_at = NOW(), delivered_at = NULL WHERE id = ? AND site_id = @site_id")->execute([$id]);
            $st->execute([$id]);
            $card = $st->fetch() ?: $card;
            $ok = $this->deliverCard($card);
            return $ok ? ['ok' => true, 'message' => 'Tarjeta reenviada por correo.'] : ['ok' => false, 'message' => 'No se pudo enviar el correo. Revisa la configuración SMTP.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo reenviar la tarjeta.'];
        }
    }

    public function adminOccasions(): array
    {
        return $this->occasions(false);
    }

    public function saveOccasion(array $d, ?int $id = null): array
    {
        $this->ensureTables();
        $db = Database::instance();
        $existing = ($id !== null && $id > 0) ? $this->occasionById($id) : null;
        $name = trim((string)($d['name'] ?? ($existing['name'] ?? '')));
        if ($name === '') return ['ok' => false, 'message' => 'Escribe el nombre de la ocasión.'];
        $slug = strtolower(trim((string)($d['slug'] ?? ($existing['slug'] ?? ''))));
        if ($slug === '') {
            $slug = preg_replace('/[^a-z0-9]+/', '-', $this->ascii($name)) ?: 'ocasion-' . substr(bin2hex(random_bytes(3)), 0, 5);
            $slug = trim($slug, '-');
        }
        $slug = preg_replace('/[^a-z0-9-]/', '', $slug) ?: 'ocasion';
        $icon = mb_substr(trim((string)($d['icon'] ?? ($existing['icon'] ?? '🎁'))), 0, 8);
        $tagline = mb_substr(trim((string)($d['tagline'] ?? ($existing['tagline'] ?? ''))), 0, 190);
        $description = mb_substr(trim((string)($d['description'] ?? ($existing['description'] ?? ''))), 0, 2000);
        $cf = preg_match('/^#[0-9a-f]{6}$/i', (string)($d['color_from'] ?? ($existing['color_from'] ?? ''))) ? (string)($d['color_from'] ?? $existing['color_from']) : '#F20D16';
        $ct = preg_match('/^#[0-9a-f]{6}$/i', (string)($d['color_to'] ?? ($existing['color_to'] ?? ''))) ? (string)($d['color_to'] ?? $existing['color_to']) : '#101d3a';
        $amounts = $d['suggested_amounts'] ?? ($existing['suggested_amounts'] ?? '');
        if (is_array($amounts)) $amounts = implode(',', array_map('intval', $amounts));
        $amounts = implode(',', array_slice(array_values(array_filter(array_map('intval', explode(',', (string)$amounts)))), 0, 12));
        if ($amounts === '') $amounts = '5000000,10000000,20000000,30000000,50000000';
        $defaultMessage = mb_substr(trim((string)($d['default_message'] ?? ($existing['default_message'] ?? ''))), 0, 255);
        $active = array_key_exists('active', $d) ? (!empty($d['active']) ? 1 : 0) : (int)($existing['active'] ?? 1);
        $sort = array_key_exists('sort_order', $d) ? (int)$d['sort_order'] : (int)($existing['sort_order'] ?? 0);
        try {
            if ($id !== null && $id > 0) {
                $st = $db->prepare("UPDATE gift_card_occasions SET slug = ?, name = ?, icon = ?, tagline = ?, description = ?,
                    color_from = ?, color_to = ?, suggested_amounts = ?, default_message = ?, active = ?, sort_order = ?
                    WHERE id = ? AND site_id = @site_id");
                $st->execute([$slug, $name, $icon, $tagline, $description, $cf, $ct, $amounts, $defaultMessage, $active, $sort, $id]);
            } else {
                $st = $db->prepare("INSERT INTO gift_card_occasions (site_id, slug, name, icon, tagline, description, color_from, color_to, suggested_amounts, default_message, active, sort_order)
                    VALUES (@site_id, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $st->execute([$slug, $name, $icon, $tagline, $description, $cf, $ct, $amounts, $defaultMessage, $active, $sort]);
                $id = (int)$db->lastInsertId();
            }
            return ['ok' => true, 'id' => $id, 'message' => 'Ocasión guardada.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo guardar la ocasión. Verifica que el slug no esté repetido.'];
        }
    }

    private function parseDeliveryAt(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') return date('Y-m-d H:i:s');
        $hasTime = (bool)preg_match('/\d{1,2}:\d{2}/', $raw);
        try {
            $tz = new \DateTimeZone('America/Bogota');
            $dt = new \DateTime($raw . ($hasTime ? '' : ' 09:00:00'), $tz);
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException('La fecha de entrega no es válida.');
        }
        $ts = $dt->getTimestamp();
        if ($ts < time() - 300) $ts = time();
        if ($ts > time() + 400 * 86400) throw new \InvalidArgumentException('La fecha de entrega no puede superar un año.');
        $dt->setTimestamp($ts);
        $dt->setTimezone(new \DateTimeZone('UTC'));
        return $dt->format('Y-m-d H:i:s');
    }

    private function ascii(string $s): string
    {
        $s = strtolower(trim($s));
        $s = strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u']);
        return $s;
    }
}
