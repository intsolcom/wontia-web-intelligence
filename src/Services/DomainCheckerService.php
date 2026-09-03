<?php
namespace App\Services;

class DomainCheckerService
{
    private const BOOTSTRAP_URL = 'https://data.iana.org/rdap/dns.json';
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
            return ['name' => $rawName, 'state' => 'INVALID', 'message' => 'Invalid domain name'];
        }

        $tld = substr($name, strrpos($name, '.') + 1);
        $base = $this->rdapBase($tld);
        if (!$base) {
            return ['name' => $name, 'state' => 'ERROR', 'message' => 'No RDAP server for .' . $tld];
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
        $error = curl_error($ch);
        curl_close($ch);

        if ($code === 0) {
            return ['name' => $name, 'state' => 'ERROR', 'message' => 'Verification unavailable, try again'];
        }

        if ($code === 404) {
            return ['name' => $name, 'state' => 'AVAILABLE', 'message' => '¡Libre! Inclúyelo con tu plan'];
        }

        if ($code === 200) {
            $data = json_decode($raw, true);
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
            return ['name' => $name, 'state' => 'TAKEN', 'message' => $msg, 'registrar' => $registrar, 'expires_at' => $expires];
        }

        if ($code === 429) {
            return ['name' => $name, 'state' => 'ERROR', 'message' => 'Demasiadas consultas — intenta en un minuto'];
        }

        return ['name' => $name, 'state' => 'ERROR', 'message' => 'Registro no respondió (HTTP ' . $code . ')'];
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
