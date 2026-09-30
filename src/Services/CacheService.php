<?php
namespace App\Services;

use App\Core\Database;

class CacheService
{
    private function root(): string
    {
        return defined('ROOT_DIR') ? (string)\ROOT_DIR : dirname(__DIR__, 2);
    }

    private function dirs(): array
    {
        $root = $this->root();
        return [
            'app_cache' => $root . '/cache',
            'storage_cache' => $root . '/storage/cache',
            'tmp' => $root . '/tmp',
        ];
    }

    private function dirStats(string $dir): array
    {
        $size = 0; $files = 0;
        if (!is_dir($dir)) return ['size' => 0, 'files' => 0, 'exists' => false];
        try {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if ($f->isFile()) { $size += (int)$f->getSize(); $files++; }
            }
        } catch (\Throwable $e) {
        }
        return ['size' => $size, 'files' => $files, 'exists' => true];
    }

    private function setting(string $k): string
    {
        try {
            $st = Database::instance()->prepare("SELECT `value` FROM settings WHERE site_id = @site_id AND `key` = :k");
            $st->execute(['k' => $k]);
            return (string)($st->fetchColumn() ?: '');
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function setSetting(string $k, string $v): void
    {
        Database::instance()->prepare("INSERT INTO settings (site_id, `key`, `value`) VALUES (@site_id, :k, :v) ON DUPLICATE KEY UPDATE `value` = :v2")
            ->execute(['k' => $k, 'v' => $v, 'v2' => $v]);
    }

    public function overview(): array
    {
        $out = ['dirs' => [], 'total_size' => 0, 'total_files' => 0, 'uploads_size' => 0, 'uploads_files' => 0];
        foreach ($this->dirs() as $k => $d) {
            $st = $this->dirStats($d);
            $out['dirs'][$k] = ['path' => $d, 'exists' => $st['exists'], 'size' => $st['size'], 'files' => $st['files']];
            $out['total_size'] += $st['size'];
            $out['total_files'] += $st['files'];
        }
        $up = $this->dirStats($this->root() . '/public/assets/uploads');
        $out['uploads_size'] = $up['size'];
        $out['uploads_files'] = $up['files'];
        $out['last_purge'] = $this->setting('cache_last_purge');
        $out['asset_version'] = $this->setting('asset_version');
        $out['opcache'] = function_exists('opcache_get_status') && @opcache_get_status(false) !== false;
        return $out;
    }

    public function purge(array $opts): array
    {
        $done = [];
        if (!empty($opts['app_cache'])) {
            $this->clearDir($this->root() . '/cache');
            $this->clearDir($this->root() . '/storage/cache');
            $done[] = 'app_cache';
        }
        if (!empty($opts['tmp'])) {
            $this->clearDir($this->root() . '/tmp');
            $done[] = 'tmp';
        }
        if (!empty($opts['opcache'])) {
            if (function_exists('opcache_reset')) { @opcache_reset(); $done[] = 'opcache'; }
        }
        $assetVersion = $this->setting('asset_version');
        if (!empty($opts['assets'])) {
            $assetVersion = (string)time();
            $this->setSetting('asset_version', $assetVersion);
            $done[] = 'assets';
        }
        if (!$done) return ['ok' => false, 'message' => 'Selecciona al menos una acción', 'done' => []];
        $this->setSetting('cache_last_purge', date('Y-m-d H:i:s'));
        return ['ok' => true, 'message' => 'Caché purgada', 'done' => $done, 'asset_version' => $assetVersion];
    }

    public function assetVersion(): string
    {
        return $this->setting('asset_version');
    }

    private function clearDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        // Whitelist: solo dentro de ROOT_DIR
        $real = realpath($dir);
        $root = realpath($this->root());
        if ($real === false || $root === false || strpos($real, $root) !== 0) return;
        try {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($real, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($it as $f) {
                if ($f->isDir()) @rmdir($f->getPathname());
                else @unlink($f->getPathname());
            }
        } catch (\Throwable $e) {
        }
    }
}
