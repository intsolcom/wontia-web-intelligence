<?php
namespace App\Services;

use App\Core\Config;
use App\Core\Database;

/**
 * YoutubeService — conexión OAuth y subida de videos a la cuenta SONIKA.
 *
 * Sin client_id/client_secret/refresh_token no se puede subir: el laboratorio
 * deja el video en sandbox y muestra el botón "Conectar YouTube".
 */
class YoutubeService
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const UPLOAD_URL = 'https://www.googleapis.com/upload/youtube/v3/videos';
    private const CHANNEL_URL = 'https://www.googleapis.com/youtube/v3/channels';
    private const SCOPES = 'https://www.googleapis.com/auth/youtube.upload https://www.googleapis.com/auth/youtube.readonly';

    public function status(): array
    {
        $connected = $this->setting('youtube_refresh_token_enc') !== '';
        return [
            'ok' => true,
            'client_configured' => $this->setting('youtube_client_id') !== '' && $this->clientSecret() !== '',
            'client_id' => $this->setting('youtube_client_id'),
            'connected' => $connected,
            'channel_id' => $this->setting('youtube_channel_id'),
            'channel_title' => $this->setting('youtube_channel_title'),
            'connected_at' => $this->setting('youtube_connected_at'),
            'redirect_uri' => $this->redirectUri(),
            'scopes' => self::SCOPES,
        ];
    }

    public function saveCredentials(string $clientId, string $clientSecret): array
    {
        $clientId = trim($clientId);
        $clientSecret = trim($clientSecret);
        if ($clientId === '' || $clientSecret === '') return ['ok' => false, 'message' => 'Client ID y Client Secret son obligatorios.'];
        $this->setSetting('youtube_client_id', $clientId);
        $this->setSetting('youtube_client_secret_enc', TwoFactorService::encrypt($clientSecret));
        return ['ok' => true, 'message' => 'Credenciales guardadas. Ahora pulsa Conectar YouTube para autorizar el canal.'];
    }

    public function authUrl(): array
    {
        $clientId = $this->setting('youtube_client_id');
        $secret = $this->clientSecret();
        if ($clientId === '' || $secret === '') return ['ok' => false, 'message' => 'Primero guarda el Client ID y Client Secret de Google.'];
        $state = bin2hex(random_bytes(20));
        $this->setSetting('youtube_oauth_state', $state);
        $params = [
            'client_id' => $clientId,
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => self::SCOPES,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
            'include_granted_scopes' => 'true',
        ];
        return ['ok' => true, 'url' => self::AUTH_URL . '?' . http_build_query($params), 'redirect_uri' => $this->redirectUri()];
    }

    public function handleCallback(string $code, string $state): array
    {
        $expected = $this->setting('youtube_oauth_state');
        if ($expected === '' || !hash_equals($expected, $state)) return ['ok' => false, 'message' => 'Estado OAuth inválido. Inicia la conexión de nuevo.'];
        $clientId = $this->setting('youtube_client_id');
        $secret = $this->clientSecret();
        if ($clientId === '' || $secret === '') return ['ok' => false, 'message' => 'Faltan credenciales de Google.'];
        $res = $this->postForm(self::TOKEN_URL, [
            'code' => $code,
            'client_id' => $clientId,
            'client_secret' => $secret,
            'redirect_uri' => $this->redirectUri(),
            'grant_type' => 'authorization_code',
        ]);
        $data = json_decode((string)$res['body'], true);
        if (!is_array($data) || empty($data['access_token'])) {
            return ['ok' => false, 'message' => 'Google rechazó la autorización: ' . (string)($data['error_description'] ?? $data['error'] ?? 'sin detalle')];
        }
        if (!empty($data['refresh_token'])) $this->setSetting('youtube_refresh_token_enc', TwoFactorService::encrypt((string)$data['refresh_token']));
        $this->setSetting('youtube_access_token', (string)$data['access_token']);
        $this->setSetting('youtube_access_expires', (string)(time() + max(60, (int)($data['expires_in'] ?? 3600))));
        $channel = $this->fetchChannel((string)$data['access_token']);
        if (!empty($channel['id'])) {
            $this->setSetting('youtube_channel_id', (string)$channel['id']);
            $this->setSetting('youtube_channel_title', (string)($channel['snippet']['title'] ?? ''));
        }
        $this->setSetting('youtube_connected_at', date('c'));
        $this->setSetting('youtube_oauth_state', '');
        return ['ok' => true, 'message' => 'Canal de YouTube conectado.', 'channel' => $channel];
    }

    public function disconnect(): array
    {
        foreach (['youtube_refresh_token_enc','youtube_access_token','youtube_access_expires','youtube_channel_id','youtube_channel_title','youtube_connected_at'] as $k) $this->setSetting($k, '');
        return ['ok' => true, 'message' => 'Conexión de YouTube eliminada.'];
    }

    public function upload(string $filePath, string $title, string $description, array $tags = [], string $privacy = 'public'): array
    {
        if (!is_file($filePath)) return ['ok' => false, 'message' => 'El archivo de video no existe.'];
        $token = $this->accessToken(true);
        if ($token === '') return ['ok' => false, 'message' => 'YouTube no está conectado. Configura las credenciales y autoriza el canal.'];
        $size = (int)filesize($filePath);
        if ($size <= 0 || $size > 536870912) return ['ok' => false, 'message' => 'El video debe pesar entre 1 byte y 500 MB.'];
        $meta = [
            'snippet' => [
                'title' => mb_substr($title, 0, 100),
                'description' => mb_substr($description, 0, 4900),
                'tags' => array_values(array_slice(array_filter(array_map('strval', $tags)), 0, 20)),
                'categoryId' => '10',
            ],
            'status' => [
                'privacyStatus' => in_array($privacy, ['public','unlisted','private'], true) ? $privacy : 'public',
                'selfDeclaredMadeForKids' => false,
            ],
        ];
        $init = curl_init(self::UPLOAD_URL . '?uploadType=resumable&part=snippet,status');
        curl_setopt_array($init, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json; charset=UTF-8',
                'X-Upload-Content-Length: ' . $size,
                'X-Upload-Content-Type: video/mp4',
            ],
            CURLOPT_POSTFIELDS => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        $resp = (string)curl_exec($init);
        $code = (int)curl_getinfo($init, CURLINFO_HTTP_CODE);
        $headerSize = (int)curl_getinfo($init, CURLINFO_HEADER_SIZE);
        curl_close($init);
        $headers = substr($resp, 0, $headerSize);
        $body = substr($resp, $headerSize);
        if ($code < 200 || $code >= 300) {
            $err = json_decode($body, true);
            return ['ok' => false, 'message' => 'YouTube rechazó la subida: ' . (string)($err['error']['message'] ?? ('HTTP ' . $code))];
        }
        if (!preg_match('/^Location:\s*(.+)$/mi', $headers, $m)) return ['ok' => false, 'message' => 'YouTube no devolvió la URL de subida.'];
        $location = trim($m[1]);
        $fh = fopen($filePath, 'rb');
        if (!$fh) return ['ok' => false, 'message' => 'No se pudo abrir el video.'];
        $put = curl_init($location);
        curl_setopt_array($put, [
            CURLOPT_UPLOAD => true,
            CURLOPT_INFILE => $fh,
            CURLOPT_INFILESIZE => $size,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 900,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: video/mp4',
                'Content-Length: ' . $size,
            ],
        ]);
        $uploadResp = (string)curl_exec($put);
        $uploadCode = (int)curl_getinfo($put, CURLINFO_HTTP_CODE);
        curl_close($put);
        fclose($fh);
        $data = json_decode($uploadResp, true);
        if ($uploadCode < 200 || $uploadCode >= 300 || empty($data['id'])) {
            return ['ok' => false, 'message' => 'La subida a YouTube falló: ' . (string)($data['error']['message'] ?? ('HTTP ' . $uploadCode)) . '.'];
        }
        return [
            'ok' => true,
            'message' => 'Video subido a YouTube.',
            'youtube_id' => (string)$data['id'],
            'youtube_url' => 'https://www.youtube.com/watch?v=' . (string)$data['id'],
        ];
    }

    private function accessToken(bool $refresh = true): string
    {
        $current = $this->setting('youtube_access_token');
        $expires = (int)$this->setting('youtube_access_expires');
        if (!$refresh && $current !== '' && $expires > time() + 60) return $current;
        $clientId = $this->setting('youtube_client_id');
        $secret = $this->clientSecret();
        $refreshToken = TwoFactorService::decrypt($this->setting('youtube_refresh_token_enc'));
        if ($clientId === '' || $secret === '' || $refreshToken === '') return '';
        $res = $this->postForm(self::TOKEN_URL, [
            'client_id' => $clientId,
            'client_secret' => $secret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);
        $data = json_decode((string)$res['body'], true);
        if (!is_array($data) || empty($data['access_token'])) return '';
        $this->setSetting('youtube_access_token', (string)$data['access_token']);
        $this->setSetting('youtube_access_expires', (string)(time() + max(60, (int)($data['expires_in'] ?? 3600))));
        return (string)$data['access_token'];
    }

    private function fetchChannel(string $token): array
    {
        $ch = curl_init(self::CHANNEL_URL . '?part=snippet&mine=true');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
        ]);
        $body = (string)curl_exec($ch);
        curl_close($ch);
        $d = json_decode($body, true);
        return is_array($d) && !empty($d['items'][0]) ? (array)$d['items'][0] : [];
    }

    private function postForm(string $url, array $fields): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
        ]);
        $body = (string)curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['body' => $body, 'code' => $code];
    }

    private function redirectUri(): string
    {
        $base = '';
        try {
            $row = Database::instance()->query('SELECT domain, name FROM sites WHERE id = @site_id LIMIT 1')->fetch();
            $domain = trim((string)($row['domain'] ?? ''));
            if ($domain === '') {
                $name = trim((string)($row['name'] ?? ''));
                if (preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $name)) $domain = $name;
            }
            if ($domain !== '') $base = 'https://' . preg_replace('~^https?://~i', '', rtrim($domain, '/'));
        } catch (\Throwable $e) {
        }
        if ($base === '') $base = rtrim((string)Config::get('APP_URL', 'https://sonika.com.co'), '/');
        return $base . '/api/v1/public/youtube/oauth/callback';
    }

    private function clientSecret(): string
    {
        return TwoFactorService::decrypt($this->setting('youtube_client_secret_enc'));
    }

    private function setting(string $key): string
    {
        try {
            $st = Database::instance()->prepare('SELECT `value` FROM settings WHERE site_id = @site_id AND `key` = ? LIMIT 1');
            $st->execute([$key]);
            $v = $st->fetchColumn();
            return $v === false ? '' : (string)$v;
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function setSetting(string $key, string $value): void
    {
        try {
            Database::instance()->prepare("INSERT INTO settings (site_id, `key`, `value`) VALUES (@site_id, ?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")
                ->execute([$key, $value]);
        } catch (\Throwable $e) {
        }
    }
}
