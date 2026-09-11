<?php
namespace App\Services;

use App\Core\Database;

class BrickMarketService
{
    private const EVENTS = ['view', 'preview', 'install', 'uninstall', 'add_to_page', 'update', 'rate'];
    private const COUNTERS = [
        'view' => 'views',
        'preview' => 'previews',
        'install' => 'installs',
        'uninstall' => 'uninstalls',
        'add_to_page' => 'add_to_page',
        'update' => 'updates',
    ];

    public function ensureTables(): void
    {
        $db = Database::instance();
        $db->exec("CREATE TABLE IF NOT EXISTS brick_ratings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            brick_slug VARCHAR(120) NOT NULL,
            site_id INT NOT NULL DEFAULT 1,
            user_id INT NOT NULL,
            rating TINYINT NOT NULL,
            comment VARCHAR(500) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_user_brick (brick_slug, user_id),
            INDEX idx_brick (brick_slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS brick_metrics (
            brick_slug VARCHAR(120) NOT NULL PRIMARY KEY,
            installs INT NOT NULL DEFAULT 0,
            views INT NOT NULL DEFAULT 0,
            previews INT NOT NULL DEFAULT 0,
            uninstalls INT NOT NULL DEFAULT 0,
            add_to_page INT NOT NULL DEFAULT 0,
            updates INT NOT NULL DEFAULT 0,
            last_event_at TIMESTAMP NULL DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS brick_events (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            brick_slug VARCHAR(120) NOT NULL,
            event VARCHAR(30) NOT NULL,
            site_id INT NOT NULL DEFAULT 1,
            user_id INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_brick_event (brick_slug, event),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function track(string $slug, string $event, ?int $userId, int $siteId): void
    {
        if (!in_array($event, self::EVENTS, true)) return;
        $slug = substr(trim($slug), 0, 120);
        if ($slug === '') return;
        $this->ensureTables();
        $db = Database::instance();
        $db->prepare("INSERT INTO brick_events (brick_slug, event, site_id, user_id) VALUES (:s, :e, :site, :u)")
            ->execute(['s' => $slug, 'e' => $event, 'site' => $siteId, 'u' => $userId]);
        $col = self::COUNTERS[$event] ?? null;
        if ($col) {
            $db->prepare("INSERT INTO brick_metrics (brick_slug, {$col}, last_event_at) VALUES (:s, 1, NOW()) ON DUPLICATE KEY UPDATE {$col} = {$col} + 1, last_event_at = NOW()")
                ->execute(['s' => $slug]);
        }
    }

    public function rate(string $slug, int $userId, int $siteId, int $rating, string $comment = ''): array
    {
        if ($rating < 1 || $rating > 5) return ['ok' => false, 'message' => 'La valoración debe estar entre 1 y 5'];
        $slug = substr(trim($slug), 0, 120);
        if ($slug === '' || $userId < 1) return ['ok' => false, 'message' => 'Valoración inválida'];
        $this->ensureTables();
        $db = Database::instance();
        $comment = substr(trim($comment), 0, 500);
        $stmt = $db->prepare("INSERT INTO brick_ratings (brick_slug, site_id, user_id, rating, comment) VALUES (:s, :site, :u, :r, :c) ON DUPLICATE KEY UPDATE rating = :r2, comment = :c2, site_id = :site2");
        $stmt->execute(['s' => $slug, 'site' => $siteId, 'u' => $userId, 'r' => $rating, 'c' => $comment !== '' ? $comment : null, 'r2' => $rating, 'c2' => $comment !== '' ? $comment : null, 'site2' => $siteId]);
        $this->track($slug, 'rate', $userId, $siteId);
        return ['ok' => true, 'message' => 'Gracias por tu valoración'];
    }

    public function summary(?int $userId): array
    {
        $this->ensureTables();
        $db = Database::instance();
        $out = [];
        $rows = $db->query("SELECT brick_slug, COUNT(*) AS c, ROUND(AVG(rating),2) AS avg, SUM(CASE WHEN rating=1 THEN 1 ELSE 0 END) AS r1, SUM(CASE WHEN rating=2 THEN 1 ELSE 0 END) AS r2, SUM(CASE WHEN rating=3 THEN 1 ELSE 0 END) AS r3, SUM(CASE WHEN rating=4 THEN 1 ELSE 0 END) AS r4, SUM(CASE WHEN rating=5 THEN 1 ELSE 0 END) AS r5 FROM brick_ratings GROUP BY brick_slug")->fetchAll();
        foreach ($rows as $r) {
            $out[$r['brick_slug']] = [
                'rating_avg' => (float)$r['avg'],
                'rating_count' => (int)$r['c'],
                'dist' => [(int)$r['r1'], (int)$r['r2'], (int)$r['r3'], (int)$r['r4'], (int)$r['r5']],
                'installs' => 0, 'views' => 0, 'previews' => 0, 'uninstalls' => 0, 'add_to_page' => 0, 'updates' => 0,
            ];
        }
        $rows = $db->query("SELECT slug, COUNT(*) AS c FROM bricks GROUP BY slug")->fetchAll();
        foreach ($rows as $r) {
            $slug = $r['slug'];
            if (!isset($out[$slug])) $out[$slug] = ['rating_avg' => 0, 'rating_count' => 0, 'dist' => [0, 0, 0, 0, 0], 'installs' => 0, 'views' => 0, 'previews' => 0, 'uninstalls' => 0, 'add_to_page' => 0, 'updates' => 0];
            $out[$slug]['installs'] = (int)$r['c'];
        }
        $rows = $db->query("SELECT * FROM brick_metrics")->fetchAll();
        foreach ($rows as $r) {
            $slug = $r['brick_slug'];
            if (!isset($out[$slug])) $out[$slug] = ['rating_avg' => 0, 'rating_count' => 0, 'dist' => [0, 0, 0, 0, 0], 'installs' => 0, 'views' => 0, 'previews' => 0, 'uninstalls' => 0, 'add_to_page' => 0, 'updates' => 0];
            $out[$slug]['installs'] = max($out[$slug]['installs'], (int)$r['installs']);
            $out[$slug]['views'] = (int)$r['views'];
            $out[$slug]['previews'] = (int)$r['previews'];
            $out[$slug]['uninstalls'] = (int)$r['uninstalls'];
            $out[$slug]['add_to_page'] = (int)$r['add_to_page'];
            $out[$slug]['updates'] = (int)$r['updates'];
        }
        $my = [];
        if ($userId) {
            $stmt = $db->prepare("SELECT brick_slug, rating FROM brick_ratings WHERE user_id = :u");
            $stmt->execute(['u' => $userId]);
            foreach ($stmt->fetchAll() as $r) {
                $my[$r['brick_slug']] = (int)$r['rating'];
            }
        }
        return ['summary' => $out, 'my_ratings' => $my, 'insights' => $this->insights($out)];
    }

    public function ratingsFor(string $slug, ?int $userId): array
    {
        $this->ensureTables();
        $db = Database::instance();
        $stmt = $db->prepare("SELECT rating, COUNT(*) AS c FROM brick_ratings WHERE brick_slug = :s GROUP BY rating");
        $stmt->execute(['s' => $slug]);
        $dist = [0, 0, 0, 0, 0];
        foreach ($stmt->fetchAll() as $r) {
            $dist[(int)$r['rating'] - 1] = (int)$r['c'];
        }
        $stmt = $db->prepare("SELECT r.rating, r.comment, r.updated_at, u.username FROM brick_ratings r LEFT JOIN users u ON u.id = r.user_id WHERE r.brick_slug = :s AND (r.comment IS NOT NULL AND r.comment <> '') ORDER BY r.updated_at DESC LIMIT 10");
        $stmt->execute(['s' => $slug]);
        $recent = $stmt->fetchAll();
        $my = 0;
        if ($userId) {
            $stmt = $db->prepare("SELECT rating FROM brick_ratings WHERE brick_slug = :s AND user_id = :u");
            $stmt->execute(['s' => $slug, 'u' => $userId]);
            $my = (int)($stmt->fetchColumn() ?: 0);
        }
        return ['dist' => $dist, 'recent' => $recent, 'my_rating' => $my];
    }

    private function insights(array $summary): array
    {
        $db = Database::instance();
        $tot = $db->query("SELECT COUNT(*) AS c, ROUND(AVG(rating),2) AS avg, SUM(CASE WHEN rating=5 THEN 1 ELSE 0 END) AS five, SUM(CASE WHEN rating<=2 THEN 1 ELSE 0 END) AS low FROM brick_ratings")->fetch();
        $dist = [0, 0, 0, 0, 0];
        foreach ($db->query("SELECT rating, COUNT(*) AS c FROM brick_ratings GROUP BY rating")->fetchAll() as $r) {
            $dist[(int)$r['rating'] - 1] = (int)$r['c'];
        }
        $events = [];
        foreach ($db->query("SELECT event, COUNT(*) AS c FROM brick_events GROUP BY event")->fetchAll() as $r) {
            $events[$r['event']] = (int)$r['c'];
        }
        $rows = [];
        foreach ($summary as $slug => $s) {
            $s['slug'] = $slug;
            $rows[] = $s;
        }
        $byRating = array_values(array_filter($rows, function ($s) { return $s['rating_count'] > 0; }));
        usort($byRating, function ($a, $b) { return $b['rating_avg'] <=> $a['rating_avg']; });
        $byInstalls = $rows;
        usort($byInstalls, function ($a, $b) { return $b['installs'] <=> $a['installs']; });
        $byPreviews = $rows;
        usort($byPreviews, function ($a, $b) { return $b['previews'] <=> $a['previews']; });
        $recent = $db->query("SELECT r.brick_slug, r.rating, r.comment, r.updated_at, u.username FROM brick_ratings r LEFT JOIN users u ON u.id = r.user_id ORDER BY r.updated_at DESC LIMIT 8")->fetchAll();
        return [
            'totals' => [
                'ratings' => (int)($tot['c'] ?? 0),
                'avg' => (float)($tot['avg'] ?? 0),
                'five' => (int)($tot['five'] ?? 0),
                'low' => (int)($tot['low'] ?? 0),
                'dist' => $dist,
                'events' => $events,
            ],
            'top_rated' => array_slice($byRating, 0, 5),
            'worst_rated' => array_slice(array_reverse($byRating), 0, 5),
            'most_installed' => array_slice($byInstalls, 0, 5),
            'most_previewed' => array_slice($byPreviews, 0, 5),
            'recent' => $recent,
        ];
    }
}
