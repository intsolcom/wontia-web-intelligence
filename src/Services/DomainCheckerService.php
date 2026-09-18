<?php
namespace App\Services;

class DomainCheckerService
{
    private const BOOTSTRAP_URL = 'https://data.iana.org/rdap/dns.json';
    private const SUGGEST_TLDS = ['com', 'net', 'org', 'co', 'com.co', 'site', 'info'];
    private const FALLBACK = [
        'com' => 'https://rdap.verisign.com/com/v1',
        'net' => 'https://rdap.verisign.com/net/v1',
        'org' => 'https://rdap.publicinterestregistry.org/rdap',
    ];

    public function allowCheck(string $ip, int $maxPerMinute = 30): bool
    {
        if ($ip === '') return true;
        $dir = (defined('ROOT_DIR') ? ROOT_DIR : sys_get_temp_dir()) . '/cache';
        if (!is_dir($dir)) @mkdir($dir, 0777, true);
        $file = $dir . '/rate_domain_' . md5($ip) . '.json';
        $now = time();
        $data = ['t' => $now, 'n' => 0];
        if (file_exists($file)) {
            $prev = json_decode((string)@file_get_contents($file), true);
            if (is_array($prev) && isset($prev['t']) && ($now - (int)$prev['t']) < 60) {
                $data = ['t' => (int)$prev['t'], 'n' => (int)($prev['n'] ?? 0)];
            }
        }
        $data['n']++;
        @file_put_contents($file, json_encode($data));
        return $data['n'] <= $maxPerMinute;
    }

    public function check(string $rawName): array
    {
        $name = strtolower(trim((string)$rawName));
        $name = preg_replace('/^https?:\/\//', '', $name);
        $name = rtrim($name, '/');
        $name = preg_replace('/\.$/', '', $name);

        if ($name === '' || !preg_match('/^(?!-)([a-z0-9-]{1,63}(?<!-)\.)+[a-z]{2,24}$/', $name)) {
            return ['name' => $rawName, 'state' => 'INVALID', 'message' => 'Invalid domain name', 'suggestions' => []];
        }

        $result = $this->lookup($name);
        $result['name'] = $name;

        $costs = $this->costEngine();
        $parts = explode('.', $name);
        $lastTld = end($parts);
        $twoLevel = count($parts) >= 3 ? ($parts[count($parts) - 2] . '.' . $lastTld) : null;
        $costKey = ($twoLevel && isset($costs[$twoLevel])) ? $twoLevel : $lastTld;
        if (isset($costs[$costKey])) {
            $result['price_reg'] = (float)$costs[$costKey]['reg'];
            $result['price_ren'] = (float)$costs[$costKey]['ren'];
        }

        $sld = substr($name, 0, strpos($name, '.'));
        $costs = $this->costEngine();
        $suggestions = [];
        foreach (self::SUGGEST_TLDS as $tld) {
            $candidate = $sld . '.' . $tld;
            if ($candidate === $name) continue;
            $lookup = $this->lookup($candidate);
            $price = $costs[$tld] ?? null;
            $suggestions[] = [
                'name' => $candidate,
                'tld' => $tld,
                'state' => $lookup['state'],
                'price_reg' => $price ? (float)$price['reg'] : null,
                'price_ren' => $price ? (float)$price['ren'] : null,
                'currency' => 'USD',
            ];
        }
        $result['suggestions'] = $suggestions;
        return $result;
    }

    private function lookup(string $name): array
    {
        $cacheFile = (defined('ROOT_DIR') ? ROOT_DIR : sys_get_temp_dir()) . '/cache/rdap_' . md5($name) . '.json';
        if (file_exists($cacheFile)) {
            $cached = json_decode((string)@file_get_contents($cacheFile), true);
            if (is_array($cached) && isset($cached['_t'])) {
                $ttl = (int)($cached['_ttl'] ?? 300);
                if (time() - (int)$cached['_t'] < $ttl) {
                    unset($cached['_t'], $cached['_ttl']);
                    return $cached;
                }
            }
        }

        $tld = substr($name, strrpos($name, '.') + 1);
        $porkbun = $this->porkbunLookup($name);
        if ($porkbun) {
            $result = $porkbun;
        } else {
            $result = $this->rdapLookup($name, $tld);
            if ($result['state'] === 'ERROR') {
                $whois = $this->whoisLookup($name, $tld);
                if ($whois['state'] !== 'ERROR') {
                    $result = $whois;
                } else {
                    $result = $this->dnsLookup($name);
                }
            }
        }
        $result['_t'] = time();
        $result['_ttl'] = $result['state'] === 'ERROR' ? 60 : 300;
        @file_put_contents($cacheFile, json_encode($result));
        unset($result['_t'], $result['_ttl']);
        return $result;
    }

    private function porkbunLookup(string $name): ?array
    {
        $keys = $this->porkbunKeys();
        if (!$keys) return null;
        $ch = curl_init('https://api.porkbun.com/api/json/v3/domain/checkDomain/' . rawurlencode($name));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode(['apikey' => $keys[0], 'secretapikey' => $keys[1]]),
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code !== 200 || !$body) return null;
        $d = json_decode((string)$body, true);
        if (!is_array($d) || ($d['status'] ?? '') !== 'SUCCESS') return null;
        $resp = $d['response'] ?? [];
        $avail = strtolower((string)($resp['avail'] ?? ''));
        if ($avail === 'yes') {
            return ['state' => 'AVAILABLE', 'message' => '¡Libre! (verificado con el registrador)', 'registrar' => 'Porkbun'];
        }
        if ($avail === 'no') {
            $price = isset($resp['price']) ? (' — renovación $' . $resp['price'] . ' USD') : '';
            return ['state' => 'TAKEN', 'message' => 'Registrado' . $price, 'registrar' => 'Porkbun'];
        }
        return null;
    }

    private function porkbunKeys(): ?array
    {
        $k = (string)\App\Core\Config::get('PORKBUN_API_KEY', '');
        $s = (string)\App\Core\Config::get('PORKBUN_SECRET_KEY', '');
        if ($k === '' || $s === '') {
            try {
                $stmt = \App\Core\Database::instance()->prepare("SELECT `key`, `value` FROM settings WHERE site_id = @site_id AND `key` IN ('wwi.porkbun_api_key','wwi.porkbun_secret_key')");
                $stmt->execute();
                foreach ($stmt->fetchAll() as $row) {
                    if ($row['key'] === 'wwi.porkbun_api_key') $k = (string)$row['value'];
                    else $s = (string)$row['value'];
                }
            } catch (\Throwable $e) {
            }
        }
        return ($k !== '' && $s !== '') ? [$k, $s] : null;
    }

    private function dnsLookup(string $name): array
    {
        $ns = @dns_get_record($name, DNS_NS);
        $a = @dns_get_record($name, DNS_A);
        if (($ns && count($ns)) || ($a && count($a))) {
            return ['state' => 'TAKEN', 'message' => 'Registrado — tiene DNS activo (se confirma con el registrador)', 'registrar' => ''];
        }
        return ['state' => 'CHECKING', 'message' => 'Sin DNS activo — parece libre; se confirma al registrar'];
    }

    private function rdapLookup(string $name, string $tld): array
    {
        $base = $this->rdapBase($tld);
        if (!$base) {
            return ['state' => 'ERROR', 'message' => 'No RDAP server for .' . $tld];
        }

        $url = rtrim($base, '/') . '/domain/' . rawurlencode($name);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 2,
            CURLOPT_HTTPHEADER => ['Accept: application/rdap+json'],
        ]);
        $raw = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 404) {
            return ['state' => 'AVAILABLE', 'message' => '¡Libre!'];
        }

        if ($code === 429) {
            return ['state' => 'ERROR', 'message' => 'Muchas consultas — intenta de nuevo en un momento'];
        }

        if ($code === 200) {
            $data = json_decode((string)$raw, true);
            $expires = null;
            $registrar = null;
            if (is_array($data)) {
                foreach (($data['events'] ?? []) as $ev) {
                    if (($ev['eventAction'] ?? '') === 'expiration' && !empty($ev['eventDate'])) {
                        $expires = substr((string)$ev['eventDate'], 0, 10);
                    }
                }
                foreach (($data['entities'] ?? []) as $ent) {
                    $vcard = $ent['vcardArray'][1] ?? [];
                    foreach ($vcard as $item) {
                        if (($item[0] ?? '') === 'fn' && !empty($item[3])) {
                            $registrar = (string)$item[3];
                            break 2;
                        }
                    }
                }
            }
            $msg = 'Ya está registrado';
            if ($registrar) $msg .= ' · ' . $registrar;
            if ($expires) $msg .= ' · expira ' . $expires;
            return ['state' => 'TAKEN', 'message' => $msg, 'registrar' => $registrar, 'expires_at' => $expires];
        }

        return ['state' => 'ERROR', 'message' => 'RDAP no respondió (HTTP ' . $code . ')'];
    }

    private function whoisLookup(string $name, string $tld): array
    {
        $servers = [
            'com' => 'whois.verisign-grs.com',
            'net' => 'whois.verisign-grs.com',
            'org' => 'whois.publicinterestregistry.net',
            'co' => 'whois.nic.co',
            'com.co' => 'whois.nic.co',
        ];
        $host = $servers[$tld] ?? null;
        if (!$host) return ['state' => 'ERROR', 'message' => 'No WHOIS server for .' . $tld];

        $fp = @fsockopen($host, 43, $errno, $errstr, 10);
        if (!$fp) return ['state' => 'ERROR', 'message' => 'WHOIS unavailable'];
        fwrite($fp, $name . "\r\n");
        stream_set_timeout($fp, 10);
        $data = '';
        while (!feof($fp)) $data .= fgets($fp, 512);
        fclose($fp);

        $upper = strtoupper((string)$data);
        if (str_contains($upper, 'NO MATCH') || str_contains($upper, 'NOT FOUND') || str_contains($upper, 'NO DATA FOUND') || str_contains($upper, 'IS FREE')) {
            return ['state' => 'AVAILABLE', 'message' => '¡Libre!'];
        }
        if (str_contains($upper, 'DOMAIN NAME') || str_contains($upper, 'REGISTRAR')) {
            $registrar = null;
            $expires = null;
            if (preg_match('/Registrar:\s*(.+)/i', (string)$data, $m)) $registrar = trim($m[1]);
            if (preg_match('/(?:Registry Expiry Date|Expiration Date):\s*(.+)/i', (string)$data, $m)) $expires = substr(trim($m[1]), 0, 10);
            $msg = 'Ya está registrado';
            if ($registrar) $msg .= ' · ' . $registrar;
            if ($expires) $msg .= ' · expira ' . $expires;
            return ['state' => 'TAKEN', 'message' => $msg, 'registrar' => $registrar, 'expires_at' => $expires];
        }
        return ['state' => 'ERROR', 'message' => 'Verificación no concluyente'];
    }

    private function costEngine(): array
    {
        $db = \App\Core\Database::instance();
        $stmt = $db->prepare("SELECT `value` FROM settings WHERE site_id = @site_id AND `key` = 'wwi.domain_costs'");
        $stmt->execute();
        $value = $stmt->fetchColumn();
        if (!$value) return [];
        $decoded = json_decode((string)$value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function rdapBase(string $tld): ?string
    {
        if (isset(self::FALLBACK[$tld])) return self::FALLBACK[$tld];
        $cacheFile = defined('ROOT_DIR') ? ROOT_DIR . '/cache/rdap_bootstrap.json' : sys_get_temp_dir() . '/rdap_bootstrap.json';
        $json = null;
        if (file_exists($cacheFile) && time() - (int)@filemtime($cacheFile) < 86400) {
            $json = json_decode((string)@file_get_contents($cacheFile), true);
        }
        if (!is_array($json)) {
            $ch = curl_init(self::BOOTSTRAP_URL);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => true]);
            $raw = curl_exec($ch);
            curl_close($ch);
            $json = json_decode((string)$raw, true);
            if (is_array($json)) @file_put_contents($cacheFile, (string)$raw);
        }
        if (is_array($json)) {
            foreach (($json['services'] ?? []) as $service) {
                $tlds = $service[0] ?? [];
                if (in_array($tld, $tlds, true) && !empty($service[1][0])) {
                    return (string)$service[1][0];
                }
            }
        }
        return null;
    }
}
