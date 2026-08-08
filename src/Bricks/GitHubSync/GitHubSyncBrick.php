<?php
namespace App\Bricks\GitHubSync;

use App\Core\BrickSystem;
use App\Core\GitHubSyncService;
use App\Core\Database;

class GitHubSyncBrick
{
    private array $config;

    public function __construct(array $config = [])
    {
        $defaults = [
            'auto_check_enabled' => true,
            'check_interval_hours' => 6,
            'auto_apply_updates' => false,
            'notify_on_update' => true,
        ];
        $this->config = array_merge($defaults, $config);
    }

    public static function meta(): array
    {
        $jsonPath = __DIR__ . '/brick.json';
        if (file_exists($jsonPath)) {
            $meta = json_decode(file_get_contents($jsonPath), true);
            if ($meta) return $meta;
        }
        return [
            'name' => 'GitHub Sync',
            'slug' => 'github-sync',
            'version' => '1.0.0',
            'description' => 'Auto-update via GitHub',
            'author' => 'Wontia',
            'category' => 'system',
        ];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'auto_check_enabled', 'label' => 'Auto Check Enabled', 'type' => 'toggle', 'default' => true],
            ['key' => 'check_interval_hours', 'label' => 'Check Interval (hours)', 'type' => 'number', 'default' => 6],
            ['key' => 'auto_apply_updates', 'label' => 'Auto Apply Updates', 'type' => 'toggle', 'default' => false],
            ['key' => 'notify_on_update', 'label' => 'Notify on Update', 'type' => 'toggle', 'default' => true],
        ];
    }

    public static function defaultConfig(): array
    {
        return [
            'auto_check_enabled' => true,
            'check_interval_hours' => 6,
            'auto_apply_updates' => false,
            'notify_on_update' => true,
        ];
    }

    public function check(): array
    {
        return BrickSystem::checkAllBricks();
    }

    public function update(string $brickId): array
    {
        return BrickSystem::applyUpdate((int)$brickId);
    }

    public function updateAll(): array
    {
        return BrickSystem::applyAllUpdates();
    }

    public function sync(): array
    {
        return BrickSystem::syncAllSources();
    }

    public function isDue(): bool
    {
        if (!$this->config['auto_check_enabled']) return false;

        $db = Database::instance();
        $stmt = $db->query("SELECT MAX(last_checked_at) as last_check FROM brick_sources WHERE is_active = 1");
        $row = $stmt->fetch();
        $lastCheck = $row['last_check'] ?? null;

        if (!$lastCheck) return true;

        $interval = $this->config['check_interval_hours'] * 3600;
        return (strtotime($lastCheck) + $interval) < time();
    }

    public function autoCheck(): array
    {
        if (!$this->isDue()) {
            return ['ok' => true, 'message' => 'Not due for check yet', 'checked' => false];
        }

        $syncResult = $this->sync();
        $updates = $this->check();

        if ($this->config['auto_apply_updates'] && !empty($updates['updates'])) {
            $applied = $this->updateAll();
            return ['ok' => true, 'checked' => true, 'synced' => $syncResult, 'updates' => $updates, 'applied' => $applied];
        }

        return ['ok' => true, 'checked' => true, 'synced' => $syncResult, 'updates' => $updates];
    }

    public static function activate(): void
    {
        $meta = self::meta();
        $existing = BrickSystem::findBySlug($meta['slug']);
        if ($existing) return;

        BrickSystem::install([
            'name' => $meta['name'],
            'slug' => $meta['slug'],
            'version' => $meta['version'],
            'description' => $meta['description'],
            'author' => $meta['author'],
            'category' => $meta['category'],
            'brick_class' => self::class,
            'installed_path' => 'src/Bricks/GitHubSync/',
            'config' => self::defaultConfig(),
        ]);
    }

    public static function deactivate(): void
    {
        $meta = self::meta();
        $existing = BrickSystem::findBySlug($meta['slug']);
        if ($existing) {
            BrickSystem::uninstall($existing['id']);
        }
    }
}
