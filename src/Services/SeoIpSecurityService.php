<?php
namespace App\Services;

use App\Core\Database;

class SeoIpSecurityService
{
    private const SENSITIVE = '~(\.env|\.git|wp-admin|wp-login|xmlrpc|phpmyadmin|/config|/server-status|/cgi-bin|/vendor/|/backup|\.sql|\.bak|/etc/passwd|\.\./)~i';

    public function scan(string $ip): array
    {
        $ip = trim($ip);
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return ['ok' => false, 'message' => 'IP inválida.'];
        }
        $intel = $this->intel($ip);
        $activity = $this->activity($ip, 86400);
        $bot = $this->verifyBot($ip, $activity['ua'], $intel);
        $risk = $this->risk($activity, $intel, $bot);
        return [
            'ok' => true,
            'ip' => $ip,
            'intel' => $intel,
            'activity' => $activity,
            'bot' => $bot,
            'verdict' => $risk['verdict'],
            'score' => $risk['score'],
            'evidence' => $risk['evidence'],
            'blocked' => self::isBlocked($ip),
            'recommendation' => $this->recommendation($risk, $bot, $intel),
        ];
    }

    public function threats(int $window = 3600): array
    {
        $window = max(300, min(86400, $window));
        $db = Database::instance();
        $since = 'created_at >= (NOW() - INTERVAL ' . $window . ' SECOND)';
        $rows = [];
        try {
            $rows = $db->query("SELECT ip, user_agent, path, status, method, is_bot, created_at FROM seo_live_requests WHERE site_id = @site_id AND is_internal = 0 AND ip IS NOT NULL AND ip <> '' AND $since ORDER BY id DESC LIMIT 10000")->fetchAll();
        } catch (\Throwable $e) {
        }
        $byIp = [];
        $signatures = [];
        $sensitivePaths = [];
        foreach ($rows as $row) {
            $ip = (string)$row['ip'];
            $ua = (string)($row['user_agent'] ?? '');
            $path = (string)$row['path'];
            $status = (int)$row['status'];
            $isBot = (int)($row['is_bot'] ?? 0) === 1;
            $sensitive = preg_match(self::SENSITIVE, $path) === 1;
            if (!isset($byIp[$ip])) {
                $byIp[$ip] = ['ip' => $ip, 'requests' => 0, 'errors' => 0, 'sensitive' => 0, 'bot' => 0, 'paths' => [], 'ua' => $ua, 'first' => $row['created_at'], 'last' => $row['created_at']];
            }
            $byIp[$ip]['requests']++;
            if ($status >= 400) $byIp[$ip]['errors']++;
            if ($sensitive) $byIp[$ip]['sensitive']++;
            if ($isBot) $byIp[$ip]['bot']++;
            $byIp[$ip]['paths'][$path] = true;
            $byIp[$ip]['ua'] = $ua !== '' ? $ua : $byIp[$ip]['ua'];
            $byIp[$ip]['first'] = min($byIp[$ip]['first'], $row['created_at']);
            $byIp[$ip]['last'] = max($byIp[$ip]['last'], $row['created_at']);

            $norm = $this->normalizePath($path);
            $key = strtolower(mb_substr($ua, 0, 140)) . '|' . $norm;
            if (!isset($signatures[$key])) {
                $signatures[$key] = ['ua' => $ua, 'path' => $norm, 'ips' => [], 'requests' => 0, 'errors' => 0, 'sensitive' => 0, 'bot' => 0, 'first' => $row['created_at'], 'last' => $row['created_at']];
            }
            $signatures[$key]['ips'][$ip] = true;
            $signatures[$key]['requests']++;
            if ($status >= 400) $signatures[$key]['errors']++;
            if ($sensitive) $signatures[$key]['sensitive']++;
            if ($isBot) $signatures[$key]['bot']++;
            $signatures[$key]['first'] = min($signatures[$key]['first'], $row['created_at']);
            $signatures[$key]['last'] = max($signatures[$key]['last'], $row['created_at']);
            if ($sensitive) {
                $sk = 'sensitive|' . $norm;
                if (!isset($sensitivePaths[$sk])) $sensitivePaths[$sk] = ['path' => $norm, 'ips' => [], 'requests' => 0, 'uas' => []];
                $sensitivePaths[$sk]['ips'][$ip] = true;
                $sensitivePaths[$sk]['requests']++;
                if ($ua !== '') $sensitivePaths[$sk]['uas'][$ua] = true;
            }
        }

        $individual = [];
        foreach ($byIp as $item) {
            $errorRate = $item['requests'] > 0 ? $item['errors'] / $item['requests'] : 0;
            $score = 0;
            if ($item['requests'] >= 100) $score += 15;
            if ($item['requests'] >= 500) $score += 15;
            if ($item['requests'] >= 2000) $score += 15;
            if ($errorRate >= 0.2) $score += 15;
            if ($errorRate >= 0.5) $score += 20;
            if ($item['sensitive'] > 0) $score += min(45, $item['sensitive'] * 15);
            if (count($item['paths']) >= 50) $score += 10;
            if (count($item['paths']) >= 150) $score += 10;
            if ($item['bot'] > 0) $score += 5;
            $score = min(100, $score);
            $cached = $this->cachedIntel($item['ip']);
            $known = $this->knownBotName($item['ua']);
            $item['paths_count'] = count($item['paths']);
            unset($item['paths']);
            if ($known !== null && $item['sensitive'] === 0) {
                $score = min(35, $score);
                $item['verdict'] = 'bot_conocido';
            } else {
                $item['verdict'] = $item['sensitive'] > 0 ? 'posible_ataque' : ($score >= 50 ? 'sospechoso' : ($item['bot'] > 0 ? 'bot' : 'normal'));
            }
            $item['known_bot'] = $known;
            $item['score'] = $score;
            $item['country'] = (string)($cached['country'] ?? '');
            $item['city'] = (string)($cached['city'] ?? '');
            $item['asn'] = (string)($cached['asn'] ?? '');
            if ($score >= 20 || $item['sensitive'] > 0) $individual[] = $item;
        }
        usort($individual, fn($a, $b) => $b['score'] <=> $a['score']);

        $campaigns = [];
        foreach ($signatures as $sig) {
            $ipCount = count($sig['ips']);
            if ($ipCount < 3) continue;
            if (!($sig['errors'] >= 3 || $sig['sensitive'] > 0 || $sig['bot'] >= 2 || $sig['requests'] >= 20)) continue;
            $known = $this->knownBotName($sig['ua']);
            $isKnownBot = $known !== null && $sig['sensitive'] === 0;
            $risk = $isKnownBot
                ? min(40, 10 + min(20, $ipCount) + min(10, $sig['errors'] * 2))
                : min(100, 20 + min(30, $ipCount * 2) + min(15, (int)($sig['requests'] / 10)) + min(20, $sig['errors'] * 2) + ($sig['sensitive'] > 0 ? 20 : 0));
            $campaigns[] = [
                'kind' => $isKnownBot ? 'bot_distribuido' : ($sig['sensitive'] > 0 ? 'posible_ataque' : 'trafico_automatizado'),
                'signature' => $sig['path'],
                'ua' => $sig['ua'],
                'known_bot' => $known,
                'ips' => array_slice(array_keys($sig['ips']), 0, 30),
                'ip_count' => $ipCount,
                'requests' => $sig['requests'],
                'errors' => $sig['errors'],
                'sensitive' => $sig['sensitive'],
                'bot_requests' => $sig['bot'],
                'first' => $sig['first'],
                'last' => $sig['last'],
                'risk' => $risk,
                'reason' => $isKnownBot ? 'Bot conocido distribuido; no bloquear automáticamente.' : ($sig['sensitive'] > 0 ? 'Misma ruta sensible desde varias IPs.' : 'Tráfico automatizado con la misma firma desde varias IPs.'),
            ];
        }
        foreach ($sensitivePaths as $sig) {
            if (count($sig['ips']) < 2) continue;
            $campaigns[] = [
                'kind' => 'ruta_sensible',
                'signature' => $sig['path'],
                'ua' => implode(' | ', array_slice(array_keys($sig['uas']), 0, 3)),
                'ips' => array_slice(array_keys($sig['ips']), 0, 30),
                'ip_count' => count($sig['ips']),
                'requests' => $sig['requests'],
                'errors' => 0,
                'sensitive' => $sig['requests'],
                'bot_requests' => 0,
                'first' => null,
                'last' => null,
                'risk' => min(100, 55 + count($sig['ips']) * 5),
                'reason' => 'Ruta de sondeo sensible desde varias IPs.',
            ];
        }
        usort($campaigns, fn($a, $b) => $b['risk'] <=> $a['risk']);

        return [
            'ok' => true,
            'window_seconds' => $window,
            'events' => count($rows),
            'individual' => array_slice($individual, 0, 30),
            'campaigns' => array_slice($campaigns, 0, 20),
            'blocked' => $this->blocked(),
            'generated_at' => date('c'),
        ];
    }

    public function block(string $ip, string $reason, string $by, int $hours = 0): array
    {
        $ip = trim($ip);
        if (!filter_var($ip, FILTER_VALIDATE_IP)) return ['ok' => false, 'message' => 'IP inválida.'];
        $hours = max(0, min(8760, $hours));
        $expires = $hours > 0 ? date('Y-m-d H:i:s', time() + ($hours * 3600)) : null;
        $reason = mb_substr(trim($reason) ?: 'Bloqueo manual desde monitor SEO', 0, 500);
        $by = mb_substr($by, 0, 190);
        try {
            Database::instance()->prepare("INSERT INTO seo_ip_blocks (site_id, ip, reason, created_by, expires_at, active, created_at) VALUES (@site_id, ?, ?, ?, ?, 1, NOW()) ON DUPLICATE KEY UPDATE reason = VALUES(reason), created_by = VALUES(created_by), expires_at = VALUES(expires_at), active = 1, created_at = NOW()")
                ->execute([$ip, $reason, $by, $expires]);
            return ['ok' => true, 'message' => 'IP bloqueada.', 'ip' => $ip, 'expires_at' => $expires];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo guardar el bloqueo.'];
        }
    }

    public function unblock(string $ip): array
    {
        $ip = trim($ip);
        if (!filter_var($ip, FILTER_VALIDATE_IP)) return ['ok' => false, 'message' => 'IP inválida.'];
        try {
            Database::instance()->prepare('UPDATE seo_ip_blocks SET active = 0 WHERE site_id = @site_id AND ip = ?')->execute([$ip]);
            return ['ok' => true, 'message' => 'IP desbloqueada.', 'ip' => $ip];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo desbloquear.'];
        }
    }

    public function blocked(): array
    {
        try {
            $rows = Database::instance()->query("SELECT ip, reason, created_by, created_at, expires_at, requests_blocked FROM seo_ip_blocks WHERE site_id = @site_id AND active = 1 AND (expires_at IS NULL OR expires_at > NOW()) ORDER BY created_at DESC LIMIT 200")->fetchAll();
            return array_map(function ($r) { return ['ip' => (string)$r['ip'], 'reason' => (string)$r['reason'], 'created_by' => (string)$r['created_by'], 'created_at' => (string)$r['created_at'], 'expires_at' => $r['expires_at'], 'requests_blocked' => (int)$r['requests_blocked']]; }, $rows);
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function isBlocked(?string $ip): bool
    {
        static $cache = [];
        if ($ip === null || $ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) return false;
        if (array_key_exists($ip, $cache)) return $cache[$ip];
        try {
            $stmt = Database::instance()->prepare("SELECT 1 FROM seo_ip_blocks WHERE site_id = @site_id AND ip = ? AND active = 1 AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1");
            $stmt->execute([$ip]);
            $cache[$ip] = (bool)$stmt->fetchColumn();
        } catch (\Throwable $e) {
            $cache[$ip] = false;
        }
        return $cache[$ip];
    }

    private function activity(string $ip, int $window): array
    {
        $out = ['requests' => 0, 'errors' => 0, 'sensitive' => 0, 'unique_paths' => 0, 'paths' => [], 'uas' => [], 'ua' => '', 'statuses' => [], 'methods' => [], 'first' => null, 'last' => null];
        try {
            $stmt = Database::instance()->prepare("SELECT path, status, method, user_agent, created_at FROM seo_live_requests WHERE site_id = @site_id AND ip = ? AND created_at >= (NOW() - INTERVAL " . max(300, min(86400, $window)) . " SECOND) ORDER BY id DESC LIMIT 5000");
            $stmt->execute([$ip]);
            foreach ($stmt->fetchAll() as $row) {
                $out['requests']++;
                $status = (int)$row['status'];
                if ($status >= 400) $out['errors']++;
                if (preg_match(self::SENSITIVE, (string)$row['path'])) $out['sensitive']++;
                $out['paths'][(string)$row['path']] = true;
                $out['statuses'][$status] = ($out['statuses'][$status] ?? 0) + 1;
                $out['methods'][(string)$row['method']] = ($out['methods'][(string)$row['method']] ?? 0) + 1;
                $ua = (string)($row['user_agent'] ?? '');
                if ($ua !== '') $out['uas'][$ua] = true;
                if ($out['ua'] === '' && $ua !== '') $out['ua'] = $ua;
                $out['first'] = $row['created_at'];
                $out['last'] = $out['last'] ?? $row['created_at'];
            }
        } catch (\Throwable $e) {
        }
        $out['unique_paths'] = count($out['paths']);
        $out['paths'] = array_slice(array_keys($out['paths']), 0, 12);
        $out['uas'] = array_slice(array_keys($out['uas']), 0, 5);
        return $out;
    }

    private function intel(string $ip, bool $force = false): array
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return ['available' => false, 'ip' => $ip, 'country' => 'Red privada/reservada', 'city' => '', 'asn' => '', 'isp' => '', 'proxy' => false, 'hosting' => false, 'private' => true];
        }
        $cached = $this->cachedIntel($ip);
        if (!$force && $cached && !empty($cached['checked_at']) && strtotime((string)$cached['checked_at']) > time() - 86400) return $cached;
        $data = $this->lookup($ip);
        if ($data) {
            try {
                Database::instance()->prepare("INSERT INTO seo_ip_intel (site_id, ip, provider, country, country_code, region, city, isp, org, asn, asname, reverse_host, is_proxy, is_hosting, is_mobile, scanned_at) VALUES (@site_id, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE provider = VALUES(provider), country = VALUES(country), country_code = VALUES(country_code), region = VALUES(region), city = VALUES(city), isp = VALUES(isp), org = VALUES(org), asn = VALUES(asn), asname = VALUES(asname), reverse_host = VALUES(reverse_host), is_proxy = VALUES(is_proxy), is_hosting = VALUES(is_hosting), is_mobile = VALUES(is_mobile), scanned_at = NOW()")
                    ->execute([$ip, $data['provider'], $data['country'], $data['country_code'], $data['region'], $data['city'], $data['isp'], $data['org'], $data['asn'], $data['asname'], $data['reverse'], $data['proxy'] ? 1 : 0, $data['hosting'] ? 1 : 0, $data['mobile'] ? 1 : 0]);
            } catch (\Throwable $e) {
            }
            return $data;
        }
        return $cached ?: ['available' => false, 'ip' => $ip, 'country' => '', 'city' => '', 'asn' => '', 'isp' => '', 'proxy' => false, 'hosting' => false, 'error' => 'No se pudo consultar la IP.'];
    }

    private function cachedIntel(string $ip): ?array
    {
        try {
            $stmt = Database::instance()->prepare('SELECT * FROM seo_ip_intel WHERE site_id = @site_id AND ip = ? LIMIT 1');
            $stmt->execute([$ip]);
            $r = $stmt->fetch();
            if (!$r) return null;
            return [
                'available' => true,
                'ip' => (string)$r['ip'],
                'provider' => (string)$r['provider'],
                'country' => (string)$r['country'],
                'country_code' => (string)$r['country_code'],
                'region' => (string)$r['region'],
                'city' => (string)$r['city'],
                'isp' => (string)$r['isp'],
                'org' => (string)$r['org'],
                'asn' => (string)$r['asn'],
                'asname' => (string)$r['asname'],
                'reverse' => (string)$r['reverse_host'],
                'proxy' => (int)$r['is_proxy'] === 1,
                'hosting' => (int)$r['is_hosting'] === 1,
                'mobile' => (int)$r['is_mobile'] === 1,
                'checked_at' => (string)$r['scanned_at'],
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function lookup(string $ip): ?array
    {
        $fields = 'status,message,country,countryCode,regionName,city,isp,org,as,asname,reverse,mobile,proxy,hosting,query';
        $url = 'http://ip-api.com/json/' . rawurlencode($ip) . '?fields=' . $fields;
        $res = $this->request($url);
        $data = json_decode($res, true);
        if (is_array($data) && ($data['status'] ?? '') === 'success') {
            return $this->normalizeIntel($data, 'ip-api.com');
        }
        $res = $this->request('https://ipwho.is/' . rawurlencode($ip));
        $data = json_decode($res, true);
        if (is_array($data) && !empty($data['success'])) {
            return $this->normalizeIpWho($data);
        }
        return null;
    }

    private function normalizeIntel(array $d, string $provider): array
    {
        return [
            'available' => true,
            'ip' => (string)($d['query'] ?? ''),
            'provider' => $provider,
            'country' => (string)($d['country'] ?? ''),
            'country_code' => strtoupper((string)($d['countryCode'] ?? '')),
            'region' => (string)($d['regionName'] ?? ''),
            'city' => (string)($d['city'] ?? ''),
            'isp' => (string)($d['isp'] ?? ''),
            'org' => (string)($d['org'] ?? ''),
            'asn' => (string)($d['as'] ?? ''),
            'asname' => (string)($d['asname'] ?? ''),
            'reverse' => (string)($d['reverse'] ?? ''),
            'proxy' => !empty($d['proxy']),
            'hosting' => !empty($d['hosting']),
            'mobile' => !empty($d['mobile']),
            'checked_at' => date('c'),
        ];
    }

    private function normalizeIpWho(array $d): array
    {
        $connection = is_array($d['connection'] ?? null) ? $d['connection'] : [];
        return [
            'available' => true,
            'ip' => (string)($d['ip'] ?? ''),
            'provider' => 'ipwho.is',
            'country' => (string)($d['country'] ?? ''),
            'country_code' => strtoupper((string)($d['country_code'] ?? '')),
            'region' => (string)($d['region'] ?? ''),
            'city' => (string)($d['city'] ?? ''),
            'isp' => (string)($connection['isp'] ?? ''),
            'org' => (string)($connection['org'] ?? ''),
            'asn' => !empty($connection['asn']) ? 'AS' . $connection['asn'] : '',
            'asname' => (string)($connection['org'] ?? ''),
            'reverse' => '',
            'proxy' => false,
            'hosting' => false,
            'mobile' => false,
            'checked_at' => date('c'),
        ];
    }

    private function request(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_USERAGENT => 'Wontia-SEO-IP-Scanner/1.0']);
        $res = curl_exec($ch);
        curl_close($ch);
        return (string)$res;
    }

    private function knownBotMap(): array
    {
        return [
            'googlebot' => ['googlebot.com', 'google.com'],
            'bingbot' => ['search.msn.com'],
            'yandexbot' => ['yandex.com', 'yandex.ru', 'yandex.net'],
            'applebot' => ['applebot.apple.com'],
            'duckduckbot' => ['duckduckgo.com'],
            'amazonbot' => ['crawl.amazonbot.amazon'],
            'gptbot' => ['openai.com'],
            'claudebot' => ['anthropic.com'],
            'ahrefsbot' => ['ahrefs.com'],
            'semrushbot' => ['semrush.com'],
            'facebookexternalhit' => ['facebook.com'],
            'twitterbot' => ['twitter.com'],
            'linkedinbot' => ['linkedin.com'],
            'reflectionbot' => ['reflection.ai'],
            'petalbot' => ['petalbot.com'],
            'mj12bot' => ['mj12bot.com'],
            'bytespider' => ['bytedance.com'],
        ];
    }

    private function knownBotName(string $ua): ?string
    {
        $lower = strtolower($ua);
        foreach (array_keys($this->knownBotMap()) as $name) {
            if (strpos($lower, $name) !== false) return $name;
        }
        return null;
    }

    private function verifyBot(string $ip, string $ua, array $intel): array
    {
        $known = $this->knownBotMap();
        $claimed = null;
        $suffixes = [];
        foreach ($known as $name => $domains) {
            if (stripos($ua, $name) !== false) { $claimed = $name; $suffixes = $domains; break; }
        }
        $host = '';
        $forward = '';
        $verified = false;
        if ($claimed !== null) {
            $host = (string)@gethostbyaddr($ip);
            if ($host !== '' && $host !== $ip) {
                $forward = (string)@gethostbyname($host);
                foreach ($suffixes as $suffix) {
                    if (str_ends_with(strtolower($host), $suffix) && $forward === $ip) { $verified = true; break; }
                }
            }
        }
        $generic = (bool)preg_match('/bot|crawl|spider|slurp|headless|preview/i', $ua);
        $isBot = $claimed !== null || $generic || !empty($intel['hosting']);
        return [
            'is_bot' => $isBot,
            'claimed' => $claimed,
            'verified' => $verified,
            'kind' => $verified ? 'legitimo' : ($claimed !== null ? 'declarado_no_verificado' : ($generic ? 'generico' : 'desconocido')),
            'reverse_host' => $host,
            'forward_ip' => $forward,
            'hosting' => !empty($intel['hosting']),
            'proxy' => !empty($intel['proxy']),
        ];
    }

    private function risk(array $activity, array $intel, array $bot): array
    {
        $score = 0;
        $evidence = [];
        $requests = (int)$activity['requests'];
        $errors = (int)$activity['errors'];
        $rate = $requests > 0 ? $errors / $requests : 0;
        if ($requests >= 100) { $score += 10; $evidence[] = 'Volumen alto de peticiones'; }
        if ($requests >= 500) { $score += 15; }
        if ($requests >= 2000) { $score += 15; }
        if ($rate >= 0.2) { $score += 15; $evidence[] = 'Tasa de error elevada'; }
        if ($rate >= 0.5) $score += 20;
        if ((int)$activity['sensitive'] > 0) { $score += min(45, (int)$activity['sensitive'] * 15); $evidence[] = 'Rutas sensibles solicitadas'; }
        if ((int)$activity['unique_paths'] >= 50) { $score += 10; $evidence[] = 'Alta diversidad de rutas'; }
        if ((int)$activity['unique_paths'] >= 150) $score += 10;
        if (!empty($bot['proxy'])) { $score += 10; $evidence[] = 'Proxy/VPN detectado por el proveedor'; }
        if (!empty($bot['hosting'])) { $score += 5; $evidence[] = 'IP de hosting/cloud, no residencial'; }
        if (!empty($bot['claimed']) && empty($bot['verified'])) { $score += 10; $evidence[] = 'Bot conocido declarado; DNS inverso no verificado'; }
        if (!empty($bot['verified'])) { $score = min($score, 25); $evidence[] = 'Bot legítimo verificado por DNS inverso'; }
        if (!empty($bot['claimed']) && empty($bot['verified'])) $score = min(45, $score);
        $score = min(100, $score);
        if (!empty($bot['verified'])) $verdict = 'bot_legitimo';
        elseif ((int)$activity['sensitive'] > 0 || $score >= 70) $verdict = 'posible_ataque';
        elseif (!empty($bot['claimed'])) $verdict = 'bot_declarado_no_verificado';
        elseif ($score >= 40) $verdict = 'sospechoso';
        elseif (!empty($bot['is_bot'])) $verdict = 'bot_no_verificado';
        else $verdict = 'normal';
        return ['score' => $score, 'verdict' => $verdict, 'evidence' => $evidence];
    }

    private function recommendation(array $risk, array $bot, array $intel): string
    {
        if ($risk['verdict'] === 'bot_legitimo') return 'No bloquear: bot legítimo verificado por DNS. Revisa robots.txt si genera demasiada carga.';
        if ($risk['verdict'] === 'posible_ataque') return 'Revisa las rutas sensibles y bloquea la IP si no es un socio o monitor autorizado.';
        if ($risk['verdict'] === 'bot_declarado_no_verificado') return 'Bot conocido declarado, pero su DNS inverso no confirma la identidad. No bloquear sin revisar; si el patrón es agresivo, aplica rate-limit.';
        if ($risk['verdict'] === 'bot_falsificado') return 'Alta prioridad: el bot no coincide con su DNS esperado. Verifica y considera bloquear.';
        if ($risk['verdict'] === 'sospechoso') return 'Vigila la IP, revisa sus rutas y bloquea si el patrón continúa.';
        if (!empty($bot['is_bot'])) return 'Bot no verificado; puede ser crawler legítimo o automatización. No bloquees sin revisar.';
        return 'Sin señales claras de ataque en la ventana analizada.';
    }

    private function normalizePath(string $path): string
    {
        $path = '/' . ltrim(parse_url($path, PHP_URL_PATH) ?: '/', '/');
        if (preg_match(self::SENSITIVE, $path)) return $path;
        if (str_starts_with($path, '/producto/')) return '/producto/*';
        if (str_starts_with($path, '/marcas/')) return '/marcas/*';
        if (str_starts_with($path, '/blog/')) return '/blog/*';
        return preg_replace('/\d+/', '*', $path) ?: $path;
    }
}
