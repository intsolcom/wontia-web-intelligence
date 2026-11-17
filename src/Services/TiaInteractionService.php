<?php
namespace App\Services;

use App\Core\AiBrick\AiRouter;
use App\Core\Database;

class TiaInteractionService
{
    private const OUTCOME_RANK = ['started' => 0, 'product_click' => 10, 'cart' => 20, 'checkout' => 30, 'paid' => 40];
    private static bool $ready = false;

    public function ensureTables(): void
    {
        if (self::$ready) return;
        $db = Database::instance();
        $db->exec("CREATE TABLE IF NOT EXISTS tia_interactions (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL DEFAULT 1,
            interaction_uid VARCHAR(64) NOT NULL,
            session_id VARCHAR(128) NULL,
            page_url VARCHAR(500) NULL,
            question TEXT NULL,
            answer TEXT NULL,
            intent_slug VARCHAR(80) NULL,
            sentiment_slug VARCHAR(80) NULL,
            role_slug VARCHAR(80) NULL,
            stage VARCHAR(40) NULL,
            next_action VARCHAR(255) NULL,
            cards_count INT NOT NULL DEFAULT 0,
            outcome VARCHAR(30) NOT NULL DEFAULT 'started',
            value_cents BIGINT NOT NULL DEFAULT 0,
            order_uuid VARCHAR(80) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uk_tia_interaction_uid (interaction_uid),
            KEY idx_tia_int_site_date (site_id, created_at),
            KEY idx_tia_int_site_outcome (site_id, outcome)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS tia_learning_insights (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL DEFAULT 1,
            title VARCHAR(255) NOT NULL,
            diagnosis TEXT NULL,
            evidence JSON NULL,
            recommendations JSON NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL,
            KEY idx_tia_insight_site (site_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS tia_playbook_rules (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL DEFAULT 1,
            insight_id BIGINT NULL,
            rule_text TEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            applied_by VARCHAR(100) NULL,
            created_at DATETIME NOT NULL,
            KEY idx_tia_rule_site (site_id, status, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS tia_feedback (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL DEFAULT 1,
            interaction_uid VARCHAR(64) NOT NULL,
            rating VARCHAR(10) NOT NULL,
            reason VARCHAR(120) NULL,
            comment VARCHAR(500) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uk_tia_feedback_interaction (site_id, interaction_uid),
            KEY idx_tia_feedback_date (site_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $this->seedPlaybookRules();
        foreach (['attention_slug VARCHAR(120) NULL', 'primary_title VARCHAR(255) NULL', 'experiment_key VARCHAR(80) NULL', 'experiment_variant CHAR(1) NULL'] as $column) {
            try {
                $db->exec('ALTER TABLE tia_interactions ADD COLUMN ' . $column);
            } catch (\Throwable $e) {
            }
        }
        self::$ready = true;
    }

    public function record(string $message, string $page, array $result): ?string
    {
        try {
            $this->ensureTables();
            $uid = bin2hex(random_bytes(12));
            $training = is_array($result['training'] ?? null) ? $result['training'] : [];
            $intent = is_array($training['intent'] ?? null) ? $training['intent'] : [];
            $sentiment = is_array($training['sentiment'] ?? null) ? $training['sentiment'] : [];
            $role = is_array($training['role'] ?? null) ? $training['role'] : [];
            $cards = is_array($result['cards'] ?? null) ? $result['cards'] : [];
            $experiment = is_array($result['experiment'] ?? null) ? $result['experiment'] : [];
            $attention = is_array($training['attention'][0] ?? null) ? $training['attention'][0] : [];
            $primaryTitle = (string)($cards[0]['title'] ?? '');
            Database::instance()->prepare("INSERT INTO tia_interactions (site_id, interaction_uid, session_id, page_url, question, answer, intent_slug, sentiment_slug, role_slug, stage, next_action, cards_count, primary_title, attention_slug, experiment_key, experiment_variant, outcome, created_at, updated_at) VALUES (@site_id, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'started', NOW(), NOW())")
                ->execute([
                    $uid,
                    mb_substr((string)session_id(), 0, 128),
                    mb_substr($page, 0, 500),
                    mb_substr($message, 0, 5000),
                    mb_substr((string)($result['reply'] ?? ''), 0, 5000),
                    mb_substr((string)($intent['slug'] ?? ''), 0, 80),
                    mb_substr((string)($sentiment['slug'] ?? ''), 0, 80),
                    mb_substr((string)($role['slug'] ?? ''), 0, 80),
                    mb_substr((string)($training['stage'] ?? ''), 0, 40),
                    mb_substr((string)($training['next_action'] ?? ''), 0, 255),
                    count($cards),
                    mb_substr($primaryTitle, 0, 255),
                    mb_substr((string)($attention['slug'] ?? ''), 0, 120),
                    mb_substr((string)($experiment['key'] ?? ''), 0, 80),
                    strtoupper((string)($experiment['variant'] ?? '')) === 'B' ? 'B' : ((string)($experiment['variant'] ?? '') === 'A' ? 'A' : null),
                ]);
            return $uid;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function event(string $uid, string $type, string $ref = '', int $valueCents = 0, string $orderUuid = ''): bool
    {
        $map = ['product_click' => 'product_click', 'card_click' => 'product_click', 'add_to_cart' => 'cart', 'cart' => 'cart', 'checkout_start' => 'checkout', 'checkout' => 'checkout', 'purchase' => 'paid', 'paid' => 'paid'];
        $outcome = $map[$type] ?? '';
        if ($outcome === '' || $uid === '') return false;
        try {
            $this->ensureTables();
            $stmt = Database::instance()->prepare('SELECT id, outcome FROM tia_interactions WHERE interaction_uid = ? AND site_id = @site_id LIMIT 1');
            $stmt->execute([$uid]);
            $row = $stmt->fetch();
            if (!$row) return false;
            $current = (string)$row['outcome'];
            if ((self::OUTCOME_RANK[$outcome] ?? 0) < (self::OUTCOME_RANK[$current] ?? 0)) return true;
            $value = $outcome === 'paid' ? max(0, $valueCents) : 0;
            Database::instance()->prepare("UPDATE tia_interactions SET outcome = ?, value_cents = CASE WHEN ? > 0 THEN ? ELSE value_cents END, order_uuid = COALESCE(NULLIF(?, ''), order_uuid), updated_at = NOW() WHERE id = ? AND site_id = @site_id")
                ->execute([$outcome, $value, $value, $orderUuid, (int)$row['id']]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function feedback(string $uid, string $rating, string $reason = '', string $comment = ''): bool
    {
        $rating = strtolower(trim($rating));
        if (!in_array($rating, ['up', 'down'], true) || $uid === '') return false;
        $this->ensureTables();
        try {
            $db = Database::instance();
            $check = $db->prepare('SELECT id FROM tia_interactions WHERE interaction_uid = ? AND site_id = @site_id LIMIT 1');
            $check->execute([$uid]);
            if (!$check->fetch()) return false;
            $db->prepare("INSERT INTO tia_feedback (site_id, interaction_uid, rating, reason, comment, created_at, updated_at) VALUES (@site_id, ?, ?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE rating = VALUES(rating), reason = VALUES(reason), comment = VALUES(comment), updated_at = NOW()")
                ->execute([$uid, $rating, mb_substr(trim($reason), 0, 120), mb_substr(trim($comment), 0, 500)]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function feedbackStats(int $days = 30): array
    {
        $this->ensureTables();
        $days = max(1, min(365, $days));
        $out = ['total' => 0, 'positive' => 0, 'negative' => 0, 'positive_pct' => 0, 'reasons' => []];
        try {
            $where = 'site_id = @site_id AND created_at >= (NOW() - INTERVAL ' . $days . ' DAY)';
            $row = Database::instance()->query("SELECT COUNT(*) total, COALESCE(SUM(rating = 'up'),0) positive, COALESCE(SUM(rating = 'down'),0) negative FROM tia_feedback WHERE $where")->fetch();
            foreach ($out as $k => $v) if (isset($row[$k])) $out[$k] = (int)$row[$k];
            $out['positive_pct'] = $out['total'] > 0 ? round($out['positive'] / $out['total'] * 100, 1) : 0;
            foreach (Database::instance()->query("SELECT reason, COUNT(*) c FROM tia_feedback WHERE $where AND rating = 'down' AND reason <> '' GROUP BY reason ORDER BY c DESC LIMIT 8")->fetchAll() as $r) {
                $out['reasons'][] = ['reason' => (string)$r['reason'], 'count' => (int)$r['c']];
            }
        } catch (\Throwable $e) {
        }
        return $out;
    }

    public function overview(int $days = 30): array
    {
        $this->ensureTables();
        $days = max(1, min(365, $days));
        $db = Database::instance();
        $where = 'site_id = @site_id AND created_at >= (NOW() - INTERVAL ' . $days . ' DAY)';
        $stats = ['interactions' => 0, 'product_click' => 0, 'cart' => 0, 'checkout' => 0, 'paid' => 0, 'no_conversion' => 0, 'revenue_cents' => 0, 'conversion_rate' => 0];
        try {
            $row = $db->query("SELECT COUNT(*) interactions, SUM(outcome = 'product_click') product_click, SUM(outcome = 'cart') cart, SUM(outcome = 'checkout') checkout, SUM(outcome = 'paid') paid, SUM(outcome = 'started') no_conversion, COALESCE(SUM(CASE WHEN outcome = 'paid' THEN value_cents ELSE 0 END), 0) revenue_cents FROM tia_interactions WHERE $where")->fetch();
            foreach ($stats as $k => $v) if (isset($row[$k])) $stats[$k] = (int)$row[$k];
            $stats['conversion_rate'] = $stats['interactions'] > 0 ? round($stats['paid'] / $stats['interactions'] * 100, 1) : 0;
        } catch (\Throwable $e) {
        }
        $byOutcome = [];
        try {
            foreach ($db->query("SELECT outcome, COUNT(*) c, COALESCE(SUM(value_cents),0) value_cents FROM tia_interactions WHERE $where GROUP BY outcome ORDER BY c DESC")->fetchAll() as $r) {
                $byOutcome[] = ['outcome' => (string)$r['outcome'], 'count' => (int)$r['c'], 'value_cents' => (int)$r['value_cents']];
            }
        } catch (\Throwable $e) {
        }
        $byIntent = [];
        try {
            foreach ($db->query("SELECT COALESCE(NULLIF(intent_slug,''),'sin_intencion') intent_slug, COUNT(*) c, SUM(outcome='paid') paid, COALESCE(SUM(CASE WHEN outcome='paid' THEN value_cents ELSE 0 END),0) revenue_cents FROM tia_interactions WHERE $where GROUP BY intent_slug ORDER BY c DESC LIMIT 15")->fetchAll() as $r) {
                $byIntent[] = ['intent' => (string)$r['intent_slug'], 'count' => (int)$r['c'], 'paid' => (int)$r['paid'], 'revenue_cents' => (int)$r['revenue_cents']];
            }
        } catch (\Throwable $e) {
        }
        $recent = [];
        try {
            foreach ($db->query("SELECT interaction_uid, question, answer, intent_slug, sentiment_slug, role_slug, stage, next_action, attention_slug, outcome, value_cents, created_at FROM tia_interactions WHERE $where ORDER BY id DESC LIMIT 50")->fetchAll() as $r) {
                $recent[] = ['id' => (string)$r['interaction_uid'], 'question' => (string)$r['question'], 'answer' => (string)$r['answer'], 'intent' => (string)$r['intent_slug'], 'sentiment' => (string)$r['sentiment_slug'], 'role' => (string)$r['role_slug'], 'stage' => (string)$r['stage'], 'next_action' => (string)$r['next_action'], 'attention' => (string)($r['attention_slug'] ?? ''), 'outcome' => (string)$r['outcome'], 'value_cents' => (int)$r['value_cents'], 'created_at' => (string)$r['created_at']];
            }
        } catch (\Throwable $e) {
        }
        $insights = [];
        try {
            foreach ($db->query("SELECT * FROM tia_learning_insights WHERE site_id = @site_id ORDER BY id DESC LIMIT 10")->fetchAll() as $r) {
                $insights[] = ['id' => (int)$r['id'], 'title' => (string)$r['title'], 'diagnosis' => (string)$r['diagnosis'], 'evidence' => json_decode((string)($r['evidence'] ?? '[]'), true) ?: [], 'recommendations' => json_decode((string)($r['recommendations'] ?? '[]'), true) ?: [], 'status' => (string)$r['status'], 'created_at' => (string)$r['created_at']];
            }
        } catch (\Throwable $e) {
        }
        $eligibleRevenue = 0;
        $excludedRevenue = 0;
        $unknownRevenue = 0;
        try {
            $paidRows = $db->query("SELECT value_cents, primary_title FROM tia_interactions WHERE $where AND outcome = 'paid' AND value_cents > 0")->fetchAll();
            $marginService = new TiaMarginService();
            foreach ($paidRows as $row) {
                $value = max(0, (int)$row['value_cents']);
                $title = trim((string)($row['primary_title'] ?? ''));
                if ($title === '') $unknownRevenue += $value;
                elseif ($marginService->isAccordion($title)) $excludedRevenue += $value;
                else $eligibleRevenue += $value;
            }
        } catch (\Throwable $e) {
        }
        $margin = (new TiaMarginService())->estimate($eligibleRevenue);
        $economics = [
            'attributed_revenue_cents' => $stats['revenue_cents'],
            'attributed_orders' => $stats['paid'],
            'avg_ticket_cents' => $stats['paid'] > 0 ? (int)round($stats['revenue_cents'] / $stats['paid']) : 0,
            'gross_margin_cents' => $margin['gross_profit_cents'],
            'gross_margin_pct' => $eligibleRevenue > 0 ? $margin['gross_margin_pct'] : null,
            'eligible_revenue_cents' => $eligibleRevenue,
            'excluded_revenue_cents' => $excludedRevenue,
            'unknown_revenue_cents' => $unknownRevenue,
            'net_target_cents' => $margin['net_target_cents'],
            'available_cost_cents' => $margin['available_cost_cents'],
            'margin_note' => 'Estimación interna: 20% bruto sobre el ingreso atribuido al producto principal; Bre-B incluido; acordeones y ventas sin producto identificado se excluyen. El 5% neto es objetivo después de costos, no utilidad realizada.',
        ];
        $experiments = (new TiaExperimentService())->stats($days);
        $feedback = $this->feedbackStats($days);
        return ['ok' => true, 'days' => $days, 'stats' => $stats, 'economics' => $economics, 'feedback' => $feedback, 'experiments' => $experiments, 'outcomes' => $byOutcome, 'intents' => $byIntent, 'recent' => $recent, 'insights' => $insights];
    }

    public function activeRules(int $limit = 8): array
    {
        return array_values(array_filter(array_map(fn($r) => trim((string)$r['rule_text']), $this->rules($limit))));
    }

    public function rules(int $limit = 20): array
    {
        $this->ensureTables();
        $limit = max(1, min(50, $limit));
        try {
            $stmt = Database::instance()->query("SELECT id, rule_text, insight_id, status, created_at FROM tia_playbook_rules WHERE site_id = @site_id AND status = 'active' ORDER BY id DESC LIMIT " . $limit);
            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function pendingInsights(int $limit = 10): array
    {
        $this->ensureTables();
        $limit = max(1, min(30, $limit));
        try {
            $stmt = Database::instance()->query("SELECT id, title, diagnosis, evidence, recommendations, status, created_at FROM tia_learning_insights WHERE site_id = @site_id AND status = 'pending' ORDER BY id DESC LIMIT " . $limit);
            return array_map(function ($r) {
                return [
                    'id' => (int)$r['id'],
                    'title' => (string)$r['title'],
                    'diagnosis' => (string)$r['diagnosis'],
                    'evidence' => json_decode((string)($r['evidence'] ?? '[]'), true) ?: [],
                    'recommendations' => json_decode((string)($r['recommendations'] ?? '[]'), true) ?: [],
                    'status' => (string)$r['status'],
                    'created_at' => (string)$r['created_at'],
                ];
            }, $stmt->fetchAll());
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function captureFailure(string $question, string $answer, array $signals = []): ?int
    {
        $this->ensureTables();
        $frustration = !empty($signals['frustration']) || !empty($signals['farewell']);
        $repeat = (int)($signals['repeat_count'] ?? 0) >= 2;
        if (!$frustration && !$repeat) return null;
        $db = Database::instance();
        try {
            $recent = $db->prepare("SELECT id FROM tia_learning_insights WHERE site_id = @site_id AND title LIKE 'Caso real:%' AND created_at >= (NOW() - INTERVAL 1 HOUR) ORDER BY id DESC LIMIT 1");
            $recent->execute();
            if ($recent->fetch()) return null;
            $recs = [];
            $evidence = ['Mensaje del cliente: ' . mb_substr(trim($question), 0, 180)];
            if (!empty($signals['image'])) {
                $recs[] = ['problem' => 'El cliente pidió una imagen y TIA no la mostró.', 'technique' => 'Respuesta multimodal', 'next_play' => 'Reutilizar las tarjetas de producto con imagen y decir que están disponibles en el chat.', 'training_rule' => 'Si el cliente pide imagen, foto o ver el producto, muestra tarjetas con imagen; no digas que no puedes enviar imágenes.'];
                $evidence[] = 'Se detectó una solicitud explícita de imagen.';
            }
            if (!empty($signals['price_mode']) || !empty($signals['cheap']) || !empty($signals['mid']) || !empty($signals['budget_max'])) {
                $recs[] = ['problem' => 'El cliente pidió barato o dio un presupuesto y recibió opciones fuera de rango.', 'technique' => 'Filtro por presupuesto', 'next_play' => 'Filtrar por precio ascendente o rango y mostrar primero la opción más económica disponible.', 'training_rule' => 'Si el cliente pide algo barato o da un presupuesto, filtra por precio y no repitas opciones fuera de rango.'];
                $evidence[] = 'Se detectó intención de precio o presupuesto.';
            }
            if ($repeat) {
                $recs[] = ['problem' => 'TIA repitió el mismo conjunto de productos.', 'technique' => 'Reparación de conversación', 'next_play' => 'Reconocer la repetición, cambiar el filtro y ofrecer una sola opción distinta.', 'training_rule' => 'Si el cliente rechaza una respuesta o el mismo set, no repitas; cambia el filtro y ofrece un siguiente paso.'];
                $evidence[] = 'Se detectó repetición en la misma sesión.';
            }
            if (!empty($signals['reject_whatsapp'])) {
                $recs[] = ['problem' => 'El cliente rechazó WhatsApp y se le volvió a ofrecer.', 'technique' => 'Respeto de canal', 'next_play' => 'Recordar el canal rechazado y ofrecer correo o formulario.', 'training_rule' => 'Si el cliente rechaza un canal, no lo vuelvas a ofrecer; respeta su preferencia.'];
                $evidence[] = 'El cliente rechazó explícitamente WhatsApp.';
            }
            if (!$recs) {
                $recs[] = ['problem' => 'El cliente expresó frustración y la conversación no avanzó.', 'technique' => 'Reconocimiento y reparación', 'next_play' => 'Validar la emoción, resumir el problema y ofrecer una acción concreta sin repetir el discurso.', 'training_rule' => 'Ante frustración, reconoce una vez, cambia de estrategia y ofrece un siguiente paso concreto.'];
            }
            $title = 'Caso real: ' . ($frustration ? 'cliente frustrado' : 'respuesta repetida');
            $diagnosis = 'Caso capturado automáticamente desde una conversación real. Requiere revisión humana antes de convertirlo en regla activa.';
            $db->prepare("INSERT INTO tia_learning_insights (site_id, title, diagnosis, evidence, recommendations, status, created_at) VALUES (@site_id, ?, ?, ?, ?, 'pending', NOW())")
                ->execute([mb_substr($title, 0, 255), $diagnosis, json_encode($evidence, JSON_UNESCAPED_UNICODE), json_encode($recs, JSON_UNESCAPED_UNICODE)]);
            return (int)$db->lastInsertId();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function seedPlaybookRules(): void
    {
        $rules = [
            'Si el cliente pide algo barato, económico o con presupuesto, filtra por precio ascendente y muestra primero la opción más económica disponible.',
            'Si el cliente pide una imagen o solo la imagen, muestra una sola imagen o ficha del producto y enlaza a su página; no repitas varias tarjetas.',
            'Si el presupuesto no alcanza para la categoría, no muestres productos de otras categorías: muestra la opción más económica de la categoría solicitada y pide subir el presupuesto o revisar otra alternativa.',
            'Si el cliente pide ver todos los productos o un enlace de categoría, usa una categoría temporal /categoria-temporal?q=...; no inventes enlaces.',
            'Si ya mostraste la opción más económica y el cliente pide algo más barato, dilo claramente, muestra una sola ficha y pide su presupuesto; no repitas el mismo grupo.',
            'Si el cliente rechaza un producto o los precios, no repitas el mismo conjunto; cambia el filtro o muestra la opción más económica.',
            'Si el cliente rechaza un canal de atención, no lo vuelvas a ofrecer; respeta su preferencia.',
        ];
        try {
            $db = Database::instance();
            try { $db->prepare("DELETE FROM tia_playbook_rules WHERE site_id = @site_id AND rule_text LIKE ?")->execute(['Si el cliente pide una imagen%']); } catch (\Throwable $e) {}
            $stmt = $db->prepare("INSERT INTO tia_playbook_rules (site_id, insight_id, rule_text, status, applied_by, created_at) SELECT @site_id, NULL, ?, 'active', 'system', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tia_playbook_rules WHERE site_id = @site_id AND rule_text = ?)");
            foreach ($rules as $rule) $stmt->execute([$rule, $rule]);
        } catch (\Throwable $e) {
        }
    }

    public function applyInsight(int $id, string $appliedBy = 'admin'): array
    {
        $this->ensureTables();
        if ($id <= 0) return ['ok' => false, 'status' => 'error', 'message' => 'Aprendizaje inválido.'];
        $db = Database::instance();
        try {
            $stmt = $db->prepare('SELECT * FROM tia_learning_insights WHERE id = ? AND site_id = @site_id LIMIT 1');
            $stmt->execute([$id]);
            $insight = $stmt->fetch();
            if (!$insight) return ['ok' => false, 'status' => 'error', 'message' => 'El aprendizaje ya no existe.'];
            if ((string)$insight['status'] === 'applied') return ['ok' => true, 'status' => 'already_applied', 'rules' => [], 'message' => 'Este aprendizaje ya está aplicado.'];
            $recommendations = json_decode((string)($insight['recommendations'] ?? '[]'), true);
            if (!is_array($recommendations)) $recommendations = [];
            $rules = [];
            $check = $db->prepare("SELECT id FROM tia_playbook_rules WHERE site_id = @site_id AND rule_text = ? AND status = 'active' LIMIT 1");
            $insert = $db->prepare("INSERT INTO tia_playbook_rules (site_id, insight_id, rule_text, status, applied_by, created_at) VALUES (@site_id, ?, ?, 'active', ?, NOW())");
            foreach ($recommendations as $rec) {
                if (!is_array($rec)) continue;
                $rule = trim((string)($rec['training_rule'] ?? ''));
                if ($rule === '') $rule = trim((string)($rec['next_play'] ?? ''));
                $rule = preg_replace('/[^\p{L}\p{N}\s.,;:!?¿¡()\-]/u', '', $rule) ?? '';
                $rule = trim(preg_replace('/\s+/', ' ', $rule) ?? '');
                $rule = mb_substr($rule, 0, 300);
                if (mb_strlen($rule) < 12) continue;
                $check->execute([$rule]);
                if ($check->fetch()) continue;
                $insert->execute([$id, $rule, mb_substr($appliedBy, 0, 100)]);
                $rules[] = $rule;
                if (count($rules) >= 8) break;
            }
            if (!$rules) return ['ok' => false, 'status' => 'error', 'message' => 'No hay reglas utilizables en este aprendizaje.'];
            $db->prepare("UPDATE tia_learning_insights SET status = 'applied' WHERE id = ? AND site_id = @site_id")->execute([$id]);
            return ['ok' => true, 'status' => 'applied', 'rules' => $rules];
        } catch (\Throwable $e) {
            return ['ok' => false, 'status' => 'error', 'message' => 'No se pudo aplicar el aprendizaje.'];
        }
    }

    public function analyze(int $days = 30): array
    {
        $this->ensureTables();
        $days = max(1, min(90, $days));
        $db = Database::instance();
        $rows = [];
        try {
            $stmt = $db->query("SELECT question, answer, intent_slug, sentiment_slug, role_slug, stage, next_action, outcome FROM tia_interactions WHERE site_id = @site_id AND outcome <> 'paid' AND created_at >= (NOW() - INTERVAL $days DAY) ORDER BY id DESC LIMIT 40");
            $rows = $stmt->fetchAll();
        } catch (\Throwable $e) {
        }
        if (!$rows) return ['ok' => false, 'status' => 'skipped', 'message' => 'No hay interacciones sin cierre en el período.'];
        $lines = [];
        foreach ($rows as $r) {
            $lines[] = 'Pregunta: ' . mb_substr((string)$r['question'], 0, 350) . ' | Intención: ' . $r['intent_slug'] . ' | Emoción: ' . $r['sentiment_slug'] . ' | Rol: ' . $r['role_slug'] . ' | Resultado: ' . $r['outcome'] . ' | Respuesta: ' . mb_substr((string)$r['answer'], 0, 400);
        }
        $prompt = "Analiza estas conversaciones de venta de una tienda de instrumentos musicales. Identifica por qué no avanzaron al cierre y propone jugadas éticas basadas en SPIN, AIDA, manejo de objeciones, prueba social, reducción de riesgo y cierre por elección. No propongas escasez falsa, presión, engaño ni descuentos no confirmados. Devuelve SOLO JSON válido: {\"title\":\"...\",\"diagnosis\":\"...\",\"evidence\":[\"patrón observado\"],\"recommendations\":[{\"problem\":\"...\",\"technique\":\"...\",\"next_play\":\"...\",\"training_rule\":\"...\"}]}. Datos:\n" . implode("\n", $lines);
        $response = (new AiRouter())->route([
            'system_id' => 'tia',
            'module' => 'sales',
            'function' => 'interaction-analysis',
            'system_prompt' => 'Eres analista de ventas conversacionales. Responde en español y solo con JSON válido.',
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'temperature' => 0.2,
            'max_tokens' => 1200,
        ]);
        if (empty($response['ok']) || trim((string)($response['content'] ?? '')) === '') return ['ok' => false, 'status' => 'error', 'message' => 'El analizador de IA no está disponible.'];
        $raw = trim((string)$response['content']);
        $raw = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $raw) ?? $raw;
        if (preg_match('/\{[\s\S]*\}/', $raw, $jsonMatch)) $raw = $jsonMatch[0];
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $diagnosis = 'Se analizaron ' . count($rows) . ' interacciones sin cierre. No hay una respuesta JSON de la IA; se generó un diagnóstico local con patrones observables.';
            $evidence = [];
            $counts = [];
            foreach ($rows as $r) $counts[(string)$r['intent_slug']] = ($counts[(string)$r['intent_slug']] ?? 0) + 1;
            arsort($counts);
            foreach (array_slice($counts, 0, 3, true) as $intent => $count) $evidence[] = 'Intención ' . $intent . ': ' . $count . ' interacciones sin cierre.';
            $recommendations = [
                ['problem' => 'Falta de un siguiente paso claro', 'technique' => 'Cierre por elección', 'next_play' => 'Después de mostrar las tarjetas, ofrecer dos opciones concretas: comparar o ir a la ficha.', 'training_rule' => 'Cada respuesta de producto debe terminar con una acción de avance.'],
                ['problem' => 'Objeciones sin resolver', 'technique' => 'Empatía y reducción de riesgo', 'next_play' => 'Validar la objeción, dar un dato exacto y ofrecer una alternativa.', 'training_rule' => 'No responder una objeción con otra pregunta genérica.'],
            ];
            try {
                $db->prepare("INSERT INTO tia_learning_insights (site_id, title, diagnosis, evidence, recommendations, status, created_at) VALUES (@site_id, ?, ?, ?, ?, 'pending', NOW())")
                    ->execute(['Patrones de cierre pendientes', $diagnosis, json_encode($evidence, JSON_UNESCAPED_UNICODE), json_encode($recommendations, JSON_UNESCAPED_UNICODE)]);
                return ['ok' => true, 'status' => 'local', 'id' => (int)$db->lastInsertId(), 'title' => 'Patrones de cierre pendientes', 'diagnosis' => $diagnosis, 'evidence' => $evidence, 'recommendations' => $recommendations];
            } catch (\Throwable $e) {
                return ['ok' => false, 'status' => 'error', 'message' => 'No se pudo guardar el análisis local.'];
            }
        }
        $title = mb_substr((string)($data['title'] ?? 'Análisis de interacciones'), 0, 255);
        $diagnosis = (string)($data['diagnosis'] ?? '');
        $evidence = is_array($data['evidence'] ?? null) ? $data['evidence'] : [];
        $recommendations = is_array($data['recommendations'] ?? null) ? $data['recommendations'] : [];
        $stmt = $db->prepare("INSERT INTO tia_learning_insights (site_id, title, diagnosis, evidence, recommendations, status, created_at) VALUES (@site_id, ?, ?, ?, ?, 'pending', NOW())");
        $stmt->execute([$title, $diagnosis, json_encode($evidence, JSON_UNESCAPED_UNICODE), json_encode($recommendations, JSON_UNESCAPED_UNICODE)]);
        return ['ok' => true, 'status' => 'ok', 'id' => (int)$db->lastInsertId(), 'title' => $title, 'diagnosis' => $diagnosis, 'evidence' => $evidence, 'recommendations' => $recommendations];
    }
}
