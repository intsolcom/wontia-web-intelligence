<?php
namespace App\Core;

class BrickHubNotificationService
{
    public static function getPendingCount(): int
    {
        try {
            $db = Database::instance();
            $stmt = $db->prepare('SELECT COUNT(*) as cnt FROM brick_updates WHERE site_id = 1 AND status = ?');
            $stmt->execute(['pending']);
            return (int) ($stmt->fetch()['cnt'] ?? 0);
        } catch (\Exception $e) {
            return 0;
        }
    }

    public static function getPendingUpdates(): array
    {
        try {
            $db = Database::instance();
            $stmt = $db->prepare(
                'SELECT u.*, b.name as brick_name, b.version as current_version, s.name as source_name
                 FROM brick_updates u
                 LEFT JOIN bricks b ON u.brick_id = b.id
                 LEFT JOIN brick_sources s ON u.source_id = s.id
                 WHERE u.site_id = 1 AND u.status = ?
                 ORDER BY u.created_at DESC'
            );
            $stmt->execute(['pending']);
            return $stmt->fetchAll();
        } catch (\Exception $e) {
            return [];
        }
    }

    public static function autoCheckAll(): array
    {
        $results = ['checked' => 0, 'updates_found' => 0, 'details' => []];

        try {
            $sources = BrickSystem::listSources();
            foreach ($sources as $source) {
                if (!$source['is_active']) continue;

                $syncResult = BrickSystem::syncSource($source['id']);
                $results['checked']++;

                if ($syncResult['ok'] && ($syncResult['updates_available'] ?? 0) > 0) {
                    $installedBricks = (new \ReflectionClass(BrickSystem::class))->getMethod('all');
                    $bricks = BrickSystem::all();
                    foreach ($bricks as $brick) {
                        if ($brick['source_id'] == $source['id']) {
                            $check = BrickSystem::checkForUpdate($brick['id']);
                            if ($check && $check['available']) {
                                $db = Database::instance();
                                $existing = $db->prepare('SELECT id FROM brick_updates WHERE brick_id = ? AND to_version = ? AND status = ?');
                                $existing->execute([$brick['id'], $check['latest_version'], 'pending']);
                                if (!$existing->fetch()) {
                                    $db->prepare('INSERT INTO brick_updates (site_id, brick_id, source_id, from_version, to_version, release_notes, release_url, status)
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([
                                        1, $brick['id'], $brick['source_id'],
                                        $check['current_version'], $check['latest_version'],
                                        $check['release_notes'] ?? '', $check['release_url'] ?? '',
                                        'pending',
                                    ]);
                                    $results['updates_found']++;
                                    $results['details'][] = [
                                        'brick' => $brick['name'],
                                        'from' => $check['current_version'],
                                        'to' => $check['latest_version'],
                                    ];
                                }
                            }
                        }
                    }
                }
            }

            self::checkMotherForUpdates();
        } catch (\Exception $e) {
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    public static function checkMotherForUpdates(): array
    {
        $motherUrl = Config::get('BRICKHUB_MOTHER_URL', '');
        if (!$motherUrl) {
            return ['ok' => true, 'message' => 'No mother URL configured', 'updates_found' => 0];
        }

        $siteKey = Config::get('BRICKHUB_SITE_KEY', '');
        if (!$siteKey) {
            $siteKey = self::generateSiteKey();
            Config::set('BRICKHUB_SITE_KEY', $siteKey);
        }

        $url = rtrim($motherUrl, '/') . '/api/v1/brickhub/mother/pending?site_key=' . urlencode($siteKey);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'User-Agent: WWI-BrickHub-Client/1.0'],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            return ['ok' => false, 'message' => 'Cannot reach mother installation', 'http_code' => $httpCode];
        }

        $data = json_decode($response, true);
        if (!$data || empty($data['updates'])) {
            return ['ok' => true, 'message' => 'No pending updates from mother', 'updates_found' => 0];
        }

        $db = Database::instance();
        $imported = 0;

        foreach ($data['updates'] as $update) {
            $existing = $db->prepare('SELECT id FROM brick_updates WHERE site_id = 1 AND brick_id = ? AND to_version = ?');
            $existing->execute([$update['brick_id'] ?? 0, $update['to_version']]);
            if ($existing->fetch()) continue;

            $db->prepare('INSERT INTO brick_updates (site_id, brick_id, source_id, from_version, to_version, release_notes, release_url, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([
                1,
                $update['brick_id'] ?? null,
                $update['source_id'] ?? null,
                $update['from_version'] ?? '0.0.0',
                $update['to_version'],
                $update['release_notes'] ?? 'Update from mother installation',
                $update['release_url'] ?? '',
                'pending',
            ]);
            $imported++;
        }

        return ['ok' => true, 'message' => "$imported updates imported from mother", 'updates_found' => $imported];
    }

    public static function registerChildSite(string $childUrl, string $childKey, string $childName = ''): array
    {
        $db = Database::instance();

        $existing = $db->prepare('SELECT id FROM brickhub_registry WHERE site_key = ?');
        $existing->execute([$childKey]);
        if ($existing->fetch()) {
            $db->prepare('UPDATE brickhub_registry SET child_url = ?, child_name = ?, last_seen_at = NOW(), is_active = 1 WHERE site_key = ?')
                ->execute([$childUrl, $childName, $childKey]);
            return ['ok' => true, 'message' => 'Site re-registered'];
        }

        $db->prepare('INSERT INTO brickhub_registry (site_id, child_url, child_name, site_key, is_active)
            VALUES (?, ?, ?, ?, ?)')->execute([1, $childUrl, $childName, $childKey, 1]);

        return ['ok' => true, 'message' => 'Site registered', 'id' => (int) $db->lastInsertId()];
    }

    public static function getRegisteredSites(): array
    {
        try {
            $db = Database::instance();
            $stmt = $db->query('SELECT * FROM brickhub_registry WHERE is_active = 1 ORDER BY last_seen_at DESC');
            return $stmt->fetchAll();
        } catch (\Exception $e) {
            return [];
        }
    }

    public static function pushToChildSites(int $brickId): array
    {
        $brick = BrickSystem::find($brickId);
        if (!$brick) return ['ok' => false, 'message' => 'Brick not found'];

        $sites = self::getRegisteredSites();
        $pushed = 0;
        $errors = [];

        foreach ($sites as $site) {
            try {
                $url = rtrim($site['child_url'], '/') . '/api/v1/brickhub/child/notify';
                $payload = json_encode([
                    'site_key' => $site['site_key'],
                    'brick_id' => $brick['id'],
                    'brick_name' => $brick['name'],
                    'brick_slug' => $brick['slug'],
                    'from_version' => $brick['version'],
                    'to_version' => $brick['version'],
                    'release_notes' => 'Update available for ' . $brick['name'],
                ]);

                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $payload,
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json', 'User-Agent: WWI-BrickHub-Mother/1.0'],
                    CURLOPT_SSL_VERIFYPEER => false,
                ]);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode >= 200 && $httpCode < 300) {
                    $pushed++;
                } else {
                    $errors[] = $site['child_name'] . ': HTTP ' . $httpCode;
                }
            } catch (\Exception $e) {
                $errors[] = $site['child_name'] . ': ' . $e->getMessage();
            }
        }

        return ['ok' => true, 'brick' => $brick['name'], 'sites_total' => count($sites), 'pushed' => $pushed, 'errors' => $errors];
    }

    public static function pushToAllSites(int $brickId): array
    {
        $pushResult = self::pushToChildSites($brickId);

        $db = Database::instance();
        $sites = $db->query('SELECT id, name FROM sites WHERE is_active = 1 AND id != 1')->fetchAll();
        $localNotified = 0;

        $brick = BrickSystem::find($brickId);
        foreach ($sites as $site) {
            $db->prepare('INSERT INTO brick_updates (site_id, brick_id, source_id, from_version, to_version, release_notes, release_url, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([
                $site['id'], $brickId, $brick['source_id'],
                $brick['version'], $brick['version'],
                'Update notification for ' . $brick['name'],
                '', 'pending',
            ]);
            $localNotified++;
        }

        return [
            'ok' => true,
            'brick' => $brick['name'],
            'remote_pushed' => $pushResult['pushed'],
            'remote_total' => $pushResult['sites_total'],
            'local_notified' => $localNotified,
            'errors' => $pushResult['errors'],
        ];
    }

    private static function generateSiteKey(): string
    {
        return 'wwi_' . bin2hex(random_bytes(16));
    }
}
