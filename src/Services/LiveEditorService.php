<?php
namespace App\Services;

use App\Core\Database;

class LiveEditorService
{
    public function ensureTables(): void
    {
        $db = Database::instance();
        $db->exec("CREATE TABLE IF NOT EXISTS wwi_section_comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL DEFAULT 1,
            section_id INT NOT NULL,
            user_id INT NOT NULL,
            username VARCHAR(100) DEFAULT '',
            body VARCHAR(1000) NOT NULL,
            status ENUM('open','resolved') DEFAULT 'open',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_section (section_id),
            INDEX idx_site_status (site_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS wwi_section_versions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL DEFAULT 1,
            section_id INT NOT NULL,
            user_id INT DEFAULT NULL,
            username VARCHAR(100) DEFAULT '',
            snapshot JSON NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_section (section_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS wwi_edit_presence (
            id INT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL DEFAULT 1,
            user_id INT NOT NULL,
            username VARCHAR(100) DEFAULT '',
            section_id INT DEFAULT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_user (site_id, user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function sectionInSite(int $sectionId): ?array
    {
        $stmt = Database::instance()->prepare("SELECT s.* FROM sections s JOIN pages p ON p.id = s.page_id WHERE s.id = :id AND p.site_id = @site_id LIMIT 1");
        $stmt->execute(['id' => $sectionId]);
        return $stmt->fetch() ?: null;
    }

    public function comments(int $sectionId): array
    {
        $this->ensureTables();
        if (!$this->sectionInSite($sectionId)) return [];
        $stmt = Database::instance()->prepare("SELECT id, section_id, user_id, username, body, status, created_at FROM wwi_section_comments WHERE section_id = :s AND site_id = @site_id ORDER BY id DESC LIMIT 100");
        $stmt->execute(['s' => $sectionId]);
        return $stmt->fetchAll();
    }

    public function addComment(int $sectionId, int $userId, string $username, string $body): array
    {
        $body = trim($body);
        if ($body === '' || mb_strlen($body) > 1000) return ['ok' => false, 'message' => 'Comentario inválido'];
        if (!$this->sectionInSite($sectionId)) return ['ok' => false, 'message' => 'Sección no encontrada'];
        $this->ensureTables();
        Database::instance()->prepare("INSERT INTO wwi_section_comments (site_id, section_id, user_id, username, body) VALUES (@site_id, :s, :u, :n, :b)")
            ->execute(['s' => $sectionId, 'u' => $userId, 'n' => substr($username, 0, 100), 'b' => $body]);
        return ['ok' => true, 'message' => 'Comentario añadido', 'id' => (int)Database::instance()->lastInsertId()];
    }

    public function commentStatus(int $id, string $status): array
    {
        $status = $status === 'resolved' ? 'resolved' : 'open';
        $this->ensureTables();
        $stmt = Database::instance()->prepare("UPDATE wwi_section_comments SET status = :st WHERE id = :id AND site_id = @site_id");
        $stmt->execute(['st' => $status, 'id' => $id]);
        return ['ok' => $stmt->rowCount() > 0];
    }

    public function deleteComment(int $id): array
    {
        $this->ensureTables();
        $stmt = Database::instance()->prepare("DELETE FROM wwi_section_comments WHERE id = :id AND site_id = @site_id");
        $stmt->execute(['id' => $id]);
        return ['ok' => $stmt->rowCount() > 0];
    }

    public function versions(int $sectionId): array
    {
        $this->ensureTables();
        if (!$this->sectionInSite($sectionId)) return [];
        $stmt = Database::instance()->prepare("SELECT id, section_id, username, created_at, LEFT(snapshot, 400) AS preview FROM wwi_section_versions WHERE section_id = :s AND site_id = @site_id ORDER BY id DESC LIMIT 30");
        $stmt->execute(['s' => $sectionId]);
        return $stmt->fetchAll();
    }

    public function addVersion(int $sectionId, ?int $userId, string $username, array $snapshot): void
    {
        try {
            $this->ensureTables();
            $db = Database::instance();
            $last = $db->prepare("SELECT snapshot FROM wwi_section_versions WHERE section_id = :s AND site_id = @site_id ORDER BY id DESC LIMIT 1");
            $last->execute(['s' => $sectionId]);
            $prev = $last->fetchColumn();
            $json = json_encode($snapshot, JSON_UNESCAPED_UNICODE);
            if ($prev && json_decode((string)$prev, true) == $snapshot) return;
            $db->prepare("INSERT INTO wwi_section_versions (site_id, section_id, user_id, username, snapshot) VALUES (@site_id, :s, :u, :n, :snap)")
                ->execute(['s' => $sectionId, 'u' => $userId, 'n' => substr($username, 0, 100), 'snap' => $json]);
        } catch (\Throwable $e) {
        }
    }

    public function restoreVersion(int $versionId): array
    {
        $this->ensureTables();
        $db = Database::instance();
        $stmt = $db->prepare("SELECT * FROM wwi_section_versions WHERE id = :id AND site_id = @site_id");
        $stmt->execute(['id' => $versionId]);
        $v = $stmt->fetch();
        if (!$v) return ['ok' => false, 'message' => 'Versión no encontrada'];
        if (!$this->sectionInSite((int)$v['section_id'])) return ['ok' => false, 'message' => 'Sección no encontrada'];
        $snap = json_decode((string)$v['snapshot'], true);
        if (!is_array($snap)) return ['ok' => false, 'message' => 'Snapshot inválido'];
        $fields = ['title', 'subtitle', 'content', 'config', 'is_active', 'type', 'widget_type', 'sort_order'];
        $sets = [];
        $params = ['id' => (int)$v['section_id']];
        foreach ($fields as $f) {
            if (!array_key_exists($f, $snap)) continue;
            $sets[] = "$f = :$f";
            $params[$f] = $f === 'config' ? json_encode($snap[$f]) : $snap[$f];
        }
        if (!$sets) return ['ok' => false, 'message' => 'Nada que restaurar'];
        $db->prepare("UPDATE sections SET " . implode(', ', $sets) . " WHERE id = :id AND page_id IN (SELECT id FROM pages WHERE site_id = @site_id)")->execute($params);
        return ['ok' => true, 'message' => 'Versión restaurada'];
    }

    public function presencePing(int $userId, string $username, ?int $sectionId): void
    {
        $this->ensureTables();
        Database::instance()->prepare("INSERT INTO wwi_edit_presence (site_id, user_id, username, section_id) VALUES (@site_id, :u, :n, :s) ON DUPLICATE KEY UPDATE username = :n2, section_id = :s2, updated_at = NOW()")
            ->execute(['u' => $userId, 'n' => substr($username, 0, 100), 's' => $sectionId, 'n2' => substr($username, 0, 100), 's2' => $sectionId]);
    }

    public function activePresence(int $excludeUserId): array
    {
        $this->ensureTables();
        $stmt = Database::instance()->prepare("SELECT user_id, username, section_id, updated_at FROM wwi_edit_presence WHERE site_id = @site_id AND user_id <> :u AND updated_at > (NOW() - INTERVAL 90 SECOND) ORDER BY updated_at DESC LIMIT 20");
        $stmt->execute(['u' => $excludeUserId]);
        return $stmt->fetchAll();
    }
}
