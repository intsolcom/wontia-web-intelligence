<?php
namespace App\Services;

use App\Core\Database;

class TiaLearningService
{
    private static bool $ready = false;

    public function ensureTables(): void
    {
        if (self::$ready) return;
        $db = Database::instance();
        $db->exec("CREATE TABLE IF NOT EXISTS tia_training_sources (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL DEFAULT 1,
            source_type VARCHAR(20) NOT NULL DEFAULT 'manual',
            title VARCHAR(255) NOT NULL,
            url VARCHAR(500) NULL,
            content MEDIUMTEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            KEY idx_tia_source_site (site_id, status, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS tia_learning_snapshots (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL DEFAULT 1,
            snapshot_date DATE NOT NULL,
            score DECIMAL(5,2) NOT NULL DEFAULT 0,
            breakdown JSON NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uk_tia_learning_day (site_id, snapshot_date),
            KEY idx_tia_learning_history (site_id, snapshot_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        self::$ready = true;
    }

    public function overview(): array
    {
        $this->ensureTables();
        (new TiaTrainingService())->ensureTables();
        (new CiuService())->ensureTables();
        (new TiaAttentionCatalogService())->ensureTables();
        (new TiaInteractionService())->ensureTables();
        $db = Database::instance();
        $roles = $this->row("SELECT COUNT(*) total, COALESCE(SUM(mission <> '' AND tone <> '' AND restrictions <> '' AND system_prompt <> ''),0) complete FROM tia_roles WHERE site_id = @site_id AND is_active = 1");
        $intents = $this->row("SELECT COUNT(*) total, COALESCE(SUM(playbook <> '' AND (JSON_LENGTH(keywords) > 0 OR JSON_LENGTH(examples) > 0)),0) complete FROM tia_intents WHERE site_id = @site_id AND is_active = 1");
        $sentiments = $this->row("SELECT COUNT(*) total, COALESCE(SUM(adjustment <> '' AND (JSON_LENGTH(keywords) > 0 OR slug = 'neutral')),0) complete FROM tia_sentiments WHERE site_id = @site_id AND is_active = 1");
        $objections = $this->row("SELECT COUNT(*) total, COALESCE(SUM(empathy <> '' AND response <> '' AND JSON_LENGTH(phrases) > 0),0) complete FROM tia_objections WHERE site_id = @site_id AND is_active = 1");
        $knowledge = $this->row("SELECT COALESCE(SUM(status = 'active'),0) active, COALESCE(SUM(status = 'active' AND doc_type = 'product'),0) products, COALESCE(SUM(status = 'active' AND doc_type IN ('page','policy')),0) policies, COALESCE(SUM(status = 'active' AND indexed_at < NOW() - INTERVAL 24 HOUR),0) stale FROM ciu_documents WHERE site_id = @site_id");
        $attention = $this->row("SELECT COUNT(*) total, COUNT(DISTINCT category) categories FROM tia_attention_scenarios WHERE site_id = @site_id AND status = 'active'");
        $rules = $this->row("SELECT COUNT(*) total FROM tia_playbook_rules WHERE site_id = @site_id AND status = 'active'");
        $sources = $this->row("SELECT COUNT(*) total FROM tia_training_sources WHERE site_id = @site_id AND status = 'active'");
        $insights = $this->row("SELECT COUNT(*) total, COALESCE(SUM(status = 'applied'),0) applied FROM tia_learning_insights WHERE site_id = @site_id");
        $interactions = $this->row("SELECT COUNT(*) total, COALESCE(SUM(attention_slug IS NOT NULL AND attention_slug <> ''),0) with_attention FROM tia_interactions WHERE site_id = @site_id AND created_at >= NOW() - INTERVAL 30 DAY");
        $feedback = $this->row("SELECT COUNT(*) total, COALESCE(SUM(rating = 'up'),0) positive FROM tia_feedback WHERE site_id = @site_id AND created_at >= NOW() - INTERVAL 30 DAY");

        $components = [
            $this->component('roles', 'Roles', $this->pct($roles['complete'], $roles['total']), 12, (int)$roles['complete'] . '/' . (int)$roles['total'] . ' completos'),
            $this->component('intents', 'Intenciones', $this->pct($intents['complete'], $intents['total']), 13, (int)$intents['complete'] . '/' . (int)$intents['total'] . ' con playbook'),
            $this->component('sentiments', 'Emociones', $this->pct($sentiments['complete'], $sentiments['total']), 8, (int)$sentiments['complete'] . '/' . (int)$sentiments['total'] . ' completas'),
            $this->component('objections', 'Objeciones', $this->pct($objections['complete'], $objections['total']), 8, (int)$objections['complete'] . '/' . (int)$objections['total'] . ' completas'),
            $this->component('knowledge', 'Conocimiento', $this->knowledgeScore($knowledge), 15, (int)$knowledge['active'] . ' documentos activos · ' . (int)$knowledge['stale'] . ' desactualizados'),
            $this->component('attention', 'Catálogo de atención', min(100, (int)round((int)$attention['total'] / 150 * 100)), 15, (int)$attention['total'] . ' escenarios · ' . (int)$attention['categories'] . ' categorías'),
            $this->component('live', 'Calidad en vivo', $this->pct($interactions['with_attention'], $interactions['total']), 10, (int)$interactions['with_attention'] . '/' . (int)$interactions['total'] . ' interacciones con escenario'),
            $this->component('feedback', 'Satisfacción', $this->pct($feedback['positive'], $feedback['total']), 7, (int)$feedback['positive'] . '/' . (int)$feedback['total'] . ' valoraciones positivas'),
            $this->component('learning', 'Actividad de aprendizaje', min(100, (int)$rules['total'] * 10 + (int)$sources['total'] * 15 + (int)$insights['applied'] * 15), 7, (int)$rules['total'] . ' reglas · ' . (int)$sources['total'] . ' fuentes · ' . (int)$insights['applied'] . ' aprendizajes aplicados'),
            $this->component('outcomes', 'Resultados', $this->pct($this->paidCount(), max(1, (int)$interactions['total'])), 5, $this->paidCount() . ' pedidos atribuidos de ' . (int)$interactions['total'] . ' interacciones'),
        ];
        $noData = ['live' => (int)$interactions['total'] === 0, 'feedback' => (int)$feedback['total'] === 0, 'outcomes' => (int)$interactions['total'] === 0];
        foreach ($components as &$component) {
            if (!empty($noData[$component['key']])) $component['no_data'] = true;
        }
        unset($component);
        $score = 0.0;
        foreach ($components as $component) $score += $component['contribution'];
        $score = round($score, 1);
        $level = $score >= 85 ? 'Experto' : ($score >= 65 ? 'Avanzado' : ($score >= 40 ? 'En desarrollo' : 'Inicial'));
        $gaps = [];
        foreach ($components as $component) if ($component['score'] < 80) $gaps[] = ['key' => $component['key'], 'label' => $component['label'], 'score' => $component['score'], 'detail' => $component['detail']];
        if ((int)$sources['total'] === 0) $gaps[] = ['key' => 'sources', 'label' => 'Manuales de atención', 'score' => 0, 'detail' => 'No hay manuales cargados.'];
        if ((int)$feedback['total'] === 0) $gaps[] = ['key' => 'feedback', 'label' => 'Feedback real', 'score' => 0, 'detail' => 'Aún no hay valoraciones de clientes.'];
        $recommendations = $this->recommendations($components, $sources, $feedback, $knowledge);
        $history = $this->snapshot($score, $components);
        return [
            'ok' => true,
            'score' => $score,
            'level' => $level,
            'components' => $components,
            'gaps' => $gaps,
            'recommendations' => $recommendations,
            'history' => $history,
            'sources' => $this->sources(),
            'counts' => [
                'roles' => (int)$roles['total'], 'intents' => (int)$intents['total'], 'sentiments' => (int)$sentiments['total'],
                'objections' => (int)$objections['total'], 'documents' => (int)$knowledge['active'], 'scenarios' => (int)$attention['total'],
                'rules' => (int)$rules['total'], 'sources' => (int)$sources['total'], 'feedback' => (int)$feedback['total'],
            ],
        ];
    }

    public function ingestUrl(string $url, string $title = '', string $user = 'admin'): array
    {
        $this->ensureTables();
        $url = trim($url);
        $parts = parse_url($url);
        if (!is_array($parts) || !in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true) || empty($parts['host'])) {
            return ['ok' => false, 'message' => 'La URL debe ser http o https.'];
        }
        $host = strtolower((string)$parts['host']);
        $ip = gethostbyname($host);
        if (!filter_var($ip, FILTER_VALIDATE_IP) || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return ['ok' => false, 'message' => 'La URL no puede apuntar a una red privada o local.'];
        }
        $context = stream_context_create(['http' => ['timeout' => 10, 'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120 Safari/537.36', 'header' => "Accept: text/html,application/xhtml+xml\r\n", 'follow_location' => 1, 'max_redirects' => 2], 'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $html = @file_get_contents($url, false, $context, 0, 2097152);
        if ($html === false || trim($html) === '') return ['ok' => false, 'message' => 'No se pudo leer la URL.'];
        $pageTitle = $title;
        if ($pageTitle === '' && preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) $pageTitle = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = preg_replace('/<(script|style|noscript)[^>]*>.*?<\/\1>/is', ' ', $html) ?? $html;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');
        if (mb_strlen($text) < 40) return ['ok' => false, 'message' => 'La URL no contiene texto útil.'];
        return $this->ingestContent($pageTitle !== '' ? $pageTitle : $host, $text, 'url', $url, $user);
    }

    public function ingestInternal(string $title, string $content, string $url = '', string $user = 'learning-worker'): array
    {
        return $this->ingestContent($title, $content, 'internal', $url, $user);
    }

    public function ingestFile(array $file, string $user = 'admin'): array
    {
        $this->ensureTables();
        $name = (string)($file['name'] ?? 'manual');
        $tmp = (string)($file['tmp_name'] ?? '');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['txt', 'md', 'csv', 'json', 'html', 'htm'], true)) {
            return ['ok' => false, 'message' => 'Formato no soportado. Usa TXT, MD, CSV, JSON o HTML.'];
        }
        if ($tmp === '' || !is_file($tmp) || filesize($tmp) > 2097152) return ['ok' => false, 'message' => 'Archivo inválido o mayor a 2 MB.'];
        $content = (string)file_get_contents($tmp);
        if (in_array($ext, ['html', 'htm'], true)) $content = html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $content = trim(preg_replace('/\s+/', ' ', $content) ?? '');
        if (mb_strlen($content) < 40) return ['ok' => false, 'message' => 'El archivo no contiene texto suficiente.'];
        return $this->ingestContent(pathinfo($name, PATHINFO_FILENAME), $content, 'file', '', $user);
    }

    public function addCommand(string $bad, string $good, string $user = 'admin'): array
    {
        $this->ensureTables();
        $bad = trim(preg_replace('/\s+/', ' ', $bad) ?? '');
        $good = trim(preg_replace('/\s+/', ' ', $good) ?? '');
        if (mb_strlen($bad) < 8 || mb_strlen($good) < 8) return ['ok' => false, 'message' => 'Describe el comportamiento inadecuado y el deseado.'];
        $rule = 'Cuando el cliente ' . mb_substr($bad, 0, 220) . ', ' . mb_substr($good, 0, 260);
        try {
            Database::instance()->prepare("INSERT INTO tia_playbook_rules (site_id, insight_id, rule_text, status, applied_by, created_at) VALUES (@site_id, NULL, ?, 'active', ?, NOW())")
                ->execute([mb_substr($rule, 0, 500), mb_substr($user, 0, 100)]);
            return ['ok' => true, 'message' => 'Corrección aplicada al comportamiento.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo aplicar la corrección.'];
        }
    }

    public function sources(): array
    {
        $this->ensureTables();
        try {
            return array_map(function ($r) {
                return ['id' => (int)$r['id'], 'source_type' => (string)$r['source_type'], 'title' => (string)$r['title'], 'url' => (string)($r['url'] ?? ''), 'size' => mb_strlen((string)$r['content']), 'created_at' => (string)$r['created_at']];
            }, Database::instance()->query("SELECT id, source_type, title, url, content, created_at FROM tia_training_sources WHERE site_id = @site_id AND status = 'active' ORDER BY id DESC LIMIT 50")->fetchAll());
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function deleteSource(int $id): array
    {
        try {
            Database::instance()->prepare("UPDATE tia_training_sources SET status = 'archived', updated_at = NOW() WHERE id = ? AND site_id = @site_id")->execute([$id]);
            return ['ok' => true];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo eliminar la fuente.'];
        }
    }

    public function context(string $query, int $limit = 2): string
    {
        $this->ensureTables();
        $tokens = array_values(array_filter(preg_split('/[^a-z0-9]+/', $this->fold($query)) ?: [], fn($t) => mb_strlen($t) >= 4));
        if (!$tokens) return '';
        try {
            $rows = Database::instance()->query("SELECT title, url, content FROM tia_training_sources WHERE site_id = @site_id AND status = 'active' ORDER BY id DESC LIMIT 60")->fetchAll();
        } catch (\Throwable $e) {
            return '';
        }
        $hits = [];
        foreach ($rows as $row) {
            $folded = $this->fold((string)$row['content']);
            $score = 0;
            foreach ($tokens as $token) $score += substr_count($folded, $token);
            if ($score > 0) $hits[] = ['score' => $score, 'row' => $row];
        }
        usort($hits, fn($a, $b) => $b['score'] <=> $a['score']);
        $parts = [];
        foreach (array_slice($hits, 0, max(1, min(5, $limit))) as $hit) {
            $content = preg_replace('/\s+/', ' ', (string)$hit['row']['content']) ?? '';
            $parts[] = 'MANUAL: ' . (string)$hit['row']['title'] . "\n" . mb_substr($content, 0, 1200);
        }
        return implode("\n\n", $parts);
    }

    private function ingestContent(string $title, string $content, string $type, string $url, string $user): array
    {
        $title = trim($title) !== '' ? mb_substr(trim($title), 0, 255) : 'Manual de atención';
        $content = mb_substr(trim($content), 0, 60000);
        if (mb_strlen($content) < 40) return ['ok' => false, 'message' => 'Contenido insuficiente para aprender.'];
        try {
            $db = Database::instance();
            $url = mb_substr($url, 0, 500);
            $check = $db->prepare("SELECT id FROM tia_training_sources WHERE site_id = @site_id AND status = 'active' AND title = ? AND (url = ? OR (url = '' AND ? = '')) LIMIT 1");
            $check->execute([$title, $url, $url]);
            $existing = $check->fetch();
            if ($existing) {
                $db->prepare("UPDATE tia_training_sources SET content = ?, source_type = ?, updated_at = NOW() WHERE id = ? AND site_id = @site_id")
                    ->execute([$content, $type, (int)$existing['id']]);
                return ['ok' => true, 'message' => 'Manual actualizado.', 'updated' => true];
            }
            $db->prepare("INSERT INTO tia_training_sources (site_id, source_type, title, url, content, status, created_at, updated_at) VALUES (@site_id, ?, ?, ?, ?, 'active', NOW(), NOW())")
                ->execute([$type, $title, $url, $content]);
            return ['ok' => true, 'message' => 'Manual incorporado al aprendizaje.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo guardar el manual.'];
        }
    }

    private function snapshot(float $score, array $components): array
    {
        try {
            $db = Database::instance();
            $breakdown = json_encode($components, JSON_UNESCAPED_UNICODE);
            $db->prepare("INSERT INTO tia_learning_snapshots (site_id, snapshot_date, score, breakdown, created_at, updated_at) VALUES (@site_id, CURDATE(), ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE score = VALUES(score), breakdown = VALUES(breakdown), updated_at = NOW()")
                ->execute([$score, $breakdown]);
            return array_map(fn($r) => ['date' => (string)$r['snapshot_date'], 'score' => (float)$r['score']], $db->query("SELECT snapshot_date, score FROM tia_learning_snapshots WHERE site_id = @site_id ORDER BY snapshot_date DESC LIMIT 30")->fetchAll());
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function recommendations(array $components, array $sources, array $feedback, array $knowledge): array
    {
        $out = [];
        $byKey = [];
        foreach ($components as $c) $byKey[$c['key']] = $c;
        if (($byKey['roles']['score'] ?? 0) < 100) $out[] = ['priority' => 'P0', 'action' => 'Completa misión, tono, restricciones y prompt en todos los roles.'];
        if (($byKey['intents']['score'] ?? 0) < 100) $out[] = ['priority' => 'P0', 'action' => 'Agrega ejemplos, palabras clave y playbook a las intenciones incompletas.'];
        if ((int)$sources['total'] === 0) $out[] = ['priority' => 'P0', 'action' => 'Carga tu primer manual de atención por URL o archivo.'];
        if ((int)$knowledge['stale'] > 0) $out[] = ['priority' => 'P0', 'action' => 'Sincroniza el CIU: hay documentos desactualizados.'];
        if (($byKey['attention']['score'] ?? 0) < 80) $out[] = ['priority' => 'P1', 'action' => 'Amplía el catálogo de escenarios; faltan preguntas reales por cubrir.'];
        if (($byKey['feedback']['score'] ?? 0) < 80) $out[] = ['priority' => 'P1', 'action' => 'Revisa los motivos de "No útil" y convierte los patrones en reglas.'];
        if ((int)$feedback['total'] === 0) $out[] = ['priority' => 'P1', 'action' => 'Activa el feedback en el chat y recoge valoraciones reales.'];
        if (($byKey['live']['score'] ?? 0) < 70) $out[] = ['priority' => 'P1', 'action' => 'Prueba conversaciones y verifica que cada consulta caiga en un escenario.'];
        if (($byKey['outcomes']['score'] ?? 0) < 50) $out[] = ['priority' => 'P2', 'action' => 'Conecta más clics y compras a las interacciones de TIA.'];
        return array_slice($out, 0, 8);
    }

    private function paidCount(): int
    {
        try {
            return (int)Database::instance()->query("SELECT COUNT(*) FROM tia_interactions WHERE site_id = @site_id AND outcome = 'paid' AND created_at >= NOW() - INTERVAL 30 DAY")->fetchColumn();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function knowledgeScore(array $row): int
    {
        $score = 0;
        if ((int)$row['products'] > 0) $score += 40;
        if ((int)$row['policies'] > 0) $score += 20;
        if ((int)$row['stale'] === 0 && (int)$row['active'] > 0) $score += 40;
        return $score;
    }

    private function component(string $key, string $label, int $score, int $weight, string $detail): array
    {
        $score = max(0, min(100, $score));
        return ['key' => $key, 'label' => $label, 'score' => $score, 'weight' => $weight, 'contribution' => round($score * $weight / 100, 1), 'detail' => $detail];
    }

    private function pct(int $part, int $total): int
    {
        return $total > 0 ? (int)round($part / $total * 100) : 0;
    }

    private function row(string $sql): array
    {
        try {
            $row = Database::instance()->query($sql)->fetch();
            return is_array($row) ? $row : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function fold(string $value): string
    {
        return strtr(mb_strtolower($value), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    }
}
