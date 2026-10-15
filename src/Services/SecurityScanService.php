<?php
namespace App\Services;

/**
 * SecurityScanService — robot de escaneo de seguridad (gratuito, sin dependencias).
 * Auto-audita el sitio (self-request a 127.0.0.1) + el webroot y guarda un informe
 * persistente con score y hallazgos. Lo ejecuta el cron y el panel (Settings → Seguridad).
 */
class SecurityScanService
{
    public function reportPath(): string
    {
        return dirname(__DIR__, 2) . '/public/assets/uploads/_catalog/security_report.json';
    }
    public function load(): ?array
    {
        $f = $this->reportPath();
        if (!is_file($f)) return null;
        $d = json_decode((string)@file_get_contents($f), true);
        return is_array($d) ? $d : null;
    }
    public function save(array $r): bool
    {
        $f = $this->reportPath();
        $dir = dirname($f);
        if (!is_dir($dir)) @mkdir($dir, 0777, true);
        return @file_put_contents($f . '.tmp', json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) !== false && @rename($f . '.tmp', $f);
    }

    public function scan(): array
    {
        $findings = [];
        $add = function (string $id, string $label, string $sev, bool $ok, string $detail = '') use (&$findings) {
            $findings[] = ['id' => $id, 'label' => $label, 'severity' => $sev, 'ok' => $ok, 'detail' => $detail];
        };

        [$c, $h] = $this->req('/');
        $add('xfo', 'X-Frame-Options', 'medium', isset($h['x-frame-options']), $h['x-frame-options'] ?? 'ausente');
        $add('nosniff', 'X-Content-Type-Options: nosniff', 'medium', (($h['x-content-type-options'] ?? '') === 'nosniff'), $h['x-content-type-options'] ?? 'ausente');
        $add('referrer', 'Referrer-Policy', 'low', isset($h['referrer-policy']), $h['referrer-policy'] ?? 'ausente');
        $add('permissions', 'Permissions-Policy', 'low', isset($h['permissions-policy']), $h['permissions-policy'] ?? 'ausente');
        $add('csp', 'Content-Security-Policy', 'medium', isset($h['content-security-policy']) || isset($h['content-security-policy-report-only']), isset($h['content-security-policy']) ? 'activa' : 'ausente (proponer Report-Only)');
        $add('hsts', 'Strict-Transport-Security (HSTS)', 'low', isset($h['strict-transport-security']), $h['strict-transport-security'] ?? 'no en este vhost (host nginx)');

        $sensitive = ['/.env', '/.git/config', '/composer.json', '/vendor/autoload.php', '/docker-compose.yml', '/.env.example'];
        foreach ($sensitive as $p) {
            [$sc] = $this->req($p);
            $safe = in_array($sc, [401, 403, 404, 301, 302, 0], true);
            $add('expose_' . ltrim($p, '/'), 'Exposición de ' . $p, 'high', $safe, 'HTTP ' . $sc);
        }
        [$up] = $this->req('/assets/uploads/__probe__.php');
        $add('uploads_exec', 'Ejecución de PHP en /assets/uploads', 'high', in_array($up, [401, 403, 404, 301, 0], true), 'HTTP ' . $up);
        $add('install', 'install.php bloqueado en producción', 'medium', in_array($this->req('/install.php')[0], [401, 403, 404, 301, 0], true), 'HTTP ' . $this->req('/install.php')[0]);
        $add('admin_auth', 'API admin exige autenticación', 'high', in_array($this->req('/api/v1/admin/settings')[0], [401, 403], true), 'HTTP ' . $this->req('/api/v1/admin/settings')[0]);

        $mainv = (string)@file_get_contents(dirname(__DIR__, 2) . '/VERSION');
        $add('https', 'APP_URL en HTTPS', 'medium', str_starts_with((string)\App\Core\Config::get('APP_URL', ''), 'https://'), (string)\App\Core\Config::get('APP_URL', ''));

        $w = ['high' => 3, 'medium' => 2, 'low' => 1];
        $tot = 0; $ok = 0;
        foreach ($findings as $f) { $wv = $w[$f['severity']] ?? 1; $tot += $wv; if ($f['ok']) $ok += $wv; }
        $score = $tot > 0 ? (int)round($ok * 100 / $tot) : 100;
        $crit = array_values(array_filter($findings, fn($f) => !$f['ok'] && $f['severity'] === 'high'));
        return ['generated_at' => date('c'), 'version' => trim($mainv), 'score' => $score, 'critical' => count($crit), 'findings' => $findings];
    }

    private function req(string $path): array
    {
        if (!function_exists('curl_init')) return [0, []];
        $ch = curl_init('http://127.0.0.1' . $path);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 6, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_FOLLOWLOCATION => false, CURLOPT_NOBODY => false]);
        $resp = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $hs = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        $raw = (string)$resp;
        $headers = [];
        foreach (explode("\n", substr($raw, 0, $hs)) as $line) {
            $p = strpos($line, ':');
            if ($p > 0) $headers[strtolower(trim(substr($line, 0, $p)))] = trim(substr($line, $p + 1));
        }
        return [$code, $headers];
    }
}
