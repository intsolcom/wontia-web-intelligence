<?php
namespace App\Services;

use App\Core\Config;
use App\Core\Database;

/**
 * Integración Mercado Pago (Colombia).
 *
 * Soporta dos modalidades:
 *  - checkout_pro: preferencia + redirección a Mercado Pago.
 *  - checkout_api: tokenización de tarjeta en el navegador y POST /v1/payments.
 *
 * Credenciales manuales: Client ID, Client Secret, Public Key y,
 * opcionalmente, Access Token y la clave secreta de webhooks.
 * Si no hay Access Token, se obtiene uno automáticamente con
 * OAuth client_credentials (Client ID + Client Secret).
 */
class MercadoPagoService
{
    private const API = 'https://api.mercadopago.com';
    private const AUTH = 'https://auth.mercadopago.com.co';
    private const DOCS = 'https://www.mercadopago.com.co/developers/es/docs/your-integrations/credentials';

    private const DEFAULTS = [
        'mercadopago_enabled' => '0',
        'mercadopago_environment' => 'sandbox',
        'mercadopago_mode' => 'checkout_pro',
        'mercadopago_client_id' => '',
        'mercadopago_client_secret' => '',
        'mercadopago_public_key' => '',
        'mercadopago_access_token' => '',
        'mercadopago_webhook_secret' => '',
        'mercadopago_statement_descriptor' => 'SONIKA',
        'mercadopago_label' => 'Mercado Pago',
        'mercadopago_description' => 'Paga con tarjeta, PSE, Nequi o efectivo.',
        'mercadopago_max_installments' => '12',
        'mercadopago_binary_mode' => '1',
        'mercadopago_success_url' => '',
        'mercadopago_token_cache' => '',
        'mercadopago_oauth_refresh' => '',
        'mercadopago_oauth_user' => '',
        'mercadopago_oauth_at' => '',
        'mercadopago_last_test' => '',
        'mercadopago_last_test_at' => '',
    ];

    private const SECRET_KEYS = ['client_secret', 'access_token', 'webhook_secret', 'token_cache', 'oauth_refresh'];

    private static bool $ensured = false;
    private static ?array $cache = null;

    public function ensureTables(): array
    {
        if (self::$ensured) return ['ok' => true, 'cached' => true];
        $root = defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 2);
        $path = $root . '/install/mercadopago.sql';
        if (!is_file($path)) return ['ok' => false, 'message' => 'No se encontró el esquema de Mercado Pago.'];
        $sql = preg_replace('/^\s*--.*$/m', '', (string)file_get_contents($path));
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            if ($statement !== '') Database::instance()->exec($statement . ';');
        }
        self::$ensured = true;
        return ['ok' => true, 'cached' => false];
    }

    public function settings(): array
    {
        if (self::$cache !== null) return self::$cache;
        $map = [];
        try {
            $keys = array_keys(self::DEFAULTS);
            $in = implode(',', array_fill(0, count($keys), '?'));
            $stmt = Database::instance()->prepare("SELECT `key`, `value` FROM settings WHERE site_id = @site_id AND `key` IN ($in)");
            $stmt->execute($keys);
            foreach ($stmt->fetchAll() as $row) $map[(string)$row['key']] = (string)$row['value'];
        } catch (\Throwable $e) {
        }
        $raw = [];
        foreach (self::DEFAULTS as $key => $default) $raw[$key] = $map[$key] ?? $default;

        $tokenCache = json_decode(TwoFactorService::decrypt((string)$raw['mercadopago_token_cache']), true) ?: [];
        $oauthUser = json_decode((string)$raw['mercadopago_oauth_user'], true) ?: [];
        $environment = in_array($raw['mercadopago_environment'], ['sandbox', 'production'], true) ? $raw['mercadopago_environment'] : 'sandbox';
        $mode = in_array($raw['mercadopago_mode'], ['checkout_pro', 'checkout_api'], true) ? $raw['mercadopago_mode'] : 'checkout_pro';

        return self::$cache = [
            'enabled' => $raw['mercadopago_enabled'] === '1',
            'environment' => $environment,
            'mode' => $mode,
            'client_id' => trim((string)$raw['mercadopago_client_id']),
            'client_secret' => TwoFactorService::decrypt((string)$raw['mercadopago_client_secret']),
            'public_key' => trim((string)$raw['mercadopago_public_key']),
            'access_token' => TwoFactorService::decrypt((string)$raw['mercadopago_access_token']),
            'webhook_secret' => TwoFactorService::decrypt((string)$raw['mercadopago_webhook_secret']),
            'statement_descriptor' => mb_substr(trim((string)$raw['mercadopago_statement_descriptor']) ?: 'SONIKA', 0, 22),
            'label' => trim((string)$raw['mercadopago_label']) ?: 'Mercado Pago',
            'description' => trim((string)$raw['mercadopago_description']) ?: 'Paga con tarjeta, PSE, Nequi o efectivo.',
            'max_installments' => max(1, min(36, (int)$raw['mercadopago_max_installments'])),
            'binary_mode' => $raw['mercadopago_binary_mode'] !== '0',
            'success_url' => trim((string)$raw['mercadopago_success_url']),
            'token_cache' => is_array($tokenCache) ? $tokenCache : [],
            'oauth_refresh' => TwoFactorService::decrypt((string)$raw['mercadopago_oauth_refresh']),
            'oauth_user' => is_array($oauthUser) ? $oauthUser : [],
            'oauth_at' => (string)$raw['mercadopago_oauth_at'],
            'last_test' => json_decode((string)$raw['mercadopago_last_test'], true) ?: [],
            'last_test_at' => (string)$raw['mercadopago_last_test_at'],
        ];
    }

    public function mode(): string
    {
        return $this->settings()['mode'];
    }

    public function environment(): string
    {
        return $this->settings()['environment'];
    }

    public function publicKey(): string
    {
        return $this->settings()['public_key'];
    }

    public function maxInstallments(): int
    {
        return $this->settings()['max_installments'];
    }

    public function adminSettings(): array
    {
        $s = $this->settings();
        $token = $this->tokenInfo();
        return [
            'enabled' => $s['enabled'],
            'environment' => $s['environment'],
            'mode' => $s['mode'],
            'client_id' => $s['client_id'],
            'client_secret_set' => $s['client_secret'] !== '',
            'public_key' => $s['public_key'],
            'access_token_set' => $s['access_token'] !== '',
            'webhook_secret_set' => $s['webhook_secret'] !== '',
            'oauth_refresh_set' => $s['oauth_refresh'] !== '',
            'oauth_connected' => !empty($s['oauth_user']['user_id']) || $s['oauth_refresh'] !== '',
            'oauth_user' => $s['oauth_user'],
            'oauth_at' => $s['oauth_at'],
            'statement_descriptor' => $s['statement_descriptor'],
            'label' => $s['label'],
            'description' => $s['description'],
            'max_installments' => $s['max_installments'],
            'binary_mode' => $s['binary_mode'],
            'success_url' => $s['success_url'],
            'fingerprints' => [
                'client_secret' => $this->fingerprint($s['client_secret']),
                'access_token' => $this->fingerprint($s['access_token']),
                'webhook_secret' => $this->fingerprint($s['webhook_secret']),
            ],
            'token' => $token,
            'configured' => $this->configured($s),
            'available' => $this->available($s),
            'webhook_url' => $this->webhookUrl(),
            'redirect_uri' => $this->oauthRedirectUri(),
            'health' => $this->health($s, $token),
            'last_test' => $s['last_test'],
            'last_test_at' => $s['last_test_at'],
            'currency' => $this->currency(),
            'docs_url' => self::DOCS,
        ];
    }

    public function saveSettings(array $data): array
    {
        $current = $this->settings();
        $environment = in_array((string)($data['environment'] ?? ''), ['sandbox', 'production'], true)
            ? (string)$data['environment'] : $current['environment'];
        $mode = in_array((string)($data['mode'] ?? ''), ['checkout_pro', 'checkout_api'], true)
            ? (string)$data['mode'] : $current['mode'];
        $clientId = trim((string)($data['client_id'] ?? ''));
        $publicKey = trim((string)($data['public_key'] ?? ''));
        $maxInstallments = max(1, min(36, (int)($data['max_installments'] ?? $current['max_installments'])));

        if ($clientId !== '' && !preg_match('/^[0-9]{4,30}$/', $clientId)) {
            throw new \InvalidArgumentException('El Client ID de Mercado Pago debe contener solo números (lo encuentras en Tus integraciones → Credenciales).');
        }
        if ($publicKey !== '' && mb_strlen($publicKey) < 20) {
            throw new \InvalidArgumentException('La Public Key no parece válida. Cópiala completa desde Mercado Pago (empieza por APP_USR-).');
        }

        $values = [
            'mercadopago_enabled' => !empty($data['enabled']) ? '1' : '0',
            'mercadopago_environment' => $environment,
            'mercadopago_mode' => $mode,
            'mercadopago_client_id' => mb_substr($clientId, 0, 40),
            'mercadopago_public_key' => mb_substr($publicKey, 0, 190),
            'mercadopago_statement_descriptor' => mb_substr(trim((string)($data['statement_descriptor'] ?? '')) ?: 'SONIKA', 0, 22),
            'mercadopago_label' => mb_substr(trim((string)($data['label'] ?? '')) ?: 'Mercado Pago', 0, 80),
            'mercadopago_description' => mb_substr(trim((string)($data['description'] ?? '')) ?: 'Paga con tarjeta, PSE, Nequi o efectivo.', 0, 160),
            'mercadopago_max_installments' => (string)$maxInstallments,
            'mercadopago_binary_mode' => !empty($data['binary_mode']) ? '1' : '0',
            'mercadopago_success_url' => $this->cleanUrl((string)($data['success_url'] ?? '')),
        ];

        // Secretos: si llega un valor nuevo se cifra; si se pide borrar, se limpia.
        foreach (['client_secret', 'access_token', 'webhook_secret'] as $secretKey) {
            $field = 'mercadopago_' . $secretKey;
            $incoming = trim((string)($data[$secretKey] ?? ''));
            if (!empty($data['clear_' . $secretKey])) {
                $values[$field] = '';
                continue;
            }
            if ($incoming === '') continue;
            if ($secretKey === 'client_secret' && mb_strlen($incoming) < 8) {
                throw new \InvalidArgumentException('El Client Secret no parece válido. Cópialo completo desde Mercado Pago.');
            }
            if (in_array($secretKey, ['access_token', 'webhook_secret'], true) && mb_strlen($incoming) < 10) {
                throw new \InvalidArgumentException('El valor de ' . str_replace('_', ' ', $secretKey) . ' no parece válido.');
            }
            $values[$field] = TwoFactorService::encrypt($incoming);
        }

        $credentialsChanged = ($clientId !== $current['client_id'])
            || ($publicKey !== $current['public_key'])
            || (isset($values['mercadopago_client_secret']) && $values['mercadopago_client_secret'] !== '')
            || (isset($values['mercadopago_access_token']) && $values['mercadopago_access_token'] !== '')
            || !empty($data['clear_client_secret'])
            || !empty($data['clear_access_token']);
        if ($credentialsChanged) {
            $values['mercadopago_token_cache'] = '';
            $values['mercadopago_oauth_refresh'] = '';
            $values['mercadopago_oauth_user'] = '';
            $values['mercadopago_oauth_at'] = '';
        }

        $db = Database::instance();
        $stmt = $db->prepare("INSERT INTO settings (site_id, `key`, `value`) VALUES (@site_id, ?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
        foreach ($values as $key => $value) $stmt->execute([$key, (string)$value]);
        self::$cache = null;
        return $this->adminSettings();
    }

    public function publicMethod(): ?array
    {
        $s = $this->settings();
        if (!$this->available($s)) return null;
        return [
            'id' => 'mercadopago',
            'label' => $s['label'],
            'description' => $s['description'],
            'active' => true,
            'mode' => $s['mode'],
        ];
    }

    public function publicConfig(): array
    {
        $s = $this->settings();
        if (!$this->available($s)) return [];
        return [
            'public_key' => $s['public_key'],
            'mode' => $s['mode'],
            'max_installments' => $s['max_installments'],
            'label' => $s['label'],
            'brick' => $s['mode'] === 'checkout_api',
            'currency' => $this->currency(),
        ];
    }

    public function isAvailable(): bool
    {
        return $this->available($this->settings());
    }

    public function isConfigured(): bool
    {
        return $this->configured($this->settings());
    }

    // ─────────────────────────── Tokens ───────────────────────────

    /**
     * Devuelve el Access Token vigente sin hacer red: solo manual o cacheado.
     */
    public function tokenInfo(): array
    {
        $s = $this->settings();
        if ($s['access_token'] !== '') {
            return ['source' => 'manual', 'label' => 'Access Token manual', 'expires_at' => '', 'available' => true];
        }
        $cache = $s['token_cache'];
        if (!empty($cache['access_token']) && (int)($cache['expires_at'] ?? 0) > time() + 60) {
            return [
                'source' => (string)($cache['source'] ?? 'cache'),
                'label' => ($cache['source'] ?? '') === 'oauth' ? 'OAuth (cuenta conectada)' : 'Client credentials (automático)',
                'expires_at' => date('Y-m-d H:i:s', (int)$cache['expires_at']),
                'available' => true,
            ];
        }
        if ($s['client_id'] !== '' && $s['client_secret'] !== '') {
            return [
                'source' => 'client_credentials',
                'label' => 'Client credentials (se generará al usar)',
                'expires_at' => '',
                'available' => true,
            ];
        }
        return ['source' => 'none', 'label' => 'Sin token disponible', 'expires_at' => '', 'available' => false];
    }

    public function resolveAccessToken(bool $force = false): array
    {
        $s = $this->settings();
        if ($s['access_token'] !== '') {
            return ['ok' => true, 'token' => $s['access_token'], 'source' => 'manual', 'expires_at' => ''];
        }
        $cache = $s['token_cache'];
        if (!$force && !empty($cache['access_token']) && (int)($cache['expires_at'] ?? 0) > time() + 60) {
            return [
                'ok' => true,
                'token' => (string)$cache['access_token'],
                'source' => (string)($cache['source'] ?? 'cache'),
                'expires_at' => date('Y-m-d H:i:s', (int)$cache['expires_at']),
            ];
        }
        if ($s['oauth_refresh'] !== '' && $s['client_id'] !== '' && $s['client_secret'] !== '') {
            $refreshed = $this->oauthToken([
                'grant_type' => 'refresh_token',
                'client_id' => $s['client_id'],
                'client_secret' => $s['client_secret'],
                'refresh_token' => $s['oauth_refresh'],
            ]);
            if (!empty($refreshed['ok'])) {
                $this->storeToken((array)$refreshed['data'], 'oauth');
                return ['ok' => true, 'token' => (string)$refreshed['data']['access_token'], 'source' => 'oauth', 'expires_at' => ''];
            }
        }
        if ($s['client_id'] !== '' && $s['client_secret'] !== '') {
            $fresh = $this->oauthToken([
                'grant_type' => 'client_credentials',
                'client_id' => $s['client_id'],
                'client_secret' => $s['client_secret'],
            ]);
            if (!empty($fresh['ok']) && !empty($fresh['data']['access_token'])) {
                $this->storeToken((array)$fresh['data'], 'client_credentials');
                return ['ok' => true, 'token' => (string)$fresh['data']['access_token'], 'source' => 'client_credentials', 'expires_at' => ''];
            }
            return ['ok' => false, 'message' => (string)($fresh['message'] ?? 'No se pudo generar el Access Token con Client ID y Client Secret.')];
        }
        return ['ok' => false, 'message' => 'Falta el Access Token o el par Client ID + Client Secret.'];
    }

    private function storeToken(array $data, string $source): void
    {
        $token = (string)($data['access_token'] ?? '');
        if ($token === '') return;
        $expiresIn = (int)($data['expires_in'] ?? 21600);
        $cache = [
            'access_token' => $token,
            'source' => $source,
            'expires_at' => time() + max(300, $expiresIn - 300),
            'user_id' => $data['user_id'] ?? null,
        ];
        $values = [
            'mercadopago_token_cache' => TwoFactorService::encrypt(json_encode($cache, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
        ];
        if (!empty($data['refresh_token'])) {
            $values['mercadopago_oauth_refresh'] = TwoFactorService::encrypt((string)$data['refresh_token']);
        }
        if (!empty($data['user_id'])) {
            $user = [
                'user_id' => $data['user_id'],
                'public_key' => $data['public_key'] ?? '',
                'live_mode' => $data['live_mode'] ?? null,
                'scope' => $data['scope'] ?? '',
            ];
            $values['mercadopago_oauth_user'] = json_encode($user, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $values['mercadopago_oauth_at'] = date('Y-m-d H:i:s');
        try {
            $db = Database::instance();
            $stmt = $db->prepare("INSERT INTO settings (site_id, `key`, `value`) VALUES (@site_id, ?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
            foreach ($values as $key => $value) $stmt->execute([$key, (string)$value]);
            self::$cache = null;
        } catch (\Throwable $e) {
        }
    }

    private function oauthToken(array $fields): array
    {
        $result = $this->http(self::API . '/oauth/token', [
            'method' => 'POST',
            'body' => http_build_query($fields),
            'headers' => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ],
            'auth' => false,
        ]);
        $data = json_decode((string)$result['body'], true);
        if ($result['status'] < 200 || $result['status'] >= 300 || !is_array($data)) {
            return ['ok' => false, 'status' => $result['status'], 'message' => $this->errorMessage($result, 'No se pudo autenticar con Mercado Pago.')];
        }
        if (empty($data['access_token'])) {
            return ['ok' => false, 'status' => $result['status'], 'message' => 'Mercado Pago no devolvió un Access Token.'];
        }
        return ['ok' => true, 'status' => $result['status'], 'data' => $data];
    }

    // ─────────────────────────── OAuth ───────────────────────────

    public function oauthStart(): array
    {
        $this->ensureTables();
        $s = $this->settings();
        if ($s['client_id'] === '' || $s['client_secret'] === '') {
            return ['ok' => false, 'message' => 'Guarda primero el Client ID y el Client Secret.'];
        }
        $verifier = bin2hex(random_bytes(48));
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $state = bin2hex(random_bytes(24));
        $redirect = $this->oauthRedirectUri();
        $expires = date('Y-m-d H:i:s', time() + 900);
        $db = Database::instance();
        $db->prepare("DELETE FROM mercadopago_oauth WHERE site_id = @site_id AND (status <> 'pending' OR expires_at < NOW())")->execute();
        $db->prepare("INSERT INTO mercadopago_oauth (site_id, state, code_verifier, redirect_uri, status, expires_at) VALUES (@site_id, ?, ?, ?, 'pending', ?)")
            ->execute([$state, $verifier, $redirect, $expires]);
        $url = self::AUTH . '/authorization?' . http_build_query([
            'client_id' => $s['client_id'],
            'response_type' => 'code',
            'platform_id' => 'mp',
            'state' => $state,
            'redirect_uri' => $redirect,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]);
        return ['ok' => true, 'url' => $url, 'redirect_uri' => $redirect, 'expires_at' => $expires];
    }

    public function oauthCallback(string $code, string $state): array
    {
        $this->ensureTables();
        if ($code === '' || $state === '') return ['ok' => false, 'message' => 'Respuesta OAuth incompleta.'];
        $db = Database::instance();
        $st = $db->prepare("SELECT * FROM mercadopago_oauth WHERE site_id = @site_id AND state = ? AND status = 'pending' AND expires_at > NOW() LIMIT 1");
        $st->execute([$state]);
        $row = $st->fetch();
        if (!$row) return ['ok' => false, 'message' => 'El estado OAuth no es válido o expiró. Intenta conectar de nuevo.'];
        $s = $this->settings();
        $fields = [
            'grant_type' => 'authorization_code',
            'client_id' => $s['client_id'],
            'client_secret' => $s['client_secret'],
            'code' => $code,
            'redirect_uri' => (string)$row['redirect_uri'],
            'code_verifier' => (string)$row['code_verifier'],
        ];
        $result = $this->oauthToken($fields);
        // Algunas aplicaciones no tienen PKCE habilitado: reintenta sin el verificador.
        if (empty($result['ok']) && !empty($fields['code_verifier'])) {
            unset($fields['code_verifier']);
            $result = $this->oauthToken($fields);
        }
        if (empty($result['ok'])) {
            $db->prepare("UPDATE mercadopago_oauth SET status = 'error' WHERE id = ? AND site_id = @site_id")->execute([(int)$row['id']]);
            return ['ok' => false, 'message' => (string)($result['message'] ?? 'No se pudo completar OAuth.')];
        }
        $this->storeToken((array)$result['data'], 'oauth');
        $db->prepare("UPDATE mercadopago_oauth SET status = 'used' WHERE id = ? AND site_id = @site_id")->execute([(int)$row['id']]);
        $s = $this->settings();
        if ($s['public_key'] === '' && !empty($result['data']['public_key'])) {
            $db->prepare("INSERT INTO settings (site_id, `key`, `value`) VALUES (@site_id, 'mercadopago_public_key', ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")
                ->execute([(string)$result['data']['public_key']]);
            self::$cache = null;
        }
        return ['ok' => true, 'message' => 'Cuenta conectada con Mercado Pago.', 'redirect' => '/admin.php?panel=mercadopago&mp_oauth=ok'];
    }

    public function oauthDisconnect(): array
    {
        $db = Database::instance();
        $stmt = $db->prepare("INSERT INTO settings (site_id, `key`, `value`) VALUES (@site_id, ?, '') ON DUPLICATE KEY UPDATE `value` = ''");
        foreach (['mercadopago_token_cache', 'mercadopago_oauth_refresh', 'mercadopago_oauth_user', 'mercadopago_oauth_at'] as $key) {
            $stmt->execute([$key]);
        }
        self::$cache = null;
        return $this->adminSettings();
    }

    // ─────────────────────────── Diagnóstico ───────────────────────────

    public function testConnection(): array
    {
        $this->ensureTables();
        $s = $this->settings();
        $token = $this->resolveAccessToken(true);
        $me = null;
        $methods = null;
        $warnings = [];
        if (empty($token['ok'])) {
            $warnings[] = (string)($token['message'] ?? 'No hay token disponible.');
        } else {
            $me = $this->apiRequest('GET', '/users/me', null, ['token' => $token['token']]);
            $methods = $this->apiRequest('GET', '/v1/payment_methods?limit=60', null, ['token' => $token['token']]);
        }
        $account = [];
        if (!empty($me['ok']) && is_array($me['data'])) {
            $account = [
                'id' => $me['data']['id'] ?? null,
                'nickname' => $me['data']['nickname'] ?? '',
                'email' => $me['data']['email'] ?? '',
                'site_id' => $me['data']['site_id'] ?? '',
                'country_id' => $me['data']['country_id'] ?? '',
            ];
            if (($account['site_id'] ?? '') !== '' && $account['site_id'] !== 'MCO') {
                $warnings[] = 'La cuenta pertenece al sitio ' . $account['site_id'] . ', no a Colombia (MCO). Verifica que sea la cuenta correcta.';
            }
        } elseif ($me !== null) {
            $warnings[] = 'No se pudo leer /users/me: ' . $this->errorMessage($me, 'token inválido o sin permisos');
        }
        $methodsCount = 0;
        if (!empty($methods['ok']) && is_array($methods['data'])) {
            $methodsCount = count($methods['data']);
        } elseif ($methods !== null) {
            $warnings[] = 'No se pudieron listar los medios de pago: ' . $this->errorMessage($methods, 'sin permisos');
        }
        if ($s['webhook_secret'] === '') {
            $warnings[] = 'Sin clave secreta de webhooks: las notificaciones se registrarán pero no podrán validarse con HMAC.';
        }
        if ($s['public_key'] === '') {
            $warnings[] = 'Falta la Public Key: el Checkout API (tokenización) no funcionará.';
        }
        if (($token['source'] ?? '') === 'client_credentials') {
            $warnings[] = 'Se está usando un token de aplicación (client_credentials). Si Mercado Pago rechaza el pago, agrega el Access Token del vendedor o conecta la cuenta por OAuth.';
        }
        $result = [
            'ok' => !empty($token['ok']) && !empty($me['ok']),
            'checked_at' => date('Y-m-d H:i:s'),
            'environment' => $s['environment'],
            'mode' => $s['mode'],
            'token_source' => $token['source'] ?? 'none',
            'account' => $account,
            'payment_methods' => $methodsCount,
            'webhook_url' => $this->webhookUrl(),
            'webhook_signature' => $s['webhook_secret'] !== '',
            'warnings' => $warnings,
            'error' => empty($me['ok']) && $me !== null ? $this->errorMessage($me, 'No se pudo validar la cuenta.') : '',
        ];
        try {
            $db = Database::instance();
            $stmt = $db->prepare("INSERT INTO settings (site_id, `key`, `value`) VALUES (@site_id, ?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
            $stmt->execute(['mercadopago_last_test', json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
            $stmt->execute(['mercadopago_last_test_at', date('Y-m-d H:i:s')]);
            self::$cache = null;
        } catch (\Throwable $e) {
        }
        return $result;
    }

    public function paymentMethods(): array
    {
        $s = $this->settings();
        $token = $this->resolveAccessToken(false);
        $query = $s['public_key'] !== '' ? '?public_key=' . rawurlencode($s['public_key']) . '&limit=60' : '?limit=60';
        return $this->apiRequest('GET', '/v1/payment_methods' . $query, null, ['token' => $token['token'] ?? '']);
    }

    public function installments(int $amountCents, string $paymentMethodId = ''): array
    {
        $s = $this->settings();
        if ($s['public_key'] === '' && $s['access_token'] === '' && ($s['client_id'] === '' || $s['client_secret'] === '')) {
            return ['ok' => false, 'message' => 'Mercado Pago no está configurado.'];
        }
        $amount = number_format(max(0, $amountCents) / 100, 2, '.', '');
        $query = ['amount' => $amount];
        if ($s['public_key'] !== '') $query['public_key'] = $s['public_key'];
        if ($paymentMethodId !== '') $query['payment_method_id'] = $paymentMethodId;
        $token = $this->resolveAccessToken(false);
        return $this->apiRequest('GET', '/v1/payment_methods/installments?' . http_build_query($query), null, ['token' => $token['token'] ?? '']);
    }

    // ─────────────────────────── Pagos ───────────────────────────

    public function createPreference(array $order): array
    {
        $this->ensureTables();
        $s = $this->settings();
        if (!$this->available($s)) return ['ok' => false, 'message' => 'Mercado Pago no está habilitado o configurado.'];
        $uuid = (string)($order['uuid'] ?? '');
        $number = (string)($order['order_number'] ?? '');
        if ($uuid === '' || $number === '') return ['ok' => false, 'message' => 'Pedido inválido.'];

        $itemNames = [];
        foreach ((array)($order['items'] ?? []) as $item) {
            $name = trim((string)($item['name'] ?? ''));
            if ($name !== '') $itemNames[] = $name;
        }
        $base = $this->baseUrl();
        $back = $base . '/pedido/' . rawurlencode($uuid);
        $payload = [
            'items' => [[
                'id' => $number,
                'title' => 'Pedido ' . $number,
                'description' => mb_substr('SONIKA · ' . implode(', ', array_slice($itemNames, 0, 6)), 0, 250),
                'quantity' => 1,
                'currency_id' => $this->currency(),
                'unit_price' => round(((int)($order['total_cents'] ?? 0)) / 100, 2),
            ]],
            'payer' => array_filter([
                'name' => (string)($order['customer_name'] ?? ''),
                'email' => (string)($order['customer_email'] ?? ''),
                'identification' => $this->identification((string)($order['customer_document'] ?? '')),
            ]),
            'external_reference' => mb_substr($number, 0, 64),
            'notification_url' => $this->webhookUrl(),
            'statement_descriptor' => $s['statement_descriptor'],
            'binary_mode' => $s['binary_mode'],
            'back_urls' => [
                'success' => $back . '?mp=success',
                'failure' => $back . '?mp=failure',
                'pending' => $back . '?mp=pending',
            ],
            'payment_methods' => [
                'installments' => $s['max_installments'],
            ],
            'metadata' => ['order_uuid' => $uuid, 'order_number' => $number, 'site' => 'sonika'],
        ];
        if (str_starts_with($back, 'https://')) $payload['auto_return'] = 'approved';

        $result = $this->apiRequest('POST', '/checkout/preferences', $payload, [
            'idempotency' => 'mp-pref-' . $uuid,
        ]);
        if (empty($result['ok'])) {
            $this->logPayment($order, [
                'mode' => 'checkout_pro',
                'status' => 'error',
                'status_detail' => mb_substr((string)($result['message'] ?? 'error'), 0, 160),
                'raw_create' => (string)($result['raw'] ?? ''),
                'environment' => $s['environment'],
            ]);
            return ['ok' => false, 'message' => (string)($result['message'] ?? 'No se pudo crear la preferencia de Mercado Pago.'), 'status' => $result['status'] ?? 0];
        }
        $data = (array)($result['data'] ?? []);
        $initPoint = (string)($data['init_point'] ?? '');
        $sandboxPoint = (string)($data['sandbox_init_point'] ?? '');
        $redirect = $s['environment'] === 'sandbox' && $sandboxPoint !== '' ? $sandboxPoint : ($initPoint !== '' ? $initPoint : $sandboxPoint);
        $preferenceId = (string)($data['id'] ?? '');
        $this->logPayment($order, [
            'mode' => 'checkout_pro',
            'preference_id' => $preferenceId,
            'status' => 'created',
            'status_detail' => 'waiting_payment',
            'init_point' => $redirect,
            'raw_create' => (string)($result['raw'] ?? ''),
            'environment' => $s['environment'],
        ]);
        return [
            'ok' => true,
            'redirect_url' => $redirect,
            'preference_id' => $preferenceId,
            'init_point' => $initPoint,
            'sandbox_init_point' => $sandboxPoint,
            'status' => 'pending',
        ];
    }

    public function createPayment(array $order, array $input): array
    {
        $this->ensureTables();
        $s = $this->settings();
        if (!$this->available($s)) return ['ok' => false, 'message' => 'Mercado Pago no está habilitado o configurado.'];
        if ($s['mode'] !== 'checkout_api') return ['ok' => false, 'message' => 'El pago transparente no está activo. Cambia la modalidad a Checkout API.'];
        $uuid = (string)($order['uuid'] ?? '');
        $token = trim((string)($input['token'] ?? ''));
        if ($uuid === '' || $token === '') return ['ok' => false, 'message' => 'Faltan datos para procesar el pago (token de tarjeta).'];
        $installments = max(1, min($s['max_installments'], (int)($input['installments'] ?? 1)));
        $amount = round(((int)($order['total_cents'] ?? 0)) / 100, 2);
        if ($amount <= 0) return ['ok' => false, 'message' => 'El monto del pedido no es válido.'];

        $name = trim((string)($order['customer_name'] ?? ''));
        $parts = preg_split('/\s+/', $name, 2) ?: [];
        $document = preg_replace('/\D+/', '', (string)($order['customer_document'] ?? ''));
        $phone = preg_replace('/\D+/', '', (string)($order['customer_phone'] ?? ''));
        $address = (array)($order['shipping_address'] ?? []);
        $payload = [
            'transaction_amount' => $amount,
            'token' => $token,
            'description' => mb_substr('Pedido ' . (string)($order['order_number'] ?? ''), 0, 250),
            'installments' => $installments,
            'external_reference' => mb_substr((string)($order['order_number'] ?? ''), 0, 64),
            'notification_url' => $this->webhookUrl(),
            'statement_descriptor' => $s['statement_descriptor'],
            'binary_mode' => $s['binary_mode'],
            'payer' => array_filter([
                'email' => (string)($order['customer_email'] ?? ''),
                'first_name' => (string)($parts[0] ?? ''),
                'last_name' => (string)($parts[1] ?? ''),
                'identification' => $this->identification($document),
                'phone' => $phone !== '' ? ['area_code' => '57', 'number' => $phone] : null,
                'address' => array_filter([
                    'zip_code' => (string)($address['postal_code'] ?? ''),
                    'street_name' => (string)($address['line1'] ?? ''),
                    'city' => (string)($address['city'] ?? ''),
                ]),
            ]),
            'metadata' => ['order_uuid' => $uuid, 'order_number' => (string)($order['order_number'] ?? ''), 'site' => 'sonika'],
        ];
        $paymentMethodId = trim((string)($input['payment_method_id'] ?? ''));
        if ($paymentMethodId !== '') $payload['payment_method_id'] = $paymentMethodId;
        $issuerId = trim((string)($input['issuer_id'] ?? ''));
        if ($issuerId !== '') $payload['issuer_id'] = $issuerId;

        $result = $this->apiRequest('POST', '/v1/payments', $payload, [
            'idempotency' => 'mp-pay-' . $uuid . '-' . substr(hash('sha256', $token), 0, 24),
        ]);
        if (empty($result['ok'])) {
            $this->logPayment($order, [
                'mode' => 'checkout_api',
                'status' => 'error',
                'status_detail' => mb_substr((string)($result['message'] ?? 'error'), 0, 160),
                'raw_create' => (string)($result['raw'] ?? ''),
                'environment' => $s['environment'],
                'installments' => $installments,
            ]);
            return [
                'ok' => false,
                'message' => (string)($result['message'] ?? 'Mercado Pago rechazó el pago.'),
                'status' => $result['status'] ?? 0,
                'details' => $this->errorDetails($result),
            ];
        }
        $data = (array)($result['data'] ?? []);
        $status = (string)($data['status'] ?? 'pending');
        $mapped = $this->mapStatus($status);
        $paymentId = (string)($data['id'] ?? '');
        $this->logPayment($order, [
            'mode' => 'checkout_api',
            'payment_id' => $paymentId,
            'status' => $mapped,
            'status_detail' => (string)($data['status_detail'] ?? ''),
            'payment_method_id' => (string)($data['payment_method_id'] ?? ''),
            'payment_type_id' => (string)($data['payment_type_id'] ?? ''),
            'installments' => (int)($data['installments'] ?? $installments),
            'payer_email' => (string)($data['payer']['email'] ?? ($order['customer_email'] ?? '')),
            'raw_create' => (string)($result['raw'] ?? ''),
            'raw_last' => (string)($result['raw'] ?? ''),
            'environment' => $s['environment'],
        ]);
        $this->applyStatusToOrder($order, $data, $paymentId);
        return [
            'ok' => true,
            'payment_id' => $paymentId,
            'status' => $mapped,
            'provider_status' => $status,
            'status_detail' => (string)($data['status_detail'] ?? ''),
            'approved' => $mapped === 'paid',
            'message' => $this->statusMessage($mapped, (string)($data['status_detail'] ?? '')),
            'payment_method_id' => (string)($data['payment_method_id'] ?? ''),
            'installments' => (int)($data['installments'] ?? $installments),
        ];
    }

    public function getPayment(string $paymentId): array
    {
        if ($paymentId === '') return ['ok' => false, 'message' => 'Pago vacío.'];
        return $this->apiRequest('GET', '/v1/payments/' . rawurlencode($paymentId));
    }

    public function syncPayment(string $paymentId): array
    {
        $this->ensureTables();
        $fetched = $this->getPayment($paymentId);
        if (empty($fetched['ok']) || !is_array($fetched['data'])) {
            return ['ok' => false, 'message' => (string)($fetched['message'] ?? 'No se pudo consultar el pago en Mercado Pago.')];
        }
        $data = (array)$fetched['data'];
        $mapped = $this->mapStatus((string)($data['status'] ?? ''));
        $ref = (string)($data['external_reference'] ?? '');
        $db = Database::instance();
        $st = $db->prepare("SELECT * FROM mercadopago_payments WHERE site_id = @site_id AND (payment_id = ? OR external_reference = ?) ORDER BY id DESC LIMIT 1");
        $st->execute([$paymentId, $ref]);
        $local = $st->fetch() ?: null;
        $order = null;
        if ($local && !empty($local['order_uuid'])) {
            $order = (new StoreService())->orderByUuid((string)$local['order_uuid']);
        }
        if (!$order && $ref !== '') {
            $st = $db->prepare("SELECT * FROM store_orders WHERE site_id = @site_id AND order_number = ? LIMIT 1");
            $st->execute([$ref]);
            $row = $st->fetch();
            if ($row) $order = (new StoreService())->orderById((int)$row['id']);
        }
        if ($local) {
            $this->updatePaymentRow((int)$local['id'], $data, $mapped, 'sync');
        } elseif ($order) {
            $this->logPayment($order, [
                'mode' => 'checkout_pro',
                'payment_id' => $paymentId,
                'status' => $mapped,
                'status_detail' => (string)($data['status_detail'] ?? ''),
                'payment_method_id' => (string)($data['payment_method_id'] ?? ''),
                'payment_type_id' => (string)($data['payment_type_id'] ?? ''),
                'installments' => (int)($data['installments'] ?? 1),
                'payer_email' => (string)($data['payer']['email'] ?? ''),
                'raw_last' => (string)($fetched['raw'] ?? ''),
            ]);
        }
        if ($order) {
            $this->applyStatusToOrder($order, $data, $paymentId);
        }
        return [
            'ok' => true,
            'status' => $mapped,
            'provider_status' => (string)($data['status'] ?? ''),
            'status_detail' => (string)($data['status_detail'] ?? ''),
            'payment_id' => $paymentId,
            'order_number' => (string)($order['order_number'] ?? $ref),
            'message' => $this->statusMessage($mapped, (string)($data['status_detail'] ?? '')),
        ];
    }

    public function statusForOrder(string $uuid): array
    {
        $this->ensureTables();
        $db = Database::instance();
        $st = $db->prepare("SELECT * FROM mercadopago_payments WHERE site_id = @site_id AND order_uuid = ? ORDER BY id DESC LIMIT 1");
        $st->execute([$uuid]);
        $local = $st->fetch() ?: null;
        if ($local && !empty($local['payment_id']) && (string)($local['status'] ?? '') !== 'paid') {
            $lastSync = strtotime((string)($local['last_synced_at'] ?? '')) ?: 0;
            if (time() - $lastSync >= 8) {
                $synced = $this->syncPayment((string)$local['payment_id']);
                if (!empty($synced['ok'])) {
                    $st->execute([$uuid]);
                    $local = $st->fetch() ?: $local;
                }
            }
        }
        $order = null;
        try {
            $order = (new StoreService())->orderByUuid($uuid);
        } catch (\Throwable $e) {
        }
        return [
            'ok' => (bool)$local || (bool)$order,
            'status' => (string)($local['status'] ?? 'missing'),
            'payment_status' => (string)($order['payment_status'] ?? 'pending'),
            'payment_id' => (string)($local['payment_id'] ?? ''),
            'preference_id' => (string)($local['preference_id'] ?? ''),
            'status_detail' => (string)($local['status_detail'] ?? ''),
            'redirect_url' => (string)($local['init_point'] ?? ($order['payment_link'] ?? '')),
            'mode' => (string)($local['mode'] ?? $this->settings()['mode']),
        ];
    }

    public function refund(string $paymentId, ?int $amountCents = null): array
    {
        $this->ensureTables();
        if ($paymentId === '') return ['ok' => false, 'message' => 'Pago vacío.'];
        $body = [];
        if ($amountCents !== null && $amountCents > 0) $body['amount'] = round($amountCents / 100, 2);
        $result = $this->apiRequest('POST', '/v1/payments/' . rawurlencode($paymentId) . '/refunds', $body, [
            'idempotency' => 'mp-refund-' . $paymentId . '-' . ($amountCents ?? 'full'),
        ]);
        if (empty($result['ok'])) {
            return ['ok' => false, 'message' => (string)($result['message'] ?? 'Mercado Pago no aceptó el reembolso.'), 'status' => $result['status'] ?? 0];
        }
        $synced = $this->syncPayment($paymentId);
        return [
            'ok' => true,
            'message' => $amountCents !== null && $amountCents > 0 ? 'Reembolso parcial solicitado.' : 'Reembolso total solicitado.',
            'refund' => $result['data'] ?? [],
            'status' => $synced['status'] ?? 'refunded',
        ];
    }

    // ─────────────────────────── Webhooks ───────────────────────────

    public function handleWebhook(array $payload, array $headers, string $raw = '', string $queryId = ''): array
    {
        $this->ensureTables();
        $s = $this->settings();
        $dataId = (string)($payload['data']['id'] ?? $payload['data_id'] ?? $queryId);
        $topic = strtolower((string)($payload['type'] ?? $payload['topic'] ?? ''));
        if ($topic === '' && $dataId !== '') $topic = 'payment';
        if ($topic === 'merchant_order') $topic = 'merchant_order';
        $xSignature = $headers['x-signature'] ?? null;
        $xRequestId = $headers['x-request-id'] ?? null;
        $signature = $this->verifySignature($xSignature, $xRequestId, $dataId);
        $eventKey = hash('sha256', $topic . '|' . $dataId . '|' . (string)($payload['action'] ?? '') . '|' . (string)($payload['date_created'] ?? '') . '|' . (string)$xRequestId);
        $db = Database::instance();

        if ($signature['valid'] === false && $s['environment'] === 'production') {
            $this->logWebhook($eventKey, $topic, $dataId, 0, 'invalid_signature', $raw);
            return ['ok' => false, 'status' => 401, 'message' => 'Firma de webhook inválida.'];
        }
        $signatureFlag = $signature['valid'] === null ? null : ($signature['valid'] ? 1 : 0);
        $stmt = $db->prepare("INSERT IGNORE INTO mercadopago_webhooks (site_id, event_key, topic, resource_id, signature_valid, payload) VALUES (@site_id, ?, ?, ?, ?, ?)");
        $stmt->execute([$eventKey, mb_substr($topic, 0, 40), mb_substr($dataId, 0, 120), $signatureFlag, mb_substr($raw, 0, 60000)]);
        if ($stmt->rowCount() === 0) {
            return ['ok' => true, 'status' => 200, 'message' => 'Evento duplicado.', 'duplicate' => true];
        }
        $logId = (int)$db->lastInsertId();

        if (!in_array($topic, ['payment', 'orders', 'merchant_order'], true)) {
            $db->prepare("UPDATE mercadopago_webhooks SET processed = 1, result = 'ignored', processed_at = NOW() WHERE id = ? AND site_id = @site_id")->execute([$logId]);
            return ['ok' => true, 'status' => 200, 'message' => 'Evento ignorado.', 'topic' => $topic];
        }
        if ($dataId === '') {
            $db->prepare("UPDATE mercadopago_webhooks SET processed = 1, result = 'missing_resource', processed_at = NOW() WHERE id = ? AND site_id = @site_id")->execute([$logId]);
            return ['ok' => true, 'status' => 200, 'message' => 'Notificación sin recurso.'];
        }
        if ($topic === 'merchant_order') {
            $moRes = $this->apiRequest('GET', '/merchant_orders/' . rawurlencode($dataId), null, []);
            if (empty($moRes['ok'])) {
                $db->prepare("UPDATE mercadopago_webhooks SET result = 'fetch_failed', processed_at = NOW() WHERE id = ? AND site_id = @site_id")->execute([$logId]);
                return ['ok' => false, 'status' => 500, 'message' => 'No se pudo consultar la orden de compra en Mercado Pago.'];
            }
            $moData = (array)($moRes['data'] ?? []);
            $paymentId = (string)($moData['payments'][0]['id'] ?? '');
            if ($paymentId === '') {
                $db->prepare("UPDATE mercadopago_webhooks SET processed = 1, result = 'order_without_payment', processed_at = NOW() WHERE id = ? AND site_id = @site_id")->execute([$logId]);
                return ['ok' => true, 'status' => 200, 'message' => 'Orden sin pago asociado.'];
            }
            $dataId = $paymentId;
        }
        if ($topic === 'orders') {
            $orderRes = $this->apiRequest('GET', '/v1/orders/' . rawurlencode($dataId), null, []);
            if (empty($orderRes['ok'])) {
                $db->prepare("UPDATE mercadopago_webhooks SET result = 'fetch_failed', processed_at = NOW() WHERE id = ? AND site_id = @site_id")->execute([$logId]);
                return ['ok' => false, 'status' => 500, 'message' => 'No se pudo consultar la orden en Mercado Pago.'];
            }
            $orderData = (array)($orderRes['data'] ?? []);
            $paymentId = (string)($orderData['transactions']['payments'][0]['id'] ?? '');
            if ($paymentId === '') {
                $db->prepare("UPDATE mercadopago_webhooks SET processed = 1, result = 'order_without_payment', processed_at = NOW() WHERE id = ? AND site_id = @site_id")->execute([$logId]);
                return ['ok' => true, 'status' => 200, 'message' => 'Orden sin pago asociado.'];
            }
            $dataId = $paymentId;
        }
        $synced = $this->syncPayment($dataId);
        $result = !empty($synced['ok']) ? (string)($synced['status'] ?? 'synced') : 'sync_failed';
        $db->prepare("UPDATE mercadopago_webhooks SET processed = ?, result = ?, processed_at = NOW() WHERE id = ? AND site_id = @site_id")
            ->execute([!empty($synced['ok']) ? 1 : 0, mb_substr($result, 0, 80), $logId]);
        if (empty($synced['ok'])) {
            return ['ok' => false, 'status' => 500, 'message' => (string)($synced['message'] ?? 'No se pudo sincronizar el pago.')];
        }
        return ['ok' => true, 'status' => 200, 'message' => 'Pago sincronizado.', 'payment_status' => $synced['status']];
    }

    public function verifySignature(?string $xSignature, ?string $xRequestId, string $dataId): array
    {
        $secret = $this->settings()['webhook_secret'];
        if ($secret === '') return ['valid' => null, 'reason' => 'Sin clave secreta configurada.'];
        if (!$xSignature) return ['valid' => false, 'reason' => 'Falta el header x-signature.'];
        $ts = '';
        $v1 = '';
        foreach (explode(',', $xSignature) as $part) {
            $pair = explode('=', trim($part), 2);
            if (count($pair) !== 2) continue;
            if ($pair[0] === 'ts') $ts = $pair[1];
            if ($pair[0] === 'v1') $v1 = $pair[1];
        }
        if ($ts === '' || $v1 === '') return ['valid' => false, 'reason' => 'x-signature incompleto.'];
        $manifest = 'id:' . strtolower($dataId) . ';request-id:' . (string)$xRequestId . ';ts:' . $ts . ';';
        $expected = hash_hmac('sha256', $manifest, $secret);
        return [
            'valid' => hash_equals($expected, strtolower($v1)),
            'reason' => hash_equals($expected, strtolower($v1)) ? 'Firma válida.' : 'La firma no coincide.',
            'manifest' => $manifest,
        ];
    }

    // ─────────────────────────── Panel ───────────────────────────

    public function overview(int $limit = 60): array
    {
        $this->ensureTables();
        $s = $this->settings();
        $db = Database::instance();
        $stats = ['payments' => 0, 'paid' => 0, 'pending' => 0, 'failed' => 0, 'refunded' => 0, 'amount_paid_cents' => 0, 'webhooks' => 0, 'webhooks_invalid' => 0];
        try {
            $st = $db->query("SELECT COUNT(*) AS payments,
                SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) AS paid,
                SUM(CASE WHEN status IN ('created','pending','in_process','authorized') THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status IN ('failed','rejected','cancelled','error') THEN 1 ELSE 0 END) AS failed,
                SUM(CASE WHEN status IN ('refunded','charged_back') THEN 1 ELSE 0 END) AS refunded,
                SUM(CASE WHEN status = 'paid' THEN amount_cents ELSE 0 END) AS amount_paid_cents
                FROM mercadopago_payments WHERE site_id = @site_id");
            foreach (($st->fetch() ?: []) as $k => $v) $stats[$k] = (int)$v;
            $stats['webhooks'] = (int)$db->query("SELECT COUNT(*) FROM mercadopago_webhooks WHERE site_id = @site_id")->fetchColumn();
            $stats['webhooks_invalid'] = (int)$db->query("SELECT COUNT(*) FROM mercadopago_webhooks WHERE site_id = @site_id AND signature_valid = 0")->fetchColumn();
        } catch (\Throwable $e) {
        }
        return [
            'settings' => $this->adminSettings(),
            'stats' => $stats,
            'payments' => $this->payments($limit),
            'webhooks' => $this->webhooks(25),
            'last_test' => $s['last_test'],
            'endpoints' => [
                'api' => self::API,
                'auth' => self::AUTH,
                'webhook' => $this->webhookUrl(),
                'redirect_uri' => $this->oauthRedirectUri(),
                'docs' => self::DOCS,
            ],
        ];
    }

    public function payments(int $limit = 60): array
    {
        $this->ensureTables();
        $limit = max(1, min(200, $limit));
        $sql = "SELECT p.*, o.order_number AS order_number_live, o.payment_status AS order_payment_status, o.fulfillment_status, o.customer_name, o.customer_email
            FROM mercadopago_payments p LEFT JOIN store_orders o ON o.id = p.order_id AND o.site_id = p.site_id
            WHERE p.site_id = @site_id ORDER BY p.id DESC LIMIT $limit";
        return Database::instance()->query($sql)->fetchAll();
    }

    public function payment(int $id): ?array
    {
        $this->ensureTables();
        $st = Database::instance()->prepare("SELECT p.*, o.order_number AS order_number_live, o.payment_status AS order_payment_status, o.fulfillment_status, o.customer_name, o.customer_email, o.total_cents AS order_total_cents
            FROM mercadopago_payments p LEFT JOIN store_orders o ON o.id = p.order_id AND o.site_id = p.site_id
            WHERE p.id = ? AND p.site_id = @site_id LIMIT 1");
        $st->execute([$id]);
        $row = $st->fetch();
        if (!$row) return null;
        $row['webhooks'] = [];
        if (!empty($row['payment_id'])) {
            $h = Database::instance()->prepare("SELECT id, topic, resource_id, signature_valid, result, received_at FROM mercadopago_webhooks WHERE site_id = @site_id AND resource_id = ? ORDER BY id DESC LIMIT 20");
            $h->execute([(string)$row['payment_id']]);
            $row['webhooks'] = $h->fetchAll();
        }
        return $row;
    }

    public function webhooks(int $limit = 25): array
    {
        $this->ensureTables();
        $limit = max(1, min(100, $limit));
        return Database::instance()->query("SELECT id, topic, resource_id, signature_valid, processed, result, received_at, processed_at FROM mercadopago_webhooks WHERE site_id = @site_id ORDER BY id DESC LIMIT $limit")->fetchAll();
    }

    public function webhookUrl(): string
    {
        return $this->baseUrl() . '/api/v1/public/mercadopago/webhook';
    }

    public function oauthRedirectUri(): string
    {
        return $this->baseUrl() . '/api/v1/public/mercadopago/oauth/callback';
    }

    public function currency(): string
    {
        $c = strtoupper((string)Config::get('STORE_CURRENCY', 'COP'));
        return $c !== '' ? $c : 'COP';
    }

    // ─────────────────────────── Internos ───────────────────────────

    private function available(array $s): bool
    {
        return $s['enabled'] && $this->configured($s);
    }

    private function configured(array $s): bool
    {
        if ($s['public_key'] === '') return false;
        return $s['access_token'] !== '' || ($s['client_id'] !== '' && $s['client_secret'] !== '');
    }

    private function health(array $s, array $token): array
    {
        $checks = [];
        $add = function (string $key, string $label, bool $ok, string $detail, string $severity = 'warn') use (&$checks): void {
            $checks[] = ['key' => $key, 'label' => $label, 'ok' => $ok, 'detail' => $detail, 'severity' => $ok ? 'ok' : $severity];
        };
        $add('client_id', 'Client ID', $s['client_id'] !== '', $s['client_id'] !== '' ? 'Guardado (' . $s['client_id'] . ')' : 'Falta el Client ID.', 'error');
        $add('client_secret', 'Client Secret', $s['client_secret'] !== '', $s['client_secret'] !== '' ? 'Cifrado · huella ' . $this->fingerprint($s['client_secret']) : 'Falta el Client Secret.', 'error');
        $add('public_key', 'Public Key', $s['public_key'] !== '', $s['public_key'] !== '' ? mb_substr($s['public_key'], 0, 18) . '…' : 'Falta la Public Key.', 'error');
        $add('token', 'Access Token', !empty($token['available']), $token['label'] . ($token['expires_at'] !== '' ? ' · vence ' . $token['expires_at'] : ''), 'error');
        $add('webhook_secret', 'Webhook firmado', $s['webhook_secret'] !== '', $s['webhook_secret'] !== '' ? 'HMAC activo · huella ' . $this->fingerprint($s['webhook_secret']) : 'Sin clave: las notificaciones no se validan.', 'warn');
        $add('mode', 'Modalidad', true, $s['mode'] === 'checkout_api' ? 'Checkout API (tarjeta en el sitio)' : 'Checkout Pro (redirección)', 'ok');
        if ($s['public_key'] !== '' && !preg_match('/^(APP_USR|TEST)-/i', $s['public_key'])) {
            $add('public_key_format', 'Formato Public Key', false, 'No empieza por APP_USR-. Verifica que copiaste la clave completa.', 'warn');
        }
        return $checks;
    }

    private function applyStatusToOrder(array $order, array $data, string $paymentId): void
    {
        $mapped = $this->mapStatus((string)($data['status'] ?? ''));
        $uuid = (string)($order['uuid'] ?? '');
        if ($uuid === '') return;
        try {
            if ($mapped === 'paid') {
                (new StoreService())->markPaid($uuid, $paymentId !== '' ? $paymentId : 'mercadopago', 'mercadopago');
            } elseif ($mapped === 'failed' || $mapped === 'refunded') {
                $db = Database::instance();
                $db->prepare("UPDATE store_orders SET payment_status = ?, updated_at = NOW() WHERE uuid = ? AND site_id = @site_id AND payment_status <> 'paid'")
                    ->execute([$mapped === 'refunded' ? 'refunded' : 'failed', $uuid]);
                if ($mapped === 'refunded') {
                    $db->prepare("UPDATE store_orders SET payment_status = 'refunded', updated_at = NOW() WHERE uuid = ? AND site_id = @site_id")
                        ->execute([$uuid]);
                }
            }
            $meta = json_decode((string)($order['payment_meta'] ?? ''), true) ?: [];
            $meta['mercadopago_status'] = $mapped;
            $meta['mercadopago_provider_status'] = (string)($data['status'] ?? '');
            $meta['mercadopago_status_detail'] = (string)($data['status_detail'] ?? '');
            $meta['mercadopago_payment_id'] = $paymentId;
            $meta['mercadopago_payment_method_id'] = (string)($data['payment_method_id'] ?? '');
            $meta['mercadopago_installments'] = (int)($data['installments'] ?? 1);
            $meta['mercadopago_mode'] = (string)($this->settings()['mode']);
            Database::instance()->prepare("UPDATE store_orders SET payment_meta = ?, updated_at = NOW() WHERE id = ? AND site_id = @site_id")
                ->execute([json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), (int)$order['id']]);
        } catch (\Throwable $e) {
        }
    }

    private function logPayment(array $order, array $data): void
    {
        try {
            $this->ensureTables();
            $s = $this->settings();
            $db = Database::instance();
            $values = [
                'environment' => (string)($data['environment'] ?? $s['environment']),
                'mode' => (string)($data['mode'] ?? $s['mode']),
                'preference_id' => $data['preference_id'] ?? null,
                'payment_id' => $data['payment_id'] ?? null,
                'status' => mb_substr((string)($data['status'] ?? 'created'), 0, 40),
                'status_detail' => mb_substr((string)($data['status_detail'] ?? ''), 0, 160),
                'payment_method_id' => mb_substr((string)($data['payment_method_id'] ?? ''), 0, 60),
                'payment_type_id' => mb_substr((string)($data['payment_type_id'] ?? ''), 0, 40),
                'installments' => max(1, (int)($data['installments'] ?? 1)),
                'amount_cents' => (int)($order['total_cents'] ?? 0),
                'currency' => (string)($order['currency'] ?? 'COP'),
                'payer_email' => mb_substr((string)($data['payer_email'] ?? ($order['customer_email'] ?? '')), 0, 190),
                'external_reference' => mb_substr((string)($order['order_number'] ?? ''), 0, 120),
                'init_point' => $data['init_point'] ?? null,
                'raw_create' => $data['raw_create'] ?? null,
                'raw_last' => $data['raw_last'] ?? null,
            ];
            $sql = "INSERT INTO mercadopago_payments (site_id, order_id, order_uuid, order_number, environment, mode, preference_id, payment_id, status, status_detail, payment_method_id, payment_type_id, installments, amount_cents, currency, payer_email, external_reference, init_point, raw_create, raw_last, last_synced_at)
                VALUES (@site_id, :order_id, :order_uuid, :order_number, :environment, :mode, :preference_id, :payment_id, :status, :status_detail, :payment_method_id, :payment_type_id, :installments, :amount_cents, :currency, :payer_email, :external_reference, :init_point, :raw_create, :raw_last, NOW())
                ON DUPLICATE KEY UPDATE
                    preference_id = COALESCE(VALUES(preference_id), preference_id),
                    payment_id = COALESCE(VALUES(payment_id), payment_id),
                    status = VALUES(status),
                    status_detail = VALUES(status_detail),
                    payment_method_id = COALESCE(VALUES(payment_method_id), payment_method_id),
                    payment_type_id = COALESCE(VALUES(payment_type_id), payment_type_id),
                    installments = VALUES(installments),
                    amount_cents = VALUES(amount_cents),
                    payer_email = COALESCE(VALUES(payer_email), payer_email),
                    init_point = COALESCE(VALUES(init_point), init_point),
                    raw_create = COALESCE(VALUES(raw_create), raw_create),
                    raw_last = COALESCE(VALUES(raw_last), raw_last),
                    last_synced_at = NOW()";
            $params = [
                'order_id' => (int)($order['id'] ?? 0) ?: null,
                'order_uuid' => (string)($order['uuid'] ?? ''),
                'order_number' => (string)($order['order_number'] ?? ''),
                'environment' => $values['environment'],
                'mode' => $values['mode'],
                'preference_id' => $values['preference_id'],
                'payment_id' => $values['payment_id'],
                'status' => $values['status'],
                'status_detail' => $values['status_detail'],
                'payment_method_id' => $values['payment_method_id'],
                'payment_type_id' => $values['payment_type_id'],
                'installments' => $values['installments'],
                'amount_cents' => $values['amount_cents'],
                'currency' => $values['currency'],
                'payer_email' => $values['payer_email'],
                'external_reference' => $values['external_reference'],
                'init_point' => $values['init_point'],
                'raw_create' => $values['raw_create'],
                'raw_last' => $values['raw_last'],
            ];
            $db->prepare($sql)->execute($params);
        } catch (\Throwable $e) {
        }
    }

    private function updatePaymentRow(int $id, array $data, string $mapped, string $source): void
    {
        try {
            $raw = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            Database::instance()->prepare("UPDATE mercadopago_payments SET payment_id = COALESCE(?, payment_id), status = ?, status_detail = ?, payment_method_id = COALESCE(?, payment_method_id), payment_type_id = COALESCE(?, payment_type_id), installments = ?, payer_email = COALESCE(?, payer_email), raw_last = ?, last_synced_at = NOW(), updated_at = NOW() WHERE id = ? AND site_id = @site_id")
                ->execute([
                    (string)($data['id'] ?? '') ?: null,
                    $mapped,
                    mb_substr((string)($data['status_detail'] ?? ''), 0, 160),
                    (string)($data['payment_method_id'] ?? '') ?: null,
                    (string)($data['payment_type_id'] ?? '') ?: null,
                    max(1, (int)($data['installments'] ?? 1)),
                    (string)($data['payer']['email'] ?? '') ?: null,
                    mb_substr($raw, 0, 60000),
                    $id,
                ]);
        } catch (\Throwable $e) {
        }
    }

    private function logWebhook(string $eventKey, string $topic, string $resourceId, ?int $signatureValid, string $result, string $raw): void
    {
        try {
            Database::instance()->prepare("INSERT IGNORE INTO mercadopago_webhooks (site_id, event_key, topic, resource_id, signature_valid, result, payload, processed, processed_at) VALUES (@site_id, ?, ?, ?, ?, ?, ?, 1, NOW())")
                ->execute([$eventKey, mb_substr($topic, 0, 40), mb_substr($resourceId, 0, 120), $signatureValid, mb_substr($result, 0, 80), mb_substr($raw, 0, 60000)]);
        } catch (\Throwable $e) {
        }
    }

    private function mapStatus(string $status): string
    {
        return match (strtolower($status)) {
            'approved' => 'paid',
            'refunded', 'charged_back' => 'refunded',
            'rejected', 'cancelled', 'canceled', 'failed' => 'failed',
            'in_process', 'pending', 'authorized' => 'pending',
            'created' => 'created',
            default => 'pending',
        };
    }

    private function statusMessage(string $mapped, string $detail): string
    {
        $detail = str_replace('_', ' ', trim($detail));
        if ($mapped === 'paid') return 'Pago aprobado.';
        if ($mapped === 'failed') return 'El pago fue rechazado' . ($detail !== '' ? ': ' . $detail : '.');
        if ($mapped === 'refunded') return 'El pago fue reembolsado.';
        return 'Pago pendiente' . ($detail !== '' ? ': ' . $detail : '.');
    }

    private function identification(string $document): ?array
    {
        $document = preg_replace('/\D+/', '', $document);
        if ($document === '') return null;
        return ['type' => 'CC', 'number' => $document];
    }

    private function fingerprint(string $secret): string
    {
        if ($secret === '') return '';
        return substr(hash('sha256', $secret), 0, 12);
    }

    private function baseUrl(): string
    {
        $public = trim((string)Config::get('PUBLIC_URL', ''));
        if ($public !== '' && preg_match('~^https?://~i', $public)) return rtrim($public, '/');
        try {
            $row = Database::instance()->query('SELECT name, domain FROM sites WHERE id = @site_id LIMIT 1')->fetch();
            if (!is_array($row)) $row = [];
            foreach (['domain', 'name'] as $key) {
                $candidate = preg_replace('~^https?://~i', '', trim((string)($row[$key] ?? '')));
                $candidate = rtrim((string)$candidate, '/');
                if ($candidate !== '' && preg_match('/^[a-z0-9.-]+\.[a-z]{2,}(:\d+)?$/i', $candidate)) {
                    return 'https://' . $candidate;
                }
            }
        } catch (\Throwable $e) {
        }
        $host = preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? ''));
        if ($host !== '' && preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $host)
            && !preg_match('/(^|\.)(wontia\.com|intsolcom\.com|localhost|127\.0\.0\.1)$/i', $host)) {
            return 'https://' . $host;
        }
        $url = rtrim((string)Config::get('APP_URL', 'https://sonika.com.co'), '/');
        return $url !== '' ? $url : 'https://sonika.com.co';
    }

    private function cleanUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') return '';
        return preg_match('~^https?://~i', $value) ? mb_substr($value, 0, 900) : '';
    }

    private function errorMessage(array $result, string $fallback = 'Mercado Pago respondió con un error.'): string
    {
        $body = json_decode((string)($result['body'] ?? ''), true);
        if (is_array($body)) {
            if (!empty($body['message'])) return (string)$body['message'];
            if (!empty($body['error_description'])) return (string)$body['error_description'];
            if (!empty($body['error'])) return (string)$body['error'];
            $causes = $body['cause'] ?? [];
            if (is_array($causes) && !empty($causes[0]['description'])) return (string)$causes[0]['description'];
        }
        if (!empty($result['error'])) return (string)$result['error'];
        return $fallback . ' (HTTP ' . (int)($result['status'] ?? 0) . ')';
    }

    private function errorDetails(array $result): array
    {
        $body = json_decode((string)($result['body'] ?? ''), true);
        if (!is_array($body)) return [];
        $out = [];
        foreach ((array)($body['cause'] ?? []) as $cause) {
            if (is_array($cause) && !empty($cause['description'])) $out[] = (string)$cause['description'];
            elseif (is_string($cause)) $out[] = $cause;
        }
        return $out;
    }

    private function http(string $url, array $options = []): array
    {
        if (!function_exists('curl_init')) return ['status' => 0, 'body' => '', 'headers' => [], 'error' => 'La extensión cURL de PHP no está disponible.'];
        $headers = [];
        $ch = curl_init($url);
        $method = strtoupper((string)($options['method'] ?? 'GET'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(['User-Agent: SONIKA-WWI-MercadoPago/1.0'], (array)($options['headers'] ?? [])));
        if ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if (array_key_exists('body', $options) && $options['body'] !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, (string)$options['body']);
        }
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $line) use (&$headers) {
            $length = strlen($line);
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
            return $length;
        });
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = $body === false ? (string)curl_error($ch) : '';
        curl_close($ch);
        return ['status' => $status, 'body' => $body === false ? '' : (string)$body, 'headers' => $headers, 'error' => $error];
    }

    private function apiRequest(string $method, string $path, ?array $body = null, array $options = []): array
    {
        $token = (string)($options['token'] ?? '');
        $source = 'manual';
        if ($token === '') {
            $resolved = $this->resolveAccessToken(false);
            if (empty($resolved['ok'])) return ['ok' => false, 'status' => 0, 'message' => (string)$resolved['message'], 'data' => null, 'raw' => ''];
            $token = (string)$resolved['token'];
            $source = (string)($resolved['source'] ?? '');
        }
        $headers = [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
        ];
        $payload = null;
        if ($body !== null) {
            $payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $headers[] = 'Content-Type: application/json';
        }
        if (!empty($options['idempotency'])) $headers[] = 'X-Idempotency-Key: ' . $options['idempotency'];
        $result = $this->http(self::API . $path, ['method' => $method, 'body' => $payload, 'headers' => $headers]);
        $data = json_decode((string)$result['body'], true);
        if (($result['status'] === 401 || $result['status'] === 403) && $source !== 'manual' && empty($options['retried'])) {
            $options['retried'] = true;
            $options['token'] = '';
            $forced = $this->resolveAccessToken(true);
            if (!empty($forced['ok'])) {
                $options['token'] = (string)$forced['token'];
                return $this->apiRequest($method, $path, $body, $options);
            }
        }
        $ok = $result['status'] >= 200 && $result['status'] < 300;
        return [
            'ok' => $ok,
            'status' => $result['status'],
            'data' => is_array($data) ? $data : null,
            'raw' => (string)$result['body'],
            'message' => $ok ? '' : $this->errorMessage($result),
            'error' => (string)($result['error'] ?? ''),
        ];
    }
}
