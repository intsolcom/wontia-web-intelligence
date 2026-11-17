<?php
namespace App\Services;

use App\Core\Database;

/**
 * Integración con el cotizador nacional de Coordinadora.
 *
 * Fuente oficial: Web Service AGS 1.5
 * https://ws.coordinadora.com/ags/1.5/server.php
 *
 * Operación usada: Cotizador_cotizar.
 * Requiere credenciales de integrador: apikey, clave, nit, div y cuenta.
 * Sin credenciales el servicio queda en modo “no configurado” y no bloquea el checkout.
 */
class CoordinadoraService
{
    public const CARRIER = 'Coordinadora';
    public const SOAP_URL = 'https://ws.coordinadora.com/ags/1.5/server.php';
    public const SOAP_NS = 'https://ws.coordinadora.com/ags/1.5/server.php';
    public const REST_BASE = 'https://api.coordinadora.tech';

    public const WEIGHT_BRACKETS = [
        ['min' => 0,     'max' => 1000,  'label' => '0-1 kg'],
        ['min' => 1001,  'max' => 3000,  'label' => '1-3 kg'],
        ['min' => 3001,  'max' => 5000,  'label' => '3-5 kg'],
        ['min' => 5001,  'max' => 10000, 'label' => '5-10 kg'],
        ['min' => 10001, 'max' => 20000, 'label' => '10-20 kg'],
        ['min' => 20001, 'max' => 30000, 'label' => '20-30 kg'],
        ['min' => 30001, 'max' => 50000, 'label' => '30-50 kg'],
    ];

    public const MAJOR_CITIES = [
        '11001' => 'Bogotá', '05001' => 'Medellín', '76001' => 'Cali', '08001' => 'Barranquilla',
        '13001' => 'Cartagena', '68001' => 'Bucaramanga', '54001' => 'Cúcuta', '66001' => 'Pereira',
        '73001' => 'Ibagué', '47001' => 'Santa Marta', '20001' => 'Valledupar', '23001' => 'Montería',
        '52001' => 'Pasto', '41001' => 'Neiva', '17001' => 'Manizales', '63001' => 'Armenia',
        '50001' => 'Villavicencio', '70001' => 'Sincelejo', '85001' => 'Yopal', '19001' => 'Popayán',
        '44001' => 'Riohacha', '18001' => 'Florencia', '15001' => 'Tunja',
    ];

    private static $lastCallAt = 0.0;
    private static $settingsCache = null;

    public function defaults(): array
    {
        return [
            'enabled' => '0',
            'mode' => 'soap',
            'soap_url' => self::SOAP_URL,
            'rest_base' => self::REST_BASE,
            'apikey' => '',
            'clave' => '',
            'nit' => '',
            'div' => '',
            'cuenta' => '',
            'producto' => '0',
            'nivel_servicio' => '1',
            'origin_code' => '08001000',
            'markup_pct' => '8',
            'rounding_cents' => '10000',
            'tolerance_pct' => '5',
            'sync_enabled' => '1',
            'sync_mode' => 'missing',
            'auto_update' => '0',
            'national_strategy' => 'median',
            'last_sync_at' => '',
            'last_sync_status' => '',
            'last_sync_message' => '',
            'last_run_id' => '0',
            'sync_requested' => '0',
            'cache_ttl' => '21600',
            'timeout_seconds' => '25',
        ];
    }

    public function settings(): array
    {
        if (self::$settingsCache !== null) return self::$settingsCache;
        $out = $this->defaults();
        try {
            $st = Database::instance()->query("SELECT `key`, `value` FROM settings WHERE site_id = @site_id AND `key` LIKE 'coordinadora_%'");
            foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $key = preg_replace('/^coordinadora_/', '', (string)$row['key']);
                if (array_key_exists($key, $out)) $out[$key] = (string)$row['value'];
            }
        } catch (\Throwable $e) {
        }
        return self::$settingsCache = $out;
    }

    public function saveSettings(array $d): array
    {
        $allowed = array_keys($this->defaults());
        $clean = [];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $d)) continue;
            $value = $d[$key];
            if (in_array($key, ['enabled', 'sync_enabled', 'auto_update'], true)) $value = (string)((int)(bool)$value);
            elseif (in_array($key, ['markup_pct', 'tolerance_pct'], true)) $value = (string)max(0, min(100, (float)$value));
            elseif (in_array($key, ['rounding_cents', 'cache_ttl', 'timeout_seconds', 'last_run_id'], true)) $value = (string)max(0, (int)$value);
            elseif ($key === 'sync_requested') $value = in_array((string)$value, ['0', 'sync', 'diagnose'], true) ? (string)$value : '0';
            elseif ($key === 'mode') $value = 'soap';
            elseif ($key === 'sync_mode') $value = in_array((string)$value, ['missing', 'update'], true) ? (string)$value : 'missing';
            elseif ($key === 'national_strategy') $value = in_array((string)$value, ['median', 'max', 'bogota'], true) ? (string)$value : 'median';
            elseif ($key === 'soap_url' || $key === 'rest_base') {
                $value = trim((string)$value);
                if (!preg_match('~^https?://~i', $value)) $value = $key === 'soap_url' ? self::SOAP_URL : self::REST_BASE;
            } elseif ($key === 'origin_code') $value = preg_replace('/\D/', '', (string)$value) ?: '08001000';
            elseif ($key === 'producto' || $key === 'nivel_servicio') $value = (string)max(0, (int)$value);
            else $value = trim((string)$value);
            $clean[$key] = $value;
        }
        if (empty($clean)) return ['ok' => false, 'message' => 'No hay cambios para guardar.'];
        try {
            $db = Database::instance();
            $st = $db->prepare("INSERT INTO settings (site_id, `key`, `value`) VALUES (@site_id, :k, :v) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
            foreach ($clean as $key => $value) $st->execute(['k' => 'coordinadora_' . $key, 'v' => $value]);
            self::$settingsCache = null;
            return ['ok' => true, 'message' => 'Configuración de Coordinadora guardada.', 'settings' => $this->publicSettings()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo guardar la configuración.'];
        }
    }

    public function publicSettings(): array
    {
        $s = $this->settings();
        $s['configured'] = $this->isConfigured();
        $s['apikey_masked'] = $s['apikey'] !== '' ? substr($s['apikey'], 0, 4) . '••••' . substr($s['apikey'], -3) : '';
        $s['clave_set'] = $s['clave'] !== '';
        unset($s['apikey'], $s['clave']);
        return $s;
    }

    public function isConfigured(): bool
    {
        $s = $this->settings();
        return $s['apikey'] !== '' && $s['clave'] !== '' && $s['nit'] !== '' && $s['div'] !== '' && $s['cuenta'] !== '';
    }

    public function cityCode(string $municipalityCode): string
    {
        $municipalityCode = preg_replace('/\D/', '', $municipalityCode);
        if ($municipalityCode === '') return '';
        if (strlen($municipalityCode) === 8) return $municipalityCode;
        if (strlen($municipalityCode) === 5) return $municipalityCode . '000';
        return $municipalityCode;
    }

    /** Consulta una cotización nacional. */
    public function quote(array $ctx): array
    {
        $s = $this->settings();
        if (!$this->isConfigured()) {
            return ['ok' => false, 'message' => 'Coordinadora no está configurada. Agrega apikey, clave, NIT, división y cuenta.'];
        }
        $destination = $this->cityCode((string)($ctx['destination_code'] ?? ($ctx['municipality_code'] ?? '')));
        if ($destination === '') {
            return ['ok' => false, 'message' => 'Falta el código de ciudad destino.'];
        }
        $weightGrams = max(1, (int)($ctx['weight_grams'] ?? 1000));
        $weightKg = max(0.1, round($weightGrams / 1000, 2));
        $qty = max(1, (int)($ctx['qty'] ?? 1));
        $declared = max(0, (int)round(((int)($ctx['declared_value_cents'] ?? 5000000)) / 100));
        $params = [
            'nit' => $s['nit'],
            'div' => $s['div'],
            'cuenta' => $s['cuenta'],
            'producto' => (int)$s['producto'],
            'origen' => $s['origin_code'],
            'destino' => $destination,
            'valoracion' => $declared,
            'nivel_servicio' => [(int)($ctx['service_level'] ?? $s['nivel_servicio'])],
            'detalle' => [[
                'ubl' => 0,
                'alto' => max(1, (int)($ctx['height_cm'] ?? 10)),
                'ancho' => max(1, (int)($ctx['width_cm'] ?? 10)),
                'largo' => max(1, (int)($ctx['length_cm'] ?? 10)),
                'peso' => $weightKg,
                'unidades' => $qty,
            ]],
            'apikey' => $s['apikey'],
            'clave' => $s['clave'],
        ];
        $cacheKey = 'coord_quote_' . md5(json_encode($params));
        $cached = $this->cacheGet($cacheKey, (int)$s['cache_ttl']);
        if (is_array($cached)) return $cached;
        $res = $this->soapCall('Cotizador_cotizar', $params, (int)$s['timeout_seconds']);
        if (empty($res['ok'])) {
            return ['ok' => false, 'message' => (string)($res['message'] ?? 'Coordinadora no respondió.'), 'raw' => (string)($res['raw'] ?? '')];
        }
        $data = $this->flatten($res['data'] ?? []);
        $flete = $this->numberValue($data['flete_total'] ?? 0);
        if ($flete <= 0) {
            $flete = $this->numberValue($data['flete_fijo'] ?? 0) + $this->numberValue($data['flete_variable'] ?? 0) + $this->numberValue($data['otros_valores'] ?? 0);
        }
        if ($flete <= 0) {
            return ['ok' => false, 'message' => 'Coordinadora devolvió una cotización sin valor de flete.', 'raw' => (string)($res['raw'] ?? '')];
        }
        $baseCents = (int)round($flete * 100);
        $markup = (float)$s['markup_pct'];
        $rounding = max(1, (int)$s['rounding_cents']);
        $withMarkup = (int)ceil($baseCents * (1 + $markup / 100));
        $costCents = (int)ceil($withMarkup / $rounding) * $rounding;
        $etaMin = max(1, min(60, (int)$this->numberValue($data['dias_entrega'] ?? 2)));
        $etaMax = max($etaMin, min(60, (int)$this->numberValue($data['dias_entrega'] ?? 5)));
        $out = [
            'ok' => true,
            'base_cents' => $baseCents,
            'cost_cents' => $costCents,
            'markup_pct' => $markup,
            'rounding_cents' => $rounding,
            'eta_min_days' => $etaMin,
            'eta_max_days' => $etaMax,
            'destination_code' => $destination,
            'weight_grams' => $weightGrams,
            'raw' => $res['raw'] ?? '',
            'data' => $data,
            'message' => 'Cotización obtenida.',
        ];
        $this->cacheSet($cacheKey, $out);
        return $out;
    }

    /** Prueba de credenciales: consulta el catálogo de departamentos. */
    public function testConnection(): array
    {
        $s = $this->settings();
        if (!$this->isConfigured()) {
            return ['ok' => false, 'message' => 'Faltan credenciales de Coordinadora.'];
        }
        $res = $this->soapCall('Cotizador_departamentos', ['apikey' => $s['apikey'], 'clave' => $s['clave']], (int)$s['timeout_seconds']);
        if (empty($res['ok'])) return $res;
        $items = $this->collectItems($res['data'] ?? []);
        if (!$items) {
            return ['ok' => false, 'message' => 'Coordinadora respondió, pero no devolvió departamentos. Revisa las credenciales.', 'raw' => (string)($res['raw'] ?? '')];
        }
        return ['ok' => true, 'message' => 'Conexión con Coordinadora correcta. ' . count($items) . ' departamentos recibidos.', 'count' => count($items), 'sample' => array_slice($items, 0, 5), 'raw' => (string)($res['raw'] ?? '')];
    }

    /** Sincroniza la tabla estándar de tarifas. */
    public function syncRates(string $mode = 'missing', int $maxDestinations = 0, int $maxBrackets = 0): array
    {
        @set_time_limit(900);
        $s = $this->settings();
        if (!$this->isConfigured()) return ['ok' => false, 'message' => 'Configura las credenciales de Coordinadora antes de sincronizar.'];
        $shipping = new ShippingService();
        $shipping->ensureTables();
        $db = Database::instance();
        $mode = in_array($mode, ['missing', 'update'], true) ? $mode : 'missing';
        $runId = $this->startRun('sync', 'Sincronización de tarifas Coordinadora');
        $stats = ['destinations' => 0, 'quotes' => 0, 'created' => 0, 'updated' => 0, 'unchanged' => 0, 'errors' => 0];
        $national = [];
        $bogota = [];
        $destinations = $this->destinations();
        $destLimit = $maxDestinations > 0 ? min($maxDestinations, count($destinations)) : count($destinations);
        $brackets = $maxBrackets > 0 ? array_slice(self::WEIGHT_BRACKETS, 0, $maxBrackets) : self::WEIGHT_BRACKETS;
        $destIndex = 0;
        foreach ($destinations as $dest) {
            if ($destIndex >= $destLimit) break;
            $destIndex++;
            foreach ($brackets as $bracket) {
                $stats['destinations']++;
                $quote = $this->quote([
                    'destination_code' => $dest['code'],
                    'weight_grams' => $bracket['max'],
                    'declared_value_cents' => 5000000,
                    'qty' => 1,
                ]);
                if (empty($quote['ok'])) {
                    $stats['errors']++;
                    $this->logItem($runId, $dest, $bracket, $quote, 'error', (string)($quote['message'] ?? 'Error'));
                    continue;
                }
                $stats['quotes']++;
                $result = $this->upsertRate($dest, $bracket, $quote, $mode);
                $stats[$result]++;
                if (($dest['scope'] ?? '') === 'department') {
                    $key = $bracket['min'] . '-' . $bracket['max'];
                    $national[$key][] = (int)$quote['cost_cents'];
                    if ((string)($dest['department_code'] ?? '') === '11') $bogota[$key] = (int)$quote['cost_cents'];
                }
                $this->logItem($runId, $dest, $bracket, $quote, $result, $result === 'unchanged' ? 'Sin cambios' : 'Tarifa ' . $result);
            }
        }
        // Tarifa nacional calculada con los departamentos (mediana, máximo o Bogotá).
        if ($national) {
            $strategy = (string)$s['national_strategy'];
            foreach ($brackets as $bracket) {
                $key = $bracket['min'] . '-' . $bracket['max'];
                if (empty($national[$key])) continue;
                $values = $national[$key];
                sort($values);
                if ($strategy === 'max') $cost = (int)end($values);
                elseif ($strategy === 'bogota') $cost = (int)($bogota[$key] ?? end($values));
                else $cost = (int)$values[(int)floor((count($values) - 1) / 2)];
                $base = $cost;
                $quote = ['ok' => true, 'base_cents' => $base, 'cost_cents' => $cost, 'markup_pct' => (float)$s['markup_pct'], 'eta_min_days' => 2, 'eta_max_days' => 5, 'raw' => ''];
                $result = $this->upsertRate(['scope' => 'national', 'department_code' => null, 'municipality_code' => null, 'name' => 'Nacional'], $bracket, $quote, $mode);
                $stats[$result]++;
                $this->logItem($runId, ['scope' => 'national', 'department_code' => null, 'municipality_code' => null, 'name' => 'Nacional'], $bracket, $quote, $result, 'Nacional ' . $result);
            }
        }
        $this->finishRun($runId, 'ok', $stats, 'Sincronización completada.');
        return ['ok' => true, 'message' => 'Sincronización completada.', 'run_id' => $runId, 'stats' => $stats, 'national' => count($national)];
    }

    /** Diagnóstico semanal: vuelve a cotizar tarifas existentes y detecta desfases. */
    public function diagnose(int $limit = 80): array
    {
        @set_time_limit(600);
        $s = $this->settings();
        if (!$this->isConfigured()) return ['ok' => false, 'message' => 'Configura las credenciales de Coordinadora antes de diagnosticar.'];
        $shipping = new ShippingService();
        $shipping->ensureTables();
        $db = Database::instance();
        $runId = $this->startRun('diagnostic', 'Diagnóstico semanal de tarifas Coordinadora');
        $limit = max(1, min(200, $limit));
        $st = $db->prepare("SELECT * FROM store_shipping_rates WHERE site_id = @site_id AND carrier = :carrier AND is_active = 1 AND (department_code IS NOT NULL OR municipality_code IS NOT NULL) ORDER BY COALESCE(last_synced_at, '2000-01-01') ASC, id ASC LIMIT " . $limit);
        $st->execute(['carrier' => self::CARRIER]);
        $rows = $st->fetchAll();
        $stats = ['destinations' => 0, 'quotes' => 0, 'created' => 0, 'updated' => 0, 'unchanged' => 0, 'errors' => 0, 'review' => 0];
        $tolerance = max(0, (float)$s['tolerance_pct']);
        $autoUpdate = (string)$s['auto_update'] === '1';
        foreach ($rows as $row) {
            $stats['destinations']++;
            $bracket = ['min' => (int)$row['weight_min_grams'], 'max' => $row['weight_max_grams'] !== null ? (int)$row['weight_max_grams'] : null, 'label' => $this->bracketLabel((int)$row['weight_min_grams'], $row['weight_max_grams'] !== null ? (int)$row['weight_max_grams'] : null)];
            $dest = ['scope' => $row['municipality_code'] ? 'municipality' : ($row['department_code'] ? 'department' : 'national'), 'department_code' => $row['department_code'], 'municipality_code' => $row['municipality_code'], 'name' => $row['name']];
            $code = $row['municipality_code'] ?: '';
            if ($code === '' && !empty($row['department_code'])) {
                try {
                    $mst = $db->prepare('SELECT code FROM shipping_municipalities WHERE department_code = :d AND is_capital = 1 ORDER BY code ASC LIMIT 1');
                    $mst->execute(['d' => $row['department_code']]);
                    $code = (string)($mst->fetchColumn() ?: '');
                } catch (\Throwable $e) {
                }
            }
            if ($code === '') $code = $s['origin_code'];
            $quote = $this->quote(['destination_code' => $this->cityCode((string)$code), 'weight_grams' => $bracket['max'] ?: 1000, 'declared_value_cents' => 5000000, 'qty' => 1]);
            if (empty($quote['ok'])) {
                $stats['errors']++;
                $this->logItem($runId, $dest, $bracket, $quote, 'error', (string)($quote['message'] ?? 'Error'), (int)$row['cost_cents'], (int)$row['id']);
                continue;
            }
            $stats['quotes']++;
            $previous = (int)$row['cost_cents'];
            $applied = (int)$quote['cost_cents'];
            $diff = $applied - $previous;
            $diffPct = $previous > 0 ? round($diff / $previous * 100, 2) : 0;
            $status = 'unchanged';
            if (abs($diffPct) > $tolerance) {
                $status = 'review';
                if ($autoUpdate && abs($diffPct) <= 25) {
                    $db->prepare('UPDATE store_shipping_rates SET cost_cents = :cost, eta_min_days = :min, eta_max_days = :max, last_synced_at = NOW() WHERE id = :id AND site_id = @site_id')
                        ->execute(['cost' => $applied, 'min' => (int)$quote['eta_min_days'], 'max' => (int)$quote['eta_max_days'], 'id' => (int)$row['id']]);
                    $status = 'updated';
                }
            }
            $stats[$status]++;
            $this->logItem($runId, $dest, $bracket, $quote, $status, $status === 'review' ? 'Variación ' . $diffPct . '%: revisar antes de actualizar.' : 'Diferencia ' . $diffPct . '%', $previous, (int)$row['id'], $diff, $diffPct);
        }
        $this->finishRun($runId, 'ok', $stats, 'Diagnóstico completado. ' . $stats['review'] . ' tarifas para revisar.');
        return ['ok' => true, 'message' => 'Diagnóstico completado.', 'run_id' => $runId, 'stats' => $stats];
    }

    /** Encola una sincronización o diagnóstico para que lo ejecute job-runner.php. */
    public function requestSync(string $action = 'sync'): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'message' => 'Configura las credenciales de Coordinadora antes de encolar una sincronización.'];
        }
        $action = in_array($action, ['sync', 'diagnose'], true) ? $action : 'sync';
        $r = $this->saveSettings(['sync_requested' => $action]);
        return ['ok' => !empty($r['ok']), 'message' => $action === 'diagnose'
            ? 'Diagnóstico encolado. El job runner lo ejecutará en el próximo ciclo.'
            : 'Sincronización encolada. El job runner la ejecutará en el próximo ciclo.'];
    }

    /** Se ejecuta desde job-runner.php; atiende la cola y la programación semanal. */
    public function runIfDue(): array
    {
        $s = $this->settings();
        if ((string)$s['enabled'] !== '1') return ['skipped' => 'disabled'];
        if (!$this->isConfigured()) return ['skipped' => 'not_configured'];
        $requested = (string)($s['sync_requested'] ?? '0');
        if ($requested === 'sync' || $requested === 'diagnose') {
            $this->saveSettings(['sync_requested' => '0']);
            $result = $requested === 'sync' ? $this->syncRates((string)($s['sync_mode'] ?? 'missing')) : $this->diagnose(80);
            $this->saveSettings([
                'last_sync_at' => date('Y-m-d H:i:s'),
                'last_sync_status' => !empty($result['ok']) ? 'ok' : 'error',
                'last_sync_message' => (string)($result['message'] ?? ''),
                'last_run_id' => (string)($result['run_id'] ?? '0'),
            ]);
            return $result;
        }
        if ((string)$s['sync_enabled'] !== '1') return ['skipped' => 'disabled'];
        $last = (string)$s['last_sync_at'];
        $lastTs = $last !== '' ? strtotime($last) : 0;
        if ($lastTs > 0 && (time() - $lastTs) < 7 * 86400) return ['skipped' => 'not_due', 'last_sync_at' => $last];
        $db = Database::instance();
        $count = 0;
        try {
            $st = $db->prepare('SELECT COUNT(*) FROM store_shipping_rates WHERE site_id = @site_id AND carrier = :carrier');
            $st->execute(['carrier' => self::CARRIER]);
            $count = (int)$st->fetchColumn();
        } catch (\Throwable $e) {
        }
        $result = $count > 0 ? $this->diagnose(80) : $this->syncRates('missing');
        $this->saveSettings([
            'last_sync_at' => date('Y-m-d H:i:s'),
            'last_sync_status' => !empty($result['ok']) ? 'ok' : 'error',
            'last_sync_message' => (string)($result['message'] ?? ''),
            'last_run_id' => (string)($result['run_id'] ?? '0'),
        ]);
        return $result;
    }

    public function latestRun(): ?array
    {
        try {
            $st = Database::instance()->query("SELECT * FROM shipping_rate_sync_runs WHERE site_id = @site_id AND carrier = 'coordinadora' ORDER BY id DESC LIMIT 1");
            $run = $st->fetch();
            if (!$run) return null;
            $items = Database::instance()->prepare('SELECT * FROM shipping_rate_sync_items WHERE site_id = @site_id AND run_id = :r ORDER BY id DESC LIMIT 60');
            $items->execute(['r' => (int)$run['id']]);
            $run['items'] = $items->fetchAll();
            return $run;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function stats(): array
    {
        $out = ['rates' => 0, 'municipalities' => 0, 'departments' => 0, 'national' => 0, 'last_sync_at' => '', 'last_status' => ''];
        try {
            $db = Database::instance();
            $st = $db->prepare("SELECT COUNT(*) total,
                COALESCE(SUM(CASE WHEN municipality_code IS NOT NULL THEN 1 ELSE 0 END), 0) municipalities,
                COALESCE(SUM(CASE WHEN municipality_code IS NULL AND department_code IS NOT NULL THEN 1 ELSE 0 END), 0) departments,
                COALESCE(SUM(CASE WHEN municipality_code IS NULL AND department_code IS NULL THEN 1 ELSE 0 END), 0) national
                FROM store_shipping_rates WHERE site_id = @site_id AND carrier = :carrier");
            $st->execute(['carrier' => self::CARRIER]);
            $row = $st->fetch(\PDO::FETCH_ASSOC) ?: [];
            $out['rates'] = (int)($row['total'] ?? 0);
            $out['municipalities'] = (int)($row['municipalities'] ?? 0);
            $out['departments'] = (int)($row['departments'] ?? 0);
            $out['national'] = (int)($row['national'] ?? 0);
            $s = $this->settings();
            $out['last_sync_at'] = (string)$s['last_sync_at'];
            $out['last_status'] = (string)$s['last_sync_status'];
        } catch (\Throwable $e) {
        }
        return $out;
    }

    private function destinations(): array
    {
        $out = [];
        try {
            $db = Database::instance();
            $depts = $db->query('SELECT code, name FROM shipping_departments ORDER BY name ASC')->fetchAll();
            foreach ($depts as $d) {
                $st = $db->prepare('SELECT code, name FROM shipping_municipalities WHERE department_code = :d AND is_capital = 1 ORDER BY code ASC LIMIT 1');
                $st->execute(['d' => $d['code']]);
                $m = $st->fetch();
                if (!$m) continue;
                $out[] = ['scope' => 'department', 'department_code' => (string)$d['code'], 'municipality_code' => null, 'code' => $this->cityCode((string)$m['code']), 'name' => (string)$d['name']];
            }
        } catch (\Throwable $e) {
        }
        foreach (self::MAJOR_CITIES as $code => $name) {
            $out[] = ['scope' => 'municipality', 'department_code' => substr((string)$code, 0, 2), 'municipality_code' => (string)$code, 'code' => $this->cityCode((string)$code), 'name' => $name];
        }
        $seen = [];
        $unique = [];
        foreach ($out as $d) {
            $key = ($d['scope'] ?? '') . ':' . ($d['department_code'] ?? '') . ':' . ($d['municipality_code'] ?? '');
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $unique[] = $d;
        }
        return $unique;
    }

    private function upsertRate(array $dest, array $bracket, array $quote, string $mode): string
    {
        $db = Database::instance();
        $scope = (string)($dest['scope'] ?? 'national');
        $dept = $dest['department_code'] ?? null;
        $mun = $dest['municipality_code'] ?? null;
        $min = (int)$bracket['min'];
        $max = $bracket['max'] !== null ? (int)$bracket['max'] : null;
        $st = $db->prepare('SELECT id, cost_cents FROM store_shipping_rates WHERE site_id = @site_id AND carrier = :carrier AND department_code <=> :dept AND municipality_code <=> :mun AND weight_min_grams = :min AND weight_max_grams <=> :max LIMIT 1');
        $st->execute(['carrier' => self::CARRIER, 'dept' => $dept, 'mun' => $mun, 'min' => $min, 'max' => $max]);
        $row = $st->fetch();
        $name = 'Coordinadora ' . ($bracket['label'] ?? '') . ' · ' . (string)($dest['name'] ?? 'Nacional');
        $cost = (int)$quote['cost_cents'];
        $etaMin = (int)($quote['eta_min_days'] ?? 2);
        $etaMax = (int)($quote['eta_max_days'] ?? 5);
        if ($row) {
            if ($mode !== 'update') return 'unchanged';
            $db->prepare('UPDATE store_shipping_rates SET name = :name, cost_cents = :cost, eta_min_days = :min, eta_max_days = :max, weight_min_grams = :wmin, weight_max_grams = :wmax, markup_pct = :markup, sync_source = \'coordinadora\', last_synced_at = NOW() WHERE id = :id AND site_id = @site_id')
                ->execute(['name' => $name, 'cost' => $cost, 'min' => $etaMin, 'max' => $etaMax, 'wmin' => $min, 'wmax' => $max, 'markup' => (float)($quote['markup_pct'] ?? 0), 'id' => (int)$row['id']]);
            return 'updated';
        }
        $db->prepare('INSERT INTO store_shipping_rates (site_id, name, department_code, municipality_code, carrier, cost_cents, free_over_cents, eta_min_days, eta_max_days, weight_min_grams, weight_max_grams, markup_pct, sync_source, cod_enabled, is_active, sort_order, last_synced_at) VALUES (@site_id, :name, :dept, :mun, :carrier, :cost, NULL, :min, :max, :wmin, :wmax, :markup, \'coordinadora\', 1, 1, 0, NOW())')
            ->execute(['name' => $name, 'dept' => $dept, 'mun' => $mun, 'carrier' => self::CARRIER, 'cost' => $cost, 'min' => $etaMin, 'max' => $etaMax, 'wmin' => $min, 'wmax' => $max, 'markup' => (float)($quote['markup_pct'] ?? 0)]);
        return 'created';
    }

    private function startRun(string $mode, string $message): int
    {
        try {
            $db = Database::instance();
            // Conserva las últimas 20 ejecuciones para no crecer indefinidamente.
            try {
                $db->exec("DELETE FROM shipping_rate_sync_items WHERE site_id = @site_id AND run_id NOT IN (SELECT id FROM (SELECT id FROM shipping_rate_sync_runs WHERE site_id = @site_id AND carrier = 'coordinadora' ORDER BY id DESC LIMIT 20) x)");
                $db->exec("DELETE FROM shipping_rate_sync_runs WHERE site_id = @site_id AND carrier = 'coordinadora' AND id NOT IN (SELECT id FROM (SELECT id FROM shipping_rate_sync_runs WHERE site_id = @site_id AND carrier = 'coordinadora' ORDER BY id DESC LIMIT 20) x)");
            } catch (\Throwable $e) {
            }
            $db->prepare('INSERT INTO shipping_rate_sync_runs (site_id, carrier, mode, status, started_at, message) VALUES (@site_id, \'coordinadora\', :mode, \'running\', NOW(), :m)')
                ->execute(['mode' => $mode, 'm' => mb_substr($message, 0, 500)]);
            return (int)$db->lastInsertId();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function finishRun(int $runId, string $status, array $stats, string $message): void
    {
        if ($runId <= 0) return;
        try {
            Database::instance()->prepare('UPDATE shipping_rate_sync_runs SET status = :status, finished_at = NOW(), destinations = :d, quotes = :q, created_count = :c, updated_count = :u, unchanged_count = :n, error_count = :e, message = :m WHERE id = :id AND site_id = @site_id')
                ->execute(['status' => $status, 'd' => (int)($stats['destinations'] ?? 0), 'q' => (int)($stats['quotes'] ?? 0), 'c' => (int)($stats['created'] ?? 0), 'u' => (int)($stats['updated'] ?? 0), 'n' => (int)($stats['unchanged'] ?? 0), 'e' => (int)($stats['errors'] ?? 0), 'm' => mb_substr($message, 0, 500), 'id' => $runId]);
        } catch (\Throwable $e) {
        }
    }

    private function logItem(int $runId, array $dest, array $bracket, array $quote, string $status, string $message, ?int $previous = null, int $rateId = 0, int $diff = 0, float $diffPct = 0.0): void
    {
        if ($runId <= 0) return;
        try {
            Database::instance()->prepare('INSERT INTO shipping_rate_sync_items (site_id, run_id, carrier, scope, department_code, municipality_code, weight_min_grams, weight_max_grams, quoted_base_cents, quoted_cents, previous_cents, applied_cents, difference_cents, difference_pct, status, message, raw_json, created_at) VALUES (@site_id, :run, \'coordinadora\', :scope, :dept, :mun, :wmin, :wmax, :base, :quoted, :prev, :applied, :diff, :diffpct, :status, :message, :raw, NOW())')
                ->execute([
                    'run' => $runId, 'scope' => (string)($dest['scope'] ?? 'national'), 'dept' => $dest['department_code'] ?? null,
                    'mun' => $dest['municipality_code'] ?? null, 'wmin' => (int)$bracket['min'], 'wmax' => $bracket['max'] !== null ? (int)$bracket['max'] : null,
                    'base' => (int)($quote['base_cents'] ?? 0), 'quoted' => (int)($quote['cost_cents'] ?? 0), 'prev' => $previous,
                    'applied' => $status === 'created' || $status === 'updated' ? (int)($quote['cost_cents'] ?? 0) : null,
                    'diff' => $diff, 'diffpct' => $diffPct, 'status' => $status, 'message' => mb_substr($message, 0, 300),
                    'raw' => mb_substr((string)($quote['raw'] ?? ''), 0, 20000),
                ]);
        } catch (\Throwable $e) {
        }
    }

    private function bracketLabel(int $min, ?int $max): string
    {
        if ($min === 0 && $max === null) return 'Todo peso';
        return round($min / 1000, 1) . '-' . ($max !== null ? round($max / 1000, 1) : '∞') . ' kg';
    }

    /** Llamada SOAP document/literal. */
    private function soapCall(string $operation, array $params, int $timeout = 25): array
    {
        $this->throttle();
        $s = $this->settings();
        $url = trim((string)($s['soap_url'] ?? '')) ?: self::SOAP_URL;
        if (!preg_match('~^https?://~i', $url)) $url = self::SOAP_URL;
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns="' . self::SOAP_NS . '">'
            . '<soap:Body><ns:' . $operation . '><p>' . $this->xmlFields($params) . '</p></ns:' . $operation . '></soap:Body></soap:Envelope>';
        if (!function_exists('curl_init')) return ['ok' => false, 'message' => 'cURL no está disponible.'];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $xml,
            CURLOPT_HTTPHEADER => [
                'Content-Type: text/xml; charset=utf-8',
                'SOAPAction: ' . self::SOAP_NS . '#' . $operation,
                'Accept: text/xml, application/xml',
            ],
            CURLOPT_TIMEOUT => max(5, min(60, $timeout)),
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false || $raw === '') return ['ok' => false, 'message' => 'Coordinadora no respondió: ' . ($err ?: 'sin respuesta'), 'http' => $code];
        if (stripos((string)$raw, '<Fault') !== false || stripos((string)$raw, '<faultstring') !== false) {
            $fault = '';
            if (preg_match('/<faultstring[^>]*>(.*?)<\/faultstring>/is', (string)$raw, $m)) $fault = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_XML1, 'UTF-8'));
            return ['ok' => false, 'message' => $fault !== '' ? $fault : 'Error SOAP de Coordinadora.', 'raw' => $raw, 'http' => $code];
        }
        $data = $this->parseLeafTags((string)$raw);
        if (!$data) return ['ok' => false, 'message' => 'Respuesta SOAP sin datos legibles.', 'raw' => $raw, 'http' => $code];
        return ['ok' => true, 'data' => $data, 'raw' => $raw, 'http' => $code];
    }

    private function xmlFields(array $data): string
    {
        $out = '';
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (!$this->isList($value)) {
                    $out .= '<' . $key . '>' . $this->xmlFields($value) . '</' . $key . '>';
                    continue;
                }
                $out .= '<' . $key . '>';
                foreach ($value as $item) {
                    if (is_array($item)) $out .= '<item>' . $this->xmlFields($item) . '</item>';
                    else $out .= '<item>' . htmlspecialchars((string)$item, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</item>';
                }
                $out .= '</' . $key . '>';
                continue;
            }
            $out .= '<' . $key . '>' . htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</' . $key . '>';
        }
        return $out;
    }

    private function isList(array $a): bool
    {
        return array_keys($a) === range(0, count($a) - 1);
    }

    /** Extrae etiquetas hoja del XML SOAP sin depender de SimpleXML. */
    private function parseLeafTags(string $raw): array
    {
        $out = [];
        if (preg_match_all('/<([A-Za-z0-9_:\-]+)(?:\s[^>]*)?>([^<]*)<\/\1>/s', $raw, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $key = (string)$match[1];
                if (str_contains($key, ':')) $key = substr($key, strrpos($key, ':') + 1);
                $value = trim(html_entity_decode((string)$match[2], ENT_QUOTES | ENT_XML1, 'UTF-8'));
                if ($value === '') continue;
                if (isset($out[$key])) {
                    if (!is_array($out[$key])) $out[$key] = [$out[$key]];
                    $out[$key][] = $value;
                } else {
                    $out[$key] = $value;
                }
            }
        }
        return $out;
    }

    private function xmlToArray(\SimpleXMLElement $xml): array
    {
        $out = [];
        foreach ($xml->children() as $key => $child) {
            $value = $this->xmlToArray($child);
            if (!$value) $value = trim((string)$child);
            if (isset($out[$key])) {
                if (!is_array($out[$key]) || !$this->isList($out[$key])) $out[$key] = [$out[$key]];
                $out[$key][] = $value;
            } else {
                $out[$key] = $value;
            }
        }
        return $out;
    }

    private function numberValue($value): float
    {
        if (is_array($value)) $value = reset($value);
        return (float)$value;
    }

    private function flatten(array $data): array
    {
        $out = [];
        $walk = function ($value) use (&$walk, &$out) {
            if (!is_array($value)) return;
            foreach ($value as $k => $v) {
                if (is_array($v)) $walk($v);
                elseif (!isset($out[$k])) $out[$k] = $v;
            }
        };
        $walk($data);
        return $out;
    }

    private function collectItems(array $data): array
    {
        $codes = $data['codigo'] ?? [];
        $names = $data['nombre'] ?? [];
        if (!is_array($codes)) $codes = [$codes];
        if (!is_array($names)) $names = [$names];
        $items = [];
        foreach (array_values($codes) as $i => $code) {
            $items[] = ['codigo' => (string)$code, 'nombre' => (string)($names[$i] ?? '')];
        }
        return $items;
    }

    private function throttle(): void
    {
        $now = microtime(true);
        $wait = 0.70 - ($now - self::$lastCallAt);
        if ($wait > 0) usleep((int)round($wait * 1000000));
        self::$lastCallAt = microtime(true);
    }

    private function cacheGet(string $key, int $ttl): ?array
    {
        if ($ttl <= 0) return null;
        $file = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'sonika_' . $key . '.json';
        if (!is_file($file) || (time() - filemtime($file)) > $ttl) return null;
        $data = json_decode((string)@file_get_contents($file), true);
        return is_array($data) ? $data : null;
    }

    private function cacheSet(string $key, array $data): void
    {
        $file = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'sonika_' . $key . '.json';
        @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
