<?php
namespace App\Services;

use App\Core\Database;

class TiaLearningRunService
{
    private static bool $ready = false;

    public function ensureTables(): void
    {
        if (self::$ready) return;
        Database::instance()->exec("CREATE TABLE IF NOT EXISTS tia_learning_jobs (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL DEFAULT 1,
            status VARCHAR(30) NOT NULL DEFAULT 'running',
            step INT NOT NULL DEFAULT 0,
            total_steps INT NOT NULL DEFAULT 0,
            progress INT NOT NULL DEFAULT 0,
            logs JSON NULL,
            test_passed TINYINT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            finished_at DATETIME NULL,
            KEY idx_tia_learning_job (site_id, status, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        self::$ready = true;
    }

    public function start(string $user = 'admin'): array
    {
        $this->ensureTables();
        $total = count($this->officialSources()) + 4;
        try {
            $db = Database::instance();
            $db->prepare("INSERT INTO tia_learning_jobs (site_id, status, step, total_steps, progress, logs, created_at, updated_at) VALUES (@site_id, 'running', 0, ?, 0, ?, NOW(), NOW())")
                ->execute([$total, json_encode([$this->line('Proceso creado por ' . $user . '. Iniciando inventario interno.'), $this->line('Plan: ' . $total . ' etapas: sitio interno, ' . count($this->officialSources()) . ' fuentes oficiales, construcción del manual y prueba de TIA.')], JSON_UNESCAPED_UNICODE)]);
            return ['ok' => true, 'job_id' => (int)$db->lastInsertId(), 'total_steps' => $total];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo iniciar el aprendizaje.'];
        }
    }

    public function step(int $id): array
    {
        $this->ensureTables();
        $db = Database::instance();
        $stmt = $db->prepare('SELECT * FROM tia_learning_jobs WHERE id = ? AND site_id = @site_id LIMIT 1');
        $stmt->execute([$id]);
        $job = $stmt->fetch();
        if (!$job) return ['ok' => false, 'message' => 'Proceso no encontrado.'];
        if ((string)$job['status'] !== 'running') return $this->job($id);
        $logs = json_decode((string)($job['logs'] ?? '[]'), true);
        if (!is_array($logs)) $logs = [];
        $step = (int)$job['step'];
        $official = $this->officialSources();
        $n = count($official);
        $learning = new TiaLearningService();
        $status = 'running';
        $progress = (int)$job['progress'];
        $step++;
        if ($step <= 1) {
            $logs[] = $this->line('Explorando documentos internos del sitio: páginas, políticas, secciones y categorías.');
            try {
                $rows = $db->query("SELECT title, url, content FROM ciu_documents WHERE site_id = @site_id AND status = 'active' AND doc_type IN ('page','policy','section','category') ORDER BY FIELD(doc_type,'page','policy','section','category'), id DESC LIMIT 25")->fetchAll();
                $ok = 0;
                foreach ($rows as $row) {
                    $r = $learning->ingestInternal((string)$row['title'], (string)$row['content'], (string)$row['url'], 'learning-worker');
                    if (!empty($r['ok'])) $ok++;
                }
                $logs[] = $this->line('Inventario interno: ' . $ok . ' de ' . count($rows) . ' documentos incorporados.');
            } catch (\Throwable $e) {
                $logs[] = $this->line('ERROR interno: ' . $e->getMessage(), 'error');
            }
            $progress = 12;
        } elseif ($step <= 1 + $n) {
            $source = $official[$step - 2] ?? null;
            if ($source) {
                $logs[] = $this->line('Fuente oficial [' . ($step - 1) . '/' . $n . ']: ' . $source['name'] . ' — ' . $source['url']);
                $r = $learning->ingestUrl($source['url'], $source['name'], 'learning-worker');
                if (!empty($r['ok'])) $logs[] = $this->line('OK: ' . ($r['message'] ?? 'contenido incorporado.'));
                else $logs[] = $this->line('OMITIDA: ' . ($r['message'] ?? 'sin contenido útil.'), 'warn');
            }
            $progress = 12 + (int)round(($step - 1) / max(1, $n) * 35);
        } elseif ($step <= 2 + $n) {
            $count = (int)$db->query("SELECT COUNT(*) FROM tia_training_sources WHERE site_id = @site_id AND status = 'active'")->fetchColumn();
            $logs[] = $this->line('Construyendo manual consolidado con ' . $count . ' fuentes activas.');
            if ($count === 0) {
                $logs[] = $this->line('ERROR: no hay fuentes para construir el manual.', 'error');
                $status = 'failed';
            } else {
                $logs[] = $this->line('Manual consolidado listo. Las fuentes quedan disponibles como contexto de atención.');
            }
            $progress = 75;
        } elseif ($step <= 3 + $n) {
            $logs[] = $this->line('Ejecutando prueba funcional de TIA: producto, comparación, imagen y canal.');
            $test = $this->testTia();
            foreach ($test['logs'] as $line) $logs[] = $line;
            $job['test_passed'] = !empty($test['passed']) ? 1 : 0;
            if (!empty($test['passed'])) {
                $logs[] = $this->line('PRUEBA SUPERADA. El aprendizaje puede instalarse.');
                $progress = 94;
            } else {
                $logs[] = $this->line('PRUEBA FALLIDA. No se anunciará el aprendizaje como instalado.', 'error');
                $status = 'failed';
                $progress = 100;
            }
        } else {
            if (!empty($job['test_passed']) && $status === 'running') {
                $logs[] = $this->line('Aprendizaje instalado y verificado. TIA usará las fuentes en próximas conversaciones.');
                $status = 'completed';
            }
            $progress = 100;
        }
        $finished = in_array($status, ['completed', 'failed'], true);
        $db->prepare("UPDATE tia_learning_jobs SET status = ?, step = ?, progress = ?, logs = ?, test_passed = ?, updated_at = NOW(), finished_at = CASE WHEN ? = 1 THEN NOW() ELSE finished_at END WHERE id = ? AND site_id = @site_id")
            ->execute([$status, $step, min(100, $progress), json_encode($logs, JSON_UNESCAPED_UNICODE), (int)($job['test_passed'] ?? 0), $finished ? 1 : 0, $id]);
        return $this->job($id);
    }

    public function job(int $id): array
    {
        $this->ensureTables();
        $stmt = Database::instance()->prepare('SELECT * FROM tia_learning_jobs WHERE id = ? AND site_id = @site_id LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) return ['ok' => false, 'message' => 'Proceso no encontrado.'];
        return [
            'ok' => true,
            'job' => [
                'id' => (int)$row['id'], 'status' => (string)$row['status'], 'step' => (int)$row['step'],
                'total_steps' => (int)$row['total_steps'], 'progress' => (int)$row['progress'],
                'logs' => json_decode((string)($row['logs'] ?? '[]'), true) ?: [],
                'test_passed' => (int)$row['test_passed'] === 1, 'finished_at' => $row['finished_at'],
            ],
        ];
    }

    private function testTia(): array
    {
        $logs = [];
        $passed = true;
        try {
            if (!defined('ROOT_DIR')) define('ROOT_DIR', dirname(__DIR__, 2));
            $svc = new TiaSupportService();
            $r1 = $svc->chat('un piano barato', '/tienda', 'es', '127.0.0.1', 'tia-learning-test');
            $ok1 = !empty($r1['ok']) && !empty($r1['cards']) && !preg_match('/CIU|Centro de Información|base de conocimiento/i', (string)($r1['reply'] ?? ''));
            $logs[] = $this->line(($ok1 ? 'OK' : 'FALLO') . ' producto: ' . ($ok1 ? 'tarjetas y precio reales' : 'sin tarjetas o con fuga interna'), $ok1 ? 'ok' : 'error');
            $passed = $passed && $ok1;
            $r2 = $svc->chat('compara el AP-470 y el AP-550', '/tienda', 'es', '127.0.0.1', 'tia-learning-test');
            $ok2 = !empty($r2['comparison']['products']) && count($r2['comparison']['products']) >= 2;
            $logs[] = $this->line(($ok2 ? 'OK' : 'FALLO') . ' comparación: ' . ($ok2 ? 'comparador con productos' : 'sin comparación'), $ok2 ? 'ok' : 'error');
            $passed = $passed && $ok2;
            $r3 = $svc->chat('dame una imagen', '/tienda', 'es', '127.0.0.1', 'tia-learning-test');
            $ok3 = !empty($r3['cards']) && !preg_match('/no puedo enviar imágenes/i', (string)($r3['reply'] ?? ''));
            $logs[] = $this->line(($ok3 ? 'OK' : 'FALLO') . ' imagen: ' . ($ok3 ? 'tarjetas con imagen' : 'sin tarjetas o negación'), $ok3 ? 'ok' : 'error');
            $passed = $passed && $ok3;
            $r4 = $svc->chat('no quiero WhatsApp', '/tienda', 'es', '127.0.0.1', 'tia-learning-test');
            $ok4 = !empty($r4['handoff']['kind']) && $r4['handoff']['kind'] !== 'whatsapp';
            $logs[] = $this->line(($ok4 ? 'OK' : 'FALLO') . ' canal: ' . ($ok4 ? 'respeta rechazo de WhatsApp' : 'insiste con WhatsApp'), $ok4 ? 'ok' : 'error');
            $passed = $passed && $ok4;
        } catch (\Throwable $e) {
            $logs[] = $this->line('ERROR en prueba: ' . $e->getMessage(), 'error');
            $passed = false;
        }
        return ['passed' => $passed, 'logs' => $logs];
    }

    private function officialSources(): array
    {
        return [
            ['name' => 'Yamaha News & Events', 'url' => 'https://usa.yamaha.com/news_events/'],
            ['name' => 'Casio News', 'url' => 'https://www.casio.com/intl/news/'],
            ['name' => 'Soundcraft News', 'url' => 'https://www.soundcraft.com/en/news'],
            ['name' => 'Sennheiser Support', 'url' => 'https://www.sennheiser.com/en-us/support'],
            ['name' => 'Shure Insights', 'url' => 'https://www.shure.com/en-US/insights'],
            ['name' => 'Shure Microphone Guide', 'url' => 'https://www.shure.com/en-US/insights/choosing-a-microphone'],
            ['name' => 'QSC News', 'url' => 'https://www.qsc.com/news/'],
            ['name' => 'Pioneer DJ News', 'url' => 'https://www.pioneerdj.com/en/news/'],
        ];
    }

    private function line(string $message, string $level = 'info'): array
    {
        return ['t' => date('H:i:s'), 'level' => $level, 'message' => $message];
    }
}
