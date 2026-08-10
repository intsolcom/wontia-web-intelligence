<?php
namespace App\Core;

class BrickSystem
{
    public static function install(array $brickData): array
    {
        $db = Database::instance();
        $stmt = $db->prepare('SELECT id FROM bricks WHERE site_id = ? AND slug = ?');
        $stmt->execute([$brickData['site_id'] ?? 1, $brickData['slug']]);
        if ($stmt->fetch()) {
            return ['ok' => false, 'message' => 'Brick already installed'];
        }

        $stmt = $db->prepare('INSERT INTO bricks (site_id, source_id, name, slug, version, category, description, author, brick_class, installed_path, config, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $brickData['site_id'] ?? 1,
            $brickData['source_id'] ?? null,
            $brickData['name'],
            $brickData['slug'],
            $brickData['version'] ?? '1.0.0',
            $brickData['category'] ?? 'general',
            $brickData['description'] ?? '',
            $brickData['author'] ?? 'Wontia',
            $brickData['brick_class'] ?? null,
            $brickData['installed_path'] ?? null,
            isset($brickData['config']) ? json_encode($brickData['config']) : '{}',
            'active',
        ]);
        $id = (int) $db->lastInsertId();
        return ['ok' => true, 'message' => 'Brick installed', 'id' => $id];
    }

    public static function uninstall(int $brickId): array
    {
        $db = Database::instance();
        $brick = self::find($brickId);
        if (!$brick) return ['ok' => false, 'message' => 'Brick not found'];

        if ($brick['installed_path']) {
            $fullPath = ROOT_DIR . '/' . ltrim($brick['installed_path'], '/');
            if (file_exists($fullPath)) {
                self::recursiveDelete($fullPath);
            }
        }

        $stmt = $db->prepare('DELETE FROM bricks WHERE id = ?');
        $stmt->execute([$brickId]);
        return ['ok' => true, 'message' => 'Brick uninstalled'];
    }

    public static function find(int $id): ?array
    {
        $db = Database::instance();
        $stmt = $db->prepare('SELECT * FROM bricks WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row && isset($row['config'])) {
            $row['config'] = json_decode($row['config'], true) ?: [];
        }
        return $row ?: null;
    }

    public static function findBySlug(string $slug): ?array
    {
        $db = Database::instance();
        $stmt = $db->prepare('SELECT * FROM bricks WHERE slug = ? AND site_id = @site_id');
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        if ($row && isset($row['config'])) {
            $row['config'] = json_decode($row['config'], true) ?: [];
        }
        return $row ?: null;
    }

    public static function all(): array
    {
        $db = Database::instance();
        $stmt = $db->query('SELECT b.*, s.name as source_name, s.repo_url as source_url FROM bricks b LEFT JOIN brick_sources s ON b.source_id = s.id WHERE b.site_id = @site_id ORDER BY b.installed_at DESC');
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['config'] = json_decode($row['config'] ?? '{}', true) ?: [];
        }
        return $rows;
    }

    public static function checkForUpdate(int $brickId): ?array
    {
        $brick = self::find($brickId);
        if (!$brick || !$brick['source_id']) return null;

        $db = Database::instance();
        $stmt = $db->prepare('SELECT * FROM brick_sources WHERE id = ? AND is_active = 1');
        $stmt->execute([$brick['source_id']]);
        $source = $stmt->fetch();
        if (!$source) return null;

        try {
            $latestRelease = GitHubSyncService::getLatestRelease($source['repo_url'], $source['auth_token']);
            if (!$latestRelease) return null;
            $latestVersion = ltrim($latestRelease['tag_name'], 'v');
            if (version_compare($latestVersion, $brick['version'], '>')) {
                return [
                    'available' => true,
                    'current_version' => $brick['version'],
                    'latest_version' => $latestVersion,
                    'release_notes' => $latestRelease['body'] ?? '',
                    'release_url' => $latestRelease['html_url'] ?? '',
                    'zip_url' => $latestRelease['zipball_url'] ?? '',
                ];
            }
        } catch (\Exception $e) {}
        return ['available' => false];
    }

    public static function applyUpdate(int $brickId): array
    {
        $update = self::checkForUpdate($brickId);
        if (!$update || !$update['available']) {
            return ['ok' => false, 'message' => 'No update available'];
        }

        $brick = self::find($brickId);
        $db = Database::instance();

        $stmt = $db->prepare('INSERT INTO brick_updates (site_id, brick_id, source_id, from_version, to_version, release_notes, release_url, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            1, $brickId, $brick['source_id'],
            $update['current_version'], $update['latest_version'],
            $update['release_notes'], $update['release_url'],
            'pending',
        ]);
        $updateId = (int) $db->lastInsertId();

        try {
            $sourceStmt = $db->prepare('SELECT * FROM brick_sources WHERE id = ?');
            $sourceStmt->execute([$brick['source_id']]);
            $source = $sourceStmt->fetch();

            $installPath = $source['install_path'] ?? '/src/Bricks/';
            $extracted = GitHubSyncService::downloadAndExtract(
                $update['zip_url'],
                $installPath,
                $source['auth_token'] ?? null
            );

            if (!$extracted) throw new \Exception('Failed to download or extract update');

            $db->prepare('UPDATE bricks SET version = ?, updated_at = NOW() WHERE id = ?')->execute([$update['latest_version'], $brickId]);
            $db->prepare('UPDATE brick_sources SET last_version = ?, last_checked_at = NOW() WHERE id = ?')->execute([$update['latest_version'], $brick['source_id']]);
            $db->prepare('UPDATE brick_updates SET status = ?, applied_at = NOW() WHERE id = ?')->execute(['applied', $updateId]);

            return ['ok' => true, 'message' => 'Brick updated from ' . $update['current_version'] . ' to ' . $update['latest_version']];
        } catch (\Exception $e) {
            $db->prepare('UPDATE brick_updates SET status = ?, error_message = ? WHERE id = ?')->execute(['failed', $e->getMessage(), $updateId]);
            return ['ok' => false, 'message' => 'Update failed: ' . $e->getMessage()];
        }
    }

    public static function addSource(string $name, string $repoUrl, string $branch = 'main', string $installPath = '/src/Bricks/', ?string $token = null): array
    {
        $db = Database::instance();
        $stmt = $db->prepare('SELECT id FROM brick_sources WHERE repo_url = ? AND site_id = @site_id');
        $stmt->execute([$repoUrl]);
        if ($stmt->fetch()) {
            return ['ok' => false, 'message' => 'Source already exists'];
        }

        $stmt = $db->prepare('INSERT INTO brick_sources (site_id, name, repo_url, branch, install_path, auth_token) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([1, $name, $repoUrl, $branch, $installPath, $token]);
        $id = (int) $db->lastInsertId();

        return ['ok' => true, 'message' => 'Source added', 'id' => $id];
    }

    public static function removeSource(int $sourceId): array
    {
        $db = Database::instance();
        $stmt = $db->prepare('SELECT id FROM brick_sources WHERE id = ?');
        $stmt->execute([$sourceId]);
        if (!$stmt->fetch()) return ['ok' => false, 'message' => 'Source not found'];

        $db->prepare('DELETE FROM brick_sources WHERE id = ?')->execute([$sourceId]);
        return ['ok' => true, 'message' => 'Source removed'];
    }

    public static function listSources(): array
    {
        $db = Database::instance();
        $stmt = $db->query('SELECT s.*, (SELECT COUNT(*) FROM bricks b WHERE b.source_id = s.id) as brick_count FROM brick_sources s WHERE s.site_id = @site_id ORDER BY s.created_at DESC');
        return $stmt->fetchAll();
    }

    public static function getSource(int $id): ?array
    {
        $db = Database::instance();
        $stmt = $db->prepare('SELECT * FROM brick_sources WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function syncAllSources(): array
    {
        $sources = self::listSources();
        $results = [];
        foreach ($sources as $source) {
            $results[] = self::syncSource($source['id']);
        }
        return $results;
    }

    public static function syncSource(int $sourceId): array
    {
        $source = self::getSource($sourceId);
        if (!$source) return ['ok' => false, 'message' => 'Source not found'];

        try {
            $releases = GitHubSyncService::getReleases($source['repo_url'], $source['auth_token']);
            if (empty($releases)) {
                return ['ok' => true, 'message' => 'No releases found', 'source' => $source['name']];
            }

            $latest = $releases[0];
            $latestVersion = ltrim($latest['tag_name'] ?? '0.0.0', 'v');

            $db = Database::instance();
            $db->prepare('UPDATE brick_sources SET last_checked_at = NOW(), last_version = ? WHERE id = ?')->execute([$latestVersion, $sourceId]);

            $installedStmt = $db->prepare('SELECT * FROM bricks WHERE source_id = ? AND site_id = @site_id');
            $installedStmt->execute([$sourceId]);
            $installedBricks = $installedStmt->fetchAll();

            $updatesAvailable = 0;
            foreach ($installedBricks as $brick) {
                if (version_compare($latestVersion, $brick['version'], '>')) {
                    $updatesAvailable++;
                }
            }

            return [
                'ok' => true,
                'source' => $source['name'],
                'latest_version' => $latestVersion,
                'release_count' => count($releases),
                'installed_bricks' => count($installedBricks),
                'updates_available' => $updatesAvailable,
            ];
        } catch (\Exception $e) {
            return ['ok' => false, 'message' => 'Sync failed: ' . $e->getMessage(), 'source' => $source['name']];
        }
    }

    public static function getUpdateHistory(int $brickId = null): array
    {
        $db = Database::instance();
        if ($brickId) {
            $stmt = $db->prepare('SELECT u.*, b.name as brick_name FROM brick_updates u JOIN bricks b ON u.brick_id = b.id WHERE u.brick_id = ? ORDER BY u.created_at DESC');
            $stmt->execute([$brickId]);
        } else {
            $stmt = $db->query('SELECT u.*, b.name as brick_name FROM brick_updates u JOIN bricks b ON u.brick_id = b.id WHERE u.site_id = @site_id ORDER BY u.created_at DESC LIMIT 50');
        }
        return $stmt->fetchAll();
    }

    public static function checkAllBricks(): array
    {
        $bricks = self::all();
        $results = [];
        foreach ($bricks as $brick) {
            if ($brick['source_id']) {
                $check = self::checkForUpdate($brick['id']);
                if ($check && $check['available']) {
                    $results[] = ['brick_id' => $brick['id'], 'name' => $brick['name'], 'current' => $check['current_version'], 'latest' => $check['latest_version']];
                }
            }
        }
        return ['ok' => true, 'updates' => $results, 'total' => count($results)];
    }

    public static function applyAllUpdates(): array
    {
        $checkResult = self::checkAllBricks();
        $results = [];
        foreach ($checkResult['updates'] as $update) {
            $results[] = self::applyUpdate($update['brick_id']);
        }
        return ['ok' => true, 'applied' => count(array_filter($results, fn($r) => $r['ok'])), 'failed' => count(array_filter($results, fn($r) => !$r['ok'])), 'details' => $results];
    }

    private static function recursiveDelete(string $dir): void
    {
        if (!is_dir($dir)) {
            if (file_exists($dir)) unlink($dir);
            return;
        }
        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? self::recursiveDelete($path) : unlink($path);
        }
        rmdir($dir);
    }
}
