<?php
namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

class SettingsController
{
    public function index(): void
    {
        $db = Database::instance();
        $rows = $db->query("SELECT `key`, `value` FROM settings WHERE site_id = 1")->fetchAll();
        $settings = [];
        foreach ($rows as $r) $settings[$r['key']] = $r['value'];
        Response::json(['ok' => true, 'data' => $settings]);
    }

    public function update(Request $req): void
    {
        $data = $req->json();
        if (empty($data)) Response::error('No data provided', 400);
        $db = Database::instance();
        $stmt = $db->prepare("INSERT INTO settings (site_id, `key`, `value`) VALUES (1, :k, :v) ON DUPLICATE KEY UPDATE `value` = :v2");
        foreach ($data as $key => $value) {
            $stmt->execute(['k' => $key, 'v' => $value, 'v2' => $value]);
        }
        Response::json(['ok' => true]);
    }
}
