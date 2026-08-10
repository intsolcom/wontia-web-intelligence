<?php
namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

class MediaController
{
    public function index(Request $req): void
    {
        $db = Database::instance();
        $page = max(1, (int)($req->get('page', 1)));
        $limit = 30;
        $offset = ($page - 1) * $limit;
        $type = $req->get('type');
        $where = "WHERE site_id = @site_id";
        $params = [];
        if ($type) {
            $where .= " AND mime LIKE :type";
            $params['type'] = "$type%";
        }
        $count = $db->prepare("SELECT COUNT(*) FROM media $where")->execute($params)->fetchColumn();
        $media = $db->prepare("SELECT * FROM media $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset")->execute($params)->fetchAll();
        Response::json(['ok' => true, 'data' => $media, 'total' => (int)$count, 'page' => $page]);
    }

    public function upload(Request $req): void
    {
        if (empty($_FILES['file'])) Response::error('No file uploaded', 400);
        $file = $_FILES['file'];
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml', 'image/gif', 'image/x-icon', 'image/vnd.microsoft.icon'];
        $maxSize = 5 * 1024 * 1024;
        if (!in_array($file['type'], $allowed)) Response::error('File type not allowed: ' . $file['type'], 400);
        if ($file['size'] > $maxSize) Response::error('File too large (max 5MB)', 400);

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $name = uniqid('wontia_') . '.' . strtolower($ext);
        $dir = ROOT_DIR . '/public/assets/uploads/';
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $path = $dir . $name;

        if (!move_uploaded_file($file['tmp_name'], $path)) Response::error('Upload failed', 500);

        [$w, $h] = @getimagesize($path) ?: [0, 0];
        $url = '/assets/uploads/' . $name;
        $size = filesize($path);

        $db = Database::instance();
        $db->prepare("INSERT INTO media (site_id, filename, url, size, mime, alt_text, width, height) VALUES (1, :name, :url, :size, :mime, :alt, :w, :h)")
            ->execute(['name' => $name, 'url' => $url, 'size' => $size, 'mime' => $file['type'], 'alt' => $req->input('alt_text', ''), 'w' => $w, 'h' => $h]);
        $id = $db->lastInsertId();
        Response::json(['ok' => true, 'data' => ['id' => $id, 'url' => $url, 'filename' => $name]], 201);
    }

    public function destroy(Request $req, string $id): void
    {
        $db = Database::instance();
        $media = $db->prepare("SELECT * FROM media WHERE id = :id AND site_id = @site_id");
        $media->execute(['id' => $id]);
        $m = $media->fetch();
        if ($m) {
            $path = ROOT_DIR . '/public' . $m['url'];
            if (file_exists($path)) unlink($path);
            $db->prepare("DELETE FROM media WHERE id = :id")->execute(['id' => $id]);
        }
        Response::json(['ok' => true]);
    }
}
