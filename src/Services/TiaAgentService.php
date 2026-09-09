<?php
namespace App\Services;

use App\Core\AiBrick\AiRouter;
use App\Core\Config;
use App\Core\Database;
use App\Core\Session;

class TiaAgentService
{
    private const WIDGET_MAP = [
        'testimonios' => 'trust',
        'servicios' => 'features',
        'contacto' => 'cta',
        'precios' => 'pricing',
        'faq' => 'features',
    ];

    public function sections(): array
    {
        $db = Database::instance();
        $stmt = $db->prepare("SELECT s.id, s.widget_type, s.title, s.sort_order, s.is_active, p.slug AS page_slug
            FROM sections s JOIN pages p ON p.id = s.page_id
            WHERE p.site_id = @site_id ORDER BY p.id DESC, s.sort_order ASC LIMIT 50");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function history(int $limit = 20): array
    {
        $stmt = Database::instance()->prepare("SELECT * FROM wwi_ai_actions WHERE site_id = @site_id ORDER BY id DESC LIMIT :lim");
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function command(string $rawCommand): array
    {
        $command = trim($rawCommand);
        if ($command === '') return ['ok' => false, 'message' => 'Escribe un comando para TIA'];
        $parsed = $this->interpret($command);
        if (!$parsed) {
            $this->audit($command, 'unknown', [], 'failed', 'No se pudo interpretar');
            return ['ok' => false, 'message' => 'No pude interpretar eso. Prueba: "cambia el color a azul", "agrega una sección de testimonios" o "muéstrame el estado del sitio".'];
        }
        if ($parsed['action'] === 'clarify') {
            $this->audit($command, 'clarify', [], 'executed', (string)$parsed['message']);
            return ['ok' => false, 'message' => (string)$parsed['message'], 'needs_clarification' => true];
        }
        return $this->execute($parsed, $command);
    }

    public function confirm(string $token): array
    {
        $db = Database::instance();
        $stmt = $db->prepare("SELECT * FROM wwi_ai_actions WHERE site_id = @site_id AND status = 'preview' AND result LIKE :t ORDER BY id DESC LIMIT 1");
        $stmt->execute(['t' => '%"token":"' . $token . '"%']);
        $row = $stmt->fetch();
        if (!$row) return ['ok' => false, 'message' => 'Token inválido o expirado'];
        $result = json_decode((string)$row['result'], true) ?: [];
        $action = $result['action'] ?? [];
        $executed = $this->applyAction($action['action'] ?? '', $action['params'] ?? []);
        $db->prepare("UPDATE wwi_ai_actions SET status = 'executed', result = :res WHERE id = :id")
            ->execute(['res' => json_encode(['action' => $action, 'token' => null, 'applied' => $executed]), 'id' => $row['id']]);
        return ['ok' => true, 'message' => 'Acción confirmada y ejecutada', 'result' => $executed];
    }

    private function execute(array $parsed, string $command): array
    {
        $action = $parsed['action'];
        $params = is_array($parsed['params'] ?? null) ? $parsed['params'] : [];
        foreach ($parsed as $k => $v) {
            if ($k !== 'action' && $k !== 'params' && !array_key_exists($k, $params)) $params[$k] = $v;
        }
        if (in_array($action, ['remove_section'], true)) {
            $section = $this->findSection((int)($params['id'] ?? 0));
            if (!$section) return ['ok' => false, 'message' => 'No encontré esa sección'];
            $token = substr(hash_hmac('sha256', $action . '|' . (int)$params['id'], (string)Config::get('JWT_SECRET', 'x')), 0, 24);
            $this->audit($command, $action, $params, 'preview', json_encode(['action' => ['action' => $action, 'params' => $params], 'token' => $token, 'description' => 'Eliminar la sección "' . $section['title'] . '" (' . $section['widget_type'] . ')']));
            return [
                'ok' => true,
                'preview' => true,
                'token' => $token,
                'message' => '⚠ Voy a ELIMINAR la sección "' . $section['title'] . '". ¿Confirmas?',
                'action' => $action,
            ];
        }
        $applied = $this->applyAction($action, $params);
        $this->audit($command, $action, $params, $applied['ok'] ? 'executed' : 'failed', json_encode($applied));
        return $applied;
    }

    private function applyAction(string $action, array $params): array
    {
        return match ($action) {
            'set_color' => $this->actSetColor($params),
            'add_section' => $this->actAddSection($params),
            'remove_section' => $this->actRemoveSection($params),
            'hide_section' => $this->actToggleSection($params, 0),
            'show_section' => $this->actToggleSection($params, 1),
            'update_text' => $this->actUpdateText($params),
            'create_page' => $this->actCreatePage($params),
            'status' => $this->actStatus(),
            default => ['ok' => false, 'message' => 'Acción no soportada aún: ' . $action],
        };
    }

    private function actRemoveSection(array $params): array
    {
        $section = $this->findSection((int)($params['id'] ?? 0));
        if (!$section) return ['ok' => false, 'message' => 'No encontré esa sección'];
        Database::instance()->prepare("DELETE FROM sections WHERE id = :id")->execute(['id' => $section['id']]);
        return ['ok' => true, 'message' => 'Sección "' . $section['title'] . '" eliminada', 'action' => 'remove_section'];
    }

    private function actSetColor(array $params): array
    {
        $color = strtolower(trim((string)($params['color'] ?? '')));
        $nameMap = [
            'azul' => '#2563eb', 'blue' => '#2563eb',
            'verde' => '#059669', 'green' => '#059669',
            'rojo' => '#dc2626', 'red' => '#dc2626',
            'negro' => '#111827', 'black' => '#111827',
            'morado' => '#7c3aed', 'violeta' => '#8b5cf6', 'purple' => '#7c3aed', 'violet' => '#8b5cf6',
            'cian' => '#22d3ee', 'cyan' => '#22d3ee',
            'amarillo' => '#f59e0b', 'yellow' => '#f59e0b',
            'naranja' => '#ea580c', 'orange' => '#ea580c',
            'rosa' => '#ec4899', 'pink' => '#ec4899',
        ];
        $color = preg_replace('/[^a-z0-9#]/', '', $color);
        if (isset($nameMap[$color])) $color = $nameMap[$color];
        if (!preg_match('/^#[0-9a-f]{6}$/', $color)) {
            return ['ok' => false, 'message' => 'Color inválido — usa formato #RRGGBB o un nombre (azul, verde, rojo, morado, cian…)'];
        }
        $db = Database::instance();
        $stmt = $db->prepare("INSERT INTO settings (site_id, `key`, `value`) VALUES (@site_id, 'wwi_brand_primary', :v) ON DUPLICATE KEY UPDATE `value` = :v2");
        $stmt->execute(['v' => $color, 'v2' => $color]);
        return ['ok' => true, 'message' => 'Color principal actualizado a ' . $color, 'action' => 'set_color', 'color' => $color];
    }

    private function actAddSection(array $params): array
    {
        $tipo = strtolower(trim((string)($params['tipo'] ?? '')));
        $widget = self::WIDGET_MAP[$tipo] ?? null;
        if (!$widget) {
            return ['ok' => false, 'message' => 'Dime qué tipo de sección: testimonios, servicios, contacto, precios o faq.'];
        }
        $db = Database::instance();
        $pageId = (int)$db->query("SELECT id FROM pages WHERE site_id = @site_id AND status = 'published' ORDER BY sort_order ASC, id ASC LIMIT 1")->fetchColumn();
        if (!$pageId) return ['ok' => false, 'message' => 'Este sitio no tiene páginas aún'];
        $maxSort = (int)$db->query("SELECT COALESCE(MAX(sort_order), -1) FROM sections WHERE page_id = $pageId")->fetchColumn();
        $titles = ['trust' => 'Testimonios', 'features' => 'Servicios', 'cta' => 'Contáctanos', 'pricing' => 'Precios'];
        $db->prepare("INSERT INTO sections (page_id, type, widget_type, title, config, sort_order, is_active) VALUES (:pid, 'widget', :wt, :t, '{}', :s, 1)")
            ->execute(['pid' => $pageId, 'wt' => $widget, 't' => $titles[$widget] ?? ucfirst($tipo), 's' => $maxSort + 1]);
        return ['ok' => true, 'message' => 'Sección "' . ($titles[$widget] ?? ucfirst($tipo)) . '" agregada al sitio', 'action' => 'add_section'];
    }

    private function actToggleSection(array $params, int $active): array
    {
        $section = $this->findSection((int)($params['id'] ?? 0));
        if (!$section) return ['ok' => false, 'message' => 'No encontré esa sección'];
        Database::instance()->prepare("UPDATE sections SET is_active = :a WHERE id = :id")->execute(['a' => $active, 'id' => $section['id']]);
        return ['ok' => true, 'message' => 'Sección "' . $section['title'] . '" ' . ($active ? 'visible' : 'oculta')];
    }

    private function actUpdateText(array $params): array
    {
        $section = $this->findSection((int)($params['id'] ?? 0));
        if (!$section) return ['ok' => false, 'message' => 'No encontré esa sección'];
        $texto = (string)($params['texto'] ?? '');
        if ($texto === '') return ['ok' => false, 'message' => '¿Qué texto quieres poner?'];
        Database::instance()->prepare("UPDATE sections SET subtitle = :t WHERE id = :id")->execute(['t' => $texto, 'id' => $section['id']]);
        return ['ok' => true, 'message' => 'Texto de "' . $section['title'] . '" actualizado'];
    }

    private function actCreatePage(array $params): array
    {
        $titulo = trim((string)($params['titulo'] ?? ''));
        if ($titulo === '') return ['ok' => false, 'message' => '¿Qué título tendrá la página?'];
        $contenido = (string)($params['contenido'] ?? '');
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $titulo)), '-'));
        if ($slug === '') $slug = 'pagina-' . time();
        $db = Database::instance();
        $stmt = $db->prepare("INSERT INTO pages (site_id, title, slug, template, meta_title, meta_description, status, sort_order) VALUES (@site_id, :t, :s, 'default', :t, '', 'published', 1)");
        try {
            $stmt->execute(['t' => $titulo, 's' => $slug]);
            $pageId = (int)$db->lastInsertId();
            $db->prepare("INSERT INTO sections (page_id, type, widget_type, title, subtitle, content, config, sort_order, is_active) VALUES (:pid, 'custom', NULL, :t, '', :c, '{}', 0, 1)")
                ->execute(['pid' => $pageId, 't' => $titulo, 'c' => $contenido]);
            return ['ok' => true, 'message' => 'Página "' . $titulo . '" creada y publicada (/' . $slug . ')', 'action' => 'create_page'];
        } catch (\PDOException $e) {
            return ['ok' => false, 'message' => $e->getCode() === '23000' ? 'Ya existe una página con ese nombre' : 'Error al crear la página'];
        }
    }

    private function actStatus(): array
    {
        return ['ok' => true, 'message' => 'Estado del sitio', 'action' => 'status', 'sections' => $this->sections()];
    }

    private function findSection(int $id): ?array
    {
        $stmt = Database::instance()->prepare("SELECT s.* FROM sections s JOIN pages p ON p.id = s.page_id WHERE s.id = :id AND p.site_id = @site_id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function interpret(string $command): ?array
    {
        $prompt = "Eres TIA, el agente de sitios web de Wontia. Interpreta el comando del usuario y responde SOLO con JSON válido, sin markdown ni texto extra.\n"
            . "Acciones disponibles:\n"
            . "- set_color: params {\"color\":\"#RRGGBB\"} (cuando pide cambiar el color)\n"
            . "- add_section: params {\"tipo\":\"testimonios|servicios|contacto|precios|faq\"} (cuando pide agregar una sección)\n"
            . "- remove_section: params {\"id\":<numero>} (cuando pide quitar/eliminar una sección)\n"
            . "- hide_section / show_section: params {\"id\":<numero>}\n"
            . "- update_text: params {\"id\":<numero>,\"texto\":\"...\"}\n"
            . "- create_page: params {\"titulo\":\"...\",\"contenido\":\"...\"}\n"
            . "- status (cuando pregunta por el estado del sitio o lista de secciones)\n"
            . "Si el comando no corresponde a ninguna acción o falta información obligatoria, responde {\"action\":\"clarify\",\"message\":\"...\"} pidiendo lo que falta.\n"
            . "Comando del usuario: \"" . mb_substr($command, 0, 500) . "\"";

        $router = new AiRouter();
        $response = $router->route([
            'system_id' => 'wontia',
            'module' => 'agent',
            'function' => 'command',
            'system_prompt' => 'Respondes únicamente con JSON válido. Nunca inventes ids de sección.',
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'max_tokens' => 300,
            'temperature' => 0.1,
        ]);
        if (empty($response['ok']) || empty($response['content'])) return null;
        $json = json_decode((string)$response['content'], true);
        return is_array($json) && isset($json['action']) ? $json : null;
    }

    private function audit(string $command, string $action, array $payload, string $status, string $result): void
    {
        $db = Database::instance();
        $db->prepare("INSERT INTO wwi_ai_actions (site_id, user_id, session_id, command, action, payload, result, status) VALUES (@site_id, :uid, :sid, :cmd, :act, :pl, :res, :st)")
            ->execute([
                'uid' => Session::userId(),
                'sid' => md5((string)Session::userId() . date('YmdH')),
                'cmd' => $command,
                'act' => $action,
                'pl' => json_encode($payload),
                'res' => $result,
                'st' => $status,
            ]);
    }
}
