<?php
namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\AiBrick\AiRouter;

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
        $db->exec("CREATE TABLE IF NOT EXISTS bridge_tasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NULL,
            site_slug VARCHAR(80) NULL,
            message_id INT NULL,
            title VARCHAR(200) NULL,
            detail TEXT NULL,
            status ENUM('pending','in_progress','done') NOT NULL DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_status (status),
            KEY idx_site (site_id)
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
        $id = (int)Database::instance()->lastInsertId();
        if ($to === 'head-team-leader' && $from !== 'head-team-leader') {
            $this->notify($from, $type, (string)($d['subject'] ?? ''), $body);
        }
        return ['ok' => true, 'id' => $id];
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

    // ── Auto-responder Head Team Leader (BRICK) ──

    /** Mensajes dirigidos al Head Team Leader pendientes de respuesta. */
    public function pendingForHtl(int $limit = 5): array
    {
        $this->ensureTables();
        $limit = max(1, min(20, $limit));
        $st = Database::instance()->prepare("SELECT * FROM agent_bridge_messages
            WHERE to_role = 'head-team-leader' AND from_role <> 'head-team-leader'
              AND status = 'abierto' AND type IN ('SOLICITUD','NOTA','RESULTADO')
            ORDER BY id ASC LIMIT " . $limit);
        $st->execute();
        return $st->fetchAll();
    }

    /** Responde automáticamente los pendientes con BRICK (genera ORDEN). */
    public function autoRespond(int $limit = 3): array
    {
        $this->ensureTables();
        $answered = 0; $failed = 0;
        foreach ($this->pendingForHtl($limit) as $m) {
            $r = $this->htlRespond($m);
            if (!empty($r['ok'])) $answered++; else $failed++;
        }
        return ['ok' => true, 'answered' => $answered, 'failed' => $failed];
    }

    private function htlRespond(array $m): array
    {
        $reply = $this->brainstorm($m);
        if ($reply === '') return ['ok' => false, 'message' => 'BRICK no disponible'];
        $post = $this->post([
            'site_id' => $m['site_id'] ?? null,
            'site_slug' => $m['site_slug'] ?? '',
            'from_role' => 'head-team-leader',
            'to_role' => (string)$m['from_role'],
            'type' => 'ORDEN',
            'subject' => 'Re: ' . (string)($m['subject'] ?? ('#' . $m['id'])),
            'body' => $reply,
            'status' => 'abierto',
            'meta' => ['re' => (int)$m['id'], 'auto' => true],
        ]);
        $this->setStatus((int)$m['id'], 'en_curso');
        // Modo 1: si la ORDEN es de PLATAFORMA → crear ticket de plataforma.
        if (preg_match('/VEREDICTO\s*:?\s*\*{0,2}\s*PLATAFORMA/i', $reply) || stripos($reply, 'PLATAFORMA') !== false) {
            $this->createTask([
                'site_id' => $m['site_id'] ?? null,
                'site_slug' => $m['site_slug'] ?? '',
                'message_id' => (int)($post['id'] ?? 0),
                'title' => (string)($m['subject'] ?? ('Solicitud #' . $m['id'])),
                'detail' => mb_substr(strip_tags($reply), 0, 4000),
            ]);
        }
        return ['ok' => true];
    }

    // ── Cola de tareas de plataforma (modo 1) ──

    public function createTask(array $d): array
    {
        $this->ensureTables();
        $st = Database::instance()->prepare('INSERT INTO bridge_tasks (site_id, site_slug, message_id, title, detail, status) VALUES (?,?,?,?,?,\'pending\')');
        $st->execute([
            isset($d['site_id']) && $d['site_id'] !== null ? (int)$d['site_id'] : null,
            isset($d['site_slug']) ? substr((string)$d['site_slug'], 0, 80) : null,
            (int)($d['message_id'] ?? 0) ?: null,
            mb_substr((string)($d['title'] ?? ''), 0, 200),
            (string)($d['detail'] ?? ''),
        ]);
        return ['ok' => true, 'id' => (int)Database::instance()->lastInsertId()];
    }

    public function tasks(?string $status = null, int $limit = 100): array
    {
        $this->ensureTables();
        $limit = max(1, min(300, $limit));
        $sql = 'SELECT id, site_id, site_slug, message_id, title, detail, status, created_at FROM bridge_tasks';
        $params = [];
        if ($status !== null && in_array($status, ['pending', 'in_progress', 'done'], true)) {
            $sql .= ' WHERE status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY (status="pending") DESC, id DESC LIMIT ' . $limit;
        $st = Database::instance()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    public function setTaskStatus(int $id, string $status): array
    {
        $this->ensureTables();
        if (!in_array($status, ['pending', 'in_progress', 'done'], true)) return ['ok' => false];
        Database::instance()->prepare('UPDATE bridge_tasks SET status = ? WHERE id = ?')->execute([$status, $id]);
        return ['ok' => true];
    }

    private function brainstorm(array $m): string
    {
        $system = "Eres el Head Team Leader de WWI (plataforma). Un agente de sitio te escribe. Reglas: "
            . "(1) la estructura del CMS (src/**, motor de themes, menús, settings, admin.js, api.php) es TUYA, NO del agente. "
            . "(2) El agente de sitio solo hace CONTENIDO vía el admin (settings, menús, sections/Builder, catálogo). "
            . "(3) PROHIBIDO hardcodear contenido editable; todo va a settings/menús/sections/config de tema. "
            . "Responde SIEMPRE en español, breve y accionable. Formato: VEREDICTO (¿es plataforma o contenido?); "
            . "si es plataforma, escribe el PROMPT CORREGIDO que el agente debe ejecutar (encajado al contrato CMS, sin tocar código de plataforma) + criterios de aceptación; "
            . "si es contenido, indícale exactamente qué editar desde el admin. Nunca le pidas al agente tocar código de plataforma.";
        $user = "De: {$m['from_role']} | Tipo: {$m['type']} | Asunto: " . ($m['subject'] ?? '') . "\nMensaje:\n" . $m['body'];
        try {
            $router = new AiRouter();
            $r = $router->route([
                'system_id' => 'wontia',
                'module' => 'agent-bridge',
                'function' => 'head-team-leader',
                'system_prompt' => $system,
                'messages' => [['role' => 'user', 'content' => $user]],
                'temperature' => 0.3,
                'max_tokens' => 900,
            ]);
            if (!empty($r['ok']) && !empty($r['content'])) return trim((string)$r['content']);
        } catch (\Throwable $e) {
        }
        return '';
    }

    public function exportMarkdown(?int $siteId = null, ?string $siteSlug = null): string
    {
        $msgs = $this->feed($siteId, 500);
        $title = ($siteSlug !== null && $siteSlug !== '') ? $siteSlug : ('site ' . ($siteId ?? '?'));
        $out = "# Puente de agente — " . $title . "\n";
        $out .= "> Espejo generado desde `agent_bridge_messages` (fuente de verdad en la BD).\n";
        $out .= "> Head Team Leader: estructura del CMS. Agente de sitio: contenido vía CMS.\n\n";
        foreach ($msgs as $m) {
            $out .= "## [{$m['type']}] — De: {$m['from_role']} → Para: {$m['to_role']}\n";
            $out .= "- Fecha (UTC): {$m['created_at']}\n- Estado: {$m['status']}\n";
            if (!empty($m['subject'])) $out .= "- Asunto: {$m['subject']}\n";
            $out .= "\n" . rtrim((string)$m['body']) . "\n\n";
        }
        return $out;
    }

    public function exportAll(): array
    {
        $this->ensureTables();
        $db = Database::instance();
        $rows = $db->query("SELECT DISTINCT site_id, site_slug FROM agent_bridge_messages WHERE site_slug IS NOT NULL AND site_slug <> ''")->fetchAll();
        $dir = ROOT_DIR . '/agent-bridges';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $files = 0;
        foreach ($rows as $r) {
            $md = $this->exportMarkdown((int)$r['site_id'], (string)$r['site_slug']);
            if (@file_put_contents($dir . '/' . basename((string)$r['site_slug']) . '.md', $md) !== false) $files++;
        }
        return ['ok' => true, 'files' => $files];
    }

    private function notify(string $from, string $type, string $subject, string $body): void
    {
        try {
            $st = Database::instance()->prepare('SELECT `value` FROM settings WHERE site_id = @site_id AND `key` = ? LIMIT 1');
            $st->execute(['wwi.bridge_notify_email']);
            $to = trim((string)$st->fetchColumn());
            if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) return;
            $e = new EmailService();
            if (!$e->configured()) return;
            $safe = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
            $html = '<div style="font-family:Inter,Arial,sans-serif;max-width:560px;margin:auto">'
                . '<h2 style="margin:0 0 8px">WWI · Canal de agentes</h2>'
                . '<p style="font-size:14px"><b>' . $safe($from) . '</b> envió <b>' . $safe($type) . '</b>: ' . $safe($subject) . '</p>'
                . '<pre style="white-space:pre-wrap;background:#f5f5f5;padding:12px;border-radius:8px">' . $safe($body) . '</pre>'
                . '<p style="color:#777;font-size:12px">Abre el panel del central (campana) para responder.</p></div>';
            $e->sendHtml($to, '[WWI] ' . $type . ' de ' . $from, $html);
        } catch (\Throwable $e) {
        }
    }
}
