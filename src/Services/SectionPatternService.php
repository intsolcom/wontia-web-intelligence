<?php
namespace App\Services;

use App\Core\Database;

class SectionPatternService
{
    public function ensureTables(): void
    {
        Database::instance()->exec("CREATE TABLE IF NOT EXISTS wwi_section_patterns (
            id INT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL DEFAULT 1,
            name VARCHAR(150) NOT NULL,
            widget_type VARCHAR(100) DEFAULT '',
            title VARCHAR(200) DEFAULT '',
            subtitle TEXT,
            config JSON,
            created_by VARCHAR(100) DEFAULT '',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_site (site_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function sectionInSite(int $sectionId): ?array
    {
        $stmt = Database::instance()->prepare("SELECT s.* FROM sections s JOIN pages p ON p.id = s.page_id WHERE s.id = :id AND p.site_id = @site_id LIMIT 1");
        $stmt->execute(['id' => $sectionId]);
        return $stmt->fetch() ?: null;
    }

    public function pageInSite(int $pageId): bool
    {
        $stmt = Database::instance()->prepare("SELECT id FROM pages WHERE id = :id AND site_id = @site_id");
        $stmt->execute(['id' => $pageId]);
        return (bool)$stmt->fetch();
    }

    public function list(): array
    {
        $this->ensureTables();
        $stmt = Database::instance()->query("SELECT id, name, widget_type, title, created_by, created_at FROM wwi_section_patterns WHERE site_id = @site_id ORDER BY id DESC LIMIT 200");
        return $stmt->fetchAll();
    }

    public function saveFromSection(int $sectionId, string $name, string $user): array
    {
        $this->ensureTables();
        $sec = $this->sectionInSite($sectionId);
        if (!$sec) return ['ok' => false, 'message' => 'Sección no encontrada'];
        $name = trim($name);
        if ($name === '') $name = trim((string)($sec['title'] ?? 'Patrón')) ?: 'Patrón';
        Database::instance()->prepare("INSERT INTO wwi_section_patterns (site_id, name, widget_type, title, subtitle, config, created_by) VALUES (@site_id, :n, :w, :t, :st, :c, :u)")
            ->execute([
                'n' => substr($name, 0, 150),
                'w' => (string)($sec['widget_type'] ?? ''),
                't' => substr((string)($sec['title'] ?? ''), 0, 200),
                'st' => (string)($sec['subtitle'] ?? ''),
                'c' => (string)($sec['config'] ?? '{}'),
                'u' => substr($user, 0, 100),
            ]);
        return ['ok' => true, 'message' => 'Patrón guardado', 'id' => (int)Database::instance()->lastInsertId()];
    }

    public function delete(int $id): array
    {
        $this->ensureTables();
        $stmt = Database::instance()->prepare("DELETE FROM wwi_section_patterns WHERE id = :id AND site_id = @site_id");
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0 ? ['ok' => true, 'message' => 'Patrón eliminado'] : ['ok' => false, 'message' => 'No encontrado'];
    }

    public function insert(int $patternId, int $pageId): array
    {
        $this->ensureTables();
        if (!$this->pageInSite($pageId)) return ['ok' => false, 'message' => 'Página no encontrada'];
        $stmt = Database::instance()->prepare("SELECT * FROM wwi_section_patterns WHERE id = :id AND site_id = @site_id");
        $stmt->execute(['id' => $patternId]);
        $p = $stmt->fetch();
        if (!$p) return ['ok' => false, 'message' => 'Patrón no encontrado'];
        $db = Database::instance();
        $maxSort = $db->prepare("SELECT COALESCE(MAX(sort_order), -1) FROM sections WHERE page_id = :pid");
        $maxSort->execute(['pid' => $pageId]);
        $nextSort = (int)$maxSort->fetchColumn() + 1;
        $db->prepare("INSERT INTO sections (page_id, type, widget_type, title, subtitle, content, image, config, sort_order, is_active) VALUES (:pid, 'widget', :widget_type, :title, :subtitle, '', '', :config, :sort_order, 1)")
            ->execute([
                'pid' => $pageId,
                'widget_type' => (string)($p['widget_type'] ?? ''),
                'title' => (string)($p['title'] ?? ''),
                'subtitle' => (string)($p['subtitle'] ?? ''),
                'config' => (string)($p['config'] ?? '{}'),
                'sort_order' => $nextSort,
            ]);
        return ['ok' => true, 'message' => 'Patrón insertado', 'id' => (int)$db->lastInsertId()];
    }
}
