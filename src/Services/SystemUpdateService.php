<?php
namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\GitHubSyncService;

class SystemUpdateService
{
    public const QUEUE = '/app/deploy-queue';
    public const DEFAULT_CENTRAL = 'https://wwi.wontia.com';
    public const DEFAULT_REPO = 'https://github.com/intsolcom/wontia-web-intelligence';
    public const CHANNELS = ['stable', 'beta'];

    public function isCentral(): bool
    {
        return is_dir(self::QUEUE) && is_writable(self::QUEUE);
    }

    public function version(): string
    {
        $f = ROOT_DIR . '/VERSION';
        $v = is_file($f) ? trim((string)file_get_contents($f)) : '';
        return $v !== '' ? $v : '0.0.0';
    }

    public function build(): int
    {
        return (int)@filemtime(ROOT_DIR . '/public/assets/js/admin.js');
    }

    public function setting(string $key, $default = null)
    {
        try {
            $stmt = Database::instance()->prepare('SELECT `value` FROM settings WHERE site_id = @site_id AND `key` = :k');
            $stmt->execute(['k' => $key]);
            $v = $stmt->fetchColumn();
            return $v === false || $v === null ? $default : $v;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    public function saveSetting(string $key, $value): void
    {
        try {
            Database::instance()->prepare('INSERT INTO settings (site_id, `key`, `value`) VALUES (@site_id, :k, :v) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)')
                ->execute(['k' => $key, 'v' => (string)$value]);
        } catch (\Throwable $e) {}
    }

    public function centralUrl(): string
    {
        $u = (string)$this->setting('wwi.update_central_url', self::DEFAULT_CENTRAL);
        return rtrim($u !== '' ? $u : self::DEFAULT_CENTRAL, '/');
    }

    public function repoUrl(): string
    {
        $u = (string)$this->setting('wwi.update_repo', self::DEFAULT_REPO);
        return $u !== '' ? $u : self::DEFAULT_REPO;
    }

    public function channel(): string
    {
        $c = (string)$this->setting('wwi.update_channel', 'stable');
        return in_array($c, self::CHANNELS, true) ? $c : 'stable';
    }

    public function secret(): string
    {
        return (string)$this->setting('wwi.update_secret', '');
    }

    public function generateSecret(): string
    {
        $s = bin2hex(random_bytes(24));
        $this->saveSetting('wwi.update_secret', $s);
        return $s;
    }

    public function localInfo(): array
    {
        $build = $this->build();
        return [
            'version' => $this->version(),
            'build' => $build,
            'build_at' => $build ? date('c', $build) : '',
            'php' => PHP_VERSION,
            'is_central' => $this->isCentral(),
            'site_id' => (int)Config::get('SITE_ID', '1'),
            'app_url' => (string)Config::get('APP_URL', ''),
            'channel' => $this->channel(),
            'central_url' => $this->centralUrl(),
            'repo' => $this->repoUrl(),
            'auto_update' => $this->setting('wwi.auto_update', '0') === '1',
            'window' => (string)$this->setting('wwi.update_window', ''),
            'has_secret' => $this->secret() !== '',
            'last_check' => (string)$this->setting('wwi.update_last_check', ''),
        ];
    }

    public function manifest(): array
    {
        return [
            'product' => 'Wontia Web Intelligence',
            'version' => $this->version(),
            'build' => $this->build(),
            'released_at' => $this->build() ? date('c', $this->build()) : '',
            'php' => PHP_VERSION,
            'channel' => $this->channel(),
            'is_central' => $this->isCentral(),
            'notes' => (string)$this->setting('wwi.release_notes', ''),
            'repo' => $this->repoUrl(),
            'update_endpoint' => (string)Config::get('APP_URL', '') . '/api/v1/public/system/update-request',
        ];
    }

    private function httpGet(string $url, int $timeout = 8): ?array
    {
        $raw = $this->http($url, 'GET', null, $timeout);
        $d = $raw !== null ? json_decode($raw, true) : null;
        return is_array($d) ? $d : null;
    }

    private function httpPostJson(string $url, array $body, int $timeout = 10): ?array
    {
        $raw = $this->http($url, 'POST', json_encode($body), $timeout);
        $d = $raw !== null ? json_decode($raw, true) : null;
        return is_array($d) ? $d : null;
    }

    private function http(string $url, string $method = 'GET', ?string $body = null, int $timeout = 8): ?string
    {
        try {
            if (function_exists('curl_init')) {
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => $timeout,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json', 'User-Agent: WWI-Updater'],
                ]);
                if ($method === 'POST') {
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $body ?? '');
                }
                $raw = curl_exec($ch);
                $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                return ($raw !== false && $code >= 200 && $code < 300) ? (string)$raw : null;
            }
        } catch (\Throwable $e) {}
        try {
            $ctx = stream_context_create(['http' => ['method' => $method, 'header' => "Content-Type: application/json\r\nUser-Agent: WWI-Updater\r\n", 'content' => $body ?? '', 'timeout' => $timeout, 'ignore_errors' => true]]);
            $raw = @file_get_contents($url, false, $ctx);
            return $raw !== false ? (string)$raw : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function fetchCentralManifest(): ?array
    {
        $d = $this->httpGet($this->centralUrl() . '/api/v1/public/system/manifest');
        return $d && !empty($d['data']) ? $d['data'] : ($d && isset($d['version']) ? $d : null);
    }

    public function fetchCentralStatus(): ?array
    {
        $d = $this->httpGet($this->centralUrl() . '/api/v1/public/system/status');
        return $d && !empty($d['data']) ? $d['data'] : null;
    }

    public function repoLatest(): ?array
    {
        try {
            $rel = GitHubSyncService::getReleases($this->repoUrl(), null, 1);
            if (is_array($rel) && !empty($rel[0])) {
                $r = $rel[0];
                $v = ltrim((string)($r['tag_name'] ?? ''), 'v');
                if ($v !== '') {
                    return [
                        'version' => $v,
                        'notes' => (string)($r['body'] ?? ''),
                        'url' => (string)($r['html_url'] ?? ''),
                        'released_at' => (string)($r['published_at'] ?? ''),
                        'source' => 'github-release',
                    ];
                }
            }
        } catch (\Throwable $e) {}
        try {
            $raw = $this->http($this->rawVersionUrl(), 'GET', null, 6);
            $v = $raw !== null ? trim($raw) : '';
            if (preg_match('/^\d+\.\d+\.\d+([.\-+][0-9A-Za-z.\-]+)?$/', $v)) {
                return ['version' => $v, 'notes' => '', 'url' => $this->repoUrl(), 'released_at' => '', 'source' => 'github-version'];
            }
        } catch (\Throwable $e) {}
        return null;
    }

    public function rawVersionUrl(): string
    {
        $repo = $this->repoUrl();
        if (preg_match('#github\.com/([^/]+)/([^/]+?)(?:\.git)?$#', $repo, $m)) {
            $branch = (string)$this->setting('wwi.update_branch', 'main');
            return 'https://raw.githubusercontent.com/' . $m[1] . '/' . $m[2] . '/' . ($branch !== '' ? $branch : 'main') . '/VERSION';
        }
        return rtrim($repo, '/') . '/VERSION';
    }

    public function check(): array
    {
        $local = $this->localInfo();
        $central = null;
        $centralError = null;
        if (!$local['is_central']) {
            $central = $this->fetchCentralManifest();
            if (!$central) $centralError = 'No se pudo contactar al servidor central (' . $local['central_url'] . ')';
        }
        $repo = $this->repoLatest();

        $candidates = [['v' => $local['version'], 'src' => 'local']];
        if ($central && !empty($central['version'])) $candidates[] = ['v' => $central['version'], 'src' => 'central', 'notes' => $central['notes'] ?? ''];
        if ($repo && !empty($repo['version'])) $candidates[] = ['v' => $repo['version'], 'src' => 'github', 'notes' => $repo['notes'] ?? ''];

        usort($candidates, fn($a, $b) => version_compare($b['v'], $a['v']));
        $latest = $candidates[0];
        $available = version_compare($latest['v'], $local['version'], '>');

        $this->saveSetting('wwi.update_last_check', date('c'));

        return [
            'ok' => true,
            'data' => [
                'local' => $local,
                'central' => $central,
                'central_error' => $centralError,
                'github' => $repo,
                'latest' => $latest,
                'update_available' => $available,
                'checked_at' => date('c'),
                'plan' => $this->plan($available, $latest, $local),
            ],
        ];
    }

    public function plan(bool $available, array $latest, array $local): array
    {
        return [
            'available' => $available,
            'from' => $local['version'],
            'to' => $latest['v'],
            'source' => $latest['src'],
            'applies_to' => $local['is_central'] ? 'todos los sitios WWI (servidor central)' : 'servidor central (' . $local['central_url'] . ')',
            'steps' => [
                '1. Descargar la versión ' . $latest['v'] . ' en el servidor central',
                '2. Construir la imagen Docker',
                '3. Recrear los contenedores WWI conservando su configuración',
                '4. Health-check por contenedor (rollback automático si falla)',
            ],
            'downtime' => '≈ 20–40 s por actualización (sin pérdida de datos)',
        ];
    }

    public function requestUpdate(string $actor, array $opts = []): array
    {
        $ch = (string)($opts['channel'] ?? '');
        $channel = in_array($ch, self::CHANNELS, true) ? $ch : $this->channel();
        $ts = time();
        if ($this->isCentral()) {
            $payload = [
                'action' => 'system_update',
                'ts' => $ts,
                'requested_by' => $actor,
                'channel' => $channel,
                'backup' => !empty($opts['backup']),
                'force' => !empty($opts['force']),
            ];
            $payload['sign'] = hash_hmac('sha256', 'system_update|' . $ts, (string)Config::get('JWT_SECRET', 'x'));
            @mkdir(self::QUEUE, 0777, true);
            if (@file_put_contents(self::QUEUE . '/update-' . $ts . '.json', json_encode($payload)) === false) {
                return ['ok' => false, 'message' => 'No se pudo encolar la actualización (permisos del volumen)'];
            }
            return ['ok' => true, 'central' => true, 'message' => 'Actualización encolada en el servidor central. Se aplicará en menos de 1 minuto.'];
        }

        $secret = $this->secret();
        if ($secret === '') {
            return ['ok' => false, 'message' => 'Configura el secreto de actualización en Settings → Update para solicitar la actualización al servidor central.'];
        }
        $body = [
            'ts' => $ts,
            'site_id' => (int)Config::get('SITE_ID', '1'),
            'requested_by' => $actor,
            'channel' => $channel,
            'sign' => hash_hmac('sha256', 'system_update|' . $ts, $secret),
        ];
        $res = $this->httpPostJson($this->centralUrl() . '/api/v1/public/system/update-request', $body);
        if ($res === null) {
            return ['ok' => false, 'message' => 'No se pudo contactar al servidor central (' . $this->centralUrl() . ')'];
        }
        if (empty($res['ok'])) {
            return ['ok' => false, 'message' => $res['message'] ?? 'El servidor central rechazó la solicitud'];
        }
        return ['ok' => true, 'central' => false, 'message' => $res['message'] ?? 'Solicitud enviada al servidor central. Se aplicará en menos de 1 minuto.'];
    }

    public function verifyCentralRequest($ts, $sign): bool
    {
        $secret = $this->secret();
        if ($secret === '' || !$ts || !$sign) return false;
        if (abs(time() - (int)$ts) > 300) return false;
        return hash_equals(hash_hmac('sha256', 'system_update|' . (int)$ts, $secret), (string)$sign);
    }

    public function status(): array
    {
        if ($this->isCentral()) {
            $svc = new FactoryService();
            return $svc->systemUpdateStatus();
        }
        $st = $this->fetchCentralStatus();
        return $st ?: ['status' => 'idle'];
    }

    public function history(): array
    {
        if (!$this->isCentral()) {
            $d = $this->httpGet($this->centralUrl() . '/api/v1/public/system/history');
            return ($d && !empty($d['data'])) ? $d['data'] : [];
        }
        $out = [];
        foreach (glob(self::QUEUE . '/update-*.json') ?: [] as $f) {
            $out[] = ['status' => 'pending', 'data' => json_decode((string)@file_get_contents($f), true)];
        }
        foreach (glob(self::QUEUE . '/done/update-*.json') ?: [] as $f) {
            $out[] = ['status' => 'done', 'data' => json_decode((string)@file_get_contents($f), true)];
        }
        usort($out, fn($a, $b) => strcmp((string)($b['data']['ts'] ?? ''), (string)($a['data']['ts'] ?? '')));
        return array_slice($out, 0, 12);
    }

    public function inWindow(): bool
    {
        $w = (string)$this->setting('wwi.update_window', '');
        if (!preg_match('/^(\d{2}):(\d{2})-(\d{2}):(\d{2})$/', $w, $m)) return true;
        $now = (int)date('H') * 60 + (int)date('i');
        $start = (int)$m[1] * 60 + (int)$m[2];
        $end = (int)$m[3] * 60 + (int)$m[4];
        return $start <= $end ? ($now >= $start && $now <= $end) : ($now >= $start || $now <= $end);
    }

    public function autoTick(): array
    {
        if ($this->setting('wwi.auto_update', '0') !== '1') {
            return ['ok' => true, 'skipped' => 'auto_update off'];
        }
        if (!$this->isCentral()) {
            return ['ok' => true, 'skipped' => 'not central'];
        }
        if (!$this->inWindow()) {
            return ['ok' => true, 'skipped' => 'outside window'];
        }
        $check = $this->check();
        if (empty($check['data']['update_available'])) {
            return ['ok' => true, 'skipped' => 'up to date'];
        }
        $r = $this->requestUpdate('auto', ['channel' => $this->channel(), 'backup' => true]);
        return ['ok' => !empty($r['ok']), 'queued' => !empty($r['ok']), 'message' => $r['message'] ?? ''];
    }
}
