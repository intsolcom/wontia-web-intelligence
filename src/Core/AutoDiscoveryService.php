<?php
namespace App\Core;

class AutoDiscoveryService
{
    public static function scanLocalBricks(): array
    {
        $bricksDir = ROOT_DIR . '/src/Bricks';
        if (!is_dir($bricksDir)) {
            return ['ok' => true, 'message' => 'No src/Bricks directory found', 'discovered' => 0, 'registered' => 0, 'bricks' => []];
        }

        $db = Database::instance();
        $discovered = 0;
        $registered = 0;
        $alreadyInstalled = 0;
        $bricks = [];

        $dirs = glob($bricksDir . '/*', GLOB_ONLYDIR);
        foreach ($dirs as $dir) {
            $jsonPath = $dir . '/brick.json';
            if (!file_exists($jsonPath)) continue;

            $json = json_decode(file_get_contents($jsonPath), true);
            if (!$json || empty($json['slug'])) continue;

            $discovered++;
            $slug = $json['slug'];

            $stmt = $db->prepare('SELECT id, version FROM bricks WHERE slug = ? AND site_id = 1');
            $stmt->execute([$slug]);
            $existing = $stmt->fetch();

            if ($existing) {
                $alreadyInstalled++;
                $bricks[] = [
                    'name' => $json['name'],
                    'slug' => $slug,
                    'version' => $json['version'],
                    'existing_version' => $existing['version'],
                    'status' => 'already_installed',
                ];

                if (version_compare($json['version'], $existing['version'], '>')) {
                    $db->prepare('UPDATE bricks SET version = ?, description = ?, author = ?, updated_at = NOW() WHERE id = ?')
                        ->execute([$json['version'], $json['description'] ?? '', $json['author'] ?? 'Wontia', $existing['id']]);
                    $bricks[count($bricks) - 1]['status'] = 'version_updated';
                }
                continue;
            }

            $stmt = $db->prepare('INSERT INTO bricks (site_id, name, slug, version, category, description, author, brick_class, installed_path, config, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                1,
                $json['name'],
                $slug,
                $json['version'],
                $json['category'] ?? 'general',
                $json['description'] ?? '',
                $json['author'] ?? 'Wontia',
                $json['brick_class'] ?? null,
                'src/Bricks/' . basename($dir) . '/',
                json_encode($json['config'] ?? new \stdClass()),
                'active',
            ]);

            $registered++;
            $bricks[] = [
                'name' => $json['name'],
                'slug' => $slug,
                'version' => $json['version'],
                'id' => (int) $db->lastInsertId(),
                'status' => 'newly_registered',
            ];
        }

        return [
            'ok' => true,
            'message' => "Scanned $discovered bricks. $registered new, $alreadyInstalled already present.",
            'discovered' => $discovered,
            'registered' => $registered,
            'already_installed' => $alreadyInstalled,
            'bricks' => $bricks,
        ];
    }

    public static function ensureBrickHubTables(): array
    {
        $db = Database::instance();
        $tables = ['brick_sources', 'bricks', 'brick_updates', 'brick_webhooks'];
        $schemaPath = ROOT_DIR . '/install/brickhub_schema.sql';
        $results = [];

        foreach ($tables as $table) {
            try {
                $db->query("SELECT 1 FROM $table LIMIT 1");
                $results[$table] = 'exists';
            } catch (\Exception $e) {
                $results[$table] = 'missing';
            }
        }

        $allExist = !in_array('missing', $results);
        if ($allExist) {
            return ['ok' => true, 'message' => 'All BrickHub tables exist', 'tables' => $results];
        }

        if (!file_exists($schemaPath)) {
            return ['ok' => false, 'message' => 'Schema file not found at ' . $schemaPath, 'tables' => $results];
        }

        $sql = file_get_contents($schemaPath);
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            fn($s) => !empty($s) && !str_starts_with($s, '--')
        );

        $created = 0;
        $errors = [];
        foreach ($statements as $stmt) {
            try {
                $db->exec($stmt . ';');
                $created++;
            } catch (\Exception $e) {
                if (!str_contains($e->getMessage(), 'already exists')) {
                    $errors[] = substr($stmt, 0, 80) . '... : ' . $e->getMessage();
                }
            }
        }

        return [
            'ok' => true,
            'message' => "Migrated $created statements. Tables ensured.",
            'tables' => $results,
            'errors' => $errors,
        ];
    }

    public static function fullSetup(): array
    {
        $tablesResult = self::ensureBrickHubTables();
        $scanResult = self::scanLocalBricks();

        $db = Database::instance();
        $stmt = $db->query('SELECT COUNT(*) as cnt FROM brick_sources');
        $sourceCount = (int) ($stmt->fetch()['cnt'] ?? 0);
        $sourceNote = $sourceCount === 0
            ? 'No sources configured. Add a GitHub source in BrickHub > Sources to enable auto-updates.'
            : "$sourceCount source(s) already configured.";

        return [
            'ok' => $tablesResult['ok'] && $scanResult['ok'],
            'tables' => $tablesResult,
            'bricks' => $scanResult,
            'sources_count' => $sourceCount,
            'source_note' => $sourceNote,
            'next_steps' => $sourceCount === 0
                ? ['add_github_source' => 'POST /api/v1/admin/brickhub/sources with {name, repo_url}']
                : ['ready' => 'BrickHub is fully operational. Visit admin panel > BrickHub.'],
        ];
    }
}
