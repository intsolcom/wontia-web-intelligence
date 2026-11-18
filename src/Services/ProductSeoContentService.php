<?php
namespace App\Services;

use App\Core\Config;
use App\Core\Database;

/**
 * ProductSeoContentService — bloque editorial complementario por producto.
 *
 * No reemplaza la descripción original del fabricante. Añade una guía rápida,
 * casos de uso y FAQ visibles en la ficha, más un meta description propio.
 * Se genera con DeepSeek desde datos reales del producto y se publica solo
 * cuando pasa las validaciones.
 */
class ProductSeoContentService
{
    public function ensureTable(): void
    {
        Database::instance()->exec("CREATE TABLE IF NOT EXISTS product_seo_content (
            id INT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL,
            product_id INT NOT NULL,
            intro TEXT NULL,
            use_cases_json JSON NULL,
            faq_json JSON NULL,
            meta_description VARCHAR(500) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            source VARCHAR(30) NOT NULL DEFAULT 'tia',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_site_product (site_id, product_id),
            KEY idx_site_status (site_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function forProduct(int $productId): ?array
    {
        $this->ensureTable();
        try {
            $st = Database::instance()->prepare("SELECT * FROM product_seo_content WHERE site_id=@site_id AND product_id=? AND status='published' LIMIT 1");
            $st->execute([$productId]);
            $row = $st->fetch(\PDO::FETCH_ASSOC);
            if (!$row) return null;
            $row['use_cases'] = json_decode((string)($row['use_cases_json'] ?? '[]'), true) ?: [];
            $row['faq'] = json_decode((string)($row['faq_json'] ?? '[]'), true) ?: [];
            return $row;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function raw(int $productId): ?array
    {
        $this->ensureTable();
        try {
            $st = Database::instance()->prepare('SELECT * FROM product_seo_content WHERE site_id=@site_id AND product_id=? LIMIT 1');
            $st->execute([$productId]);
            return $st->fetch(\PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function generate(int $productId, string $status = 'draft'): array
    {
        $this->ensureTable();
        $db = Database::instance();
        $st = $db->prepare("SELECT p.id, p.name, p.slug, p.sku, p.vendor, p.price_cents, p.description, p.seo_description, p.tags, p.short_description,
                c.name category_name, c.path category_path
            FROM store_products p
            LEFT JOIN store_categories c ON c.id = p.category_id AND c.site_id = p.site_id
            WHERE p.site_id=@site_id AND p.id=? LIMIT 1");
        $st->execute([$productId]);
        $p = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$p) return ['ok' => false, 'message' => 'Producto no encontrado.'];

        $plain = static function ($html, int $max = 1500): string {
            $v = strip_tags((string)$html);
            $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', (string)$v) ?? '';
            $v = trim((string)preg_replace('/\s+/u', ' ', $v));
            return mb_substr($v, 0, $max);
        };
        $name = trim((string)$p['name']);
        $brand = trim((string)$p['vendor']);
        $sku = trim((string)$p['sku']);
        $cat = trim((string)($p['category_name'] ?? ''));
        $path = trim((string)($p['category_path'] ?? ''));
        $body = $plain($p['description'], 1500);
        $seo = $plain($p['seo_description'], 500);
        $short = $plain($p['short_description'], 300);
        $tags = json_decode((string)($p['tags'] ?? '[]'), true) ?: [];
        $tags = array_slice(array_values(array_filter(array_map('strval', $tags))), 0, 10);
        $price = number_format(((int)($p['price_cents'] ?? 0)) / 100, 0, ',', '.');

        $prompt = "Producto: {$name}\n"
            . ($brand !== '' ? "Marca: {$brand}\n" : '')
            . ($sku !== '' ? "SKU: {$sku}\n" : '')
            . ($cat !== '' ? "Categoría: {$cat}" . ($path !== '' ? " (/{$path})" : '') . "\n" : '')
            . "Precio de referencia: {$price} COP (no lo repitas en el texto)\n"
            . ($tags ? 'Etiquetas: ' . implode(', ', $tags) . "\n" : '')
            . 'Descripción actual: ' . ($body !== '' ? $body : '(no disponible)') . "\n"
            . ($seo !== '' ? 'SEO description actual: ' . $seo . "\n" : '')
            . ($short !== '' ? 'Resumen actual: ' . $short . "\n" : '')
            . "\nEscribe un bloque editorial complementario para la ficha del producto en SONIKA, tienda de instrumentos musicales en Colombia.\n"
            . "Reglas estrictas:\n"
            . "- intro: 150 a 220 palabras en 2 o 3 párrafos separados por una línea en blanco. Explica qué es, para quién es y en qué contextos se usa. No repitas literalmente la descripción actual.\n"
            . "- No inventes especificaciones técnicas, medidas, materiales, potencia, conectividad, garantías, stock, precios, descuentos ni promociones. Si no hay dato, sé general y no afirmes nada técnico.\n"
            . "- use_cases: exactamente 4 frases cortas de máximo 12 palabras, con contextos reales en Colombia (casa, estudio, tarima, iglesia, colegio, ensayo, etc. según el producto).\n"
            . "- faq: exactamente 3 preguntas con respuestas de máximo 45 palabras. Solo con información disponible; no inventes datos. Enfoca en uso, cuidados o compatibilidad general.\n"
            . "- meta_description: máximo 155 caracteres, incluye el nombre del producto y una razón para comprar.\n"
            . "- Español de Colombia, tono claro y comercial, sin exageraciones ni keyword stuffing.\n"
            . "- No uses markdown ni HTML.\n"
            . 'Devuelve SOLO un JSON válido con esta forma: {"intro":"...","use_cases":["...","...","...","..."],"faq":[{"q":"...","a":"..."},{"q":"...","a":"..."},{"q":"...","a":"..."}],"meta_description":"..."}';

        $key = (string)Config::get('DEEPSEEK_API_KEY', '');
        if ($key === '') return ['ok' => false, 'message' => 'Falta DEEPSEEK_API_KEY.'];
        $ch = curl_init('https://api.deepseek.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $key],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => 'deepseek-chat',
                'messages' => [
                    ['role' => 'system', 'content' => 'Eres redactor SEO de e-commerce en español de Colombia. No inventas datos técnicos. Respondes solo con JSON válido.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.45,
                'max_tokens' => 1300,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 90,
        ]);
        $raw = (string)curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = json_decode($raw, true);
        $content = is_array($data) ? (string)($data['choices'][0]['message']['content'] ?? '') : '';
        $content = preg_replace('/^```(?:json)?\s*/i', '', trim($content));
        $content = preg_replace('/\s*```$/', '', (string)$content);
        $start = strpos((string)$content, '{');
        $end = strrpos((string)$content, '}');
        if ($start !== false && $end !== false && $end > $start) $content = substr((string)$content, $start, $end - $start + 1);
        $json = json_decode((string)$content, true);
        if ($http !== 200 || !is_array($json)) {
            return ['ok' => false, 'message' => 'Respuesta IA inválida.', 'http' => $http, 'raw' => mb_substr($raw, 0, 300)];
        }

        $intro = trim((string)($json['intro'] ?? ''));
        $intro = preg_replace('/^#{1,6}\s*/m', '', $intro) ?? $intro;
        $intro = trim(str_replace(['**', '__'], '', $intro));
        $introWords = str_word_count(strip_tags($intro));
        $meta = trim((string)($json['meta_description'] ?? ''));
        $meta = trim((string)preg_replace('/\s+/', ' ', strip_tags($meta)));
        if (mb_strlen($meta) > 155) $meta = rtrim(mb_substr($meta, 0, 155), " ,.;:") . '…';
        $useCases = [];
        foreach ((array)($json['use_cases'] ?? []) as $u) {
            $u = trim((string)preg_replace('/\s+/', ' ', strip_tags((string)$u)));
            if ($u !== '') $useCases[] = mb_substr($u, 0, 140);
        }
        $faq = [];
        foreach ((array)($json['faq'] ?? []) as $f) {
            if (!is_array($f)) continue;
            $q = trim((string)preg_replace('/\s+/', ' ', strip_tags((string)($f['q'] ?? ''))));
            $a = trim((string)preg_replace('/\s+/', ' ', strip_tags((string)($f['a'] ?? ''))));
            if ($q !== '' && $a !== '') $faq[] = ['q' => mb_substr($q, 0, 220), 'a' => mb_substr($a, 0, 420)];
        }
        $errors = [];
        if ($introWords < 90) $errors[] = 'intro corta (' . $introWords . ' palabras)';
        if (count($useCases) < 3) $errors[] = 'pocos casos de uso';
        if (count($faq) < 2) $errors[] = 'pocas FAQ';
        if (mb_strlen($meta) < 60) $errors[] = 'meta description corta';
        if ($errors) {
            return ['ok' => false, 'message' => 'Validación: ' . implode(', ', $errors), 'json' => $json, 'http' => $http];
        }

        $save = $db->prepare("INSERT INTO product_seo_content (site_id, product_id, intro, use_cases_json, faq_json, meta_description, status, source)
            VALUES (@site_id, ?, ?, ?, ?, ?, ?, 'tia')
            ON DUPLICATE KEY UPDATE intro=VALUES(intro), use_cases_json=VALUES(use_cases_json), faq_json=VALUES(faq_json), meta_description=VALUES(meta_description), status=VALUES(status), source='tia', updated_at=NOW()");
        $save->execute([
            $productId,
            $intro,
            json_encode(array_slice($useCases, 0, 4), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            json_encode(array_slice($faq, 0, 3), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $meta,
            $status,
        ]);
        return [
            'ok' => true,
            'product_id' => $productId,
            'name' => $name,
            'intro_words' => $introWords,
            'use_cases' => count($useCases),
            'faq' => count($faq),
            'meta_len' => mb_strlen($meta),
            'tokens' => (int)($data['usage']['total_tokens'] ?? 0),
            'model' => 'deepseek-chat',
            'intro' => $intro,
            'meta' => $meta,
        ];
    }

    public function publish(int $productId): bool
    {
        $this->ensureTable();
        return Database::instance()->prepare("UPDATE product_seo_content SET status='published' WHERE site_id=@site_id AND product_id=?")->execute([$productId]);
    }

    /**
     * Productos recomendados para una categoría: primero las fichas con guía
     * publicada y, si faltan, los más vistos en los últimos 90 días.
     * El resultado se cachea 6 horas por conjunto de rutas.
     */
    public function categoryHighlights(array $paths, int $limit = 3): array
    {
        $this->ensureTable();
        $paths = array_values(array_filter(array_map('strval', $paths)));
        if (!$paths) return [];
        $limit = max(1, min(6, $limit));
        $db = Database::instance();
        $in = implode(',', array_fill(0, count($paths), '?'));
        $rows = [];
        try {
            $st = $db->prepare("SELECT p.slug, p.name FROM store_products p
                JOIN store_categories c ON c.id=p.category_id AND c.site_id=p.site_id
                JOIN product_seo_content s ON s.product_id=p.id AND s.site_id=p.site_id AND s.status='published'
                WHERE p.site_id=@site_id AND c.path IN ($in) AND p.status='active' AND p.visibility='public'
                ORDER BY p.id DESC LIMIT $limit");
            $st->execute($paths);
            $rows = $st->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $rows = [];
        }
        if (count($rows) >= $limit) return $rows;

        $cacheFile = ROOT_DIR . '/cache/seo-cat-highlights-' . md5(implode('|', $paths)) . '.json';
        $top = [];
        if (is_file($cacheFile) && (time() - (int)filemtime($cacheFile)) < 21600) {
            $top = json_decode((string)file_get_contents($cacheFile), true) ?: [];
        }
        if (!$top) {
            try {
                $st = $db->prepare("SELECT p.slug, p.name, COUNT(*) v
                    FROM seo_live_requests r
                    JOIN store_products p ON p.slug COLLATE utf8mb4_unicode_ci = SUBSTRING(r.path, 11) AND p.site_id=r.site_id
                    JOIN store_categories c ON c.id=p.category_id AND c.site_id=p.site_id
                    WHERE r.site_id=@site_id AND r.is_internal=0 AND r.is_bot=0 AND r.is_page_view=1
                      AND r.path LIKE '/producto/%' AND r.created_at >= (NOW() - INTERVAL 90 DAY)
                      AND c.path IN ($in) AND p.status='active' AND p.visibility='public'
                    GROUP BY p.id, p.slug, p.name ORDER BY v DESC LIMIT 6");
                $st->execute($paths);
                $top = $st->fetchAll(\PDO::FETCH_ASSOC);
                if ($top) @file_put_contents($cacheFile, json_encode($top, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            } catch (\Throwable $e) {
                $top = [];
            }
        }
        $seen = [];
        foreach ($rows as $r) $seen[(string)$r['slug']] = true;
        foreach ($top as $t) {
            if (count($rows) >= $limit) break;
            $slug = (string)($t['slug'] ?? '');
            if ($slug === '' || isset($seen[$slug])) continue;
            $seen[$slug] = true;
            $rows[] = ['slug' => $slug, 'name' => (string)($t['name'] ?? '')];
        }
        return $rows;
    }


    public function stats(): array
    {
        $this->ensureTable();
        $db = Database::instance();
        return [
            'total' => (int)$db->query('SELECT COUNT(*) FROM product_seo_content WHERE site_id=@site_id')->fetchColumn(),
            'published' => (int)$db->query("SELECT COUNT(*) FROM product_seo_content WHERE site_id=@site_id AND status='published'")->fetchColumn(),
            'draft' => (int)$db->query("SELECT COUNT(*) FROM product_seo_content WHERE site_id=@site_id AND status='draft'")->fetchColumn(),
        ];
    }
}
