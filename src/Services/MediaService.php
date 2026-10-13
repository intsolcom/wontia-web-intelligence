<?php
namespace App\Services;

use App\Core\Database;

/**
 * MediaService — Media Library 2.0 (multi-tenant, sin site_id leaks).
 * - Carpetas + tipos (image/video/icon/doc/audio/other) + papelera.
 * - Optimización automática a WebP + thumbnail (GD). El original SIEMPRE se conserva.
 * - Dedupe por hash. Metadatos: ruta relativa, peso original, formato y peso convertido.
 * - Sincroniza assets de marca (logo/favicon) al catálogo.
 */
class MediaService
{
    private static bool $ensured = false;

    public const KINDS = ['image', 'video', 'icon', 'doc', 'audio', 'other'];
    private const TYPE = [
        'jpg' => ['image/jpeg', 'image'], 'jpeg' => ['image/jpeg', 'image'], 'png' => ['image/png', 'image'],
        'webp' => ['image/webp', 'image'], 'avif' => ['image/avif', 'image'], 'gif' => ['image/gif', 'image'],
        'svg' => ['image/svg+xml', 'icon'], 'ico' => ['image/x-icon', 'icon'],
        'mp4' => ['video/mp4', 'video'], 'webm' => ['video/webm', 'video'], 'mov' => ['video/quicktime', 'video'],
        'mp3' => ['audio/mpeg', 'audio'], 'wav' => ['audio/wav', 'audio'],
        'pdf' => ['application/pdf', 'doc'],
    ];

    public function ensureTables(): array
    {
        if (self::$ensured) return ['ok' => true, 'cached' => true];
        $db = Database::instance();
        $cols = [
            'folder_id' => "ALTER TABLE media ADD COLUMN folder_id INT NULL",
            'original_name' => "ALTER TABLE media ADD COLUMN original_name VARCHAR(255) NULL",
            'original_size' => "ALTER TABLE media ADD COLUMN original_size INT NULL",
            'original_ext' => "ALTER TABLE media ADD COLUMN original_ext VARCHAR(10) NULL",
            'converted_size' => "ALTER TABLE media ADD COLUMN converted_size INT NULL",
            'converted_ext' => "ALTER TABLE media ADD COLUMN converted_ext VARCHAR(10) NULL",
            'variants' => "ALTER TABLE media ADD COLUMN variants JSON NULL",
            'hash' => "ALTER TABLE media ADD COLUMN hash CHAR(40) NULL",
            'kind' => "ALTER TABLE media ADD COLUMN kind VARCHAR(12) NULL",
            'is_brand' => "ALTER TABLE media ADD COLUMN is_brand TINYINT NOT NULL DEFAULT 0",
            'deleted_at' => "ALTER TABLE media ADD COLUMN deleted_at DATETIME NULL",
        ];
        foreach ($cols as $col => $sql) {
            try {
                $st = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'media' AND COLUMN_NAME = ?");
                $st->execute([$col]);
                if ((int)$st->fetchColumn() === 0) $db->exec($sql);
            } catch (\Throwable $e) {
            }
        }
        $db->exec("CREATE TABLE IF NOT EXISTS media_folders (
            id INT AUTO_INCREMENT PRIMARY KEY, site_id INT NOT NULL DEFAULT 1, parent_id INT NULL,
            name VARCHAR(120) NOT NULL, slug VARCHAR(140) NOT NULL, kind VARCHAR(12) NOT NULL DEFAULT 'mixed',
            sort_order INT NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_folder (site_id, slug), KEY idx_parent (site_id, parent_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $this->seedFolders();
        self::$ensured = true;
        return ['ok' => true, 'cached' => false];
    }

    private function seedFolders(): void
    {
        $defaults = [['Imágenes', 'imagenes', 'image'], ['Videos', 'videos', 'video'], ['Iconos', 'iconos', 'icon'], ['Marca', 'marca', 'brand'], ['Documentos', 'documentos', 'doc']];
        try {
            $st = Database::instance()->prepare("INSERT IGNORE INTO media_folders (site_id, name, slug, kind, sort_order) VALUES (@site_id, ?, ?, ?, ?)");
            $i = 0;
            foreach ($defaults as $d) $st->execute([$d[0], $d[1], $d[2], $i++]);
        } catch (\Throwable $e) {
        }
    }

    // ── Folders ──

    public function folders(): array
    {
        $this->ensureTables();
        $st = Database::instance()->prepare("SELECT f.*, (SELECT COUNT(*) FROM media m WHERE m.site_id = f.site_id AND m.folder_id = f.id AND m.deleted_at IS NULL) AS count FROM media_folders f WHERE f.site_id = @site_id ORDER BY f.sort_order ASC, f.name ASC");
        $st->execute();
        return $st->fetchAll();
    }

    public function folderSave(array $d, ?int $id = null): array
    {
        $this->ensureTables();
        $name = trim((string)($d['name'] ?? ''));
        if ($name === '') return ['ok' => false, 'message' => 'Nombre requerido.'];
        $slug = preg_replace('/[^a-z0-9-]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $name) ?: $name));
        $slug = trim($slug, '-') ?: ('f' . time());
        $kind = in_array((string)($d['kind'] ?? 'mixed'), array_merge(self::KINDS, ['mixed', 'brand']), true) ? (string)$d['kind'] : 'mixed';
        $db = Database::instance();
        if ($id) {
            $db->prepare("UPDATE media_folders SET name = ?, slug = ?, kind = ? WHERE id = ? AND site_id = @site_id")->execute([$name, $slug, $kind, $id]);
            return ['ok' => true, 'id' => $id];
        }
        $db->prepare("INSERT INTO media_folders (site_id, name, slug, kind) VALUES (@site_id, ?, ?, ?)")->execute([$name, $slug, $kind]);
        return ['ok' => true, 'id' => (int)$db->lastInsertId()];
    }

    public function folderDelete(int $id): array
    {
        $this->ensureTables();
        $db = Database::instance();
        $db->prepare("UPDATE media SET folder_id = NULL WHERE site_id = @site_id AND folder_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM media_folders WHERE id = ? AND site_id = @site_id")->execute([$id]);
        return ['ok' => true];
    }

    // ── Upload + optimization ──

    public function upload(array $file, string $alt = '', ?int $folderId = null): array
    {
        $this->ensureTables();
        $maxSize = 8 * 1024 * 1024;
        if (empty($file) || empty($file['tmp_name'])) return ['ok' => false, 'message' => 'No se recibió archivo.'];
        $origName = (string)($file['name'] ?? 'archivo');
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!isset(self::TYPE[$ext])) return ['ok' => false, 'message' => 'Tipo no permitido: .' . $ext];
        [$mime, $kind] = self::TYPE[$ext];
        if (($file['size'] ?? 0) <= 0 || $file['size'] > $maxSize) return ['ok' => false, 'message' => 'Archivo muy grande (máx 8MB).'];
        $tmp = $file['tmp_name'];
        if (!is_uploaded_file($tmp)) return ['ok' => false, 'message' => 'Subida inválida.'];

        $hash = (string)@hash_file('sha1', $tmp);
        $db = Database::instance();
        if ($hash !== '') {
            $d = $db->prepare("SELECT id, url, filename FROM media WHERE site_id = @site_id AND hash = ? AND deleted_at IS NULL LIMIT 1");
            $d->execute([$hash]);
            if ($dup = $d->fetch()) return ['ok' => true, 'duplicate' => true, 'data' => ['id' => (int)$dup['id'], 'url' => $dup['url'], 'filename' => $dup['filename']]];
        }

        $type = null;
        $w = 0;
        $h = 0;
        if ($kind === 'icon' && $ext === 'svg') {
            $svg = (string)@file_get_contents($tmp);
            if (stripos($svg, '<svg') === false) return ['ok' => false, 'message' => 'SVG inválido.'];
        } elseif ($kind === 'image' || $kind === 'icon') {
            $info = @getimagesize($tmp);
            if ($info === false) return ['ok' => false, 'message' => 'Imagen inválida.'];
            if (!in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF, IMAGETYPE_ICO, IMAGETYPE_BMP], true)) {
                return ['ok' => false, 'message' => 'Formato de imagen no soportado.'];
            }
            $type = $info[2];
            $w = (int)$info[0];
            $h = (int)$info[1];
        }

        $dir = dirname(__DIR__, 2) . '/public/assets/uploads/';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $fname = 'wontia_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $path = $dir . $fname;
        if (!move_uploaded_file($tmp, $path)) return ['ok' => false, 'message' => 'No se pudo guardar el archivo.'];
        @chmod($path, 0644);
        if ($ext === 'svg') {
            @file_put_contents($path, $this->svgSafe((string)file_get_contents($path)));
        }
        $origSize = (int)@filesize($path);

        $variants = [];
        $convSize = null;
        $convExt = null;
        if ($kind === 'image' && in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $v = $this->optimize($path, (int)$type);
            if ($v) {
                $base = '/assets/uploads/' . preg_replace('/\.[a-z0-9]+$/i', '', $fname);
                if (isset($v['webp'])) {
                    $variants['webp'] = ['url' => $base . '.webp', 'w' => $v['webp']['w'], 'h' => $v['webp']['h'], 'size' => $v['webp']['size']];
                    $convSize = $v['webp']['size'];
                    $convExt = 'webp';
                }
                if (isset($v['thumb'])) {
                    $variants['thumb'] = ['url' => $base . '-thumb.webp', 'w' => $v['thumb']['w'], 'h' => $v['thumb']['h'], 'size' => $v['thumb']['size']];
                }
            }
        }

        $url = '/assets/uploads/' . $fname;
        $db->prepare("INSERT INTO media (site_id, filename, url, size, mime, alt_text, width, height, folder_id, original_name, original_size, original_ext, converted_size, converted_ext, variants, hash, kind) VALUES (@site_id, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
           ->execute([$fname, $url, $origSize, $mime, mb_substr($alt, 0, 255), $w, $h, $folderId, mb_substr($origName, 0, 255), $origSize, $ext, $convSize, $convExt, json_encode($variants), $hash, $kind]);
        $id = (int)$db->lastInsertId();
        return ['ok' => true, 'data' => [
            'id' => $id, 'url' => $url, 'filename' => $fname, 'kind' => $kind,
            'original_size' => $origSize, 'converted_ext' => $convExt, 'converted_size' => $convSize,
            'variants' => $variants, 'width' => $w, 'height' => $h,
        ]];
    }

    private function resize($img, int $w, int $h)
    {
        $dst = imagecreatetruecolor($w, $h);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $img, 0, 0, 0, 0, $w, $h, imagesx($img), imagesy($img));
        return $dst;
    }

    private function optimize(string $path, int $type): array
    {
        if (!function_exists('imagewebp')) return [];
        $img = null;
        if ($type === IMAGETYPE_JPEG) $img = @imagecreatefromjpeg($path);
        elseif ($type === IMAGETYPE_PNG) $img = @imagecreatefrompng($path);
        elseif ($type === IMAGETYPE_WEBP) $img = @imagecreatefromwebp($path);
        if (!$img) return [];
        $w = imagesx($img);
        $h = imagesy($img);
        $base = preg_replace('/\.[a-z0-9]+$/i', '', $path);
        $out = [];

        $max = 2000;
        $mw = $w;
        $mh = $h;
        if (max($w, $h) > $max) {
            $r = $max / max($w, $h);
            $mw = max(1, (int)round($w * $r));
            $mh = max(1, (int)round($h * $r));
        }
        $main = ($mw !== $w || $mh !== $h) ? $this->resize($img, $mw, $mh) : $img;
        $wp = $base . '.webp';
        if (@imagewebp($main, $wp, 85)) $out['webp'] = ['w' => $mw, 'h' => $mh, 'size' => (int)@filesize($wp)];

        $tw = 480;
        $th = max(1, (int)round($h * ($tw / $w)));
        if ($th > 480) { $th = 480; $tw = max(1, (int)round($w * ($th / $h))); }
        $tw = min($tw, $w);
        $th = min($th, $h);
        $thumb = ($tw !== $w || $th !== $h) ? $this->resize($img, $tw, $th) : $img;
        $tp = $base . '-thumb.webp';
        if (@imagewebp($thumb, $tp, 80)) $out['thumb'] = ['w' => $tw, 'h' => $th, 'size' => (int)@filesize($tp)];

        if ($main !== $img) imagedestroy($main);
        if ($thumb !== $img) imagedestroy($thumb);
        imagedestroy($img);
        return $out;
    }

    private function svgSafe(string $svg): string
    {
        $svg = preg_replace('/<\s*script\b.*?<\s*\/\s*script\s*>/is', '', $svg);
        $svg = preg_replace('/<\s*script\b[^>]*\/?>/i', '', $svg);
        $svg = preg_replace('/<\s*foreignObject\b.*?<\s*\/\s*foreignObject\s*>/is', '', $svg);
        $svg = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $svg);
        $svg = preg_replace('/(href|src|xlink:href)\s*=\s*("|\')\s*javascript:[^"\']*(\2)/i', '', $svg);
        return $svg;
    }

    // ── Listing / metadata ──

    public function listMedia(array $f): array
    {
        $this->ensureTables();
        $db = Database::instance();
        $w = "site_id = @site_id";
        $p = [];
        if (!empty($f['trash'])) $w .= " AND deleted_at IS NOT NULL";
        else $w .= " AND deleted_at IS NULL";
        if (!empty($f['brand'])) $w .= " AND is_brand = 1";
        if (!empty($f['folder_id'])) { $w .= " AND folder_id = ?"; $p[] = (int)$f['folder_id']; }
        if (!empty($f['kind']) && in_array($f['kind'], self::KINDS, true)) { $w .= " AND kind = ?"; $p[] = $f['kind']; }
        if (!empty($f['q'])) { $w .= " AND (filename LIKE ? OR original_name LIKE ? OR alt_text LIKE ?)"; $like = '%' . $f['q'] . '%'; array_push($p, $like, $like, $like); }
        $limit = max(1, min(120, (int)($f['limit'] ?? 60)));
        $st = $db->prepare("SELECT id, filename, url, size, mime, alt_text, width, height, folder_id, original_name, original_size, original_ext, converted_size, converted_ext, variants, kind, is_brand, deleted_at, created_at FROM media WHERE $w ORDER BY created_at DESC LIMIT $limit");
        $st->execute($p);
        return $st->fetchAll();
    }

    public function move(int $id, ?int $folderId): array
    {
        $this->ensureTables();
        Database::instance()->prepare("UPDATE media SET folder_id = ? WHERE id = ? AND site_id = @site_id")->execute([$folderId, $id]);
        return ['ok' => true];
    }

    public function saveAlt(int $id, string $alt): array
    {
        $this->ensureTables();
        Database::instance()->prepare("UPDATE media SET alt_text = ? WHERE id = ? AND site_id = @site_id")->execute([mb_substr($alt, 0, 255), $id]);
        return ['ok' => true];
    }

    public function trash(int $id): array
    {
        $this->ensureTables();
        Database::instance()->prepare("UPDATE media SET deleted_at = NOW() WHERE id = ? AND site_id = @site_id")->execute([$id]);
        return ['ok' => true];
    }

    public function restore(int $id): array
    {
        $this->ensureTables();
        Database::instance()->prepare("UPDATE media SET deleted_at = NULL WHERE id = ? AND site_id = @site_id")->execute([$id]);
        return ['ok' => true];
    }

    public function purge(int $id): array
    {
        $this->ensureTables();
        $db = Database::instance();
        $st = $db->prepare("SELECT * FROM media WHERE id = ? AND site_id = @site_id LIMIT 1");
        $st->execute([$id]);
        $m = $st->fetch();
        if ($m) {
            $files = [$m['url']];
            $vars = json_decode((string)($m['variants'] ?? ''), true) ?: [];
            foreach ($vars as $v) if (!empty($v['url'])) $files[] = $v['url'];
            foreach ($files as $u) {
                if (strpos($u, '/assets/uploads/') === 0) { $path = dirname(__DIR__, 2) . '/public' . $u; if (is_file($path)) @unlink($path); }
            }
            $db->prepare("DELETE FROM media WHERE id = ?")->execute([$id]);
        }
        return ['ok' => true];
    }

    public function usage(int $id): array
    {
        $this->ensureTables();
        $db = Database::instance();
        $st = $db->prepare("SELECT url FROM media WHERE id = ? AND site_id = @site_id LIMIT 1");
        $st->execute([$id]);
        $url = (string)$st->fetchColumn();
        if ($url === '') return ['ok' => true, 'data' => []];
        $like = '%' . $url . '%';
        $hits = [];
        foreach ([
            'settings' => "SELECT COUNT(*) FROM settings WHERE site_id = @site_id AND `value` LIKE ?",
            'sections' => "SELECT COUNT(*) FROM sections s JOIN pages p ON p.id = s.page_id WHERE p.site_id = @site_id AND s.config LIKE ?",
        ] as $label => $sql) {
            try {
                $q = $db->prepare($sql);
                $q->execute([$like]);
                $n = (int)$q->fetchColumn();
                if ($n > 0) $hits[] = ['where' => $label, 'count' => $n];
            } catch (\Throwable $e) {
            }
        }
        return ['ok' => true, 'data' => $hits, 'url' => $url];
    }

    public function stats(): array
    {
        $this->ensureTables();
        $db = Database::instance();
        $st = $db->prepare("SELECT COUNT(*) c, COALESCE(SUM(COALESCE(original_size, size)),0) orig, COALESCE(SUM(converted_size),0) conv, SUM(kind='video') v, SUM(kind='icon') ic FROM media WHERE site_id = @site_id AND deleted_at IS NULL");
        $st->execute();
        $r = $st->fetch() ?: [];
        $orig = (int)($r['orig'] ?? 0);
        $conv = (int)($r['conv'] ?? 0);
        return [
            'total' => (int)($r['c'] ?? 0),
            'videos' => (int)($r['v'] ?? 0),
            'icons' => (int)($r['ic'] ?? 0),
            'original_bytes' => $orig,
            'converted_bytes' => $conv,
            'saved_bytes' => max(0, $orig - $conv),
        ];
    }

    /** Registra logo/favicon/touch del sitio en el catálogo (idempotente). */
    public function syncBrandAssets(): int
    {
        $this->ensureTables();
        $db = Database::instance();
        $keys = ['logo', 'logo_image', 'favicon', 'favicon_touch'];
        $ph = implode(',', array_fill(0, count($keys), '?'));
        $st = $db->prepare("SELECT `key`, `value` FROM settings WHERE site_id = @site_id AND `key` IN ($ph)");
        $st->execute($keys);
        $folderId = 0;
        try {
            $f = $db->prepare("SELECT id FROM media_folders WHERE site_id = @site_id AND slug = 'marca' LIMIT 1");
            $f->execute();
            $folderId = (int)$f->fetchColumn();
        } catch (\Throwable $e) {
        }
        $added = 0;
        foreach ($st->fetchAll() as $row) {
            $url = trim((string)$row['value']);
            if ($url === '') continue;
            $chk = $db->prepare("SELECT id FROM media WHERE site_id = @site_id AND url = ? LIMIT 1");
            $chk->execute([$url]);
            if ($chk->fetchColumn()) continue;
            $path = dirname(__DIR__, 2) . '/public' . (parse_url($url, PHP_URL_PATH) ?: '');
            $size = is_file($path) ? (int)filesize($path) : 0;
            $ext = strtolower(pathinfo((parse_url($url, PHP_URL_PATH) ?: ''), PATHINFO_EXTENSION));
            $kind = in_array($ext, ['svg', 'ico'], true) ? 'icon' : 'image';
            $wh = is_file($path) ? (@getimagesize($path) ?: [0, 0]) : [0, 0];
            $fname = $row['key'] . '—' . basename((string)(parse_url($url, PHP_URL_PATH) ?: $url));
            $db->prepare("INSERT INTO media (site_id, filename, url, size, mime, alt_text, width, height, folder_id, original_name, original_size, original_ext, kind, is_brand) VALUES (@site_id, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)")
               ->execute([mb_substr($fname, 0, 255), $url, $size, (string)($ext === 'svg' ? 'image/svg+xml' : ($ext === 'ico' ? 'image/x-icon' : 'image/png')), $row['key'] === 'logo' ? 'Logo' : 'Favicon', (int)($wh[0] ?? 0), (int)($wh[1] ?? 0), $folderId ?: null, mb_substr($fname, 0, 255), $size, $ext, $kind]);
            $added++;
        }
        return $added;
    }
}
