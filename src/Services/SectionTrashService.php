<?php
namespace App\Services;

use App\Core\Database;

class SectionTrashService
{
    public function ensureTables(): void
    {
        Database::instance()->exec("CREATE TABLE IF NOT EXISTS wwi_section_trash (
            id INT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL DEFAULT 1,
            section_id INT NOT NULL,
            page_id INT NOT NULL,
            widget_type VARCHAR(100) DEFAULT '',
            title VARCHAR(200) DEFAULT '',
            payload JSON NOT NULL,
            deleted_by VARCHAR(100) DEFAULT '',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_site (site_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function pageInSite(int $pageId): bool
    {
        $stmt = Database::instance()->prepare("SELECT id FROM pages WHERE id = :id AND site_id = @site_id");
        $stmt->execute(['id' => $pageId]);
        return (bool)$stmt->fetch();
    }

    public function snapshot(array $section, string $user): void
    {
        $this->ensureTables();
        Database::instance()->prepare("INSERT INTO wwi_section_trash (site_id, section_id, page_id, widget_type, title, payload, deleted_by) VALUES (@site_id, :sid, :pid, :w, :t, :p, :u)")
            ->execute([
                'sid' => (int)$section['id'],
                'pid' => (int)$section['page_id'],
                'w' => (string)($section['widget_type'] ?? ''),
                't' => substr((string)($section['title'] ?? ''), 0, 200),
                'p' => json_encode($section, JSON_UNESCAPED_UNICODE),
                'u' => substr($user, 0, 100),
            ]);
    }

    public function list(): array
    {
        $this->ensureTables();
        $stmt = Database::instance()->query("SELECT id, section_id, page_id, widget_type, title, deleted_by, created_at FROM wwi_section_trash WHERE site_id = @site_id ORDER BY id DESC LIMIT 100");
        return $stmt->fetchAll();
    }

    public function restore(int $id): array
    {
        $this->ensureTables();
        $db = Database::instance();
        $stmt = $db->prepare("SELECT * FROM wwi_section_trash WHERE id = :id AND site_id = @site_id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) return ['ok' => false, 'message' => 'No encontrado'];
        $s = json_decode((string)$row['payload'], true);
        if (!is_array($s) || !$this->pageInSite((int)$row['page_id'])) return ['ok' => false, 'message' => 'Página no disponible'];
        $maxSort = $db->prepare("SELECT COALESCE(MAX(sort_order), -1) FROM sections WHERE page_id = :pid");
        $maxSort->execute(['pid' => (int)$row['page_id']]);
        $nextSort = (int)$maxSort->fetchColumn() + 1;
        $db->prepare("INSERT INTO sections (page_id, type, widget_type, title, subtitle, content, image, config, sort_order, is_active) VALUES (:pid, :type, :widget_type, :title, :subtitle, :content, :image, :config, :sort_order, :is_active)")
            ->execute([
                'pid' => (int)$row['page_id'],
                'type' => (string)($s['type'] ?? 'widget'),
                'widget_type' => (string)($s['widget_type'] ?? ''),
                'title' => (string)($s['title'] ?? ''),
                'subtitle' => (string)($s['subtitle'] ?? ''),
                'content' => (string)($s['content'] ?? ''),
                'image' => (string)($s['image'] ?? ''),
                'config' => (string)($s['config'] ?? '{}'),
                'sort_order' => $nextSort,
                'is_active' => (int)($s['is_active'] ?? 1),
            ]);
        $newId = (int)$db->lastInsertId();
        $db->prepare("DELETE FROM wwi_section_trash WHERE id = :id AND site_id = @site_id")->execute(['id' => $id]);
        return ['ok' => true, 'message' => 'Sección restaurada', 'id' => $newId, 'page_id' => (int)$row['page_id']];
    }

    public function purge(int $id): array
    {
        $this->ensureTables();
        $stmt = Database::instance()->prepare("DELETE FROM wwi_section_trash WHERE id = :id AND site_id = @site_id");
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0 ? ['ok' => true, 'message' => 'Eliminado'] : ['ok' => false, 'message' => 'No encontrado'];
    }
}
