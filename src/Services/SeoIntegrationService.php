<?php
namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Services\TwoFactorService;

class SeoIntegrationService
{
    private function setting(string $key, string $default = ''): string
    {
        try {
            $stmt = Database::instance()->prepare('SELECT `value` FROM settings WHERE `key` = ? AND site_id = @site_id LIMIT 1');
            $stmt->execute([$key]);
            $row = $stmt->fetch();
            return $row ? (string)$row['value'] : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    private function setSetting(string $key, string $value): void
    {
        $stmt = Database::instance()->prepare("INSERT INTO settings (site_id, `key`, `value`) VALUES (@site_id, ?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
        $stmt->execute([$key, $value]);
    }

    public function siteUrl(): string
    {
        // URL canónica configurada: se usa también en CLI/cron, donde no hay
        // HTTP_HOST ni dominio de sitio en la base de datos.
        $configured = trim($this->setting('seo_site_url'));
        if ($configured !== '' && preg_match('~^https?://~i', $configured)) {
            return rtrim($configured, '/');
        }
        $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
        if ($host !== '' && preg_match('/^[a-z0-9.-]+(?::\d+)?$/i', $host)) {
            $scheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')) ? 'https' : 'http';
            return $scheme . '://' . $host;
        }
        try {
            $domain = (string)Database::instance()->query('SELECT domain FROM sites WHERE id = @site_id')->fetchColumn();
            if ($domain !== '') return 'https://' . rtrim($domain, '/');
        } catch (\Throwable $e) {
        }
        return rtrim((string)Config::get('APP_URL', 'https://wontia.com'), '/');
    }

    public function publicConfig(): array
    {
        $indexKey = $this->setting('seo_indexnow_key');
        return [
            'site_url' => $this->siteUrl(),
            'indexnow' => [
                'configured' => $indexKey !== '',
                'key' => $indexKey,
                'key_location' => $indexKey !== '' ? $this->siteUrl() . '/' . $indexKey . '.txt' : '',
            ],
            'pagespeed' => [
                'configured' => $this->setting('seo_pagespeed_key') !== '',
                'masked' => $this->mask($this->decrypt($this->setting('seo_pagespeed_key'))),
            ],
            'gsc' => [
                'configured' => $this->setting('seo_gsc_service_json') !== '',
                'property' => $this->setting('seo_gsc_property'),
                'email' => $this->gscAccountEmail(),
                'masked' => $this->mask($this->decrypt($this->setting('seo_gsc_service_json'))),
            ],
            'bing' => [
                'configured' => $this->setting('seo_bing_key') !== '',
                'masked' => $this->mask($this->decrypt($this->setting('seo_bing_key'))),
            ],
            'last_saved_at' => $this->setting('seo_integrations_saved_at'),
        ];
    }

    public function save(array $in): array
    {
        $indexKey = strtolower(trim((string)($in['indexnow_key'] ?? '')));
        if ($indexKey === '' && $this->setting('seo_indexnow_key') === '') $indexKey = bin2hex(random_bytes(16));
        if ($indexKey !== '' && !preg_match('/^[a-z0-9-]{8,128}$/', $indexKey)) {
            return ['ok' => false, 'message' => 'La clave IndexNow debe tener entre 8 y 128 caracteres alfanuméricos o guiones.'];
        }
        if ($indexKey !== '') $this->setSetting('seo_indexnow_key', $indexKey);

        $siteUrl = trim((string)($in['site_url'] ?? ''));
        if ($siteUrl !== '' && !preg_match('~^https?://~i', $siteUrl)) {
            return ['ok' => false, 'message' => 'La URL canónica del sitio debe empezar por http:// o https://.'];
        }
        if ($siteUrl !== '') $this->setSetting('seo_site_url', rtrim($siteUrl, '/'));

        $secretFields = [
            'pagespeed_key' => 'seo_pagespeed_key',
            'gsc_service_json' => 'seo_gsc_service_json',
            'bing_key' => 'seo_bing_key',
        ];
        foreach ($secretFields as $field => $setting) {
            $value = trim((string)($in[$field] ?? ''));
            if ($value === '') continue;
            if ($field === 'gsc_service_json' && !$this->validServiceJson($value)) {
                return ['ok' => false, 'message' => 'El JSON de la cuenta de servicio de Google no es válido.'];
            }
            $this->setSetting($setting, $this->encrypt($value));
        }

        $property = trim((string)($in['gsc_property'] ?? ''));
        if ($property !== '' && !preg_match('~^(https?://|sc-domain:)~i', $property)) {
            return ['ok' => false, 'message' => 'La propiedad de Search Console debe ser una URL completa o sc-domain:dominio.'];
        }
        if ($property !== '') $this->setSetting('seo_gsc_property', $property);

        $this->setSetting('seo_integrations_saved_at', date('c'));
        return ['ok' => true, 'message' => 'Integraciones guardadas.', 'data' => $this->publicConfig()];
    }

    public function indexNow(): array
    {
        $key = $this->setting('seo_indexnow_key');
        if ($key === '') return ['ok' => false, 'status' => 'skipped', 'message' => 'IndexNow no está configurado.'];
        $base = $this->siteUrl();
        $host = (string)parse_url($base, PHP_URL_HOST);
        $urls = [];
        try {
            $rows = Database::instance()->query("SELECT page_url FROM seo_pages WHERE site_id = @site_id AND is_active = 1 ORDER BY updated_at DESC LIMIT 500")->fetchAll();
            foreach ($rows as $row) {
                $url = trim((string)$row['page_url']);
                if ($url !== '' && str_starts_with($url, $base)) $urls[$url] = true;
            }
        } catch (\Throwable $e) {
        }
        if (!$urls) $urls[$base . '/'] = true;
        $payload = [
            'host' => $host,
            'key' => $key,
            'keyLocation' => $base . '/' . $key . '.txt',
            'urlList' => array_values(array_keys($urls)),
        ];
        $result = $this->request('POST', 'https://api.indexnow.org/indexnow', ['Content-Type: application/json'], json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        if ($result['error']) return ['ok' => false, 'status' => 'error', 'message' => $result['error']];
        $ok = $result['http'] >= 200 && $result['http'] < 300;
        return [
            'ok' => $ok,
            'status' => $ok ? 'ok' : 'error',
            'http' => $result['http'],
            'submitted' => count($payload['urlList']),
            'message' => $ok ? 'IndexNow recibió ' . count($payload['urlList']) . ' URLs.' : ('IndexNow respondió HTTP ' . $result['http'] . '.'),
        ];
    }

    public function pageSpeed(string $strategy = 'mobile'): array
    {
        $key = $this->decrypt($this->setting('seo_pagespeed_key'));
        if ($key === '') return ['ok' => false, 'status' => 'skipped', 'message' => 'PageSpeed no está configurado.'];
        $strategy = $strategy === 'desktop' ? 'desktop' : 'mobile';
        $url = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url=' . rawurlencode($this->siteUrl() . '/') . '&strategy=' . $strategy . '&key=' . rawurlencode($key);
        $result = $this->request('GET', $url, [], null, 90);
        if ($result['error']) return ['ok' => false, 'status' => 'error', 'message' => $result['error']];
        $data = json_decode($result['body'], true);
        if ($result['http'] !== 200 || !is_array($data)) {
            $message = (string)($data['error']['message'] ?? ('PageSpeed respondió HTTP ' . $result['http']));
            return ['ok' => false, 'status' => 'error', 'http' => $result['http'], 'message' => $message];
        }
        $cats = $data['lighthouseResult']['categories'] ?? [];
        $score = function (string $key) use ($cats): ?int {
            return isset($cats[$key]['score']) ? (int)round($cats[$key]['score'] * 100) : null;
        };
        return [
            'ok' => true,
            'status' => 'ok',
            'strategy' => $strategy,
            'performance' => $score('performance'),
            'seo' => $score('seo'),
            'accessibility' => $score('accessibility'),
            'best_practices' => $score('best-practices'),
            'fetched_at' => date('c'),
        ];
    }

    public function gscTest(): array
    {
        $credentials = $this->gscCredentials();
        if (!$credentials) return ['ok' => false, 'status' => 'skipped', 'message' => 'Google Search Console no está configurado.'];
        $token = $this->gscToken($credentials);
        if (!$token) return ['ok' => false, 'status' => 'error', 'message' => 'No se pudo obtener el token de Google.'];
        $result = $this->request('GET', 'https://www.googleapis.com/webmasters/v3/sites', ['Authorization: Bearer ' . $token]);
        if ($result['error']) return ['ok' => false, 'status' => 'error', 'message' => $result['error']];
        $data = json_decode($result['body'], true);
        if ($result['http'] !== 200) {
            return ['ok' => false, 'status' => 'error', 'http' => $result['http'], 'message' => (string)($data['error']['message'] ?? 'Google respondió HTTP ' . $result['http'])];
        }
        return ['ok' => true, 'status' => 'ok', 'sites' => array_map(function ($s) { return (string)($s['siteUrl'] ?? ''); }, $data['siteEntry'] ?? []), 'message' => 'Cuenta de servicio autenticada.'];
    }

    public function gscSubmitSitemap(): array
    {
        $credentials = $this->gscCredentials();
        $property = $this->setting('seo_gsc_property');
        if (!$credentials || $property === '') return ['ok' => false, 'status' => 'skipped', 'message' => 'Configura la cuenta de servicio y la propiedad de Search Console.'];
        $token = $this->gscToken($credentials);
        if (!$token) return ['ok' => false, 'status' => 'error', 'message' => 'No se pudo obtener el token de Google.'];
        $sitemap = $this->siteUrl() . '/sitemap.xml';
        $url = 'https://www.googleapis.com/webmasters/v3/sites/' . rawurlencode($property) . '/sitemaps/' . rawurlencode($sitemap);
        $result = $this->request('PUT', $url, ['Authorization: Bearer ' . $token, 'Content-Length: 0']);
        if ($result['error']) return ['ok' => false, 'status' => 'error', 'message' => $result['error']];
        $ok = $result['http'] >= 200 && $result['http'] < 300;
        return ['ok' => $ok, 'status' => $ok ? 'ok' : 'error', 'http' => $result['http'], 'sitemap' => $sitemap, 'message' => $ok ? 'Sitemap enviado a Search Console.' : ('Google respondió HTTP ' . $result['http'] . '.')];
    }

    public function bingTest(): array
    {
        $key = $this->decrypt($this->setting('seo_bing_key'));
        if ($key === '') return ['ok' => false, 'status' => 'skipped', 'message' => 'Bing Webmaster no está configurado.'];
        $result = $this->request('GET', 'https://ssl.bing.com/webmaster/api.svc/json/GetUserSites?apikey=' . rawurlencode($key));
        if ($result['error']) return ['ok' => false, 'status' => 'error', 'message' => $result['error']];
        $data = json_decode($result['body'], true);
        if ($result['http'] !== 200) return ['ok' => false, 'status' => 'error', 'http' => $result['http'], 'message' => (string)($data['Message'] ?? ('Bing respondió HTTP ' . $result['http']))];
        return ['ok' => true, 'status' => 'ok', 'sites' => array_map(function ($s) { return (string)($s['Url'] ?? ''); }, $data['d'] ?? []), 'message' => 'Clave de Bing válida.'];
    }

    public function bingSubmitSitemap(): array
    {
        $key = $this->decrypt($this->setting('seo_bing_key'));
        if ($key === '') return ['ok' => false, 'status' => 'skipped', 'message' => 'Bing Webmaster no está configurado.'];
        $site = rtrim($this->siteUrl(), '/') . '/';
        $sitemap = $this->siteUrl() . '/sitemap.xml';
        $result = $this->request('POST', 'https://ssl.bing.com/webmaster/api.svc/json/SubmitSitemap?apikey=' . rawurlencode($key), ['Content-Type: application/json'], json_encode(['siteUrl' => $site, 'feedUrl' => $sitemap]));
        if ($result['error']) return ['ok' => false, 'status' => 'error', 'message' => $result['error']];
        $ok = $result['http'] >= 200 && $result['http'] < 300;
        return ['ok' => $ok, 'status' => $ok ? 'ok' : 'error', 'http' => $result['http'], 'message' => $ok ? 'Sitemap enviado a Bing.' : ('Bing respondió HTTP ' . $result['http'] . '.')];
    }

    public function indexNowKey(): string
    {
        return $this->setting('seo_indexnow_key');
    }

    /** Correo de la cuenta de servicio, para autorizarla en Search Console. */
    public function gscAccountEmail(): string
    {
        $credentials = $this->gscCredentials();
        return $credentials ? (string)$credentials['client_email'] : '';
    }

    private function gscCredentials(): ?array
    {
        $raw = $this->decrypt($this->setting('seo_gsc_service_json'));
        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['client_email']) || empty($data['private_key'])) return null;
        return $data;
    }

    private function gscToken(array $credentials): ?string
    {
        $now = time();
        $header = $this->b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claims = $this->b64url(json_encode([
            'iss' => (string)$credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/webmasters',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));
        $signature = '';
        if (!openssl_sign($header . '.' . $claims, $signature, (string)$credentials['private_key'], OPENSSL_ALGO_SHA256)) return null;
        $jwt = $header . '.' . $claims . '.' . $this->b64url($signature);
        $result = $this->request('POST', 'https://oauth2.googleapis.com/token', ['Content-Type: application/x-www-form-urlencoded'], http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]));
        $data = json_decode($result['body'], true);
        return is_array($data) && !empty($data['access_token']) ? (string)$data['access_token'] : null;
    }

    private function request(string $method, string $url, array $headers = [], ?string $body = null, int $timeout = 45): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_HTTPHEADER => array_merge(['User-Agent: Wontia-SEO-Integrations/1.0'], $headers),
        ]);
        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        $response = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        return ['http' => $http, 'body' => (string)$response, 'error' => $error !== '' ? $error : null];
    }

    private function validServiceJson(string $raw): bool
    {
        $data = json_decode($raw, true);
        return is_array($data) && !empty($data['client_email']) && !empty($data['private_key']);
    }

    private function encrypt(string $value): string
    {
        return class_exists(TwoFactorService::class) ? TwoFactorService::encrypt($value) : 'plain:' . base64_encode($value);
    }

    private function decrypt(string $value): string
    {
        if ($value === '') return '';
        if (class_exists(TwoFactorService::class)) return TwoFactorService::decrypt($value);
        return str_starts_with($value, 'plain:') ? (string)base64_decode(substr($value, 6)) : '';
    }

    private function mask(string $value): string
    {
        if ($value === '') return '';
        return strlen($value) <= 8 ? '••••' : ('••••' . substr($value, -4));
    }

    private function b64url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
