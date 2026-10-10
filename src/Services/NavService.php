<?php
namespace App\Services;

use App\Core\Config;
use App\Core\Database;

/**
 * NavService — navegación administrable de WWI.
 * Reemplaza los menús hardcodeados de los themes por datos editables desde el admin.
 * Multi-tenant (@site_id). Todas las ubicaciones (locations) son configurables.
 */
class NavService
{
    public const LOCATIONS = [
        'primary' => 'Menú principal',
        'footer_shop' => 'Footer · Tienda',
        'footer_help' => 'Footer · Ayuda',
        'footer_legal' => 'Footer · Legal',
    ];

    private static bool $ensured = false;

    public function ensureTables(): array
    {
        if (self::$ensured) return ['ok' => true, 'cached' => true];
        $db = Database::instance();
        $db->exec("CREATE TABLE IF NOT EXISTS nav_menus (
            id INT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL DEFAULT 1,
            location VARCHAR(50) NOT NULL,
            name VARCHAR(120) NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_site_loc (site_id, location)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS nav_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL DEFAULT 1,
            menu_id INT NOT NULL,
            parent_id INT NULL,
            label VARCHAR(160) NOT NULL,
            url VARCHAR(500) NOT NULL DEFAULT '#',
            sort_order INT NOT NULL DEFAULT 0,
            is_visible TINYINT NOT NULL DEFAULT 1,
            target VARCHAR(20) NOT NULL DEFAULT '_self',
            KEY idx_menu (menu_id, sort_order),
            KEY idx_site (site_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        self::$ensured = true;
        return ['ok' => true, 'cached' => false];
    }

    public function siteId(): int
    {
        return (int)Config::get('SITE_ID', '1');
    }

    public function menuId(string $location, bool $create = true): int
    {
        $this->ensureTables();
        $location = preg_replace('/[^a-z0-9_]/', '', strtolower($location)) ?: 'primary';
        $db = Database::instance();
        $st = $db->prepare('SELECT id FROM nav_menus WHERE site_id = @site_id AND location = ? LIMIT 1');
        $st->execute([$location]);
        $id = $st->fetchColumn();
        if ($id) return (int)$id;
        if (!$create) return 0;
        $name = self::LOCATIONS[$location] ?? ucfirst($location);
        $db->prepare('INSERT INTO nav_menus (site_id, location, name) VALUES (@site_id, ?, ?)')->execute([$location, $name]);
        return (int)$db->lastInsertId();
    }

    /** Lista plana ordenada (por padre y orden). */
    public function items(string $location, bool $visibleOnly = false): array
    {
        $this->ensureTables();
        $this->lazySeed();
        $menuId = $this->menuId($location, false);
        if ($menuId === 0) return [];
        $sql = 'SELECT id, parent_id, label, url, sort_order, is_visible, target FROM nav_items WHERE site_id = @site_id AND menu_id = ?';
        if ($visibleOnly) $sql .= ' AND is_visible = 1';
        $sql .= ' ORDER BY (parent_id IS NULL) DESC, parent_id ASC, sort_order ASC, id ASC';
        $st = Database::instance()->prepare($sql);
        $st->execute([$menuId]);
        return array_map(function ($r) {
            $r['id'] = (int)$r['id'];
            $r['parent_id'] = $r['parent_id'] !== null ? (int)$r['parent_id'] : null;
            $r['is_visible'] = (int)$r['is_visible'];
            return $r;
        }, $st->fetchAll());
    }

    /** Árbol anidado para render. */
    public function tree(string $location, bool $visibleOnly = true): array
    {
        $rows = $this->items($location, $visibleOnly);
        $byId = [];
        foreach ($rows as $r) { $r['children'] = []; $byId[$r['id']] = $r; }
        $root = [];
        foreach ($byId as $id => $r) {
            if ($r['parent_id'] !== null && isset($byId[$r['parent_id']])) {
                $byId[$r['parent_id']]['children'][] = &$byId[$id];
            } else {
                $root[] = &$byId[$id];
            }
        }
        return $root;
    }

    public function saveItem(array $d): array
    {
        $this->ensureTables();
        $location = (string)($d['location'] ?? 'primary');
        $menuId = $this->menuId($location);
        $label = trim((string)($d['label'] ?? ''));
        if ($label === '') return ['ok' => false, 'message' => 'Falta la etiqueta.'];
        $url = trim((string)($d['url'] ?? '#')) ?: '#';
        $parent = !empty($d['parent_id']) ? (int)$d['parent_id'] : null;
        $target = in_array(($d['target'] ?? '_self'), ['_self', '_blank'], true) ? $d['target'] : '_self';
        $visible = !empty($d['is_visible']) ? 1 : 0;
        $order = (int)($d['sort_order'] ?? 0);
        $db = Database::instance();
        $id = (int)($d['id'] ?? 0);
        if ($id > 0) {
            $db->prepare('UPDATE nav_items SET parent_id=?, label=?, url=?, sort_order=?, is_visible=?, target=? WHERE id=? AND site_id=@site_id')
               ->execute([$parent, $label, $url, $order, $visible, $target, $id]);
            return ['ok' => true, 'id' => $id];
        }
        if ($order === 0) {
            $mx = $db->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM nav_items WHERE site_id=@site_id AND menu_id=?');
            $mx->execute([$menuId]);
            $order = (int)$mx->fetchColumn();
        }
        $db->prepare('INSERT INTO nav_items (site_id, menu_id, parent_id, label, url, sort_order, is_visible, target) VALUES (@site_id,?,?,?,?,?,?,?)')
           ->execute([$menuId, $parent, $label, $url, $order, $visible, $target]);
        return ['ok' => true, 'id' => (int)$db->lastInsertId()];
    }

    public function deleteItem(int $id): array
    {
        $this->ensureTables();
        Database::instance()->prepare('DELETE FROM nav_items WHERE id=? AND site_id=@site_id')->execute([$id]);
        Database::instance()->prepare('DELETE FROM nav_items WHERE parent_id=? AND site_id=@site_id')->execute([$id]);
        return ['ok' => true];
    }

    public function reorder(string $location, array $ids): array
    {
        $this->ensureTables();
        $db = Database::instance();
        $i = 1;
        $st = $db->prepare('UPDATE nav_items SET sort_order=? WHERE id=? AND site_id=@site_id');
        foreach ($ids as $id) { $st->execute([$i++, (int)$id]); }
        return ['ok' => true];
    }

    private static bool $seeded = false;

    private function lazySeed(): void
    {
        if (self::$seeded) return;
        self::$seeded = true;
        try {
            $db = Database::instance();
            $c = (int)$db->query('SELECT COUNT(*) FROM nav_menus WHERE site_id=@site_id')->fetchColumn();
            if ($c > 0) return;
            // Solo sembrar si el tema activo opta por el CMS de menús (theme.json -> "cms_nav": true).
            $theme = (string)$db->query('SELECT theme FROM sites WHERE id=@site_id')->fetchColumn();
            $opts = false;
            if ($theme !== '' && preg_match('/^[a-z0-9\-]{2,50}$/', $theme)) {
                $f = ROOT_DIR . '/templates/themes/' . $theme . '/theme.json';
                if (is_file($f)) {
                    $m = json_decode((string)file_get_contents($f), true) ?: [];
                    $opts = !empty($m['cms_nav']);
                }
            }
            if ($opts) $this->seedDefaults();
        } catch (\Throwable $e) {
        }
    }

    /** Menú por defecto (referencia, también usado por el onboarding). */
    private function defaultMenu(): array
    {
        return [
            'primary' => [['Inicio', '/'], ['Tienda', '/tienda'], ['Ofertas', '/ofertas'], ['Marcas', '/marcas'], ['Contacto', '/contacto'], ['Blog', '/blog']],
            'footer_shop' => [['Todos los productos', '/tienda'], ['Ofertas', '/ofertas'], ['Marcas', '/marcas']],
            'footer_help' => [['Envíos y entregas', '/envios'], ['Devoluciones', '/devoluciones'], ['Garantía', '/garantia'], ['Contacto', '/contacto']],
            'footer_legal' => [['Privacidad', '/privacidad'], ['Términos', '/terminos'], ['Cookies', '/cookies']],
        ];
    }

    /** Siembra menús por defecto (idempotente) si el sitio no tiene ninguno. */
    public function seedDefaults(): array
    {
        $this->ensureTables();
        $db = Database::instance();
        $count = (int)$db->query('SELECT COUNT(*) FROM nav_menus WHERE site_id=@site_id')->fetchColumn();
        if ($count > 0) return ['ok' => true, 'seeded' => false];
        foreach ($this->defaultMenu() as $loc => $items) {
            $menuId = $this->menuId($loc);
            $order = 1;
            foreach ($items as $it) {
                $db->prepare('INSERT INTO nav_items (site_id, menu_id, label, url, sort_order, is_visible, target) VALUES (@site_id,?,?,?,?,1,\'_self\')')
                   ->execute([$menuId, $it[0], $it[1], $order++]);
            }
        }
        return ['ok' => true, 'seeded' => true];
    }

    /** Siembra menús por defecto para un site_id explícito (onboarding de clientes). */
    public function seedForSite(int $siteId): array
    {
        $this->ensureTables();
        $db = Database::instance();
        $st = $db->prepare('SELECT COUNT(*) FROM nav_menus WHERE site_id = ?');
        $st->execute([$siteId]);
        if ((int)$st->fetchColumn() > 0) return ['ok' => true, 'seeded' => false];
        foreach ($this->defaultMenu() as $loc => $items) {
            $db->prepare('INSERT INTO nav_menus (site_id, location, name) VALUES (?,?,?)')
               ->execute([$siteId, $loc, self::LOCATIONS[$loc] ?? $loc]);
            $menuId = (int)$db->lastInsertId();
            $order = 1;
            $ins = $db->prepare('INSERT INTO nav_items (site_id, menu_id, label, url, sort_order, is_visible, target) VALUES (?,?,?,?,?,1,\'_self\')');
            foreach ($items as $it) { $ins->execute([$siteId, $menuId, $it[0], $it[1], $order++]); }
        }
        return ['ok' => true, 'seeded' => true];
    }
}
