<?php
namespace App\Services;

use App\Core\Database;

class CiuService
{
    private static bool $ready = false;

    public function ensureTables(): void
    {
        if (self::$ready) return;
        $db = Database::instance();
        $db->exec("CREATE TABLE IF NOT EXISTS ciu_documents (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL DEFAULT 1,
            source_key VARCHAR(80) NOT NULL,
            doc_type VARCHAR(30) NOT NULL,
            ref VARCHAR(190) NOT NULL,
            title VARCHAR(255) NOT NULL,
            url VARCHAR(700) NULL,
            content LONGTEXT NOT NULL,
            metadata JSON NULL,
            source_hash CHAR(64) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            indexed_at DATETIME NOT NULL,
            source_updated_at DATETIME NULL,
            UNIQUE KEY uk_ciu_site_ref (site_id, doc_type, ref),
            KEY idx_ciu_site_type (site_id, doc_type, status),
            KEY idx_ciu_indexed (site_id, indexed_at),
            FULLTEXT KEY ft_ciu_content (title, content)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS ciu_sources (
            id INT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL DEFAULT 1,
            source_key VARCHAR(80) NOT NULL,
            label VARCHAR(190) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            doc_count INT NOT NULL DEFAULT 0,
            last_sync_at DATETIME NULL,
            error VARCHAR(500) NULL,
            meta JSON NULL,
            UNIQUE KEY uk_ciu_source (site_id, source_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS ciu_sync_runs (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL DEFAULT 1,
            mode VARCHAR(20) NOT NULL DEFAULT 'full',
            status VARCHAR(20) NOT NULL DEFAULT 'running',
            started_at DATETIME NOT NULL,
            finished_at DATETIME NULL,
            documents INT NOT NULL DEFAULT 0,
            errors INT NOT NULL DEFAULT 0,
            summary JSON NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS ciu_questions (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL DEFAULT 1,
            question VARCHAR(1000) NOT NULL,
            answer TEXT NULL,
            matched TINYINT NOT NULL DEFAULT 0,
            refs JSON NULL,
            created_at DATETIME NOT NULL,
            KEY idx_ciu_q_site (site_id, created_at),
            KEY idx_ciu_q_matched (site_id, matched)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        self::$ready = true;
    }

    public function syncAll(): array
    {
        $this->ensureTables();
        @set_time_limit(300);
        $db = Database::instance();
        $started = date('Y-m-d H:i:s');
        $db->prepare("INSERT INTO ciu_sync_runs (site_id, mode, status, started_at) VALUES (@site_id, 'full', 'running', ?)")->execute([$started]);
        $runId = (int)$db->lastInsertId();
        $seen = [];
        $counts = [];
        $errors = [];
        $upsert = $db->prepare("INSERT INTO ciu_documents (site_id, source_key, doc_type, ref, title, url, content, metadata, source_hash, status, indexed_at, source_updated_at)
            VALUES (@site_id, :source, :type, :ref, :title, :url, :content, :meta, :hash, 'active', NOW(), :updated)
            ON DUPLICATE KEY UPDATE source_key = VALUES(source_key), title = VALUES(title), url = VALUES(url), content = VALUES(content), metadata = VALUES(metadata), source_hash = VALUES(source_hash), status = 'active', indexed_at = NOW(), source_updated_at = VALUES(source_updated_at)");

        $put = function (string $source, string $type, string $ref, string $title, string $url, string $content, array $meta, ?string $updated = null) use ($upsert, &$seen, &$counts): void {
            $content = $this->stripMicheDomain(trim($content));
            if ($content === '') return;
            $hash = hash('sha256', $type . "\n" . $ref . "\n" . $title . "\n" . $url . "\n" . $content);
            $upsert->execute([
                'source' => $source,
                'type' => $type,
                'ref' => mb_substr($ref, 0, 190),
                'title' => mb_substr($title, 0, 255),
                'url' => mb_substr($url, 0, 700),
                'content' => mb_substr($content, 0, 120000),
                'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'hash' => $hash,
                'updated' => $this->dateValue($updated),
            ]);
            $seen[$type . '|' . $ref] = true;
            $counts[$source] = ($counts[$source] ?? 0) + 1;
        };

        try {
            $this->ingestPages($put);
            $this->ingestPosts($put);
            $this->ingestBuilder($put);
            $this->ingestCatalog($put);
            $this->ingestStoreProducts($put);
        } catch (\Throwable $e) {
            $errors[] = $e->getMessage();
        }

        try {
            $db->prepare("UPDATE ciu_documents SET status = 'stale' WHERE site_id = @site_id AND status = 'active' AND indexed_at < ?")->execute([$started]);
            if (!$errors) {
                $db->prepare("DELETE FROM ciu_documents WHERE site_id = @site_id AND status = 'stale'")->execute();
            }
        } catch (\Throwable $e) {
            $errors[] = $e->getMessage();
        }

        $sources = [
            'pages' => 'Páginas y políticas',
            'sections' => 'Secciones del CMS',
            'builder' => 'Bloques del Builder',
            'catalog' => 'Catálogo de productos y categorías',
            'store' => 'Productos locales de la tienda',
            'blog' => 'Blog',
        ];
        foreach ($sources as $key => $label) {
            $db->prepare("INSERT INTO ciu_sources (site_id, source_key, label, status, doc_count, last_sync_at, error) VALUES (@site_id, ?, ?, ?, ?, NOW(), ?) ON DUPLICATE KEY UPDATE label = VALUES(label), status = VALUES(status), doc_count = VALUES(doc_count), last_sync_at = NOW(), error = VALUES(error)")
                ->execute([$key, $label, ($counts[$key] ?? 0) > 0 ? 'ok' : 'empty', (int)($counts[$key] ?? 0), $errors ? implode(' | ', array_slice($errors, 0, 3)) : null]);
        }

        $total = array_sum($counts);
        $summary = ['counts' => $counts, 'errors' => $errors];
        $db->prepare("UPDATE ciu_sync_runs SET status = ?, finished_at = NOW(), documents = ?, errors = ?, summary = ? WHERE id = ? AND site_id = @site_id")
            ->execute([$errors ? 'partial' : 'ok', $total, count($errors), json_encode($summary, JSON_UNESCAPED_UNICODE), $runId]);
        if (!$errors) {
            $this->setSetting('ciu_last_sync_at', (string)time());
            $this->setSetting('ciu_last_fingerprint', $this->sourceFingerprint());
        }
        return ['ok' => !$errors, 'run_id' => $runId, 'documents' => $total, 'counts' => $counts, 'errors' => $errors];
    }

    public function syncIfDue(int $hours = 6): array
    {
        $this->ensureTables();
        $last = (int)$this->setting('ciu_last_sync_at', '0');
        $fingerprint = $this->sourceFingerprint();
        if ($last > 0 && (time() - $last) < max(1, $hours) * 3600 && $this->setting('ciu_last_fingerprint', '') === $fingerprint) {
            return ['ok' => true, 'skipped' => true, 'documents' => 0, 'message' => 'CIU actualizado; sin cambios.'];
        }
        return $this->syncAll();
    }

    public function sourceFingerprint(): string
    {
        $parts = [];
        $root = defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 2);
        foreach ([$root . '/public/assets/uploads/_catalog/catalog.json', $root . '/templates/themes/sonika/catalog.json'] as $path) {
            if (is_file($path)) $parts[] = 'catalog:' . (int)filemtime($path);
        }
        try {
            $db = Database::instance();
            $queries = [
                "SELECT COUNT(*) c, COALESCE(MAX(updated_at), '') m FROM pages WHERE site_id = @site_id",
                "SELECT COUNT(*) c, COALESCE(MAX(s.updated_at), '') m FROM sections s JOIN pages p ON p.id = s.page_id WHERE p.site_id = @site_id",
                "SELECT COUNT(*) c, COALESCE(MAX(updated_at), '') m FROM blog_posts WHERE site_id = @site_id",
                "SELECT COUNT(*) c, COALESCE(MAX(updated_at), '') m FROM wwi_page_blocks WHERE site_id = @site_id",
            ];
            foreach ($queries as $sql) {
                try {
                    $row = $db->query($sql)->fetch();
                    $parts[] = (string)($row['c'] ?? 0) . ':' . (string)($row['m'] ?? '');
                } catch (\Throwable $e) {
                }
            }
        } catch (\Throwable $e) {
        }
        return hash('sha256', implode('|', $parts));
    }

    public function isGrounded(string $reply, string $context): bool
    {
        if ($context === '') return true;
        if (!preg_match_all('/\$\s?([0-9][0-9.,]*)/u', $reply, $m)) return true;
        $contextNumbers = [];
        if (preg_match_all('/([0-9][0-9.,]*)/u', $context, $cm)) {
            foreach ($cm[1] as $number) {
                $clean = preg_replace('/[^0-9]/', '', $number) ?? '';
                if ($clean !== '') $contextNumbers[$clean] = true;
            }
        }
        foreach ($m[1] as $number) {
            $clean = preg_replace('/[^0-9]/', '', $number) ?? '';
            if ($clean !== '' && !isset($contextNumbers[$clean])) return false;
        }
        return true;
    }

    private function setting(string $key, string $default = ''): string
    {
        try {
            $stmt = Database::instance()->prepare('SELECT `value` FROM settings WHERE `key` = ? AND site_id = @site_id LIMIT 1');
            $stmt->execute([$key]);
            $row = $stmt->fetch();
            return $row ? (string)$row['value'] : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    private function setSetting(string $key, string $value): void
    {
        try {
            Database::instance()->prepare("INSERT INTO settings (site_id, `key`, `value`) VALUES (@site_id, ?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")->execute([$key, $value]);
        } catch (\Throwable $e) {
        }
    }

    public function overview(): array
    {
        $this->ensureTables();
        $db = Database::instance();
        $byType = [];
        foreach ($db->query("SELECT doc_type, COUNT(*) c FROM ciu_documents WHERE site_id = @site_id AND status = 'active' GROUP BY doc_type ORDER BY c DESC")->fetchAll() as $r) {
            $byType[(string)$r['doc_type']] = (int)$r['c'];
        }
        $total = array_sum($byType);
        $stale = (int)$db->query("SELECT COUNT(*) FROM ciu_documents WHERE site_id = @site_id AND status = 'stale'")->fetchColumn();
        $sources = $db->query("SELECT source_key, label, status, doc_count, last_sync_at, error FROM ciu_sources WHERE site_id = @site_id ORDER BY id ASC")->fetchAll();
        $lastRun = $db->query("SELECT id, status, started_at, finished_at, documents, errors, summary FROM ciu_sync_runs WHERE site_id = @site_id ORDER BY id DESC LIMIT 1")->fetch();
        $unanswered = (int)$db->query("SELECT COUNT(*) FROM ciu_questions WHERE site_id = @site_id AND matched = 0")->fetchColumn();
        return [
            'ok' => true,
            'documents' => $total,
            'by_type' => $byType,
            'stale' => $stale,
            'sources' => array_map(function ($r) {
                return ['source_key' => (string)$r['source_key'], 'label' => (string)$r['label'], 'status' => (string)$r['status'], 'doc_count' => (int)$r['doc_count'], 'last_sync_at' => $r['last_sync_at'], 'error' => (string)($r['error'] ?? '')];
            }, $sources),
            'last_run' => $lastRun ?: null,
            'unanswered' => $unanswered,
            'generated_at' => date('c'),
        ];
    }

    public function search(string $query, int $limit = 8): array
    {
        $this->ensureTables();
        $query = trim($query);
        if ($query === '') return [];
        $limit = max(1, min(30, $limit));
        $db = Database::instance();
        $rows = [];
        try {
            $stmt = $db->prepare("SELECT id, doc_type, ref, title, url, content, metadata, MATCH(title, content) AGAINST (:q IN NATURAL LANGUAGE MODE) AS score FROM ciu_documents WHERE site_id = @site_id AND status = 'active' HAVING score > 0 ORDER BY score DESC LIMIT " . $limit);
            $stmt->execute(['q' => $query]);
            $rows = $stmt->fetchAll();
        } catch (\Throwable $e) {
        }
        if (!$rows) {
            $like = '%' . $query . '%';
            try {
                $stmt = $db->prepare("SELECT id, doc_type, ref, title, url, content, metadata, 0 AS score FROM ciu_documents WHERE site_id = @site_id AND status = 'active' AND (title LIKE :q1 OR ref LIKE :q2 OR content LIKE :q3) ORDER BY CASE WHEN title LIKE :q4 THEN 0 ELSE 1 END, indexed_at DESC LIMIT " . $limit);
                $stmt->execute(['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like]);
                $rows = $stmt->fetchAll();
            } catch (\Throwable $e) {
            }
        }
        $tokenRows = $this->tokenSearch($query, $limit);
        if ($tokenRows) {
            $merged = [];
            foreach ($tokenRows as $row) $merged[(int)$row['id']] = $row;
            foreach ($rows as $row) if (!isset($merged[(int)$row['id']])) $merged[(int)$row['id']] = $row;
            $rows = array_slice(array_values($merged), 0, $limit);
        }
        return array_map(function ($r) {
            $content = trim(preg_replace('/\s+/u', ' ', (string)$r['content']) ?? '');
            return [
                'id' => (int)$r['id'],
                'type' => (string)$r['doc_type'],
                'ref' => (string)$r['ref'],
                'title' => (string)$r['title'],
                'url' => (string)$r['url'],
                'snippet' => mb_substr($content, 0, 420),
                'score' => (float)($r['score'] ?? 0),
                'metadata' => json_decode((string)($r['metadata'] ?? '{}'), true) ?: [],
            ];
        }, $rows);
    }

    private function tokenSearch(string $query, int $limit): array
    {
        $tokens = $this->queryTokens($query);
        if (!$tokens) return [];
        $where = [];
        $params = [];
        foreach ($tokens as $token) {
            $where[] = "(title LIKE ? OR content LIKE ?)";
            $params[] = '%' . $token . '%';
            $params[] = '%' . $token . '%';
        }
        $sql = "SELECT id, doc_type, ref, title, url, content, metadata, 0 AS score FROM ciu_documents WHERE site_id = @site_id AND status = 'active' AND " . implode(' AND ', $where) . " ORDER BY CASE WHEN doc_type = 'product' THEN 0 WHEN doc_type = 'category' THEN 1 ELSE 2 END, title ASC LIMIT " . max(1, min(30, $limit));
        try {
            $stmt = Database::instance()->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function queryTokens(string $query): array
    {
        $query = mb_strtolower($query);
        $parts = preg_split('/[^a-z0-9áéíóúñü]+/u', $query) ?: [];
        $stop = ['que', 'cual', 'cuales', 'como', 'donde', 'cuando', 'tienen', 'tiene', 'hay', 'para', 'por', 'con', 'los', 'las', 'del', 'una', 'un', 'unos', 'unas', 'es', 'son', 'me', 'mi', 'tu', 'el', 'la', 'de', 'y', 'o', 'en', 'al', 'se', 'lo', 'le', 'su', 'sus'];
        $tokens = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if (mb_strlen($part) < 3 || in_array($part, $stop, true)) continue;
            if (preg_match('/^(.+?)(es|s)$/u', $part, $m) && mb_strlen($m[1]) >= 3) $part = $m[1];
            $tokens[$part] = true;
        }
        return array_slice(array_keys($tokens), 0, 4);
    }

    public function productCandidates(string $term, int $limit = 20): array
    {
        $this->ensureTables();
        $limit = max(1, min(1000, $limit));
        try {
            $stmt = Database::instance()->prepare("SELECT id, doc_type, ref, title, url, content, metadata, 0 AS score FROM ciu_documents WHERE site_id = @site_id AND status = 'active' AND doc_type = 'product' AND title LIKE ? ORDER BY title ASC LIMIT " . $limit);
            $stmt->execute(['%' . trim($term) . '%']);
            $rows = $stmt->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
        return array_map(function ($r) {
            return [
                'id' => (int)$r['id'],
                'type' => (string)$r['doc_type'],
                'ref' => (string)$r['ref'],
                'title' => (string)$r['title'],
                'url' => (string)$r['url'],
                'snippet' => mb_substr(trim((string)$r['content']), 0, 420),
                'score' => 0,
                'metadata' => json_decode((string)($r['metadata'] ?? '{}'), true) ?: [],
            ];
        }, $rows);
    }

    public function document(int $id): ?array
    {
        $this->ensureTables();
        $stmt = Database::instance()->prepare("SELECT * FROM ciu_documents WHERE id = ? AND site_id = @site_id LIMIT 1");
        $stmt->execute([$id]);
        $r = $stmt->fetch();
        if (!$r) return null;
        return [
            'id' => (int)$r['id'],
            'source_key' => (string)$r['source_key'],
            'type' => (string)$r['doc_type'],
            'ref' => (string)$r['ref'],
            'title' => (string)$r['title'],
            'url' => (string)$r['url'],
            'content' => (string)$r['content'],
            'metadata' => json_decode((string)($r['metadata'] ?? '{}'), true) ?: [],
            'status' => (string)$r['status'],
            'indexed_at' => (string)$r['indexed_at'],
            'source_updated_at' => $r['source_updated_at'],
        ];
    }

    public function documentByRef(string $ref): ?array
    {
        $this->ensureTables();
        $stmt = Database::instance()->prepare("SELECT id FROM ciu_documents WHERE site_id = @site_id AND status = 'active' AND ref = ? LIMIT 1");
        $stmt->execute([$ref]);
        $row = $stmt->fetch();
        return $row ? $this->document((int)$row['id']) : null;
    }

    public function context(string $question, int $limit = 6): array
    {
        $hits = $this->search($question, $limit);
        if (!$hits) return ['text' => '', 'sources' => []];
        $parts = [];
        $sources = [];
        foreach ($hits as $hit) {
            $doc = $this->document((int)$hit['id']);
            if (!$doc) continue;
            $content = trim($doc['content']);
            $parts[] = "### " . $doc['title'] . "\nTipo: " . $doc['type'] . "\nURL: " . $doc['url'] . "\n" . mb_substr($content, 0, 1800);
            $sources[] = ['title' => $doc['title'], 'url' => $doc['url'], 'type' => $doc['type'], 'ref' => $doc['ref']];
        }
        return ['text' => implode("\n\n", $parts), 'sources' => $sources];
    }

    public function recordQuestion(string $question, string $answer, bool $matched, array $refs = []): void
    {
        try {
            $this->ensureTables();
            Database::instance()->prepare("INSERT INTO ciu_questions (site_id, question, answer, matched, refs, created_at) VALUES (@site_id, ?, ?, ?, ?, NOW())")
                ->execute([mb_substr($question, 0, 1000), mb_substr($answer, 0, 5000), $matched ? 1 : 0, json_encode(array_slice($refs, 0, 10), JSON_UNESCAPED_UNICODE)]);
        } catch (\Throwable $e) {
        }
    }

    public function questions(int $limit = 50, bool $unmatched = false): array
    {
        $this->ensureTables();
        $limit = max(1, min(200, $limit));
        $where = $unmatched ? ' AND matched = 0' : '';
        $rows = Database::instance()->query("SELECT id, question, answer, matched, refs, created_at FROM ciu_questions WHERE site_id = @site_id" . $where . " ORDER BY id DESC LIMIT " . $limit)->fetchAll();
        return array_map(function ($r) { return ['id' => (int)$r['id'], 'question' => (string)$r['question'], 'answer' => (string)($r['answer'] ?? ''), 'matched' => (int)$r['matched'] === 1, 'refs' => json_decode((string)($r['refs'] ?? '[]'), true) ?: [], 'created_at' => (string)$r['created_at']]; }, $rows);
    }

    private function ingestPages(callable $put): void
    {
        $db = Database::instance();
        $pages = $db->query("SELECT id, title, slug, meta_description, status, updated_at FROM pages WHERE site_id = @site_id AND status = 'published' ORDER BY sort_order ASC")->fetchAll();
        foreach ($pages as $page) {
            $slug = trim((string)$page['slug'], '/');
            $url = $this->siteUrl() . '/' . ($slug === 'home' ? '' : $slug);
            $pageSections = $db->prepare("SELECT id, widget_type, title, subtitle, content, config, sort_order FROM sections WHERE page_id = ? AND is_active = 1 ORDER BY sort_order ASC");
            $pageSections->execute([(int)$page['id']]);
            $sections = $pageSections->fetchAll();
            $pageText = [];
            foreach ($sections as $s) {
                $secTitle = trim((string)($s['title'] ?? ''));
                $secText = $this->flatten([$s['subtitle'] ?? '', $s['content'] ?? '', $this->decode($s['config'] ?? '')]);
                $base = $secTitle !== '' ? $secTitle : (string)($s['widget_type'] ?? 'Sección');
                if ($secText !== '') $pageText[] = $base . ': ' . $secText;
                $put('sections', 'section', 'section:' . (int)$s['id'], trim((string)$page['title']) . ' · ' . $base, $url . '#section-' . (int)$s['id'], "Sección: " . $base . "\nPágina: " . $page['title'] . "\nURL: " . $url . "\nContenido: " . $secText, ['page' => $slug, 'widget_type' => (string)$s['widget_type'], 'section_id' => (int)$s['id']], (string)($page['updated_at'] ?? ''));
            }
            $isPolicy = (bool)preg_match('/politica|termino|garantia|envio|devolucion|pago|privacidad|cookie|promocion/i', $slug);
            $content = "Página: " . $page['title'] . "\nURL: " . $url . "\n" . ($page['meta_description'] ? "Descripción: " . $page['meta_description'] . "\n" : '') . implode("\n", $pageText);
            $put('pages', $isPolicy ? 'policy' : 'page', 'page:' . $slug, (string)$page['title'], $url, $content, ['slug' => $slug, 'sections' => count($sections)], (string)($page['updated_at'] ?? ''));
        }
    }

    private function ingestPosts(callable $put): void
    {
        try {
            $rows = Database::instance()->query("SELECT id, title, slug, excerpt, content, updated_at FROM blog_posts WHERE site_id = @site_id AND status = 'published' ORDER BY published_at DESC")->fetchAll();
        } catch (\Throwable $e) {
            return;
        }
        foreach ($rows as $row) {
            $url = $this->siteUrl() . '/blog/' . rawurlencode((string)$row['slug']);
            $content = "Artículo: " . $row['title'] . "\nURL: " . $url . "\n" . $this->text((string)($row['excerpt'] ?? '')) . "\n" . $this->text((string)($row['content'] ?? ''));
            $put('blog', 'post', 'post:' . $row['slug'], (string)$row['title'], $url, $content, ['slug' => (string)$row['slug']], (string)($row['updated_at'] ?? ''));
        }
    }

    private function ingestBuilder(callable $put): void
    {
        try {
            $rows = Database::instance()->query("SELECT b.id, b.type, b.brick_slug, b.props, p.id page_id, p.title page_title, p.slug page_slug, p.updated_at
                FROM wwi_page_blocks b
                JOIN wwi_page_slots s ON s.id = b.slot_id
                JOIN wwi_page_columns c ON c.id = s.column_id
                JOIN wwi_page_rows r ON r.id = c.row_id
                JOIN pages p ON p.id = r.page_id
                WHERE b.site_id = @site_id AND p.site_id = @site_id AND p.status = 'published' AND b.is_active = 1
                ORDER BY r.page_id, r.sort_order, b.sort_order")->fetchAll();
        } catch (\Throwable $e) {
            return;
        }
        foreach ($rows as $row) {
            $slug = trim((string)$row['page_slug'], '/');
            $url = $this->siteUrl() . '/' . ($slug === 'home' ? '' : $slug);
            $props = $this->decode($row['props'] ?? '');
            $text = $this->flatten([$props]);
            if ($text === '') continue;
            $label = (string)($row['type'] ?: 'bloque');
            $put('builder', 'block', 'block:' . (int)$row['id'], trim((string)$row['page_title']) . ' · ' . $label, $url . '#block-' . (int)$row['id'], "Página: " . $row['page_title'] . "\nURL: " . $url . "\nBloque: " . $label . "\n" . $text, ['page' => $slug, 'block_type' => $label, 'brick' => (string)($row['brick_slug'] ?? '')], (string)($row['updated_at'] ?? ''));
        }
    }

    private function ingestCatalog(callable $put): void
    {
        $catalog = $this->catalog();
        $products = (array)($catalog['products'] ?? []);
        if (!$products) return;
        $categories = [];
        foreach ($products as $p) {
            $handle = trim((string)($p['handle'] ?? ''));
            if ($handle === '') continue;
            $title = trim((string)($p['title'] ?? $handle));
            $vendor = trim((string)($p['vendor'] ?? ''));
            $type = trim((string)($p['product_type'] ?? ''));
            $tags = array_values(array_filter(array_map('strval', (array)($p['tags'] ?? []))));
            $variants = (array)($p['variants'] ?? []);
            $variant = (array)($variants[0] ?? []);
            $price = (float)($variant['price'] ?? 0);
            $compare = (float)($variant['compare_at_price'] ?? 0);
            $available = !empty($variant['available']);
            $sku = (string)($variant['sku'] ?? '');
            $body = $this->text((string)($p['body_html'] ?? ''));
            $catParts = [];
            foreach ($tags as $tag) {
                if (preg_match('/^(\d+)\)\s*([^_]+)_(.+)$/u', trim($tag), $m)) {
                    $level = (int)$m[1];
                    $value = trim($m[3]);
                    if ($value === '') continue;
                    $key = $level . '|' . mb_strtolower($value);
                    if (!isset($categories[$key])) $categories[$key] = ['level' => $level, 'value' => $value, 'count' => 0, 'brands' => [], 'types' => [], 'samples' => []];
                    $categories[$key]['count']++;
                    if ($vendor !== '') $categories[$key]['brands'][$vendor] = true;
                    if ($type !== '') $categories[$key]['types'][$type] = true;
                    if (count($categories[$key]['samples']) < 8) $categories[$key]['samples'][] = $title;
                    $catParts[] = $value;
                }
            }
            $image = '';
            foreach ((array)($p['images'] ?? []) as $img) {
                $src = is_array($img) ? (string)($img['src'] ?? '') : (string)$img;
                if ($src !== '') { $image = $src; break; }
            }
            $priceText = $price > 0 ? '$' . number_format($price, 0, ',', '.') . ' COP' : 'No informado';
            $content = "Producto: " . $title . "\nHandle: " . $handle . "\nSKU: " . ($sku ?: 'No informado') . "\nMarca: " . ($vendor ?: 'No informada') . "\nTipo: " . ($type ?: 'No informado') . "\nCategorías: " . implode(', ', array_unique($catParts)) . "\nPrecio: " . $priceText . ($compare > $price && $price > 0 ? " (antes $" . number_format($compare, 0, ',', '.') . ")" : '') . "\nDisponibilidad: " . ($available ? 'Disponible' : 'Agotado') . "\nURL: " . $this->siteUrl() . '/producto/' . rawurlencode($handle) . "\n\nDescripción:\n" . ($body ?: 'Sin descripción adicional en el catálogo.') . "\n\nEtiquetas: " . implode(', ', array_slice($tags, 0, 30));
            $put('catalog', 'product', 'product:' . $handle, $title, $this->siteUrl() . '/producto/' . rawurlencode($handle), $content, ['handle' => $handle, 'sku' => $sku, 'vendor' => $vendor, 'product_type' => $type, 'price_cents' => (int)round($price * 100), 'available' => $available, 'image' => $image, 'updated_at' => (string)($p['updated_at'] ?? '')], (string)($p['updated_at'] ?? ''));
        }
        foreach ($categories as $key => $cat) {
            $ref = 'category:' . $key;
            $content = "Categoría: " . $cat['value'] . "\nNivel: " . $cat['level'] . "\nProductos: " . $cat['count'] . "\nMarcas: " . implode(', ', array_slice(array_keys($cat['brands']), 0, 20)) . "\nTipos: " . implode(', ', array_slice(array_keys($cat['types']), 0, 20)) . "\nEjemplos: " . implode(', ', $cat['samples']) . "\nURL de búsqueda: " . $this->siteUrl() . '/buscar?tax=' . rawurlencode($cat['level'] . ':' . $cat['value']);
            $put('catalog', 'category', $ref, $cat['value'], $this->siteUrl() . '/buscar?tax=' . rawurlencode($cat['level'] . ':' . $cat['value']), $content, ['level' => $cat['level'], 'count' => $cat['count'], 'brands' => array_slice(array_keys($cat['brands']), 0, 20)]);
        }
    }

    private function ingestStoreProducts(callable $put): void
    {
        try {
            $rows = Database::instance()->query("SELECT id, name, slug, description, short_description, price_cents, currency, sku, stock, vendor, product_type, updated_at FROM store_products WHERE site_id = @site_id AND status = 'active' AND (visibility IS NULL OR visibility = '' OR visibility = 'public')")->fetchAll();
        } catch (\Throwable $e) {
            return;
        }
        $catalogRefs = [];
        try {
            foreach (Database::instance()->query("SELECT ref FROM ciu_documents WHERE site_id = @site_id AND source_key = 'catalog' AND doc_type = 'product'")->fetchAll() as $ref) {
                $catalogRefs[(string)$ref['ref']] = true;
            }
        } catch (\Throwable $e) {
        }
        foreach ($rows as $row) {
            $slug = (string)($row['slug'] ?: $row['id']);
            if (isset($catalogRefs['product:' . $slug])) continue;
            $url = $this->siteUrl() . '/producto/' . rawurlencode($slug);
            $content = "Producto local: " . $row['name'] . "\nSKU: " . ($row['sku'] ?: 'No informado') . "\nMarca: " . ($row['vendor'] ?: 'No informada') . "\nTipo: " . ($row['product_type'] ?: 'No informado') . "\nPrecio: $" . number_format(((int)$row['price_cents']) / 100, 0, ',', '.') . " " . ($row['currency'] ?: 'COP') . "\nDisponibilidad: " . ((int)$row['stock'] > 0 ? 'Disponible' : 'Agotado') . "\nURL: " . $url . "\n\n" . $this->text((string)($row['description'] ?: $row['short_description']));
            $put('store', 'product', 'store_product:' . $row['id'], (string)$row['name'], $url, $content, ['slug' => $slug, 'sku' => (string)$row['sku'], 'price_cents' => (int)$row['price_cents'], 'stock' => (int)$row['stock']], (string)($row['updated_at'] ?? ''));
        }
    }

    private function catalog(): array
    {
        $root = defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 2);
        $paths = [$root . '/public/assets/uploads/_catalog/catalog.json', $root . '/templates/themes/sonika/catalog.json'];
        foreach ($paths as $path) {
            if (!is_file($path)) continue;
            $data = json_decode((string)@file_get_contents($path), true);
            if (is_array($data) && !empty($data['products'])) return $data;
        }
        return [];
    }

    private function siteUrl(): string
    {
        $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
        if ($host !== '' && preg_match('/^[a-z0-9.-]+(?::\d+)?$/i', $host)) {
            $scheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')) ? 'https' : 'http';
            return $scheme . '://' . $host;
        }
        try {
            $domain = (string)Database::instance()->query('SELECT domain FROM sites WHERE id = @site_id')->fetchColumn();
            if ($domain !== '') return 'https://' . rtrim($domain, '/');
        } catch (\Throwable $e) {
        }
        return 'https://sonika.com.co';
    }

    private function dateValue(?string $value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') return null;
        $ts = strtotime($value);
        return $ts !== false ? date('Y-m-d H:i:s', $ts) : null;
    }

    private function decode($value): array
    {
        if (is_array($value)) return $value;
        $decoded = json_decode((string)$value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function stripMicheDomain(string $text): string
    {
        $text = preg_replace('~<a\b[^>]*href\s*=\s*("[^"]*miche\.com\.co[^"]*"|\'[^\']*miche\.com\.co[^\']*\')[^>]*>(.*?)</a>~is', '$2', $text) ?? $text;
        $text = preg_replace('~(?:https?://)?(?:www\.)?miche\.com\.co/?~i', '', $text) ?? $text;
        $text = preg_replace('~<(p|span|div)>\s*</\1>~i', '', $text) ?? $text;
        return trim($text);
    }

    private function text(string $html): string
    {
        $html = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    private function flatten($value): string
    {
        $out = [];
        $walk = function ($v) use (&$walk, &$out) {
            if (is_string($v)) {
                $v = trim($v);
                if ($v === '' || strlen($v) > 5000 || preg_match('~^https?://~i', $v) || preg_match('~^data:~i', $v)) return;
                $t = $this->text($v);
                if ($t !== '') $out[] = $t;
                return;
            }
            if (!is_array($v)) return;
            foreach ($v as $k => $item) {
                if (is_string($k) && preg_match('/image|img|icon|color|background|url|href|class|style/i', $k)) continue;
                $walk($item);
            }
        };
        $walk($value);
        return trim(implode(' ', array_slice($out, 0, 120)));
    }
}
