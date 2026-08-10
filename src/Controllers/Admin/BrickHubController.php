<?php
namespace App\Controllers\Admin;

use App\Core\Response;
use App\Core\BrickSystem;
use App\Core\GitHubSyncService;
use App\Core\Database;
use App\Core\AutoDiscoveryService;
use App\Core\BrickHubNotificationService;

class BrickHubController
{
    public function marketplace(): void
    {
        $sources = BrickSystem::listSources();
        $installed = BrickSystem::all();
        $installedSlugs = array_column($installed, 'slug');

        $availableBricks = [];
        foreach ($sources as $source) {
            try {
                $discovered = GitHubSyncService::discoverBricks($source['repo_url'], $source['auth_token']);
                foreach ($discovered as $brick) {
                    $brick['source_id'] = $source['id'];
                    $brick['source_name'] = $source['name'];
                    $brick['repo_url'] = $source['repo_url'];
                    $brick['installed'] = in_array($brick['slug'], $installedSlugs);
                    $availableBricks[] = $brick;
                }
            } catch (\Exception $e) {}
        }

        foreach ($installed as $brick) {
            $found = false;
            foreach ($availableBricks as &$ab) {
                if ($ab['slug'] === $brick['slug']) {
                    $ab['installed'] = true;
                    $ab['installed_version'] = $brick['version'];
                    $ab['installed_id'] = $brick['id'];
                    $ab['status'] = $brick['status'];
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $availableBricks[] = [
                    'name' => $brick['name'],
                    'slug' => $brick['slug'],
                    'source_id' => $brick['source_id'],
                    'source_name' => $brick['source_name'] ?? 'Manual',
                    'repo_url' => $brick['source_url'] ?? '',
                    'installed' => true,
                    'installed_version' => $brick['version'],
                    'installed_id' => $brick['id'],
                    'status' => $brick['status'],
                    'category' => $brick['category'],
                ];
            }
        }

        Response::json(['ok' => true, 'data' => array_values($availableBricks), 'total' => count($availableBricks)]);
    }

    public function sources(): void
    {
        $sources = BrickSystem::listSources();
        Response::json(['ok' => true, 'data' => $sources, 'total' => count($sources)]);
    }

    public function addSource(\App\Core\Request $request): void
    {
        $name = $request->input('name', '');
        $repoUrl = $request->input('repo_url', '');
        $branch = $request->input('branch', 'main');
        $installPath = $request->input('install_path', '/src/Bricks/');
        $token = $request->input('auth_token', null);

        if (!$name || !$repoUrl) {
            Response::error('Name and repo_url are required', 400);
        }

        $verify = GitHubSyncService::verifyRepo($repoUrl, $token);
        if (!$verify['ok']) {
            Response::error('Cannot access repository: ' . ($verify['message'] ?? 'Unknown error'), 400);
        }

        $result = BrickSystem::addSource($name, $repoUrl, $branch, $installPath, $token);
        if ($result['ok']) {
            $result['repo_info'] = $verify;
        }
        Response::json($result);
    }

    public function removeSource(\App\Core\Request $request, string $id): void
    {
        $result = BrickSystem::removeSource((int)$id);
        Response::json($result);
    }

    public function verifySource(\App\Core\Request $request): void
    {
        $repoUrl = $request->input('repo_url', '');
        $token = $request->input('auth_token', null);
        if (!$repoUrl) Response::error('repo_url required', 400);
        $result = GitHubSyncService::verifyRepo($repoUrl, $token);
        Response::json($result);
    }

    public function discoverBricks(\App\Core\Request $request, string $sourceId): void
    {
        $source = BrickSystem::getSource((int)$sourceId);
        if (!$source) Response::error('Source not found', 404);
        $bricks = GitHubSyncService::discoverBricks($source['repo_url'], $source['auth_token']);
        Response::json(['ok' => true, 'data' => $bricks, 'source' => $source['name']]);
    }

    public function install(\App\Core\Request $request): void
    {
        $sourceId = (int)$request->input('source_id', 0);
        $slug = $request->input('slug', '');

        $source = BrickSystem::getSource($sourceId);
        if (!$source) Response::error('Source not found', 404);

        $brickDef = [
            'site_id' => 1,
            'source_id' => $sourceId,
            'name' => $request->input('name', $slug),
            'slug' => $slug,
            'version' => $source['last_version'] ?? '1.0.0',
            'category' => $request->input('category', 'general'),
            'description' => $request->input('description', ''),
            'author' => $request->input('author', 'Wontia'),
            'brick_class' => $request->input('brick_class', null),
            'installed_path' => rtrim($source['install_path'] ?? '/src/Bricks/', '/') . '/' . $slug,
        ];

        $result = BrickSystem::install($brickDef);
        Response::json($result);
    }

    public function uninstall(\App\Core\Request $request, string $id): void
    {
        $result = BrickSystem::uninstall((int)$id);
        Response::json($result);
    }

    public function sync(\App\Core\Request $request): void
    {
        $sourceId = $request->input('source_id', null);
        if ($sourceId) {
            $result = BrickSystem::syncSource((int)$sourceId);
        } else {
            $result = BrickSystem::syncAllSources();
        }
        Response::json(['ok' => true, 'data' => $result]);
    }

    public function checkUpdates(): void
    {
        $result = BrickSystem::checkAllBricks();
        Response::json($result);
    }

    public function applyUpdate(\App\Core\Request $request, string $brickId): void
    {
        $result = BrickSystem::applyUpdate((int)$brickId);
        Response::json($result);
    }

    public function applyAllUpdates(): void
    {
        $result = BrickSystem::applyAllUpdates();
        Response::json($result);
    }

    public function updateHistory(\App\Core\Request $request): void
    {
        $brickId = $request->input('brick_id', null);
        $history = BrickSystem::getUpdateHistory($brickId ? (int)$brickId : null);
        Response::json(['ok' => true, 'data' => $history, 'total' => count($history)]);
    }

    public function installedBricks(): void
    {
        $bricks = BrickSystem::all();
        Response::json(['ok' => true, 'data' => $bricks, 'total' => count($bricks)]);
    }

    public function checkBrickUpdate(\App\Core\Request $request, string $brickId): void
    {
        $result = BrickSystem::checkForUpdate((int)$brickId);
        Response::json(['ok' => true, 'data' => $result]);
    }

    public function broadcastUpdate(\App\Core\Request $request, string $slug): void
    {
        $brickClass = null;
        $brickDirs = [ROOT_DIR . '/src/Bricks/', ROOT_DIR . '/src/Widgets/'];
        foreach ($brickDirs as $dir) {
            $jsonPath = $dir . str_replace('-', '', ucwords($slug, '-')) . '/brick.json';
            if (file_exists($jsonPath)) {
                $meta = json_decode(file_get_contents($jsonPath), true);
                if ($meta && ($meta['slug'] ?? '') === $slug) {
                    $brickClass = $meta['brick_class'] ?? null;
                    break;
                }
            }
        }

        if ($brickClass && class_exists($brickClass) && method_exists($brickClass, 'broadcastUpdateNotification')) {
            $result = $brickClass::broadcastUpdateNotification();
            Response::json($result);
        }

        $brick = BrickSystem::findBySlug($slug);
        if (!$brick) {
            $meta = \App\Widgets\WidgetRegistry::all()[$slug] ?? null;
            if ($meta) {
                $brick = BrickSystem::findBySlug($slug);
                if (!$brick) {
                    Response::json([
                        'ok' => true,
                        'message' => 'Brick exists as widget but is not registered in BrickHub. Install it first via Marketplace.',
                        'widget' => $meta,
                        'action' => 'install_to_broadcast',
                    ]);
                }
            } else {
                Response::error('Brick not found: ' . $slug, 404);
            }
        }

        $db = Database::instance();
        $sites = $db->query('SELECT id, name, domain FROM sites WHERE is_active = 1')->fetchAll();
        $notified = 0;
        foreach ($sites as $site) {
            $db->prepare('INSERT INTO brick_updates (site_id, brick_id, source_id, from_version, to_version, release_notes, release_url, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([
                $site['id'], $brick['id'], $brick['source_id'],
                $brick['version'], $brick['version'],
                'New update notification broadcasted for ' . $brick['name'],
                '', 'pending',
            ]);
            $notified++;
        }

        Response::json([
            'ok' => true,
            'brick' => $brick['name'],
            'slug' => $slug,
            'version' => $brick['version'],
            'sites_notified' => $notified,
            'message' => 'Update notification sent to ' . $notified . ' site(s)',
        ]);
    }

    public function scanLocal(): void
    {
        $result = AutoDiscoveryService::scanLocalBricks();
        Response::json($result);
    }

    public function ensureTables(): void
    {
        $result = AutoDiscoveryService::ensureBrickHubTables();
        Response::json($result);
    }

    public function fullSetup(): void
    {
        $result = AutoDiscoveryService::fullSetup();
        Response::json($result);
    }

    public function pendingNotifications(): void
    {
        $count = BrickHubNotificationService::getPendingCount();
        $updates = BrickHubNotificationService::getPendingUpdates();
        Response::json(['ok' => true, 'pending' => $count, 'updates' => $updates]);
    }

    public function autoCheck(): void
    {
        $result = BrickHubNotificationService::autoCheckAll();
        Response::json(['ok' => true, 'data' => $result]);
    }

    public function motherPending(\App\Core\Request $request): void
    {
        $siteKey = $request->input('site_key', '');
        if (!$siteKey) Response::error('site_key required', 400);

        $db = Database::instance();
        $stmt = $db->prepare('SELECT id FROM brickhub_registry WHERE site_key = ? AND is_active = 1');
        $stmt->execute([$siteKey]);
        if (!$stmt->fetch()) Response::error('Unknown site key', 403);

        $db->prepare('UPDATE brickhub_registry SET last_seen_at = NOW() WHERE site_key = ?')->execute([$siteKey]);

        $updates = BrickHubNotificationService::getPendingUpdates();
        Response::json(['ok' => true, 'updates' => $updates]);
    }

    public function childNotify(\App\Core\Request $request): void
    {
        $siteKey = $request->input('site_key', '');
        $brickName = $request->input('brick_name', '');
        $brickSlug = $request->input('brick_slug', '');
        $toVersion = $request->input('to_version', '1.0.0');
        $fromVersion = $request->input('from_version', '0.0.0');
        $releaseNotes = $request->input('release_notes', '');

        if (!$siteKey || !$brickSlug) Response::error('site_key and brick_slug required', 400);

        $existingBrick = BrickSystem::findBySlug($brickSlug);
        $brickId = $existingBrick ? $existingBrick['id'] : null;

        $db = Database::instance();
        $db->prepare('INSERT INTO brick_updates (site_id, brick_id, source_id, from_version, to_version, release_notes, release_url, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([
            1, $brickId, null, $fromVersion, $toVersion,
            $releaseNotes ?: 'Push notification from mother: ' . $brickName,
            '', 'pending',
        ]);

        Response::json(['ok' => true, 'message' => 'Notification received for ' . $brickName]);
    }

    public function registerChildSite(\App\Core\Request $request): void
    {
        $childUrl = $request->input('child_url', '');
        $childKey = $request->input('site_key', '');
        $childName = $request->input('child_name', '');

        if (!$childUrl || !$childKey) Response::error('child_url and site_key required', 400);

        $result = BrickHubNotificationService::registerChildSite($childUrl, $childKey, $childName);
        Response::json($result);
    }

    public function registeredSites(): void
    {
        $sites = BrickHubNotificationService::getRegisteredSites();
        Response::json(['ok' => true, 'data' => $sites, 'total' => count($sites)]);
    }

    public function pushToSites(\App\Core\Request $request, string $brickId): void
    {
        $result = BrickHubNotificationService::pushToAllSites((int)$brickId);
        Response::json($result);
    }
}
