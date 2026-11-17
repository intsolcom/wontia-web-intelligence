<?php
namespace App\Services;

use App\Core\Database;

class TiaAttentionCatalogService
{
    private static bool $ready = false;
    private static array $cache = [];

    public function ensureTables(): void
    {
        if (self::$ready) return;
        $db = Database::instance();
        $db->exec("CREATE TABLE IF NOT EXISTS tia_attention_scenarios (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL DEFAULT 1,
            slug VARCHAR(120) NOT NULL,
            category VARCHAR(80) NOT NULL,
            segment VARCHAR(80) NOT NULL DEFAULT 'General',
            question VARCHAR(255) NOT NULL,
            keywords JSON NULL,
            objection VARCHAR(80) NULL,
            sentiment VARCHAR(80) NULL,
            goal TEXT NULL,
            ask VARCHAR(500) NULL,
            next_step TEXT NULL,
            close_type VARCHAR(40) NOT NULL DEFAULT 'eleccion',
            priority INT NOT NULL DEFAULT 50,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uk_tia_attention (site_id, slug),
            KEY idx_tia_attention_category (site_id, category, status),
            KEY idx_tia_attention_priority (site_id, priority)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        try {
            $count = (int)$db->query("SELECT COUNT(*) FROM tia_attention_scenarios WHERE site_id = @site_id")->fetchColumn();
            if ($count === 0) $this->seed();
        } catch (\Throwable $e) {
        }
        self::$ready = true;
    }

    public function match(string $message, array $training = []): array
    {
        $this->ensureTables();
        $text = $this->fold($message);
        if (mb_strlen($text) < 2) return [];
        $tokens = array_values(array_filter(preg_split('/[^a-z0-9]+/', $text) ?: [], fn($t) => mb_strlen($t) >= 3));
        $sentiment = (string)($training['sentiment']['slug'] ?? '');
        $objection = (string)($training['objection']['slug'] ?? '');
        $rows = $this->rows();
        $scored = [];
        foreach ($rows as $row) {
            $score = 0;
            $keywords = json_decode((string)($row['keywords'] ?? '[]'), true);
            if (!is_array($keywords)) $keywords = [];
            foreach ($keywords as $keyword) {
                $keyword = $this->fold((string)$keyword);
                if ($keyword !== '' && str_contains($text, $keyword)) $score += str_word_count($keyword) > 1 ? 6 : 3;
            }
            $question = $this->fold((string)$row['question']);
            $questionTokens = array_values(array_filter(preg_split('/[^a-z0-9]+/', $question) ?: [], fn($t) => mb_strlen($t) >= 4));
            foreach ($tokens as $token) if (in_array($token, $questionTokens, true)) $score += 1;
            if ($sentiment !== '' && (string)$row['sentiment'] === $sentiment) $score += 1;
            if ($objection !== '' && (string)$row['objection'] === $objection) $score += 2;
            if ($score > 0) $score += min(5, (int)$row['priority'] / 25);
            if ($score > 0) $scored[] = ['score' => $score] + $row;
        }
        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
        $out = [];
        foreach (array_slice($scored, 0, 3) as $row) {
            $out[] = [
                'slug' => (string)$row['slug'],
                'category' => (string)$row['category'],
                'segment' => (string)$row['segment'],
                'question' => (string)$row['question'],
                'objection' => (string)($row['objection'] ?? ''),
                'sentiment' => (string)($row['sentiment'] ?? ''),
                'goal' => (string)($row['goal'] ?? ''),
                'ask' => (string)($row['ask'] ?? ''),
                'next_step' => (string)($row['next_step'] ?? ''),
                'close_type' => (string)$row['close_type'],
                'priority' => (int)$row['priority'],
                'score' => (int)$row['score'],
            ];
        }
        return $out;
    }

    public function search(string $query = '', string $category = '', int $limit = 30): array
    {
        $this->ensureTables();
        $limit = max(1, min(200, $limit));
        $rows = $this->rows();
        $query = $this->fold(trim($query));
        if ($query !== '') {
            $rows = array_values(array_filter($rows, function ($r) use ($query) {
                return str_contains($this->fold((string)$r['question']), $query)
                    || str_contains($this->fold((string)$r['goal']), $query)
                    || str_contains($this->fold((string)$r['next_step']), $query)
                    || str_contains($this->fold((string)$r['keywords']), $query);
            }));
        }
        if ($category !== '') {
            $rows = array_values(array_filter($rows, fn($r) => (string)$r['category'] === $category));
        }
        usort($rows, function ($a, $b) use ($query) {
            $qa = $query !== '' && str_contains($this->fold((string)$a['question']), $query) ? 0 : 1;
            $qb = $query !== '' && str_contains($this->fold((string)$b['question']), $query) ? 0 : 1;
            return ($qa <=> $qb) ?: ((int)$b['priority'] <=> (int)$a['priority']);
        });
        return array_map(function ($r) {
            return [
                'slug' => (string)$r['slug'],
                'category' => (string)$r['category'],
                'segment' => (string)$r['segment'],
                'question' => (string)$r['question'],
                'keywords' => json_decode((string)($r['keywords'] ?? '[]'), true) ?: [],
                'objection' => (string)($r['objection'] ?? ''),
                'sentiment' => (string)($r['sentiment'] ?? ''),
                'goal' => (string)($r['goal'] ?? ''),
                'ask' => (string)($r['ask'] ?? ''),
                'next_step' => (string)($r['next_step'] ?? ''),
                'close_type' => (string)$r['close_type'],
                'priority' => (int)$r['priority'],
            ];
        }, array_slice($rows, 0, $limit));
    }

    public function stats(): array
    {
        $this->ensureTables();
        $rows = $this->rows();
        $byCategory = [];
        foreach ($rows as $r) {
            $cat = (string)$r['category'];
            $byCategory[$cat] = ($byCategory[$cat] ?? 0) + 1;
        }
        arsort($byCategory);
        return ['total' => count($rows), 'categories' => $byCategory];
    }

    private function rows(): array
    {
        $this->ensureTables();
        if (self::$cache) return self::$cache;
        try {
            self::$cache = Database::instance()->query("SELECT * FROM tia_attention_scenarios WHERE site_id = @site_id AND status = 'active' ORDER BY priority DESC, id ASC")->fetchAll();
        } catch (\Throwable $e) {
            self::$cache = [];
        }
        return self::$cache;
    }

    private function seed(): void
    {
        $path = dirname(__DIR__, 2) . '/install/tia_attention_catalog.json';
        if (!is_file($path)) return;
        $data = json_decode((string)file_get_contents($path), true);
        if (!is_array($data)) return;
        $db = Database::instance();
        $stmt = $db->prepare("INSERT INTO tia_attention_scenarios (site_id, slug, category, segment, question, keywords, objection, sentiment, goal, ask, next_step, close_type, priority, status, created_at, updated_at) VALUES (@site_id, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW()) ON DUPLICATE KEY UPDATE category = VALUES(category), segment = VALUES(segment), question = VALUES(question), keywords = VALUES(keywords), objection = VALUES(objection), sentiment = VALUES(sentiment), goal = VALUES(goal), ask = VALUES(ask), next_step = VALUES(next_step), close_type = VALUES(close_type), priority = VALUES(priority), updated_at = NOW()");
        foreach ($data as $row) {
            if (!is_array($row) || empty($row['slug']) || empty($row['question'])) continue;
            $stmt->execute([
                mb_substr((string)$row['slug'], 0, 120),
                mb_substr((string)($row['category'] ?? 'General'), 0, 80),
                mb_substr((string)($row['segment'] ?? 'General'), 0, 80),
                mb_substr((string)$row['question'], 0, 255),
                json_encode(array_values((array)($row['keywords'] ?? [])), JSON_UNESCAPED_UNICODE),
                mb_substr((string)($row['objection'] ?? ''), 0, 80),
                mb_substr((string)($row['sentiment'] ?? ''), 0, 80),
                (string)($row['goal'] ?? ''),
                mb_substr((string)($row['ask'] ?? ''), 0, 500),
                (string)($row['next_step'] ?? ''),
                mb_substr((string)($row['close_type'] ?? 'eleccion'), 0, 40),
                max(0, min(100, (int)($row['priority'] ?? 50))),
            ]);
        }
    }

    private function fold(string $value): string
    {
        return strtr(mb_strtolower($value), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    }
}
