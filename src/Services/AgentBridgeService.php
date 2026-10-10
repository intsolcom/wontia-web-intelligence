<?php
namespace App\Services;

use App\Core\Config;
use App\Core\Database;

/**
 * AgentBridgeService — canal de coordinación de agentes de WWI (núcleo).
 * Mensajes entre agentes de sitio y el Head Team Leader, con API por clave de agente.
 */
class AgentBridgeService
{
    public const TYPES = ['SOLICITUD', 'ORDEN', 'RESULTADO', 'RESPUESTA', 'ACLARACION', 'NOTA'];

    private static bool $ensured = false;

    public function ensureTables(): array
    {
        if (self::$ensured) return ['ok' => true, 'cached' => true];
        $db = Database::instance();
        $db->exec("CREATE TABLE IF NOT EXISTS agent_bridge_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NULL,
            site_slug VARCHAR(80) NULL,
            from_role VARCHAR(80) NOT NULL,
            to_role VARCHAR(80) NOT NULL DEFAULT 'head-team-leader',
            type ENUM('SOLICITUD','ORDEN','RESULTADO','RESPUESTA','ACLARACION','NOTA') NOT NULL DEFAULT 'NOTA',
            subject VARCHAR(200) NULL,
            body TEXT NOT NULL,
            status ENUM('abierto','en_curso','resuelto') NOT NULL DEFAULT 'abierto',
            meta JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_site (site_id, created_at),
            KEY idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS agent_bridge_agents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(80) NOT NULL,
            site_id INT NULL,
            name VARCHAR(160) NULL,
            api_key_hash VARCHAR(255) NOT NULL,
            is_active TINYINT NOT NULL DEFAULT 1,
            last_seen DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        self::$ensured = true;
        return ['ok' => true, 'cached' => false];
    }

    private function keyHash(string $key): string
    {
        return hash_hmac('sha256', 'agent-bridge|' . $key, (string)Config::get('JWT_SECRET', 'wwi'));
    }

    public function post(array $d): array
    {
        $this->ensureTables();
        $from = substr(trim((string)($d['from_role'] ?? 'agent')), 0, 80) ?: 'agent';
        $to = substr(trim((string)($d['to_role'] ?? 'head-team-leader')), 0, 80) ?: 'head-team-leader';
        $type = strtoupper((string)($d['type'] ?? 'NOTA'));
        if (!in_array($type, self::TYPES, true)) $type = 'NOTA';
        $body = trim((string)($d['body'] ?? ''));
        if ($body === '') return ['ok' => false, 'message' => 'Mensaje vacío.'];
        $status = in_array(($d['status'] ?? 'abierto'), ['abierto', 'en_curso', 'resuelto'], true) ? $d['status'] : 'abierto';
        $stmt = Database::instance()->prepare('INSERT INTO agent_bridge_messages (site_id, site_slug, from_role, to_role, type, subject, body, status, meta) VALUES (?,?,?,?,?,?,?,?,?)');
        $stmt->execute([
            isset($d['site_id']) && $d['site_id'] !== null ? (int)$d['site_id'] : null,
            isset($d['site_slug']) ? substr((string)$d['site_slug'], 0, 80) : null,
            $from, $to, $type,
            isset($d['subject']) ? substr((string)$d['subject'], 0, 200) : null,
            substr($body, 0, 20000),
            $status,
            !empty($d['meta']) ? json_encode($d['meta'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ]);
        return ['ok' => true, 'id' => (int)Database::instance()->lastInsertId()];
    }

    public function feed(?int $siteId = null, int $limit = 200, ?int $afterId = null): array
    {
        $this->ensureTables();
        $limit = max(1, min(500, $limit));
        $where = [];
        $params = [];
        if ($siteId !== null) { $where[] = 'site_id = ?'; $params[] = $siteId; }
        if ($afterId !== null) { $where[] = 'id > ?'; $params[] = $afterId; }
        $sql = 'SELECT id, site_id, site_slug, from_role, to_role, type, subject, body, status, created_at FROM agent_bridge_messages';
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY id DESC LIMIT ' . $limit;
        $st = Database::instance()->prepare($sql);
        $st->execute($params);
        return array_reverse($st->fetchAll());
    }

    public function maxId(): int
    {
        $this->ensureTables();
        return (int)Database::instance()->query('SELECT COALESCE(MAX(id),0) FROM agent_bridge_messages')->fetchColumn();
    }

    public function setStatus(int $id, string $status): array
    {
        $this->ensureTables();
        if (!in_array($status, ['abierto', 'en_curso', 'resuelto'], true)) return ['ok' => false];
        Database::instance()->prepare('UPDATE agent_bridge_messages SET status = ? WHERE id = ?')->execute([$status, $id]);
        return ['ok' => true];
    }

    // ── Agentes + claves ──

    public function agents(): array
    {
        $this->ensureTables();
        $st = Database::instance()->query('SELECT id, slug, site_id, name, is_active, last_seen, created_at FROM agent_bridge_agents ORDER BY id DESC');
        return $st->fetchAll();
    }

    public function createAgent(string $slug, string $name = '', ?int $siteId = null, bool $rotate = false): array
    {
        $this->ensureTables();
        $slug = preg_replace('/[^a-z0-9\-_]/', '', strtolower($slug));
        if ($slug === '') return ['ok' => false, 'message' => 'Slug inválido.'];
        $key = bin2hex(random_bytes(16));
        $hash = $this->keyHash($key);
        $db = Database::instance();
        $st = $db->prepare('SELECT id FROM agent_bridge_agents WHERE slug = ? LIMIT 1');
        $st->execute([$slug]);
        $id = (int)$st->fetchColumn();
        if ($id > 0) {
            if (!$rotate) return ['ok' => false, 'message' => 'El agente ya existe. Usa rotar para nueva clave.'];
            $db->prepare('UPDATE agent_bridge_agents SET api_key_hash = ?, name = ?, site_id = ?, is_active = 1 WHERE id = ?')
               ->execute([$hash, substr($name, 0, 160), $siteId, $id]);
        } else {
            $db->prepare('INSERT INTO agent_bridge_agents (slug, site_id, name, api_key_hash, is_active) VALUES (?,?,?,?,1)')
               ->execute([$slug, $siteId, substr($name, 0, 160), $hash]);
            $id = (int)$db->lastInsertId();
        }
        return ['ok' => true, 'id' => $id, 'slug' => $slug, 'key' => $key, 'message' => 'Clave generada. Cópiala ahora; no se vuelve a mostrar.'];
    }

    public function verifyByKey(string $key): ?array
    {
        $this->ensureTables();
        if ($key === '') return null;
        $st = Database::instance()->prepare('SELECT * FROM agent_bridge_agents WHERE api_key_hash = ? AND is_active = 1 LIMIT 1');
        $st->execute([$this->keyHash($key)]);
        $row = $st->fetch();
        if (!$row) return null;
        Database::instance()->prepare('UPDATE agent_bridge_agents SET last_seen = NOW() WHERE id = ?')->execute([$row['id']]);
        return $row;
    }
}
