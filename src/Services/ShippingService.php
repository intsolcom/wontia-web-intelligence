<?php
namespace App\Services;

use App\Core\Database;

class ShippingService
{
    private static $ensured = false;

    public function ensureTables(): array
    {
        if (self::$ensured) return ['ok' => true, 'seeded' => 0];
        $db = Database::instance();
        $created = false;
        try {
            $db->query('SELECT 1 FROM shipping_departments LIMIT 1');
            $db->query('SELECT 1 FROM shipping_municipalities LIMIT 1');
            $db->query('SELECT 1 FROM store_shipping_rates LIMIT 1');
        } catch (\Throwable $e) {
            $this->createTables();
            $created = true;
        }
        $seeded = $this->seedLocations();
        $this->seedDefaultRate();
        $this->migrateColumns();
        $this->ensureSyncTables();
        self::$ensured = true;
        return ['ok' => true, 'created' => $created, 'seeded' => $seeded];
    }

    private function migrateColumns(): void
    {
        $db = Database::instance();
        foreach ([
            'ALTER TABLE store_shipping_rates ADD COLUMN IF NOT EXISTS weight_min_grams INT NOT NULL DEFAULT 0',
            'ALTER TABLE store_shipping_rates ADD COLUMN IF NOT EXISTS weight_max_grams INT NULL DEFAULT NULL',
            'ALTER TABLE store_shipping_rates ADD COLUMN IF NOT EXISTS markup_pct DECIMAL(6,2) NOT NULL DEFAULT 0',
            'ALTER TABLE store_shipping_rates ADD COLUMN IF NOT EXISTS external_id VARCHAR(100) NULL',
            'ALTER TABLE store_shipping_rates ADD COLUMN IF NOT EXISTS last_synced_at DATETIME NULL',
            'ALTER TABLE store_shipping_rates ADD COLUMN IF NOT EXISTS sync_source VARCHAR(20) NULL',
        ] as $sql) {
            try { $db->exec($sql); } catch (\Throwable $e) {}
        }
    }

    private function ensureSyncTables(): void
    {
        $db = Database::instance();
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS shipping_rate_sync_runs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                site_id INT NOT NULL DEFAULT 1,
                carrier VARCHAR(40) NOT NULL DEFAULT 'coordinadora',
                mode VARCHAR(20) NOT NULL DEFAULT 'sync',
                status VARCHAR(20) NOT NULL DEFAULT 'running',
                started_at DATETIME NOT NULL,
                finished_at DATETIME NULL,
                destinations INT NOT NULL DEFAULT 0,
                quotes INT NOT NULL DEFAULT 0,
                created_count INT NOT NULL DEFAULT 0,
                updated_count INT NOT NULL DEFAULT 0,
                unchanged_count INT NOT NULL DEFAULT 0,
                error_count INT NOT NULL DEFAULT 0,
                message VARCHAR(500) NULL,
                report_json LONGTEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY idx_sync_site (site_id, carrier, started_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $db->exec("CREATE TABLE IF NOT EXISTS shipping_rate_sync_items (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                site_id INT NOT NULL DEFAULT 1,
                run_id INT NOT NULL,
                carrier VARCHAR(40) NOT NULL DEFAULT 'coordinadora',
                scope VARCHAR(20) NOT NULL DEFAULT 'national',
                department_code CHAR(2) NULL,
                municipality_code CHAR(5) NULL,
                weight_min_grams INT NOT NULL DEFAULT 0,
                weight_max_grams INT NULL,
                quoted_base_cents BIGINT NOT NULL DEFAULT 0,
                quoted_cents BIGINT NOT NULL DEFAULT 0,
                previous_cents BIGINT NULL,
                applied_cents BIGINT NULL,
                difference_cents BIGINT NOT NULL DEFAULT 0,
                difference_pct DECIMAL(8,2) NOT NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL DEFAULT 'ok',
                message VARCHAR(300) NULL,
                raw_json LONGTEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY idx_sync_items_run (run_id, id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (\Throwable $e) {}
    }

    private function createTables(): void
    {
        $path = (defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 2)) . '/install/shipping.sql';
        if (!is_file($path)) throw new \RuntimeException('No se encontró el esquema de envíos.');
        $sql = preg_replace('/^\s*--.*$/m', '', (string)file_get_contents($path));
        $db = Database::instance();
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            if ($statement !== '') $db->exec($statement . ';');
        }
    }

    private function seedLocations(): int
    {
        $db = Database::instance();
        $count = (int)$db->query('SELECT COUNT(*) FROM shipping_departments')->fetchColumn();
        $munCount = (int)$db->query('SELECT COUNT(*) FROM shipping_municipalities')->fetchColumn();
        if ($count >= 33 && $munCount >= 1119) return 0;
        $path = (defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 2)) . '/install/shipping_locations.json';
        if (!is_file($path)) throw new \RuntimeException('No se encontraron los municipios de Colombia.');
        $data = json_decode((string)file_get_contents($path), true);
        if (!is_array($data) || empty($data['departments'])) throw new \RuntimeException('Los municipios de Colombia no son válidos.');
        $insertDept = $db->prepare('INSERT IGNORE INTO shipping_departments (code, name, capital) VALUES (?, ?, ?)');
        $insertMun = $db->prepare('INSERT IGNORE INTO shipping_municipalities (code, department_code, name, province, is_capital) VALUES (?, ?, ?, ?, ?)');
        $inserted = 0;
        $db->beginTransaction();
        try {
            foreach ($data['departments'] as $department) {
                $deptCode = trim((string)($department['code'] ?? ''));
                $deptName = trim((string)($department['name'] ?? ''));
                $capital = trim((string)($department['capital'] ?? ''));
                if (!preg_match('/^\d{2}$/', $deptCode) || $deptName === '') continue;
                $insertDept->execute([$deptCode, $deptName, $capital]);
                $capitalCode = '';
                foreach ((array)($department['municipalities'] ?? []) as $municipality) {
                    if (strcasecmp(trim((string)($municipality['name'] ?? '')), $capital) === 0) {
                        $capitalCode = trim((string)($municipality['code'] ?? ''));
                        break;
                    }
                }
                foreach ((array)($department['municipalities'] ?? []) as $municipality) {
                    $code = trim((string)($municipality['code'] ?? ''));
                    $name = trim((string)($municipality['name'] ?? ''));
                    if (!preg_match('/^\d{5}$/', $code) || $name === '') continue;
                    $isCapital = ($code === $capitalCode || $code === '11001') ? 1 : 0;
                    $insertMun->execute([$code, $deptCode, $name, trim((string)($municipality['province'] ?? '')), $isCapital]);
                    $inserted++;
                }
            }
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
        return $inserted;
    }

    private function seedDefaultRate(): void
    {
        $db = Database::instance();
        $check = $db->prepare("SELECT `value` FROM settings WHERE site_id = @site_id AND `key` = 'store_shipping_seeded' LIMIT 1");
        $check->execute();
        if ((string)$check->fetchColumn() === '1') return;
        $countStmt = $db->query('SELECT COUNT(*) FROM store_shipping_rates WHERE site_id = @site_id');
        if ((int)$countStmt->fetchColumn() > 0) {
            $this->markSeeded();
            return;
        }
        $cost = 1500000;
        try {
            $breb = (new BrebService())->settings();
            $cost = max(0, (int)($breb['shipping_cents'] ?? $cost));
        } catch (\Throwable $e) {
        }
        $stmt = $db->prepare('INSERT INTO store_shipping_rates (site_id, name, department_code, municipality_code, carrier, cost_cents, free_over_cents, eta_min_days, eta_max_days, cod_enabled, is_active, sort_order) VALUES (@site_id, ?, NULL, NULL, ?, ?, NULL, 2, 5, 1, 1, 0)');
        $stmt->execute(['Nacional', 'Por definir', $cost]);
        $this->markSeeded();
    }

    private function markSeeded(): void
    {
        Database::instance()->prepare("INSERT INTO settings (site_id, `key`, `value`) VALUES (@site_id, 'store_shipping_seeded', '1') ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")->execute();
    }

    public function departments(): array
    {
        $this->ensureTables();
        $stmt = Database::instance()->query('SELECT d.code, d.name, d.capital, COUNT(m.code) AS municipalities FROM shipping_departments d LEFT JOIN shipping_municipalities m ON m.department_code = d.code GROUP BY d.code, d.name, d.capital ORDER BY d.name ASC');
        return $stmt->fetchAll();
    }

    public function municipalities(string $departmentCode): array
    {
        $this->ensureTables();
        $departmentCode = trim($departmentCode);
        if (!preg_match('/^\d{2}$/', $departmentCode)) return [];
        $stmt = Database::instance()->prepare('SELECT code, department_code, name, province, is_capital FROM shipping_municipalities WHERE department_code = :department ORDER BY is_capital DESC, name ASC');
        $stmt->execute(['department' => $departmentCode]);
        return $stmt->fetchAll();
    }

    public function cityLabels(): array
    {
        $this->ensureTables();
        $stmt = Database::instance()->query('SELECT m.name, d.name AS department FROM shipping_municipalities m INNER JOIN shipping_departments d ON d.code = m.department_code ORDER BY d.name ASC, m.name ASC');
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $city = trim((string)($row['name'] ?? ''));
            $department = trim((string)($row['department'] ?? ''));
            if ($city === '' || $department === '') continue;
            $label = $city . ', ' . $department;
            $out[$label] = $label;
        }
        return array_values($out);
    }

    public function rates(): array
    {
        $this->ensureTables();
        $stmt = Database::instance()->prepare('SELECT r.*, d.name AS department_name, m.name AS municipality_name FROM store_shipping_rates r LEFT JOIN shipping_departments d ON d.code = r.department_code LEFT JOIN shipping_municipalities m ON m.code = r.municipality_code WHERE r.site_id = @site_id ORDER BY r.sort_order ASC, r.id ASC');
        $stmt->execute();
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) $row['scope'] = $this->scopeOf($row);
        return $rows;
    }

    public function saveRate(array $data, ?int $id = null): int
    {
        $this->ensureTables();
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') throw new \InvalidArgumentException('Escribe un nombre para la tarifa.');
        $scope = (string)($data['scope'] ?? 'national');
        if (!in_array($scope, ['national', 'department', 'municipality'], true)) $scope = 'national';
        $department = $this->cleanCode((string)($data['department_code'] ?? ''), 2);
        $municipality = $this->cleanCode((string)($data['municipality_code'] ?? ''), 5);
        if ($scope === 'national') {
            $department = '';
            $municipality = '';
        } elseif ($scope === 'department') {
            $municipality = '';
            if ($department === '' || !$this->departmentExists($department)) throw new \InvalidArgumentException('Selecciona un departamento válido.');
        } else {
            if ($municipality === '') throw new \InvalidArgumentException('Selecciona un municipio válido.');
            $row = $this->municipalityRow($municipality);
            if (!$row) throw new \InvalidArgumentException('El municipio no existe.');
            if ($department !== '' && $department !== (string)$row['department_code']) throw new \InvalidArgumentException('El municipio no pertenece al departamento.');
            $department = (string)$row['department_code'];
        }
        $cost = max(0, (int)round((float)($data['cost_cents'] ?? 0)));
        $freeRaw = array_key_exists('free_over_cents', $data) ? $data['free_over_cents'] : null;
        $free = ($freeRaw === null || $freeRaw === '') ? null : max(0, (int)round((float)$freeRaw));
        $etaMin = $this->cleanEta($data['eta_min_days'] ?? null);
        $etaMax = $this->cleanEta($data['eta_max_days'] ?? null);
        if ($etaMin !== null && $etaMax !== null && $etaMax < $etaMin) throw new \InvalidArgumentException('El tiempo máximo no puede ser menor que el mínimo.');
        $cod = array_key_exists('cod_enabled', $data) ? (int)(bool)$data['cod_enabled'] : 1;
        $active = array_key_exists('is_active', $data) ? (int)(bool)$data['is_active'] : 1;
        $sort = max(0, (int)($data['sort_order'] ?? 0));
        $carrier = trim((string)($data['carrier'] ?? ''));
        $weightMin = max(0, (int)($data['weight_min_grams'] ?? 0));
        $weightMaxRaw = $data['weight_max_grams'] ?? null;
        $weightMax = ($weightMaxRaw === null || $weightMaxRaw === '') ? null : max(0, (int)$weightMaxRaw);
        if ($weightMax !== null && $weightMax < $weightMin) throw new \InvalidArgumentException('El peso máximo no puede ser menor que el mínimo.');
        $markup = max(0, min(100, (float)($data['markup_pct'] ?? 0)));
        $syncSource = strtolower(trim((string)($data['sync_source'] ?? 'manual')));
        if (!in_array($syncSource, ['manual', 'coordinadora', 'csv'], true)) $syncSource = 'manual';
        $externalId = trim((string)($data['external_id'] ?? ''));
        $db = Database::instance();
        $params = [
            'name' => mb_substr($name, 0, 150),
            'department_code' => $department !== '' ? $department : null,
            'municipality_code' => $municipality !== '' ? $municipality : null,
            'carrier' => $carrier !== '' ? mb_substr($carrier, 0, 100) : null,
            'cost_cents' => $cost,
            'free_over_cents' => $free,
            'eta_min_days' => $etaMin,
            'eta_max_days' => $etaMax,
            'weight_min_grams' => $weightMin,
            'weight_max_grams' => $weightMax,
            'markup_pct' => $markup,
            'external_id' => $externalId !== '' ? mb_substr($externalId, 0, 100) : null,
            'sync_source' => $syncSource,
            'cod_enabled' => $cod,
            'is_active' => $active,
            'sort_order' => $sort,
        ];
        if ($id) {
            $params['id'] = $id;
            $db->prepare('UPDATE store_shipping_rates SET name = :name, department_code = :department_code, municipality_code = :municipality_code, carrier = :carrier, cost_cents = :cost_cents, free_over_cents = :free_over_cents, eta_min_days = :eta_min_days, eta_max_days = :eta_max_days, weight_min_grams = :weight_min_grams, weight_max_grams = :weight_max_grams, markup_pct = :markup_pct, external_id = :external_id, sync_source = :sync_source, cod_enabled = :cod_enabled, is_active = :is_active, sort_order = :sort_order, last_synced_at = CASE WHEN :sync_source2 = \'coordinadora\' THEN NOW() ELSE last_synced_at END WHERE id = :id AND site_id = @site_id')->execute($params + ['sync_source2' => $syncSource]);
            return $id;
        }
        $db->prepare('INSERT INTO store_shipping_rates (site_id, name, department_code, municipality_code, carrier, cost_cents, free_over_cents, eta_min_days, eta_max_days, weight_min_grams, weight_max_grams, markup_pct, external_id, sync_source, cod_enabled, is_active, sort_order, last_synced_at) VALUES (@site_id, :name, :department_code, :municipality_code, :carrier, :cost_cents, :free_over_cents, :eta_min_days, :eta_max_days, :weight_min_grams, :weight_max_grams, :markup_pct, :external_id, :sync_source, :cod_enabled, :is_active, :sort_order, CASE WHEN :sync_source2 = \'coordinadora\' THEN NOW() ELSE NULL END)')->execute($params + ['sync_source2' => $syncSource]);
        return (int)$db->lastInsertId();
    }

    public function deleteRate(int $id): void
    {
        $this->ensureTables();
        Database::instance()->prepare('DELETE FROM store_shipping_rates WHERE id = :id AND site_id = @site_id')->execute(['id' => $id]);
    }

    public function quote(array $context, int $netBeforeCents, int $globalFreeCents = 0): array
    {
        $this->ensureTables();
        $department = $this->cleanCode((string)($context['department_code'] ?? ''), 2);
        $municipality = $this->cleanCode((string)($context['municipality_code'] ?? ''), 5);
        $departmentName = '';
        $municipalityName = '';
        $selected = $department !== '' || $municipality !== '';
        if (($department === '') !== ($municipality === '')) {
            return $this->quoteError('Selecciona departamento y municipio.');
        }
        if ($municipality !== '') {
            $row = $this->municipalityRow($municipality);
            if (!$row || $department !== (string)$row['department_code']) return $this->quoteError('El municipio seleccionado no es válido.');
            $municipalityName = (string)$row['name'];
            $departmentName = $this->departmentName($department);
        }
        $weightGrams = max(0, (int)($context['weight_grams'] ?? 0));
        $rate = $this->findRate($department, $municipality, $weightGrams);
        if (!$rate) {
            return $this->quoteError($selected ? 'Aún no tenemos envío disponible para este municipio. Escríbenos por WhatsApp para cotizarlo.' : 'Configura una tarifa nacional de envío.');
        }
        $source = $this->scopeOf($rate);
        $globalFree = max(0, $globalFreeCents);
        $threshold = $rate['free_over_cents'] !== null ? max(0, (int)$rate['free_over_cents']) : $globalFree;
        $free = $threshold > 0 && $netBeforeCents >= $threshold;
        $cost = max(0, (int)$rate['cost_cents']);
        $etaMin = $this->cleanEta($rate['eta_min_days'] ?? null) ?? 2;
        $etaMax = $this->cleanEta($rate['eta_max_days'] ?? null) ?? 5;
        if ($etaMax < $etaMin) $etaMax = $etaMin;
        $etaNote = '';
        if ($department === '08') {
            $etaNote = $municipality === '08001'
                ? 'Entrega local en Barranquilla: 1 a 2 días hábiles.'
                : 'Entrega en Atlántico: 1 a 2 días hábiles.';
        }
        if ($municipality === '08001' && $weightGrams >= 20000) {
            $etaMin = 2;
            $etaMax = 2;
            $etaNote = 'Artículo de gran tamaño: la entrega en Barranquilla se coordina en 2 días hábiles.';
        }
        $etaLabel = $etaMin === $etaMax ? $etaMin . ' días' : $etaMin . '-' . $etaMax . ' días';
        return [
            'ok' => true,
            'covered' => true,
            'rate_id' => (int)$rate['id'],
            'rate_name' => (string)$rate['name'],
            'carrier' => (string)($rate['carrier'] ?? ''),
            'scope' => $source,
            'source' => $source,
            'base_cost_cents' => $cost,
            'cost_cents' => $cost,
            'shipping_cents' => $free ? 0 : $cost,
            'free_shipping' => $free,
            'free_over_cents' => $threshold,
            'free_shipping_enabled' => $threshold > 0,
            'eta_min_days' => $etaMin,
            'eta_max_days' => $etaMax,
            'eta_label' => $etaLabel,
            'eta_note' => $etaNote,
            'weight_grams' => $weightGrams,
            'cod_enabled' => (int)($rate['cod_enabled'] ?? 1) === 1,
            'department_code' => $department,
            'department' => $departmentName,
            'municipality_code' => $municipality,
            'municipality' => $municipalityName,
            'city' => $municipalityName,
            'region' => $departmentName,
            'location_selected' => $selected,
            'location_missing' => !$selected,
        ];
    }

    private function quoteError(string $message): array
    {
        return [
            'ok' => false,
            'covered' => false,
            'message' => $message,
            'shipping_cents' => 0,
            'free_shipping' => false,
            'free_over_cents' => 0,
            'location_selected' => false,
            'location_missing' => true,
        ];
    }

    private function findRate(string $department, string $municipality, int $weightGrams = 0): ?array
    {
        $db = Database::instance();
        $sql = 'SELECT * FROM store_shipping_rates WHERE site_id = @site_id AND is_active = 1';
        $params = [];
        if ($municipality !== '') {
            $sql .= ' AND ((municipality_code = :municipality) OR (municipality_code IS NULL AND department_code = :department) OR (department_code IS NULL AND municipality_code IS NULL))';
            $params = ['municipality' => $municipality, 'department' => $department];
        } elseif ($department !== '') {
            $sql .= ' AND ((municipality_code IS NULL AND department_code = :department) OR (department_code IS NULL AND municipality_code IS NULL))';
            $params = ['department' => $department];
        } else {
            $sql .= ' AND department_code IS NULL AND municipality_code IS NULL';
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        if (!$rows) return null;
        $weight = max(0, $weightGrams);
        $score = static function (array $row) use ($department, $municipality, $weight): array {
            $loc = 2;
            if ($municipality !== '' && (string)($row['municipality_code'] ?? '') === $municipality) $loc = 0;
            elseif ($department !== '' && (string)($row['department_code'] ?? '') === $department) $loc = 1;
            $min = max(0, (int)($row['weight_min_grams'] ?? 0));
            $max = $row['weight_max_grams'] === null || $row['weight_max_grams'] === '' ? null : max(0, (int)$row['weight_max_grams']);
            $match = ($min <= $weight && ($max === null || $max >= $weight)) ? 0 : 1;
            // 0 = tarifa específica por peso; 1 = tarifa genérica de respaldo.
            $generic = ($min === 0 && $max === null) ? 1 : 0;
            return [$match, $loc, $generic, -$min, (int)($row['sort_order'] ?? 0), (int)($row['id'] ?? 0)];
        };
        usort($rows, static function (array $a, array $b) use ($score): int {
            return $score($a) <=> $score($b);
        });
        return $rows[0] ?: null;
    }

    public function overview(): array
    {
        $departments = $this->departments();
        $rates = $this->rates();
        $national = null;
        $departmentRates = [];
        $municipalityRates = [];
        foreach ($rates as $rate) {
            if ((int)$rate['is_active'] !== 1) continue;
            $scope = $this->scopeOf($rate);
            if ($scope === 'nacional') $national = $rate;
            elseif ($scope === 'departamento') $departmentRates[(string)$rate['department_code']] = $rate;
            elseif ($scope === 'municipio') $municipalityRates[(string)$rate['municipality_code']] = $rate;
        }
        $covered = 0;
        $coveredMunicipalities = 0;
        $missing = [];
        foreach ($departments as &$department) {
            $code = (string)$department['code'];
            $rate = $departmentRates[$code] ?? $national;
            $department['covered'] = !empty($rate) && (int)($rate['is_active'] ?? 0) === 1;
            $department['rate_id'] = $rate['id'] ?? null;
            $department['rate_name'] = $rate['name'] ?? '';
            $department['rate_source'] = $rate ? $this->scopeOf($rate) : 'sin_cobertura';
            $department['cost_cents'] = $rate['cost_cents'] ?? null;
            $department['free_over_cents'] = $rate['free_over_cents'] ?? null;
            $department['eta_min_days'] = $rate['eta_min_days'] ?? null;
            $department['eta_max_days'] = $rate['eta_max_days'] ?? null;
            $department['exceptions'] = 0;
            if ($department['covered']) {
                $covered++;
                $coveredMunicipalities += (int)$department['municipalities'];
            } else {
                $missing[] = ['code' => $code, 'name' => $department['name']];
            }
        }
        unset($department);
        foreach ($municipalityRates as $rate) {
            $dept = (string)($rate['department_code'] ?? '');
            foreach ($departments as &$department) {
                if ((string)$department['code'] === $dept) { $department['exceptions']++; break; }
            }
            unset($department);
        }
        $total = count($departments);
        $settings = [];
        try { $settings = (new BrebService())->settings(); } catch (\Throwable $e) {}
        return [
            'stats' => [
                'departments' => $total,
                'municipalities' => (int)array_sum(array_map(fn($d) => (int)$d['municipalities'], $departments)),
                'covered_departments' => $covered,
                'covered_municipalities' => $coveredMunicipalities,
                'coverage_pct' => $total > 0 ? round($covered / $total * 100, 1) : 0,
                'active_rates' => count(array_filter($rates, fn($r) => (int)$r['is_active'] === 1)),
                'department_rates' => count($departmentRates),
                'municipality_rates' => count($municipalityRates),
            ],
            'defaults' => [
                'free_over_cents' => (int)($settings['free_shipping_net_cents'] ?? 30000000),
                'cost_cents' => (int)($settings['shipping_cents'] ?? 1500000),
            ],
            'national' => $national,
            'departments' => $departments,
            'rates' => $rates,
            'missing' => $missing,
        ];
    }

    public function municipalitiesWithRates(string $departmentCode): array
    {
        $this->ensureTables();
        $departmentCode = $this->cleanCode($departmentCode, 2);
        if ($departmentCode === '') return ['department' => null, 'municipalities' => [], 'national' => null];
        $departments = $this->departments();
        $department = null;
        foreach ($departments as $row) if ((string)$row['code'] === $departmentCode) { $department = $row; break; }
        if (!$department) return ['department' => null, 'municipalities' => [], 'national' => null];
        $rates = $this->rates();
        $national = null;
        $deptRate = null;
        $exact = [];
        foreach ($rates as $rate) {
            if ((int)$rate['is_active'] !== 1) continue;
            $scope = $this->scopeOf($rate);
            if ($scope === 'nacional') $national = $rate;
            elseif ($scope === 'departamento' && (string)$rate['department_code'] === $departmentCode) $deptRate = $rate;
            elseif ($scope === 'municipio' && (string)$rate['department_code'] === $departmentCode) $exact[(string)$rate['municipality_code']] = $rate;
        }
        $municipalities = $this->municipalities($departmentCode);
        foreach ($municipalities as &$municipality) {
            $code = (string)$municipality['code'];
            $rate = $exact[$code] ?? $deptRate ?? $national;
            $municipality['rate_id'] = $rate['id'] ?? null;
            $municipality['rate_name'] = $rate['name'] ?? '';
            $municipality['rate_source'] = $rate ? $this->scopeOf($rate) : 'sin_cobertura';
            $municipality['cost_cents'] = $rate['cost_cents'] ?? null;
            $municipality['free_over_cents'] = $rate['free_over_cents'] ?? null;
            $municipality['eta_min_days'] = $rate['eta_min_days'] ?? null;
            $municipality['eta_max_days'] = $rate['eta_max_days'] ?? null;
            $municipality['covered'] = !empty($rate) && (int)($rate['is_active'] ?? 0) === 1;
            $municipality['has_exception'] = isset($exact[$code]);
        }
        unset($municipality);
        return ['department' => $department, 'municipalities' => $municipalities, 'national' => $national, 'department_rate' => $deptRate];
    }

    private function scopeOf(array $rate): string
    {
        if (!empty($rate['municipality_code'])) return 'municipio';
        if (!empty($rate['department_code'])) return 'departamento';
        return 'nacional';
    }

    private function municipalityRow(string $code): ?array
    {
        $stmt = Database::instance()->prepare('SELECT code, department_code, name FROM shipping_municipalities WHERE code = :code LIMIT 1');
        $stmt->execute(['code' => $code]);
        return $stmt->fetch() ?: null;
    }

    private function departmentExists(string $code): bool
    {
        $stmt = Database::instance()->prepare('SELECT 1 FROM shipping_departments WHERE code = :code LIMIT 1');
        $stmt->execute(['code' => $code]);
        return (bool)$stmt->fetchColumn();
    }

    private function departmentName(string $code): string
    {
        $stmt = Database::instance()->prepare('SELECT name FROM shipping_departments WHERE code = :code LIMIT 1');
        $stmt->execute(['code' => $code]);
        return (string)($stmt->fetchColumn() ?: '');
    }

    private function cleanCode(string $value, int $length): string
    {
        $value = preg_replace('/\D+/', '', trim($value));
        return strlen($value) === $length ? $value : '';
    }

    private function cleanEta($value): ?int
    {
        if ($value === null || $value === '') return null;
        return max(1, min(60, (int)$value));
    }
}
