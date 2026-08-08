<?php
namespace App\Core;

class GitHubSyncService
{
    private static string $apiBase = 'https://api.github.com';

    public static function getLatestRelease(string $repoUrl, ?string $token = null): ?array
    {
        $repo = self::parseRepo($repoUrl);
        if (!$repo) return null;

        $url = self::$apiBase . "/repos/$repo/releases/latest";
        $response = self::apiGet($url, $token);
        if (!$response) return null;

        $data = json_decode($response, true);
        return isset($data['tag_name']) ? $data : null;
    }

    public static function getReleases(string $repoUrl, ?string $token = null, int $perPage = 10): array
    {
        $repo = self::parseRepo($repoUrl);
        if (!$repo) return [];

        $url = self::$apiBase . "/repos/$repo/releases?per_page=$perPage";
        $response = self::apiGet($url, $token);
        if (!$response) return [];

        $data = json_decode($response, true);
        return is_array($data) ? $data : [];
    }

    public static function getReleaseByTag(string $repoUrl, string $tag, ?string $token = null): ?array
    {
        $repo = self::parseRepo($repoUrl);
        if (!$repo) return null;

        $url = self::$apiBase . "/repos/$repo/releases/tags/" . urlencode($tag);
        $response = self::apiGet($url, $token);
        if (!$response) return null;

        $data = json_decode($response, true);
        return isset($data['tag_name']) ? $data : null;
    }

    public static function downloadAndExtract(string $zipUrl, string $targetPath, ?string $token = null): bool
    {
        $tmpFile = ROOT_DIR . '/cache/brick_update_' . uniqid() . '.zip';

        $ch = curl_init($zipUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_USERAGENT => 'Wontia-BrickHub/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $headers = ['Accept: application/octet-stream'];
        if ($token) {
            $headers[] = 'Authorization: token ' . $token;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$data) return false;

        $cacheDir = dirname($tmpFile);
        if (!is_dir($cacheDir)) mkdir($cacheDir, 0755, true);
        file_put_contents($tmpFile, $data);

        $zip = new \ZipArchive();
        if ($zip->open($tmpFile) !== true) {
            unlink($tmpFile);
            return false;
        }

        $fullTarget = ROOT_DIR . '/' . ltrim($targetPath, '/');
        if (!is_dir($fullTarget)) mkdir($fullTarget, 0755, true);

        $extractDir = ROOT_DIR . '/cache/brick_extract_' . uniqid();
        $zip->extractTo($extractDir);
        $zip->close();
        unlink($tmpFile);

        $extractedItems = array_diff(scandir($extractDir), ['.', '..']);
        $sourceDir = $extractDir;
        if (count($extractedItems) === 1 && is_dir($extractDir . '/' . $extractedItems[0])) {
            $sourceDir = $extractDir . '/' . $extractedItems[0];
        }

        self::copyDirectory($sourceDir, $fullTarget);
        self::removeDirectory($extractDir);

        return true;
    }

    public static function verifyRepo(string $repoUrl, ?string $token = null): array
    {
        $repo = self::parseRepo($repoUrl);
        if (!$repo) return ['ok' => false, 'message' => 'Invalid repo URL format'];

        $url = self::$apiBase . "/repos/$repo";
        $response = self::apiGet($url, $token);
        if (!$response) return ['ok' => false, 'message' => 'Repo not found or inaccessible'];

        $data = json_decode($response, true);
        return [
            'ok' => true,
            'name' => $data['full_name'] ?? $repo,
            'description' => $data['description'] ?? '',
            'stars' => $data['stargazers_count'] ?? 0,
            'language' => $data['language'] ?? '',
            'default_branch' => $data['default_branch'] ?? 'main',
            'private' => $data['private'] ?? false,
        ];
    }

    public static function discoverBricks(string $repoUrl, ?string $token = null): array
    {
        $repo = self::parseRepo($repoUrl);
        if (!$repo) return [];

        $url = self::$apiBase . "/repos/$repo/contents/src/Bricks";
        $response = self::apiGet($url, $token);
        if (!$response) {
            $url = self::$apiBase . "/repos/$repo/contents/bricks";
            $response = self::apiGet($url, $token);
            if (!$response) return [];
        }

        $items = json_decode($response, true);
        if (!is_array($items)) return [];

        $bricks = [];
        foreach ($items as $item) {
            if ($item['type'] === 'dir') {
                $bricks[] = [
                    'name' => $item['name'],
                    'slug' => strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $item['name'])),
                    'path' => $item['path'],
                ];
            }
        }
        return $bricks;
    }

    private static function parseRepo(string $url): ?string
    {
        $url = rtrim($url, '/');
        $url = rtrim($url, '.git');

        if (preg_match('#github\.com[:/]([^/]+/[^/]+)#', $url, $m)) {
            return $m[1];
        }
        return null;
    }

    private static function apiGet(string $url, ?string $token = null): ?string
    {
        $ch = curl_init($url);
        $headers = [
            'Accept: application/vnd.github+json',
            'User-Agent: Wontia-BrickHub/1.0',
        ];
        if ($token) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) return $response;

        if ($httpCode === 403) {
            $remaining = curl_getinfo($ch, CURLINFO_HEADER_OUT);
        }
        return null;
    }

    private static function copyDirectory(string $src, string $dst): void
    {
        if (!is_dir($src)) return;
        if (!is_dir($dst)) mkdir($dst, 0755, true);

        $dir = opendir($src);
        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') continue;
            $srcPath = $src . '/' . $file;
            $dstPath = $dst . '/' . $file;
            if (is_dir($srcPath)) {
                self::copyDirectory($srcPath, $dstPath);
            } else {
                copy($srcPath, $dstPath);
            }
        }
        closedir($dir);
    }

    private static function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;
        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . '/' . $item;
            is_dir($path) ? self::removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
