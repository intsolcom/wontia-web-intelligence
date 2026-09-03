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

    public function check(string $rawName): array
    {
        $name = strtolower(trim((string)$rawName));
        $name = preg_replace('/^https?:\/\//', '', $name);
        $name = rtrim($name, '/');
        $name = preg_replace('/\.$/', '', $name);

        if ($name === '' || !preg_match('/^(?!-)[a-z0-9-]{1,63}(?<!-)\.[a-z]{2,24}$/', $name)) {
            return ['name' => $rawName, 'state' => 'INVALID', 'message' => 'Invalid domain name', 'suggestions' => []];
        }

        $result = $this->lookup($name);
        $result['name'] = $name;

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
        if (file_exists($cacheFile) && time() - (int)@filemtime($cacheFile) < 300) {
            $cached = json_decode((string)@file_get_contents($cacheFile), true);
            if (is_array($cached)) return $cached;
        }

        $tld = substr($name, strrpos($name, '.') + 1);
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

        if ($code === 0) {
            return ['state' => 'ERROR', 'message' => 'Verification unavailable'];
        }

        if ($code === 404) {
            $result = ['state' => 'AVAILABLE', 'message' => '¡Libre!'];
            @file_put_contents($cacheFile, json_encode($result));
            return $result;
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
            $result = ['state' => 'TAKEN', 'message' => $msg, 'registrar' => $registrar, 'expires_at' => $expires];
            @file_put_contents($cacheFile, json_encode($result));
            return $result;
        }

        if ($code === 429) {
            return ['state' => 'ERROR', 'message' => 'Demasiadas consultas — intenta en un minuto'];
        }

        return ['state' => 'ERROR', 'message' => 'Registro no respondió (HTTP ' . $code . ')'];
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
