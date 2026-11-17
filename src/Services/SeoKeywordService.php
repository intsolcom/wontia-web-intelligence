<?php
namespace App\Services;

use App\Core\Config;
use App\Core\Database;

class SeoKeywordService
{
    private const MAX_KEYWORDS = 8;
    private static bool $ready = false;
    private static array $cache = [];

    public function ensureTables(): void
    {
        if (self::$ready) return;
        Database::instance()->exec("CREATE TABLE IF NOT EXISTS seo_keyword_records (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL DEFAULT 1,
            entity_type VARCHAR(20) NOT NULL DEFAULT 'page',
            entity_key VARCHAR(191) NOT NULL,
            entity_url VARCHAR(500) NOT NULL DEFAULT '',
            title VARCHAR(255) NOT NULL DEFAULT '',
            primary_keyword VARCHAR(120) NOT NULL DEFAULT '',
            keywords JSON NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            source VARCHAR(20) NOT NULL DEFAULT 'manual',
            language VARCHAR(5) NOT NULL DEFAULT 'es',
            created_by VARCHAR(120) NULL,
            updated_by VARCHAR(120) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_seo_keyword_entity (site_id, entity_type, entity_key),
            KEY idx_seo_keyword_status (site_id, status, entity_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        self::$ready = true;
    }

    public function overview(): array
    {
        $this->ensureTables();
        $db = Database::instance();
        $stats = ['draft' => 0, 'approved' => 0, 'rejected' => 0, 'products' => 0, 'pages' => 0];
        $rows = $db->query("SELECT entity_type, status, COUNT(*) AS total FROM seo_keyword_records WHERE site_id = @site_id GROUP BY entity_type, status")->fetchAll();
        foreach ($rows as $row) {
            $status = (string)$row['status'];
            if (isset($stats[$status])) $stats[$status] += (int)$row['total'];
            if ((string)$row['entity_type'] === 'product') $stats['products'] += (int)$row['total'];
            if ((string)$row['entity_type'] === 'page') $stats['pages'] += (int)$row['total'];
        }
        $totalProducts = 0;
        try {
            $totalProducts = (int)$db->query("SELECT COUNT(*) FROM ciu_documents WHERE site_id = @site_id AND status = 'active' AND doc_type = 'product'")->fetchColumn();
        } catch (\Throwable $e) {
        }
        return [
            'stats' => $stats,
            'catalog_products' => $totalProducts,
            'coverage' => $totalProducts > 0 ? round($stats['products'] / $totalProducts * 100, 1) : 0,
            'approved_coverage' => $totalProducts > 0 ? round($stats['approved'] / $totalProducts * 100, 1) : 0,
            'limit' => self::MAX_KEYWORDS,
        ];
    }

    public function listRecords(string $query = '', string $status = '', string $type = '', int $page = 1, int $perPage = 50): array
    {
        $this->ensureTables();
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $where = ['site_id = @site_id'];
        $params = [];
        if ($query !== '') {
            $where[] = '(title LIKE :q1 OR entity_key LIKE :q2 OR primary_keyword LIKE :q3)';
            $params['q1'] = '%' . $query . '%';
            $params['q2'] = '%' . $query . '%';
            $params['q3'] = '%' . $query . '%';
        }
        if (in_array($status, ['draft', 'approved', 'rejected'], true)) {
            $where[] = 'status = :status';
            $params['status'] = $status;
        }
        if (in_array($type, ['page', 'product'], true)) {
            $where[] = 'entity_type = :type';
            $params['type'] = $type;
        }
        $sql = 'SELECT id, entity_type, entity_key, entity_url, title, primary_keyword, keywords, status, source, language, updated_at FROM seo_keyword_records WHERE ' . implode(' AND ', $where) . ' ORDER BY updated_at DESC, id DESC LIMIT ' . (($page - 1) * $perPage) . ',' . $perPage;
        $stmt = Database::instance()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['keywords'] = json_decode((string)($row['keywords'] ?? '[]'), true) ?: [];
        }
        unset($row);
        $countSql = 'SELECT COUNT(*) FROM seo_keyword_records WHERE ' . implode(' AND ', $where);
        $countStmt = Database::instance()->prepare($countSql);
        $countStmt->execute($params);
        return ['items' => $rows, 'total' => (int)$countStmt->fetchColumn(), 'page' => $page, 'per_page' => $perPage];
    }

    public function saveManual(array $data, string $user = 'admin'): array
    {
        $this->ensureTables();
        $type = in_array((string)($data['entity_type'] ?? ''), ['page', 'product'], true) ? (string)$data['entity_type'] : 'page';
        $path = $this->normalizePath((string)($data['path'] ?? ''));
        if ($path === '') return ['ok' => false, 'message' => 'Indica la ruta o URL del producto o página.'];
        $keywords = $this->sanitizeKeywords((string)($data['keywords'] ?? ''));
        if (!$keywords) return ['ok' => false, 'message' => 'Añade al menos una palabra clave válida.'];
        $status = (string)($data['status'] ?? 'draft') === 'approved' ? 'approved' : 'draft';
        $url = trim((string)($data['url'] ?? ''));
        if ($url === '') $url = rtrim((string)Config::get('APP_URL', 'https://sonika.com.co'), '/') . '/' . ltrim($path, '/');
        $title = mb_substr(trim(strip_tags((string)($data['title'] ?? ''))), 0, 255);
        try {
            Database::instance()->prepare("INSERT INTO seo_keyword_records (site_id, entity_type, entity_key, entity_url, title, primary_keyword, keywords, status, source, language, created_by, updated_by, created_at, updated_at) VALUES (@site_id, ?, ?, ?, ?, ?, ?, ?, 'manual', ?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE entity_url = VALUES(entity_url), title = VALUES(title), primary_keyword = VALUES(primary_keyword), keywords = VALUES(keywords), status = VALUES(status), source = 'manual', updated_by = VALUES(updated_by), updated_at = NOW()")
                ->execute([$type, $path, $url, $title, $keywords[0], json_encode($keywords, JSON_UNESCAPED_UNICODE), $status, (string)($data['language'] ?? 'es'), $user, $user]);
            self::$cache = [];
            return ['ok' => true, 'message' => $status === 'approved' ? 'Keywords guardadas y aprobadas.' : 'Keywords guardadas como borrador.', 'status' => $status, 'keywords' => $keywords];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudieron guardar las keywords.'];
        }
    }

    public function generatePreview(int $limit = 20): array
    {
        $this->ensureTables();
        $products = $this->products($limit);
        $items = [];
        foreach ($products as $product) {
            $key = $this->productKey($product);
            if ($key === '') continue;
            $items[] = ['entity_key' => $key, 'title' => (string)$product['title'], 'keywords' => $this->keywordsForProduct($product)];
        }
        return ['items' => $items, 'limit' => self::MAX_KEYWORDS];
    }

    public function generateBatch(int $afterId = 0, int $limit = 50, string $user = 'admin'): array
    {
        $this->ensureTables();
        $limit = max(1, min(100, $limit));
        $products = $this->products($limit, max(0, $afterId));
        $db = Database::instance();
        $created = 0;
        $skipped = 0;
        $lastId = $afterId;
        $samples = [];
        foreach ($products as $product) {
            $lastId = max($lastId, (int)($product['id'] ?? 0));
            $key = $this->productKey($product);
            if ($key === '') { $skipped++; continue; }
            $check = $db->prepare("SELECT id FROM seo_keyword_records WHERE site_id = @site_id AND entity_type = 'product' AND entity_key = ? LIMIT 1");
            $check->execute([$key]);
            if ($check->fetch()) { $skipped++; continue; }
            $keywords = $this->keywordsForProduct($product);
            if (!$keywords) { $skipped++; continue; }
            try {
                $db->prepare("INSERT INTO seo_keyword_records (site_id, entity_type, entity_key, entity_url, title, primary_keyword, keywords, status, source, language, created_by, updated_by, created_at, updated_at) VALUES (@site_id, 'product', ?, ?, ?, ?, ?, 'draft', 'generated', 'es', ?, ?, NOW(), NOW())")
                    ->execute([$key, (string)($product['url'] ?? ''), (string)$product['title'], $keywords[0], json_encode($keywords, JSON_UNESCAPED_UNICODE), $user, $user]);
                $created++;
                if (count($samples) < 8) $samples[] = ['entity_key' => $key, 'title' => (string)$product['title'], 'keywords' => $keywords];
            } catch (\Throwable $e) {
                $skipped++;
            }
        }
        self::$cache = [];
        return ['ok' => true, 'processed' => count($products), 'created' => $created, 'skipped' => $skipped, 'last_id' => $lastId, 'samples' => $samples];
    }

    public function approve(array $ids, string $user = 'admin'): int
    {
        $this->ensureTables();
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) return 0;
        $ids = array_slice($ids, 0, 100);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::instance()->prepare("UPDATE seo_keyword_records SET status = 'approved', updated_by = ?, updated_at = NOW() WHERE site_id = @site_id AND status = 'draft' AND id IN ($placeholders)");
        $stmt->execute(array_merge([$user], $ids));
        self::$cache = [];
        return $stmt->rowCount();
    }

    public function reject(array $ids, string $user = 'admin'): int
    {
        $this->ensureTables();
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) return 0;
        $ids = array_slice($ids, 0, 100);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::instance()->prepare("UPDATE seo_keyword_records SET status = 'rejected', updated_by = ?, updated_at = NOW() WHERE site_id = @site_id AND status IN ('draft','approved') AND id IN ($placeholders)");
        $stmt->execute(array_merge([$user], $ids));
        self::$cache = [];
        return $stmt->rowCount();
    }

    public function keywordsForPath(string $path): array
    {
        $key = $this->normalizePath($path);
        if ($key === '') return [];
        $siteId = (int)Config::get('SITE_ID', '1');
        $cacheKey = $siteId . '|' . $key;
        if (array_key_exists($cacheKey, self::$cache)) return self::$cache[$cacheKey];
        try {
            $stmt = Database::instance()->prepare("SELECT keywords FROM seo_keyword_records WHERE site_id = @site_id AND entity_key = ? AND status = 'approved' LIMIT 1");
            $stmt->execute([$key]);
            $row = $stmt->fetch();
            $keywords = $row ? (json_decode((string)($row['keywords'] ?? '[]'), true) ?: []) : [];
            $keywords = array_slice($this->sanitizeKeywords(implode(',', $keywords)), 0, self::MAX_KEYWORDS);
        } catch (\Throwable $e) {
            $keywords = [];
        }
        self::$cache[$cacheKey] = $keywords;
        return $keywords;
    }

    public function sanitizeKeywords(string $input): array
    {
        $parts = preg_split('/[\n,;]+/u', $input) ?: [];
        $seen = [];
        $out = [];
        foreach ($parts as $part) {
            $value = html_entity_decode(strip_tags((string)$part), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
            $value = trim($value, " \t\n\r\0\x0B\"'“”");
            $value = mb_strtolower($value);
            $value = preg_replace('/[^\pL\pN\s\-+.]/u', '', $value) ?? $value;
            $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
            if ($value === '' || mb_strlen($value) < 2 || mb_strlen($value) > 80) continue;
            if (preg_match('/\b(mejor del mundo|numero 1|#1|garantizado|gratis|estafa)\b/iu', $value)) continue;
            $key = mb_strtolower($value);
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $out[] = $value;
            if (count($out) >= self::MAX_KEYWORDS) break;
        }
        return $out;
    }

    private function products(int $limit, int $afterId = 0): array
    {
        $limit = max(1, min(100, $limit));
        $stmt = Database::instance()->prepare("SELECT id, ref, title, url, metadata FROM ciu_documents WHERE site_id = @site_id AND status = 'active' AND doc_type = 'product' AND id > ? ORDER BY id ASC LIMIT " . $limit);
        $stmt->execute([$afterId]);
        return $stmt->fetchAll();
    }

    private function productKey(array $product): string
    {
        $meta = json_decode((string)($product['metadata'] ?? '{}'), true) ?: [];
        $handle = trim((string)($meta['handle'] ?? ''));
        if ($handle === '') {
            $urlPath = (string)parse_url((string)($product['url'] ?? ''), PHP_URL_PATH);
            if (preg_match('~/producto/([^/]+)~u', $urlPath, $m)) $handle = urldecode($m[1]);
        }
        $handle = trim($handle, '/');
        return $handle !== '' ? 'producto/' . $handle : '';
    }

    private function keywordsForProduct(array $product): array
    {
        $meta = json_decode((string)($product['metadata'] ?? '{}'), true) ?: [];
        $title = trim((string)($product['title'] ?? ''));
        if ($title === '') return [];
        $generic = $this->genericFor($title);
        $plural = $this->pluralize($generic);
        $brand = $this->brandFor($title, $generic, (string)($meta['vendor'] ?? ''));
        $model = $this->modelFor($title, $generic, $brand);
        $terms = [$generic];
        if ($brand !== '') {
            $terms[] = $generic . ' ' . $brand;
            $terms[] = $plural . ' ' . $brand;
        }
        $terms[] = 'comprar ' . $generic;
        $terms[] = $generic . ' precio';
        $terms[] = $generic . ' colombia';
        if ($model !== '') {
            $terms[] = $generic . ' ' . $model;
            if ($brand !== '') $terms[] = $brand . ' ' . $model;
        }
        return array_slice($this->sanitizeKeywords(implode(',', $terms)), 0, self::MAX_KEYWORDS);
    }

    private function genericFor(string $title): string
    {
        $text = $this->fold($title);
        $generics = [
            'guitarra electrica' => 'guitarra electrica', 'guitarra acustica' => 'guitarra acustica',
            'guitarra clasica' => 'guitarra clasica', 'guitarra' => 'guitarra', 'bajo electrico' => 'bajo electrico',
            'piano digital' => 'piano digital', 'piano' => 'piano', 'teclado' => 'teclado', 'bateria' => 'bateria',
            'bongo cajon' => 'bongo cajon', 'bongo' => 'bongo', 'cajon' => 'cajon', 'timbal' => 'timbal',
            'conga' => 'conga', 'acordeon' => 'acordeon', 'microfono' => 'microfono',
            'interfaz de audio' => 'interfaz de audio', 'consola de sonido' => 'consola de sonido',
            'mezcladora' => 'mezcladora', 'controlador dj' => 'controlador dj', 'controlador' => 'controlador',
            'cable' => 'cable', 'violin' => 'violin', 'ukelele' => 'ukelele', 'amplificador' => 'amplificador',
            'cabina' => 'cabina', 'parlante' => 'parlante', 'audifono' => 'audifono', 'pedal' => 'pedal',
            'iluminacion' => 'iluminacion', 'percusion' => 'percusion', 'acople' => 'acople',
            'soporte' => 'soporte', 'tarima' => 'tarima', 'truss' => 'truss', 'estuche' => 'estuche',
            'funda' => 'funda', 'stand' => 'stand', 'baqueta' => 'baqueta', 'parche' => 'parche',
            'cuerdas' => 'cuerdas', 'conector' => 'conector', 'adaptador' => 'adaptador', 'fuente' => 'fuente',
            'atril' => 'atril', 'banqueta' => 'banqueta', 'metronomo' => 'metronomo', 'afinador' => 'afinador',
            'auricular' => 'auricular', 'cabeza movil' => 'cabeza movil', 'laser' => 'laser',
        ];
        foreach ($generics as $needle => $label) {
            if (str_contains($text, $needle)) return $label;
        }
        $tokens = array_values(array_filter(preg_split('/\s+/u', $text) ?: [], fn($token) => mb_strlen($token) > 1));
        return $tokens ? mb_substr(implode(' ', array_slice($tokens, 0, 3)), 0, 50) : 'producto';
    }

    private function brandFor(string $title, string $generic, string $vendor): string
    {
        $vendor = $this->titleCase(trim($vendor));
        if ($vendor !== '' && mb_stripos($title, $vendor) !== false) return $vendor;
        $text = $this->fold($title);
        $pos = mb_strpos($text, $generic);
        $rest = $pos === false ? $text : mb_substr($text, $pos + mb_strlen($generic));
        foreach (preg_split('/\s+/u', trim($rest)) ?: [] as $token) {
            $token = trim($token, "-_/.,");
            if (mb_strlen($token) < 2 || preg_match('/^\d+$/', $token)) continue;
            if (preg_match('/\b(combo|kit|set|pack|en|color|con|para|de|la|el|los|las|nuevo|nueva)\b/iu', $token)) continue;
            return $this->titleCase($token);
        }
        return $vendor;
    }

    private function pluralize(string $generic): string
    {
        $parts = explode(' ', $generic);
        $first = $parts[0];
        $adjectives = ['electrica' => 'electricas', 'electrico' => 'electricos', 'acustica' => 'acusticas', 'acustico' => 'acusticos', 'clasica' => 'clasicas', 'clasico' => 'clasicos', 'digital' => 'digitales'];
        if (isset($parts[1], $adjectives[$parts[1]])) $parts[1] = $adjectives[$parts[1]];
        $plurals = [
            'guitarra' => 'guitarras', 'bajo' => 'bajos', 'piano' => 'pianos', 'teclado' => 'teclados',
            'bateria' => 'baterias', 'bongo' => 'bongos', 'cajon' => 'cajones', 'timbal' => 'timbales',
            'conga' => 'congas', 'acordeon' => 'acordeones', 'microfono' => 'microfonos',
            'consola' => 'consolas', 'mezcladora' => 'mezcladoras', 'controlador' => 'controladores',
            'cable' => 'cables', 'violin' => 'violines', 'ukelele' => 'ukeleles', 'interfaz' => 'interfaces',
            'amplificador' => 'amplificadores', 'cabina' => 'cabinas', 'parlante' => 'parlantes',
            'audifono' => 'audifonos', 'pedal' => 'pedales', 'acople' => 'acoples', 'soporte' => 'soportes',
            'tarima' => 'tarimas', 'estuche' => 'estuches', 'funda' => 'fundas', 'stand' => 'stands',
            'baqueta' => 'baquetas', 'parche' => 'parches', 'conector' => 'conectores',
            'adaptador' => 'adaptadores', 'fuente' => 'fuentes', 'atril' => 'atriles',
            'banqueta' => 'banquetas', 'metronomo' => 'metronomos', 'afinador' => 'afinadores',
            'auricular' => 'auriculares',
        ];
        if (isset($plurals[$first])) $parts[0] = $plurals[$first];
        return implode(' ', $parts);
    }

    private function modelFor(string $title, string $generic, string $brand): string
    {
        $text = $title;
        foreach ([$generic, $brand] as $remove) {
            if ($remove !== '') $text = preg_replace('/\b' . preg_quote($remove, '/') . '\b/iu', ' ', $text) ?? $text;
        }
        $text = preg_replace('/\b(EN COLOR|COLOR|NEGRO|NEGRA|BLANCO|BLANCA|MARRON|MARRÓN|ROJO|ROJA|AZUL|VERDE|NATURAL|INCLUYE|ADAPTADOR|CON|PARA|DE|LA|EL|LOS|LAS)\b/iu', ' ', $text) ?? $text;
        $tokens = array_values(array_filter(preg_split('/\s+/u', trim($text)) ?: [], function ($token) {
            return mb_strlen($token) > 1 && !preg_match('/^\d+$/', $token);
        }));
        $model = implode(' ', array_slice($tokens, -3));
        return mb_strlen($model) > 50 ? mb_substr($model, 0, 47) . '...' : $model;
    }

    private function normalizePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') return '';
        $parsed = parse_url($path, PHP_URL_PATH);
        if (is_string($parsed)) $path = $parsed;
        $path = trim(rawurldecode($path), '/');
        $path = preg_replace('/\s+/u', ' ', $path) ?? $path;
        if ($path === '') $path = 'home';
        return mb_substr($path, 0, 190);
    }

    private function fold(string $value): string
    {
        return strtr(mb_strtolower($value), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    }

    private function titleCase(string $value): string
    {
        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }
}
