<?php
namespace App\Services;

use App\Core\Database;
use App\Widgets\WidgetRegistry;

class BrickLifecycleService
{
    public const NEW_DAYS = 30;
    public const TARGET_DAYS = 90;
    public const MATURITIES = ['alpha', 'beta', 'rc', 'stable'];

    public function ensureTables(): array
    {
        $db = Database::instance();
        foreach (['brick_lifecycle', 'brick_lifecycle_events'] as $t) {
            try {
                $db->query("SELECT 1 FROM $t LIMIT 1");
            } catch (\Throwable $e) {
                $path = ROOT_DIR . '/install/brick_lifecycle.sql';
                if (!is_file($path)) return ['ok' => false, 'message' => 'Schema file not found'];
                $sql = preg_replace('/^\s*--.*$/m', '', (string)file_get_contents($path));
                foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
                    try { $db->exec($stmt); } catch (\Throwable $e2) {}
                }
                break;
            }
        }
        return ['ok' => true];
    }

    public function all(): array
    {
        $out = [];
        try {
            $rows = Database::instance()->query('SELECT * FROM brick_lifecycle')->fetchAll();
            foreach ($rows as $r) $out[$r['slug']] = $r;
        } catch (\Throwable $e) {}
        return $out;
    }

    public function get(string $slug): ?array
    {
        try {
            $stmt = Database::instance()->prepare('SELECT * FROM brick_lifecycle WHERE slug = ?');
            $stmt->execute([$slug]);
            $row = $stmt->fetch();
            return $row ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function assignType(string $slug): string
    {
        if ($slug === 'seo-global-launch') return 'incubator';
        if (WidgetRegistry::get($slug)) return 'core';
        return 'repo';
    }

    public function resolve(string $slug, array $item = []): array
    {
        $row = $this->get($slug);
        $functional = array_key_exists('functional', $item) ? (bool)$item['functional'] : true;
        $type = $row['brick_type'] ?? $this->assignType($slug);

        $state = $row['state'] ?? ($functional ? 'launched' : 'incubator');
        $target = $row['target_launch_at'] ?? null;
        $estimated = false;
        if ($state === 'incubator' && !$target) {
            $target = $this->defaultTarget();
            $estimated = true;
        }
        $launchedAt = $row['launched_at'] ?? null;
        $isNew = false;
        if ($launchedAt) {
            $age = time() - (int)strtotime($launchedAt);
            $isNew = $age >= 0 && $age <= self::NEW_DAYS * 86400;
        }

        return [
            'state' => $state,
            'brick_type' => $type,
            'maturity' => $row['maturity'] ?? 'alpha',
            'target_launch_at' => $target,
            'target_estimated' => $estimated,
            'launched_at' => $launchedAt,
            'launched_by' => $row['launched_by'] ?? null,
            'release_notes' => $row['release_notes'] ?? '',
            'interest' => (int)($row['interest'] ?? 0),
            'hidden' => (int)($row['hidden'] ?? 0),
            'is_new' => $isNew,
            'countdown' => $state === 'incubator' ? $this->countdown($target) : null,
        ];
    }

    public function launch(string $slug, string $actor, array $opts = []): array
    {
        $type = $this->assignType($slug);
        $ready = $this->readiness($slug);
        $force = !empty($opts['force']);
        if (!$force && $ready['checks'] && $ready['score'] < 40) {
            return ['ok' => false, 'message' => 'El brick no está listo para lanzarse', 'readiness' => $ready];
        }
        $row = $this->get($slug);
        $prev = $row['state'] ?? 'incubator';
        if ($prev === 'launched') {
            return ['ok' => true, 'message' => 'El brick ya está en el Marketplace', 'slug' => $slug, 'idempotent' => true];
        }
        return $this->save($slug, [
            'brick_type' => $type,
            'state' => 'launched',
            'launched_at' => date('Y-m-d H:i:s'),
            'launched_by' => $actor,
            'target_launch_at' => null,
            'release_notes' => (string)($opts['release_notes'] ?? ($row['release_notes'] ?? '')),
            'maturity' => (string)($opts['maturity'] ?? ($row['maturity'] ?? 'stable')),
        ], 'launch', $actor, $prev, 'launched', (string)($opts['reason'] ?? ''));
    }

    public function schedule(string $slug, string $targetAt, string $actor, ?string $reason = null): array
    {
        $ts = strtotime($targetAt);
        if (!$ts) return ['ok' => false, 'message' => 'Fecha inválida'];
        if ($ts <= time()) return ['ok' => false, 'message' => 'La fecha debe estar en el futuro'];
        $row = $this->get($slug);
        return $this->save($slug, [
            'brick_type' => $row['brick_type'] ?? $this->assignType($slug),
            'state' => 'incubator',
            'target_launch_at' => date('Y-m-d H:i:s', $ts),
            'launched_at' => null,
        ], 'schedule', $actor, $row['state'] ?? 'incubator', 'incubator', $reason);
    }

    public function incubate(string $slug, string $actor, ?string $reason = null): array
    {
        $row = $this->get($slug);
        $prev = $row['state'] ?? 'launched';
        return $this->save($slug, [
            'brick_type' => $row['brick_type'] ?? $this->assignType($slug),
            'state' => 'incubator',
            'launched_at' => null,
        ], 'incubate', $actor, $prev, 'incubator', $reason);
    }

    public function setHidden(string $slug, bool $hidden, string $actor): array
    {
        $row = $this->get($slug);
        return $this->save($slug, [
            'brick_type' => $row['brick_type'] ?? $this->assignType($slug),
            'hidden' => $hidden ? 1 : 0,
        ], $hidden ? 'hide' : 'unhide', $actor, $row['state'] ?? null, $row['state'] ?? null, null);
    }

    public function setMaturity(string $slug, string $maturity, string $actor): array
    {
        if (!in_array($maturity, self::MATURITIES, true)) return ['ok' => false, 'message' => 'Madurez inválida'];
        $row = $this->get($slug);
        return $this->save($slug, [
            'brick_type' => $row['brick_type'] ?? $this->assignType($slug),
            'maturity' => $maturity,
        ], 'maturity', $actor, $row['maturity'] ?? null, $maturity, null);
    }

    public function addInterest(string $slug): array
    {
        $this->ensureTables();
        $db = Database::instance();
        try {
            $db->prepare('INSERT INTO brick_lifecycle (slug, brick_type, state, interest) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE interest = interest + 1')
                ->execute([$slug, $this->assignType($slug), 'incubator']);
            $r = $this->get($slug);
            return ['ok' => true, 'interest' => (int)($r['interest'] ?? 1)];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo registrar el interés'];
        }
    }

    public function countdown(?string $target, ?int $now = null): array
    {
        $now = $now ?? time();
        $empty = ['years' => 0, 'months' => 0, 'weeks' => 0, 'days' => 0, 'hours' => 0, 'minutes' => 0, 'seconds' => 0, 'total' => 0, 'expired' => true, 'target' => $target];
        if (!$target) return $empty;
        $ts = strtotime($target);
        if (!$ts) return $empty;
        $total = $ts - $now;
        if ($total <= 0) return array_merge($empty, ['target' => $target]);
        $from = new \DateTime('@' . $now);
        $to = new \DateTime($target);
        $d = $from->diff($to);
        $weeks = intdiv($d->d, 7);
        return [
            'years' => $d->y,
            'months' => $d->m,
            'weeks' => $weeks,
            'days' => $d->d % 7,
            'hours' => $d->h,
            'minutes' => $d->i,
            'seconds' => $d->s,
            'total' => $total,
            'expired' => false,
            'target' => $target,
        ];
    }

    public function readiness(string $type): array
    {
        $class = WidgetRegistry::get($type);
        if (!$class) {
            return ['score' => 0, 'ready' => false, 'checks' => []];
        }
        $checks = [];
        $score = 0;

        $functional = false;
        try { $functional = strlen(WidgetRegistry::render($type, [])) >= 200; } catch (\Throwable $e) {}
        $checks[] = ['key' => 'renders', 'label' => 'Renderiza contenido real', 'ok' => $functional, 'weight' => 40];
        if ($functional) $score += 40;

        $schema = method_exists($class, 'configSchema') ? (array)$class::configSchema() : [];
        $hasSchema = count($schema) > 0;
        $checks[] = ['key' => 'schema', 'label' => 'Campos configurables', 'ok' => $hasSchema, 'weight' => 20];
        if ($hasSchema) $score += 20;

        $meta = method_exists($class, 'meta') ? (array)$class::meta() : [];
        $hasMeta = !empty($meta['icon']) && !empty($meta['name']);
        $checks[] = ['key' => 'meta', 'label' => 'Nombre e icono', 'ok' => $hasMeta, 'weight' => 10];
        if ($hasMeta) $score += 10;

        $preview = '';
        try { $preview = method_exists($class, 'adminPreview') ? (string)$class::adminPreview() : ''; } catch (\Throwable $e) {}
        $hasPreview = strlen($preview) >= 100;
        $checks[] = ['key' => 'preview', 'label' => 'Preview de admin', 'ok' => $hasPreview, 'weight' => 15];
        if ($hasPreview) $score += 15;

        $usage = 0;
        try {
            $stmt = Database::instance()->prepare('SELECT COUNT(*) FROM sections s JOIN pages p ON p.id = s.page_id WHERE s.widget_type = ? AND p.site_id = @site_id');
            $stmt->execute([$type]);
            $usage = (int)$stmt->fetchColumn();
        } catch (\Throwable $e) {}
        $hasUsage = $usage > 0;
        $checks[] = ['key' => 'usage', 'label' => 'En uso en páginas', 'ok' => $hasUsage, 'weight' => 15];
        if ($hasUsage) $score += 15;

        return ['score' => $score, 'ready' => $functional, 'checks' => $checks];
    }

    public function events(string $slug, int $limit = 30): array
    {
        try {
            $stmt = Database::instance()->prepare('SELECT * FROM brick_lifecycle_events WHERE slug = ? ORDER BY created_at DESC LIMIT ' . max(1, min(200, $limit)));
            $stmt->execute([$slug]);
            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function recentEvents(int $limit = 20): array
    {
        try {
            $stmt = Database::instance()->query('SELECT * FROM brick_lifecycle_events ORDER BY created_at DESC LIMIT ' . max(1, min(200, $limit)));
            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function processDueLaunches(): array
    {
        $this->ensureTables();
        $due = [];
        try {
            $stmt = Database::instance()->query("SELECT slug FROM brick_lifecycle WHERE state = 'incubator' AND target_launch_at IS NOT NULL AND target_launch_at <= NOW()");
            $due = array_column($stmt->fetchAll(), 'slug');
        } catch (\Throwable $e) {}
        $launched = [];
        foreach ($due as $slug) {
            $r = $this->launch($slug, 'system', ['force' => true, 'reason' => 'auto-launch programado']);
            if (!empty($r['ok'])) $launched[] = $slug;
        }
        return ['ok' => true, 'due' => count($due), 'launched' => $launched];
    }

    private function save(string $slug, array $fields, string $event, string $actor, ?string $from, ?string $to, ?string $reason): array
    {
        $this->ensureTables();
        $db = Database::instance();
        $fields['slug'] = $slug;
        $cols = array_keys($fields);
        $ph = array_map(fn($c) => $c === 'slug' ? ':slug' : ':' . $c, $cols);
        $updates = [];
        foreach ($cols as $c) {
            if ($c === 'slug') continue;
            $updates[] = "$c = VALUES($c)";
        }
        $sql = 'INSERT INTO brick_lifecycle (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $ph) . ') ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);
        try {
            $db->prepare($sql)->execute($fields);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo guardar el estado: ' . $e->getMessage()];
        }
        $this->log($slug, $event, $from, $to, $actor, $reason, []);
        return ['ok' => true, 'message' => 'ok', 'slug' => $slug, 'state' => $to];
    }

    private function log(string $slug, string $event, ?string $from, ?string $to, string $actor, ?string $reason, array $meta): void
    {
        try {
            Database::instance()->prepare('INSERT INTO brick_lifecycle_events (slug, event, from_state, to_state, actor, reason, meta) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([$slug, $event, $from, $to, $actor, $reason, $meta ? json_encode($meta) : null]);
        } catch (\Throwable $e) {}
    }

    private function defaultTarget(): string
    {
        return date('Y-m-d H:i:s', time() + self::TARGET_DAYS * 86400);
    }
}
