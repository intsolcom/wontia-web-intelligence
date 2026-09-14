<?php
namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

class SettingsController
{
    public function index(): void
    {
        $db = Database::instance();
        $rows = $db->query("SELECT `key`, `value` FROM settings WHERE site_id = @site_id")->fetchAll();
        $settings = [];
        foreach ($rows as $r) $settings[$r['key']] = $r['value'];
        Response::json(['ok' => true, 'data' => $settings]);
    }

    public function update(Request $req): void
    {
        $data = $req->json();
        if (empty($data)) Response::error('No data provided', 400);
        $db = Database::instance();
        $stmt = $db->prepare("INSERT INTO settings (site_id, `key`, `value`) VALUES (@site_id, :k, :v) ON DUPLICATE KEY UPDATE `value` = :v2");
        foreach ($data as $key => $value) {
            $stmt->execute(['k' => $key, 'v' => $value, 'v2' => $value]);
        }
        Response::json(['ok' => true]);
    }

    public function themes(): void
    {
        $dir = ROOT_DIR . '/templates/themes';
        $themes = [];
        foreach (glob($dir . '/*', GLOB_ONLYDIR) ?: [] as $d) {
            $slug = basename($d);
            if ($slug === '_shared') continue;
            if (!is_file($d . '/index.php')) continue;
            $themes[] = $slug;
        }
        $active = (string)(Database::instance()->query("SELECT theme FROM sites WHERE id = @site_id")->fetchColumn() ?: '');
        Response::json(['ok' => true, 'data' => ['themes' => $themes, 'active' => $active]]);
    }

    public function setTheme(Request $req): void
    {
        if (Session::userRole() !== 'superadmin') Response::error('Forbidden', 403);
        $theme = (string)$req->input('theme', '');
        if (!preg_match('/^[a-z0-9\-]{2,50}$/', $theme)) Response::error('Invalid theme', 400);
        if (!is_file(ROOT_DIR . '/templates/themes/' . $theme . '/index.php')) Response::error('Theme not found', 404);
        $stmt = Database::instance()->prepare("UPDATE sites SET theme = :t WHERE id = @site_id");
        $stmt->execute(['t' => $theme]);
        Response::success(null, 'Tema activado: ' . $theme);
    }
}
