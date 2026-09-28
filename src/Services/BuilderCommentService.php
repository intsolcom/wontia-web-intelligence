<?php
namespace App\Services;

use App\Core\Database;

class BuilderCommentService
{
    public function ensureTables(): void
    {
        Database::instance()->exec("CREATE TABLE IF NOT EXISTS wwi_builder_comments (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL DEFAULT 1,
            block_id INT NOT NULL,
            page_id INT NOT NULL DEFAULT 0,
            user_id INT DEFAULT NULL,
            username VARCHAR(100) DEFAULT '',
            body VARCHAR(1500) NOT NULL,
            status ENUM('open','resolved') DEFAULT 'open',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_page (site_id, page_id),
            INDEX idx_block (site_id, block_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function list(int $pageId): array
    {
        $this->ensureTables();
        $stmt = Database::instance()->prepare("SELECT id, block_id, user_id, username, body, status, created_at FROM wwi_builder_comments WHERE page_id = :p AND site_id = @site_id ORDER BY id DESC LIMIT 300");
        $stmt->execute(['p' => $pageId]);
        return $stmt->fetchAll();
    }

    public function add(int $blockId, int $pageId, int $userId, string $username, string $body): array
    {
        $body = trim($body);
        if ($body === '') return ['ok' => false, 'message' => 'Comentario vacío'];
        $this->ensureTables();
        Database::instance()->prepare("INSERT INTO wwi_builder_comments (site_id, block_id, page_id, user_id, username, body) VALUES (@site_id, :b, :p, :u, :n, :body)")
            ->execute(['b' => $blockId, 'p' => $pageId, 'u' => $userId, 'n' => substr($username, 0, 100), 'body' => substr($body, 0, 1500)]);
        return ['ok' => true, 'message' => 'Comentario añadido', 'id' => (int)Database::instance()->lastInsertId()];
    }

    public function status(int $id, string $status): array
    {
        $status = $status === 'resolved' ? 'resolved' : 'open';
        $stmt = Database::instance()->prepare("UPDATE wwi_builder_comments SET status = :s WHERE id = :id AND site_id = @site_id");
        $stmt->execute(['s' => $status, 'id' => $id]);
        return $stmt->rowCount() > 0 ? ['ok' => true, 'message' => 'Actualizado'] : ['ok' => false, 'message' => 'No encontrado'];
    }

    public function delete(int $id): array
    {
        $stmt = Database::instance()->prepare("DELETE FROM wwi_builder_comments WHERE id = :id AND site_id = @site_id");
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0 ? ['ok' => true, 'message' => 'Eliminado'] : ['ok' => false, 'message' => 'No encontrado'];
    }
}
