<?php
namespace App\Services;

use App\Core\Config;
use App\Core\Database;

/**
 * SeoInternalTrafficService — separa el tráfico del propietario/equipo del
 * tráfico real de clientes.
 *
 * La MAC de la tarjeta de red no viaja en HTTP y no puede detectarse desde el
 * servidor. En su lugar se usan tres señales verificables, en este orden:
 *   1. Sesión de administrador activa.
 *   2. Cookie firmada de dispositivo (marcar este equipo).
 *   3. Allowlist de IP/CIDR (incluye loopback para pruebas del servidor).
 *
 * El tráfico interno NO se borra: se marca con is_internal=1 y un motivo, y
 * todas las métricas de clientes lo excluyen.
 */
class SeoInternalTrafficService
{
    public const COOKIE = 'wwi_owner_device';
    public const REASON_ADMIN = 'admin_session';
    public const REASON_DEVICE = 'device_cookie';
    public const REASON_NETWORK = 'ip_allowlist';
    public const REASON_LOCAL = 'local_request';

    private static ?array $detected = null;
    private static ?array $networksCache = null;
    private static ?string $cookieSecretCache = null;
    private static ?string $visitorSaltCache = null;

    /** Captura temprana (antes de que se envíen cabeceras). */
    public static function capture(): array
    {
        self::ensureSessionCookie();
        return self::detect();
    }

    /** Crea la sesión técnica de primer acceso para que el monitor y la
     *  analítica usen el mismo session_id. No contiene datos personales. */
    public static function ensureSessionCookie(): ?string
    {
        if (PHP_SAPI === 'cli') return null;
        $existing = self::sessionId();
        if ($existing !== null) return $existing;
        $sid = 's' . bin2hex(random_bytes(8));
        $_COOKIE['wwi_seo_sid'] = $sid;
        if (!headers_sent()) {
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
            setcookie('wwi_seo_sid', $sid, [
                'expires' => time() + 1800,
                'path' => '/',
                'secure' => $secure,
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
        }
        return $sid;
    }

    public static function detect(bool $refresh = false): array
    {
        if (self::$detected !== null && !$refresh) return self::$detected;

        $ip = self::clientIp();
        $result = [
            'internal' => false,
            'reason' => '',
            'label' => '',
            'ip' => $ip,
            'device_id' => null,
            'network' => null,
            'admin_session' => false,
        ];

        if (self::adminSessionActive()) {
            $result['internal'] = true;
            $result['reason'] = self::REASON_ADMIN;
            $result['label'] = 'Sesión de administrador';
            $result['admin_session'] = true;
            self::$detected = $result;
            return $result;
        }

        $device = self::currentDevice();
        if ($device !== null) {
            $result['internal'] = true;
            $result['reason'] = self::REASON_DEVICE;
            $result['label'] = 'Dispositivo marcado' . (!empty($device['label']) ? ': ' . $device['label'] : '');
            $result['device_id'] = $device['device_id'];
            self::$detected = $result;
            return $result;
        }

        if ($ip !== null && self::isLoopback($ip)) {
            $result['internal'] = true;
            $result['reason'] = self::REASON_LOCAL;
            $result['label'] = 'Petición local del servidor';
            self::$detected = $result;
            return $result;
        }

        $network = $ip !== null ? self::matchingNetwork($ip) : null;
        if ($network !== null) {
            $result['internal'] = true;
            $result['reason'] = self::REASON_NETWORK;
            $result['label'] = 'Red permitida' . (!empty($network['label']) ? ': ' . $network['label'] : ': ' . $network['cidr']);
            $result['network'] = $network;
        }

        self::$detected = $result;
        return $result;
    }

    public static function isInternal(): bool
    {
        return !empty(self::detect()['internal']);
    }

    public static function reasonLabel(string $reason): string
    {
        return [
            self::REASON_ADMIN => 'Sesión admin',
            self::REASON_DEVICE => 'Dispositivo marcado',
            self::REASON_NETWORK => 'IP/CIDR permitida',
            self::REASON_LOCAL => 'Servidor local',
            'ip_allowlist_backfill' => 'IP permitida (histórico)',
            'device_cookie_backfill' => 'Dispositivo (histórico)',
        ][$reason] ?? ($reason !== '' ? $reason : 'Interno');
    }

    /** IP real del cliente, sin confiar ciegamente en cabeceras de proxy. */
    public static function clientIp(): ?string
    {
        $remote = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        $remoteValid = $remote !== '' && filter_var($remote, FILTER_VALIDATE_IP) !== false;
        $trustProxy = $remoteValid && self::isPrivateOrLoopback($remote);

        $cf = trim((string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
        if ($trustProxy && $cf !== '' && filter_var($cf, FILTER_VALIDATE_IP)) return $cf;

        if ($trustProxy) {
            $xff = trim((string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
            if ($xff !== '') {
                foreach (explode(',', $xff) as $candidate) {
                    $candidate = trim($candidate);
                    if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_IP)) return $candidate;
                }
            }
        }

        if ($remoteValid) return $remote;
        return null;
    }

    public static function isPrivateOrLoopback(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) return false;
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    public static function isLoopback(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) return false;
        return $ip === '127.0.0.1' || $ip === '::1' || str_starts_with($ip, '127.');
    }

    public static function adminSessionActive(): bool
    {
        try {
            if (session_status() === PHP_SESSION_ACTIVE) {
                return !empty($_SESSION['user']);
            }
            $sessionName = session_name();
            if ($sessionName !== '' && !empty($_COOKIE[$sessionName]) && !headers_sent()) {
                return \App\Core\Session::user() !== null;
            }
        } catch (\Throwable $e) {
        }
        return false;
    }

    // ─────────────────────────────────────────────
    // Dispositivo firmado (sustituto real de la MAC)
    // ─────────────────────────────────────────────

    public static function markDevice(string $label, string $by = 'admin'): array
    {
        try {
            $db = Database::instance();
            $deviceId = bin2hex(random_bytes(16));
            $db->prepare("INSERT INTO seo_internal_devices (site_id, device_id, label, user_agent, created_by, last_seen_at, created_at)
                VALUES (@site_id, ?, ?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE label = VALUES(label), user_agent = VALUES(user_agent), created_by = VALUES(created_by), revoked_at = NULL, last_seen_at = NOW()")
                ->execute([
                    $deviceId,
                    mb_substr(trim($label) ?: 'Este equipo', 0, 190),
                    mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
                    mb_substr($by, 0, 190),
                ]);
            self::setDeviceCookie($deviceId);
            self::$detected = null;
            return ['ok' => true, 'message' => 'Este equipo quedó marcado como tráfico propio.', 'device_id' => $deviceId];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo marcar este equipo.'];
        }
    }

    public static function unmarkDevice(): array
    {
        $deviceId = self::currentDeviceId();
        try {
            if ($deviceId !== null) {
                Database::instance()->prepare('UPDATE seo_internal_devices SET revoked_at = NOW() WHERE site_id = @site_id AND device_id = ?')->execute([$deviceId]);
            }
            self::clearDeviceCookie();
            self::$detected = null;
            return ['ok' => true, 'message' => 'Este equipo dejó de estar marcado como tráfico propio.'];
        } catch (\Throwable $e) {
            self::clearDeviceCookie();
            return ['ok' => false, 'message' => 'No se pudo desmarcar el equipo.'];
        }
    }

    public static function currentDeviceId(): ?string
    {
        $value = (string)($_COOKIE[self::COOKIE] ?? '');
        if ($value === '') return null;
        $parts = explode('.', $value);
        if (count($parts) !== 3 || $parts[0] !== 'v1') return null;
        $deviceId = $parts[1];
        $signature = $parts[2];
        if (!preg_match('/^[a-f0-9]{32}$/', $deviceId)) return null;
        $expected = hash_hmac('sha256', 'v1.' . $deviceId, self::cookieSecret());
        if (!hash_equals($expected, $signature)) return null;
        return $deviceId;
    }

    private static function currentDevice(): ?array
    {
        $deviceId = self::currentDeviceId();
        if ($deviceId === null) return null;
        try {
            $stmt = Database::instance()->prepare('SELECT device_id, label, user_agent, created_at, last_seen_at FROM seo_internal_devices WHERE site_id = @site_id AND device_id = ? AND revoked_at IS NULL LIMIT 1');
            $stmt->execute([$deviceId]);
            $row = $stmt->fetch();
            if (!$row) return null;
            if (random_int(1, 20) === 1) {
                try {
                    Database::instance()->prepare('UPDATE seo_internal_devices SET last_seen_at = NOW() WHERE site_id = @site_id AND device_id = ?')->execute([$deviceId]);
                } catch (\Throwable $e) {
                }
            }
            return [
                'device_id' => (string)$row['device_id'],
                'label' => (string)($row['label'] ?? ''),
                'user_agent' => (string)($row['user_agent'] ?? ''),
                'created_at' => (string)($row['created_at'] ?? ''),
                'last_seen_at' => (string)($row['last_seen_at'] ?? ''),
            ];
        } catch (\Throwable $e) {
            // Si la tabla aún no existe, la firma HMAC sigue siendo válida.
            return ['device_id' => $deviceId, 'label' => '', 'user_agent' => '', 'created_at' => '', 'last_seen_at' => ''];
        }
    }

    public static function devices(): array
    {
        try {
            $rows = Database::instance()->query('SELECT id, device_id, label, user_agent, created_by, last_seen_at, revoked_at, created_at FROM seo_internal_devices WHERE site_id = @site_id ORDER BY created_at DESC LIMIT 50')->fetchAll();
            return array_map(static function ($r) {
                return [
                    'id' => (int)$r['id'],
                    'device_id' => (string)$r['device_id'],
                    'label' => (string)($r['label'] ?? ''),
                    'user_agent' => (string)($r['user_agent'] ?? ''),
                    'created_by' => (string)($r['created_by'] ?? ''),
                    'last_seen_at' => $r['last_seen_at'] ? (string)$r['last_seen_at'] : null,
                    'revoked_at' => $r['revoked_at'] ? (string)$r['revoked_at'] : null,
                    'created_at' => (string)$r['created_at'],
                    'active' => empty($r['revoked_at']),
                ];
            }, $rows);
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function revokeDevice(int $id): array
    {
        try {
            Database::instance()->prepare('UPDATE seo_internal_devices SET revoked_at = NOW() WHERE site_id = @site_id AND id = ?')->execute([$id]);
            return ['ok' => true, 'message' => 'Dispositivo revocado.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo revocar el dispositivo.'];
        }
    }

    private static function setDeviceCookie(string $deviceId): void
    {
        $signature = hash_hmac('sha256', 'v1.' . $deviceId, self::cookieSecret());
        $value = 'v1.' . $deviceId . '.' . $signature;
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        setcookie(self::COOKIE, $value, [
            'expires' => time() + 315360000, // 10 años
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[self::COOKIE] = $value;
    }

    private static function clearDeviceCookie(): void
    {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        setcookie(self::COOKIE, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        unset($_COOKIE[self::COOKIE]);
    }

    private static function cookieSecret(): string
    {
        if (self::$cookieSecretCache !== null) return self::$cookieSecretCache;
        $secret = self::getSetting('seo_internal_secret');
        if ($secret === '') {
            $secret = bin2hex(random_bytes(32));
            self::setSetting('seo_internal_secret', $secret);
        }
        return self::$cookieSecretCache = $secret;
    }

    // ─────────────────────────────────────────────
    // Redes IP / CIDR
    // ─────────────────────────────────────────────

    public static function networks(): array
    {
        try {
            $rows = Database::instance()->query('SELECT id, cidr, label, created_by, created_at FROM seo_internal_networks WHERE site_id = @site_id AND active = 1 ORDER BY created_at DESC LIMIT 100')->fetchAll();
            return array_map(static function ($r) {
                return [
                    'id' => (int)$r['id'],
                    'cidr' => (string)$r['cidr'],
                    'label' => (string)($r['label'] ?? ''),
                    'created_by' => (string)($r['created_by'] ?? ''),
                    'created_at' => (string)$r['created_at'],
                ];
            }, $rows);
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function addNetwork(string $cidr, string $label = '', string $by = 'admin'): array
    {
        $normalized = self::normalizeCidr($cidr);
        if ($normalized === null) return ['ok' => false, 'message' => 'IP o CIDR inválido. Usa por ejemplo 190.84.116.204 o 190.84.0.0/16.'];
        $label = mb_substr(trim($label) ?: $normalized, 0, 190);
        try {
            Database::instance()->prepare("INSERT INTO seo_internal_networks (site_id, cidr, label, created_by, active, created_at)
                VALUES (@site_id, ?, ?, ?, 1, NOW())
                ON DUPLICATE KEY UPDATE label = VALUES(label), created_by = VALUES(created_by), active = 1")
                ->execute([$normalized, $label, mb_substr($by, 0, 190)]);
            self::$networksCache = null;
            self::$detected = null;
            return ['ok' => true, 'message' => 'Red interna guardada.', 'cidr' => $normalized];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo guardar la red interna.'];
        }
    }

    public static function removeNetwork(int $id): array
    {
        try {
            Database::instance()->prepare('UPDATE seo_internal_networks SET active = 0 WHERE site_id = @site_id AND id = ?')->execute([$id]);
            self::$networksCache = null;
            self::$detected = null;
            return ['ok' => true, 'message' => 'Red interna eliminada.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo eliminar la red interna.'];
        }
    }

    public static function matchingNetwork(string $ip): ?array
    {
        if (self::$networksCache === null) self::$networksCache = self::networks();
        foreach (self::$networksCache as $network) {
            if (self::ipInCidr($ip, $network['cidr'])) return $network;
        }
        return null;
    }

    public static function normalizeCidr(string $cidr): ?string
    {
        $cidr = trim($cidr);
        if ($cidr === '') return null;
        if (strpos($cidr, '/') === false) {
            if (filter_var($cidr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return $cidr . '/32';
            if (filter_var($cidr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) return $cidr . '/128';
            return null;
        }
        [$ip, $prefix] = explode('/', $cidr, 2);
        $ip = trim($ip);
        $prefix = trim($prefix);
        if ($ip === '' || $prefix === '' || !ctype_digit($prefix)) return null;
        $packed = @inet_pton($ip);
        if ($packed === false) return null;
        $max = strlen($packed) === 4 ? 32 : 128;
        $prefix = (int)$prefix;
        if ($prefix < 0 || $prefix > $max) return null;
        return $ip . '/' . $prefix;
    }

    public static function ipInCidr(string $ip, string $cidr): bool
    {
        $normalized = self::normalizeCidr($cidr);
        if ($normalized === null) return false;
        [$networkIp, $prefix] = explode('/', $normalized, 2);
        $ipBin = @inet_pton($ip);
        $netBin = @inet_pton($networkIp);
        if ($ipBin === false || $netBin === false || strlen($ipBin) !== strlen($netBin)) return false;
        $prefix = (int)$prefix;
        $bytes = intdiv($prefix, 8);
        $bits = $prefix % 8;
        for ($i = 0; $i < $bytes; $i++) {
            if ($ipBin[$i] !== $netBin[$i]) return false;
        }
        if ($bits > 0) {
            $mask = (0xFF << (8 - $bits)) & 0xFF;
            if ((ord($ipBin[$bytes]) & $mask) !== (ord($netBin[$bytes]) & $mask)) return false;
        }
        return true;
    }

    // ─────────────────────────────────────────────
    // Resumen "Mi tráfico"
    // ─────────────────────────────────────────────

    public function summary(int $days = 7): array
    {
        $days = max(1, min(365, $days));
        $db = Database::instance();
        $since = 'created_at >= (NOW() - INTERVAL ' . $days . ' DAY)';
        $scalar = static function (string $sql) use ($db) {
            try { return $db->query($sql)->fetchColumn(); } catch (\Throwable $e) { return 0; }
        };
        $rows = static function (string $sql) use ($db) {
            try { return $db->query($sql)->fetchAll(); } catch (\Throwable $e) { return []; }
        };

        $client = $rows("SELECT COUNT(*) requests,
            COALESCE(SUM(is_page_view = 1), 0) page_views,
            COUNT(DISTINCT NULLIF(visitor_hash, '')) visitors,
            COALESCE(SUM(is_bot = 1), 0) bots
            FROM seo_live_requests WHERE site_id = @site_id AND is_internal = 0 AND is_bot = 0 AND $since")[0] ?? [];
        $internal = $rows("SELECT COUNT(*) requests,
            COALESCE(SUM(is_page_view = 1), 0) page_views,
            COUNT(DISTINCT NULLIF(visitor_hash, '')) visitors,
            COALESCE(SUM(is_bot = 1), 0) bots
            FROM seo_live_requests WHERE site_id = @site_id AND is_internal = 1 AND $since")[0] ?? [];
        $bots = (int)$scalar("SELECT COUNT(*) FROM seo_live_requests WHERE site_id = @site_id AND is_bot = 1 AND $since");

        $events = $rows("SELECT COUNT(*) events,
            COUNT(DISTINCT session_id) sessions,
            COALESCE(SUM(type = 'page_view'), 0) views,
            COALESCE(SUM(type IN ('link_click','cta_click','outbound')), 0) interactions,
            COALESCE(SUM(type = 'form_submit'), 0) submits
            FROM seo_tracking_events WHERE site_id = @site_id AND is_internal = 1 AND is_bot = 0 AND $since")[0] ?? [];
        $analytics = $rows("SELECT COUNT(*) views, COUNT(DISTINCT COALESCE(NULLIF(visitor_hash, ''), ip_hash)) visitors
            FROM analytics_views WHERE site_id = @site_id AND is_internal = 1 AND is_bot = 0 AND $since")[0] ?? [];

        $reasons = $rows("SELECT internal_reason reason, COUNT(*) n, COALESCE(SUM(is_page_view = 1), 0) page_views
            FROM seo_live_requests WHERE site_id = @site_id AND is_internal = 1 AND $since
            GROUP BY internal_reason ORDER BY n DESC");
        $topInternal = $rows("SELECT path, COUNT(*) n, COALESCE(SUM(is_page_view = 1), 0) page_views
            FROM seo_live_requests WHERE site_id = @site_id AND is_internal = 1 AND $since
            GROUP BY path ORDER BY n DESC LIMIT 15");
        $latest = $rows("SELECT id, method, path, status, duration_ms, ip, device, os, browser, internal_reason, is_page_view, utm_source, utm_campaign, created_at
            FROM seo_live_requests WHERE site_id = @site_id AND is_internal = 1 AND $since ORDER BY id DESC LIMIT 40");
        $latestEvents = $rows("SELECT type, page_url, target, label, device, browser, internal_reason, created_at
            FROM seo_tracking_events WHERE site_id = @site_id AND is_internal = 1 AND $since ORDER BY id DESC LIMIT 25");
        $ordersInternal = (int)$scalar("SELECT COUNT(*) FROM store_orders WHERE site_id = @site_id AND is_internal = 1 AND $since");

        $current = self::detect(true);
        $totalRequests = (int)($client['requests'] ?? 0) + (int)($internal['requests'] ?? 0) + $bots;
        $internalRequests = (int)($internal['requests'] ?? 0);
        $internalPageViews = (int)($internal['page_views'] ?? 0);
        $clientPageViews = (int)($client['page_views'] ?? 0);

        return [
            'ok' => true,
            'days' => $days,
            'current' => [
                'ip' => $current['ip'] ?? self::clientIp(),
                'internal' => !empty($current['internal']),
                'reason' => (string)($current['reason'] ?? ''),
                'reason_label' => self::reasonLabel((string)($current['reason'] ?? '')),
                'label' => (string)($current['label'] ?? ''),
                'device_id' => $current['device_id'] ?? null,
                'network' => $current['network'] ?? null,
                'admin_session' => !empty($current['admin_session']),
                'cookie_marked' => self::currentDeviceId() !== null,
                'user_agent' => (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
            ],
            'client' => [
                'requests' => (int)($client['requests'] ?? 0),
                'page_views' => $clientPageViews,
                'visitors' => (int)($client['visitors'] ?? 0),
            ],
            'internal' => [
                'requests' => $internalRequests,
                'page_views' => $internalPageViews,
                'visitors' => (int)($internal['visitors'] ?? 0),
                'events' => (int)($events['events'] ?? 0),
                'sessions' => (int)($events['sessions'] ?? 0),
                'event_views' => (int)($events['views'] ?? 0),
                'interactions' => (int)($events['interactions'] ?? 0),
                'submits' => (int)($events['submits'] ?? 0),
                'analytics_views' => (int)($analytics['views'] ?? 0),
                'analytics_visitors' => (int)($analytics['visitors'] ?? 0),
                'orders' => $ordersInternal,
            ],
            'bots' => $bots,
            'share' => [
                'requests_pct' => $totalRequests > 0 ? round($internalRequests / $totalRequests * 100, 1) : 0,
                'page_views_pct' => ($clientPageViews + $internalPageViews) > 0 ? round($internalPageViews / ($clientPageViews + $internalPageViews) * 100, 1) : 0,
            ],
            'reasons' => array_map(static function ($r) {
                return [
                    'reason' => (string)($r['reason'] ?? ''),
                    'label' => self::reasonLabel((string)($r['reason'] ?? '')),
                    'requests' => (int)($r['n'] ?? 0),
                    'page_views' => (int)($r['page_views'] ?? 0),
                ];
            }, $reasons),
            'top_internal' => array_map(static function ($r) {
                return ['path' => (string)$r['path'], 'requests' => (int)$r['n'], 'page_views' => (int)$r['page_views']];
            }, $topInternal),
            'latest' => array_map(static function ($r) {
                return [
                    'id' => (int)$r['id'],
                    'method' => (string)$r['method'],
                    'path' => (string)$r['path'],
                    'status' => (int)$r['status'],
                    'duration_ms' => (int)$r['duration_ms'],
                    'ip' => (string)($r['ip'] ?? ''),
                    'device' => (string)($r['device'] ?? ''),
                    'os' => (string)($r['os'] ?? ''),
                    'browser' => (string)($r['browser'] ?? ''),
                    'reason' => (string)($r['internal_reason'] ?? ''),
                    'reason_label' => self::reasonLabel((string)($r['internal_reason'] ?? '')),
                    'is_page_view' => (int)($r['is_page_view'] ?? 0) === 1,
                    'utm_source' => (string)($r['utm_source'] ?? ''),
                    'utm_campaign' => (string)($r['utm_campaign'] ?? ''),
                    'created_at' => (string)$r['created_at'],
                ];
            }, $latest),
            'latest_events' => array_map(static function ($r) {
                return [
                    'type' => (string)$r['type'],
                    'page_url' => (string)($r['page_url'] ?? ''),
                    'target' => (string)($r['target'] ?? ''),
                    'label' => (string)($r['label'] ?? ''),
                    'device' => (string)($r['device'] ?? ''),
                    'browser' => (string)($r['browser'] ?? ''),
                    'reason' => (string)($r['internal_reason'] ?? ''),
                    'reason_label' => self::reasonLabel((string)($r['internal_reason'] ?? '')),
                    'created_at' => (string)($r['created_at'] ?? ''),
                ];
            }, $latestEvents),
            'networks' => self::networks(),
            'devices' => self::devices(),
            'mac_note' => 'La MAC de la tarjeta de red no se transmite en HTTP. Este equipo se identifica con una cookie firmada de larga duración, la sesión de administrador y una allowlist de IP/CIDR.',
            'retention_days' => self::retentionDays(),
            'generated_at' => date('c'),
        ];
    }

    /**
     * Reclasifica datos históricos de las IPs de la allowlist. No toca el
     * tráfico que no coincida. Se ejecuta solo cuando el admin lo confirma.
     */
    public function backfill(bool $dryRun = false): array
    {
        $db = Database::instance();
        $networks = self::networks();
        $loopback = ['127.0.0.1', '::1'];
        $candidates = [];
        try {
            $ips = $db->query("SELECT DISTINCT ip FROM seo_live_requests WHERE site_id = @site_id AND ip IS NOT NULL AND ip <> '' AND created_at >= (NOW() - INTERVAL 90 DAY)")->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\Throwable $e) {
            $ips = [];
        }
        $loopbackMatched = [];
        $networkMatched = [];
        foreach ($ips as $ip) {
            $ip = (string)$ip;
            if (self::isLoopback($ip)) { $loopbackMatched[] = $ip; continue; }
            foreach ($networks as $network) {
                if (self::ipInCidr($ip, $network['cidr'])) { $networkMatched[] = $ip; break; }
            }
        }
        $networkMatched = array_values(array_unique(array_diff($networkMatched, $loopbackMatched)));
        foreach ($loopback as $ip) if (!in_array($ip, $loopbackMatched, true)) $loopbackMatched[] = $ip;
        $candidates = array_values(array_unique(array_merge($loopbackMatched, $networkMatched)));

        $counts = ['live_requests' => 0, 'events' => 0, 'analytics_views' => 0, 'orders' => 0];
        if ($dryRun) {
            if ($candidates) {
                $ph = implode(',', array_fill(0, count($candidates), '?'));
                try {
                    $st = $db->prepare("SELECT COUNT(*) FROM seo_live_requests WHERE site_id = @site_id AND is_internal = 0 AND ip IN ($ph)");
                    $st->execute($candidates);
                    $counts['live_requests'] = (int)$st->fetchColumn();
                } catch (\Throwable $e) {}
                $hashes = array_map(static fn($ip) => hash('sha256', $ip), $candidates);
                $hph = implode(',', array_fill(0, count($hashes), '?'));
                try { $st = $db->prepare("SELECT COUNT(*) FROM seo_tracking_events WHERE site_id = @site_id AND is_internal = 0 AND ip_hash IN ($hph)"); $st->execute($hashes); $counts['events'] = (int)$st->fetchColumn(); } catch (\Throwable $e) {}
                try { $st = $db->prepare("SELECT COUNT(*) FROM analytics_views WHERE site_id = @site_id AND is_internal = 0 AND ip_hash IN ($hph)"); $st->execute($hashes); $counts['analytics_views'] = (int)$st->fetchColumn(); } catch (\Throwable $e) {}
                try { $st = $db->prepare("SELECT COUNT(*) FROM store_orders WHERE site_id = @site_id AND is_internal = 0 AND ip_hash IN ($hph)"); $st->execute($hashes); $counts['orders'] = (int)$st->fetchColumn(); } catch (\Throwable $e) {}
            }
            return ['ok' => true, 'dry_run' => true, 'candidates' => $candidates, 'counts' => $counts];
        }

        $signals = $this->backfillSignals();
        if ($loopbackMatched) {
            $ph = implode(',', array_fill(0, count($loopbackMatched), '?'));
            try {
                $st = $db->prepare("UPDATE seo_live_requests SET is_internal = 1, internal_reason = '" . self::REASON_LOCAL . "' WHERE site_id = @site_id AND is_internal = 0 AND ip IN ($ph)");
                $st->execute($loopbackMatched);
                $counts['live_requests'] += $st->rowCount();
            } catch (\Throwable $e) {}
        }
        if ($networkMatched) {
            foreach (array_chunk($networkMatched, 200) as $chunk) {
                $ph = implode(',', array_fill(0, count($chunk), '?'));
                try {
                    $st = $db->prepare("UPDATE seo_live_requests SET is_internal = 1, internal_reason = 'ip_allowlist_backfill' WHERE site_id = @site_id AND is_internal = 0 AND ip IN ($ph)");
                    $st->execute($chunk);
                    $counts['live_requests'] += $st->rowCount();
                } catch (\Throwable $e) {}
            }
        }
        $hashes = array_values(array_unique(array_map(static fn($ip) => hash('sha256', $ip), $candidates)));
        foreach (array_chunk($hashes, 200) as $chunk) {
            $ph = implode(',', array_fill(0, count($chunk), '?'));
            try {
                $st = $db->prepare("UPDATE seo_tracking_events SET is_internal = 1, internal_reason = 'ip_allowlist_backfill' WHERE site_id = @site_id AND is_internal = 0 AND ip_hash IN ($ph)");
                $st->execute($chunk);
                $counts['events'] += $st->rowCount();
            } catch (\Throwable $e) {}
            try {
                $st = $db->prepare("UPDATE analytics_views SET is_internal = 1 WHERE site_id = @site_id AND is_internal = 0 AND ip_hash IN ($ph)");
                $st->execute($chunk);
                $counts['analytics_views'] += $st->rowCount();
            } catch (\Throwable $e) {}
            try {
                $st = $db->prepare("UPDATE store_orders SET is_internal = 1, internal_reason = 'ip_allowlist_backfill' WHERE site_id = @site_id AND is_internal = 0 AND ip_hash IN ($ph)");
                $st->execute($chunk);
                $counts['orders'] += $st->rowCount();
            } catch (\Throwable $e) {}
        }

        return ['ok' => true, 'dry_run' => false, 'candidates' => $candidates, 'counts' => $counts, 'signals' => $signals];
    }

    /**
     * Rellena señales históricas que faltaban: page_view, visitor_hash y
     * loopback. Es idempotente y no toca las IPs de la allowlist.
     */
    public function backfillSignals(): array
    {
        $db = Database::instance();
        $out = ['page_views' => 0, 'visitor_hashes' => 0, 'loopback' => 0, 'analytics_visitors' => 0, 'event_visitors' => 0];
        try {
            $out['page_views'] = (int)$db->exec("UPDATE seo_live_requests SET is_page_view = 1
                WHERE site_id = @site_id AND is_page_view = 0 AND method = 'GET' AND status >= 200 AND status < 400
                AND path NOT REGEXP '^/(api|assets)(/|$)'
                AND path NOT IN ('/favicon.ico','/robots.txt','/buscar/sugerencias')
                AND path NOT LIKE '/sitemap%'");
        } catch (\Throwable $e) {
        }
        try {
            $out['loopback'] = (int)$db->exec("UPDATE seo_live_requests SET is_internal = 1, internal_reason = 'local_request'
                WHERE site_id = @site_id AND is_internal = 0 AND ip IN ('127.0.0.1','::1')");
        } catch (\Throwable $e) {
        }
        foreach ([['127.0.0.1'], ['::1']] as $ipPair) {
            $hash = hash('sha256', $ipPair[0]);
            try {
                $db->prepare("UPDATE seo_tracking_events SET is_internal = 1, internal_reason = 'local_request' WHERE site_id = @site_id AND is_internal = 0 AND ip_hash = ?")->execute([$hash]);
            } catch (\Throwable $e) {}
            try {
                $db->prepare("UPDATE analytics_views SET is_internal = 1 WHERE site_id = @site_id AND is_internal = 0 AND ip_hash = ?")->execute([$hash]);
            } catch (\Throwable $e) {}
            try {
                $db->prepare("UPDATE store_orders SET is_internal = 1, internal_reason = 'local_request' WHERE site_id = @site_id AND is_internal = 0 AND ip_hash = ?")->execute([$hash]);
            } catch (\Throwable $e) {}
        }
        try {
            $salt = self::visitorSalt();
            $upd = $db->prepare("UPDATE seo_live_requests SET visitor_hash = SHA2(CONCAT(?, '|', site_id, '|', ip, '|', LOWER(COALESCE(user_agent, ''))), 256)
                WHERE site_id = @site_id AND visitor_hash IS NULL AND ip IS NOT NULL AND ip <> ''");
            $upd->execute([$salt]);
            $out['visitor_hashes'] = $upd->rowCount();
        } catch (\Throwable $e) {
        }
        try {
            $out['analytics_visitors'] = (int)$db->exec("UPDATE analytics_views SET visitor_hash = ip_hash WHERE site_id = @site_id AND visitor_hash IS NULL AND ip_hash IS NOT NULL AND ip_hash <> ''");
        } catch (\Throwable $e) {
        }
        try {
            $out['event_visitors'] = (int)$db->exec("UPDATE seo_tracking_events SET visitor_hash = ip_hash WHERE site_id = @site_id AND visitor_hash IS NULL AND ip_hash IS NOT NULL AND ip_hash <> ''");
        } catch (\Throwable $e) {
        }
        return $out;
    }

    // ─────────────────────────────────────────────
    // Atribución / cookies de sesión
    // ─────────────────────────────────────────────

    public static function visitorHash(?string $ip, string $ua): ?string
    {
        if ($ip === null || $ip === '') return null;
        $siteId = (string)Config::get('SITE_ID', '1');
        return hash('sha256', self::visitorSalt() . '|' . $siteId . '|' . $ip . '|' . strtolower($ua));
    }

    private static function visitorSalt(): string
    {
        if (self::$visitorSaltCache !== null) return self::$visitorSaltCache;
        $salt = self::getSetting('seo_visitor_salt');
        if ($salt === '') {
            $salt = bin2hex(random_bytes(32));
            self::setSetting('seo_visitor_salt', $salt);
        }
        return self::$visitorSaltCache = $salt;
    }

    public static function sessionId(): ?string
    {
        $sid = (string)($_COOKIE['wwi_seo_sid'] ?? '');
        $sid = preg_replace('/[^a-zA-Z0-9_-]/', '', $sid) ?? '';
        return $sid !== '' ? substr($sid, 0, 64) : null;
    }

    public static function utmFromRequest(): array
    {
        $get = $_GET ?? [];
        $pick = static function (string $key, int $max = 160) use ($get): ?string {
            $value = isset($get[$key]) && is_scalar($get[$key]) ? trim((string)$get[$key]) : '';
            return $value !== '' ? mb_substr($value, 0, $max) : null;
        };
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http';
        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $url = $host !== '' ? $scheme . '://' . $host . $uri : $uri;
        $query = (string)parse_url($uri, PHP_URL_QUERY);
        return [
            'utm_source' => $pick('utm_source', 120),
            'utm_medium' => $pick('utm_medium', 120),
            'utm_campaign' => $pick('utm_campaign'),
            'utm_term' => $pick('utm_term'),
            'utm_content' => $pick('utm_content'),
            'fbclid' => $pick('fbclid', 255),
            'landing_url' => mb_substr($url, 0, 500),
            'query_string' => mb_substr($query, 0, 500),
        ];
    }

    public static function isPageView(string $method, int $status, string $path): bool
    {
        if ($method !== 'GET' || $status >= 400) return false;
        if (preg_match('~^/(api|assets)(/|$)~i', $path)) return false;
        if (preg_match('~^/(favicon\.ico|robots\.txt|sitemap[^/]*\.xml)$~i', $path)) return false;
        if (preg_match('~^/buscar/sugerencias$~i', $path)) return false;
        if (strcasecmp((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''), 'XMLHttpRequest') === 0) return false;
        $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
        if ($accept !== '' && strpos($accept, 'text/html') === false && strpos($accept, 'application/xhtml+xml') === false) return false;
        return true;
    }

    public static function retentionDays(): int
    {
        try {
            $stmt = Database::instance()->prepare('SELECT `value` FROM settings WHERE `key` = ? AND site_id = @site_id LIMIT 1');
            $stmt->execute(['live_monitor_retention_days']);
            $value = (int)$stmt->fetchColumn();
            if ($value >= 7) return min(365, $value);
        } catch (\Throwable $e) {
        }
        return 90;
    }

    private static function getSetting(string $key): string
    {
        try {
            $stmt = Database::instance()->prepare('SELECT `value` FROM settings WHERE `key` = ? AND site_id = @site_id LIMIT 1');
            $stmt->execute([$key]);
            $value = $stmt->fetchColumn();
            return $value === false ? '' : (string)$value;
        } catch (\Throwable $e) {
            return '';
        }
    }

    private static function setSetting(string $key, string $value): void
    {
        try {
            Database::instance()->prepare("INSERT INTO settings (site_id, `key`, `value`) VALUES (@site_id, ?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")
                ->execute([$key, $value]);
        } catch (\Throwable $e) {
        }
    }
}
