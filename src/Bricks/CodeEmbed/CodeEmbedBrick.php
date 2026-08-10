<?php
namespace App\Bricks\CodeEmbed;

use App\Core\BrickSystem;
use App\Core\Database;

class CodeEmbedBrick
{
    public static function meta(): array
    {
        $jsonPath = __DIR__ . '/brick.json';
        if (file_exists($jsonPath)) {
            $meta = json_decode(file_get_contents($jsonPath), true);
            if ($meta) return $meta;
        }
        return [
            'name' => 'Code Embed',
            'slug' => 'code-embed',
            'version' => '1.0.0',
            'description' => 'Universal code injector for Wontia pages',
            'author' => 'Wontia',
            'category' => 'embed',
        ];
    }

    public static function activate(): array
    {
        $meta = self::meta();
        $existing = BrickSystem::findBySlug($meta['slug']);
        if ($existing) {
            return ['ok' => true, 'message' => 'Already installed', 'id' => $existing['id']];
        }

        $result = BrickSystem::install([
            'name' => $meta['name'],
            'slug' => $meta['slug'],
            'version' => $meta['version'],
            'description' => $meta['description'],
            'author' => $meta['author'],
            'category' => $meta['category'],
            'brick_class' => self::class,
            'installed_path' => 'src/Bricks/CodeEmbed/',
            'config' => ['widget_enabled' => true],
        ]);

        return $result;
    }

    public static function deactivate(): array
    {
        $meta = self::meta();
        $existing = BrickSystem::findBySlug($meta['slug']);
        if ($existing) {
            return BrickSystem::uninstall($existing['id']);
        }
        return ['ok' => false, 'message' => 'Not installed'];
    }

    public static function broadcastUpdateNotification(): array
    {
        $meta = self::meta();
        $db = Database::instance();

        $sites = $db->query('SELECT id, name, domain FROM sites WHERE is_active = 1')->fetchAll();
        $notified = 0;
        $errors = [];

        foreach ($sites as $site) {
            $brick = BrickSystem::findBySlug($meta['slug']);
            if ($brick) {
                $check = BrickSystem::checkForUpdate($brick['id']);
                if ($check && $check['available']) {
                    $db->prepare('INSERT INTO brick_updates (site_id, brick_id, source_id, from_version, to_version, release_notes, release_url, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([
                        $site['id'], $brick['id'], $brick['source_id'],
                        $check['current_version'], $check['latest_version'],
                        'New version of ' . $meta['name'] . ' available: ' . $meta['version'],
                        '', 'pending',
                    ]);
                    $notified++;
                }
            }
        }

        return [
            'ok' => true,
            'brick' => $meta['name'],
            'version' => $meta['version'],
            'sites_scanned' => count($sites),
            'updates_queued' => $notified,
            'errors' => $errors,
        ];
    }
}
