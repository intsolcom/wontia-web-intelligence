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
            page_id INT DEFAULT NULL,
            cursor_x DECIMAL(6,2) DEFAULT NULL,
            cursor_y DECIMAL(6,2) DEFAULT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_user (site_id, user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS wwi_section_variants (
            id INT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL DEFAULT 1,
            section_id INT NOT NULL,
            name VARCHAR(100) NOT NULL DEFAULT 'B',
            config JSON NOT NULL,
            weight INT NOT NULL DEFAULT 50,
            views INT NOT NULL DEFAULT 0,
            clicks INT NOT NULL DEFAULT 0,
            is_active TINYINT NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_section (section_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS wwi_edit_events (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL DEFAULT 1,
            user_id INT DEFAULT NULL,
            section_id INT NOT NULL,
            widget_type VARCHAR(100) DEFAULT '',
            field VARCHAR(120) DEFAULT '',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_site_field (site_id, field),
            INDEX idx_section (section_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        foreach ([
            "ALTER TABLE wwi_edit_presence ADD COLUMN IF NOT EXISTS page_id INT DEFAULT NULL",
            "ALTER TABLE wwi_edit_presence ADD COLUMN IF NOT EXISTS cursor_x DECIMAL(6,2) DEFAULT NULL",
            "ALTER TABLE wwi_edit_presence ADD COLUMN IF NOT EXISTS cursor_y DECIMAL(6,2) DEFAULT NULL",
        ] as $sql) {
            try {
                $db->exec($sql);
            } catch (\Throwable $e) {
            }
        }
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
        $db = Database::instance();
        $db->prepare("INSERT INTO wwi_section_comments (site_id, section_id, user_id, username, body) VALUES (@site_id, :s, :u, :n, :b)")
            ->execute(['s' => $sectionId, 'u' => $userId, 'n' => substr($username, 0, 100), 'b' => $body]);
        return ['ok' => true, 'message' => 'Comentario añadido', 'id' => (int)$db->lastInsertId()];
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
        $stmt = Database::instance()->prepare("SELECT id, section_id, username, created_at, snapshot FROM wwi_section_versions WHERE section_id = :s AND site_id = @site_id ORDER BY id DESC LIMIT 30");
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

    public function presencePing(int $userId, string $username, ?int $sectionId, ?int $pageId, ?float $cx, ?float $cy): void
    {
        $this->ensureTables();
        Database::instance()->prepare("INSERT INTO wwi_edit_presence (site_id, user_id, username, section_id, page_id, cursor_x, cursor_y) VALUES (@site_id, :u, :n, :s, :p, :cx, :cy) ON DUPLICATE KEY UPDATE username = :n2, section_id = :s2, page_id = :p2, cursor_x = :cx2, cursor_y = :cy2, updated_at = NOW()")
            ->execute(['u' => $userId, 'n' => substr($username, 0, 100), 's' => $sectionId, 'p' => $pageId, 'cx' => $cx, 'cy' => $cy, 'n2' => substr($username, 0, 100), 's2' => $sectionId, 'p2' => $pageId, 'cx2' => $cx, 'cy2' => $cy]);
    }

    public function activePresence(int $excludeUserId): array
    {
        $this->ensureTables();
        $stmt = Database::instance()->prepare("SELECT user_id, username, section_id, page_id, cursor_x, cursor_y, updated_at FROM wwi_edit_presence WHERE site_id = @site_id AND user_id <> :u AND updated_at > (NOW() - INTERVAL 90 SECOND) ORDER BY updated_at DESC LIMIT 20");
        $stmt->execute(['u' => $excludeUserId]);
        return $stmt->fetchAll();
    }

    public function variants(int $sectionId): array
    {
        $this->ensureTables();
        if (!$this->sectionInSite($sectionId)) return [];
        $stmt = Database::instance()->prepare("SELECT id, section_id, name, config, weight, views, clicks, is_active, created_at FROM wwi_section_variants WHERE section_id = :s AND site_id = @site_id ORDER BY id ASC");
        $stmt->execute(['s' => $sectionId]);
        return $stmt->fetchAll();
    }

    public function variantSave(int $sectionId, ?int $id, string $name, array $config, int $weight, int $isActive): array
    {
        if (!$this->sectionInSite($sectionId)) return ['ok' => false, 'message' => 'Sección no encontrada'];
        $this->ensureTables();
        $db = Database::instance();
        $name = substr(trim($name) !== '' ? trim($name) : 'B', 0, 100);
        $weight = max(1, min(100, $weight));
        $isActive = $isActive ? 1 : 0;
        if ($id) {
            $stmt = $db->prepare("UPDATE wwi_section_variants SET name = :n, config = :c, weight = :w, is_active = :a WHERE id = :id AND section_id = :s AND site_id = @site_id");
            $stmt->execute(['n' => $name, 'c' => json_encode($config, JSON_UNESCAPED_UNICODE), 'w' => $weight, 'a' => $isActive, 'id' => $id, 's' => $sectionId]);
            return ['ok' => true, 'message' => 'Variante actualizada', 'id' => $id];
        }
        $db->prepare("INSERT INTO wwi_section_variants (site_id, section_id, name, config, weight, is_active) VALUES (@site_id, :s, :n, :c, :w, :a)")
            ->execute(['s' => $sectionId, 'n' => $name, 'c' => json_encode($config, JSON_UNESCAPED_UNICODE), 'w' => $weight, 'a' => $isActive]);
        return ['ok' => true, 'message' => 'Variante creada', 'id' => (int)$db->lastInsertId()];
    }

    public function variantDelete(int $id): array
    {
        $this->ensureTables();
        $stmt = Database::instance()->prepare("DELETE FROM wwi_section_variants WHERE id = :id AND site_id = @site_id");
        $stmt->execute(['id' => $id]);
        return ['ok' => $stmt->rowCount() > 0];
    }

    public function pickVariants(array $sectionIds, string $visitorKey): array
    {
        if (!$sectionIds) return [];
        $this->ensureTables();
        $db = Database::instance();
        $in = implode(',', array_map('intval', $sectionIds));
        $rows = $db->query("SELECT id, section_id, name, config, weight FROM wwi_section_variants WHERE site_id = @site_id AND is_active = 1 AND section_id IN ($in) ORDER BY id ASC")->fetchAll();
        $bySection = [];
        foreach ($rows as $r) {
            $bySection[(int)$r['section_id']][] = $r;
        }
        $bucket = (int)(abs(crc32($visitorKey)) % 100);
        $out = [];
        foreach ($bySection as $sid => $list) {
            $total = 0;
            foreach ($list as $v) $total += max(1, (int)$v['weight']);
            $roll = $bucket % max(1, $total);
            $acc = 0;
            foreach ($list as $v) {
                $acc += max(1, (int)$v['weight']);
                if ($roll < $acc) {
                    $out[$sid] = ['variant_id' => (int)$v['id'], 'config' => json_decode((string)$v['config'], true) ?: []];
                    break;
                }
            }
        }
        return $out;
    }

    public function sourcePlans(): array
    {
        $this->ensureTables();
        $rows = Database::instance()->query("SELECT id, slug, name_es, name_en, price_cop, price_usd, features, is_active FROM wwi_plans WHERE site_id = @site_id ORDER BY sort_order ASC")->fetchAll();
        foreach ($rows as &$r) {
            $r['features'] = is_string($r['features'] ?? null) ? (json_decode($r['features'], true) ?: []) : ($r['features'] ?? []);
            $r['price_cop'] = (float)$r['price_cop'];
            $r['price_usd'] = (float)$r['price_usd'];
        }
        return $rows;
    }

    public function sourcePlanSave(int $id, array $d): array
    {
        $sets = [];
        $p = ['id' => $id];
        foreach (['name_es', 'name_en'] as $f) {
            if (array_key_exists($f, $d)) {
                $sets[] = "$f = :$f";
                $p[$f] = substr((string)$d[$f], 0, 150);
            }
        }
        if (array_key_exists('price_cop', $d)) {
            $sets[] = 'price_cop = :price_cop';
            $p['price_cop'] = (float)$d['price_cop'];
        }
        if (array_key_exists('price_usd', $d)) {
            $sets[] = 'price_usd = :price_usd';
            $p['price_usd'] = (float)$d['price_usd'];
        }
        if (array_key_exists('features', $d)) {
            $sets[] = 'features = :features';
            $feats = array_values(array_filter(array_map('trim', (array)$d['features']), function ($x) {
                return $x !== '';
            }));
            $p['features'] = json_encode($feats, JSON_UNESCAPED_UNICODE);
        }
        if (array_key_exists('is_active', $d)) {
            $sets[] = 'is_active = :is_active';
            $p['is_active'] = (int)$d['is_active'];
        }
        if (!$sets) return ['ok' => false, 'message' => 'Nada que actualizar'];
        $stmt = Database::instance()->prepare("UPDATE wwi_plans SET " . implode(', ', $sets) . " WHERE id = :id AND site_id = @site_id");
        $stmt->execute($p);
        return ['ok' => $stmt->rowCount() > 0, 'message' => 'Plan actualizado'];
    }

    public function sourceTemplates(): array
    {
        $this->ensureTables();
        return Database::instance()->query("SELECT t.id, t.slug, t.name_es, t.name_en, t.status, c.slug AS category_slug FROM wwi_templates t LEFT JOIN wwi_template_categories c ON c.id = t.category_id WHERE t.site_id = @site_id ORDER BY t.sort_order ASC")->fetchAll();
    }

    public function sourceTemplateSave(int $id, array $d): array
    {
        $sets = [];
        $p = ['id' => $id];
        foreach (['name_es', 'name_en'] as $f) {
            if (array_key_exists($f, $d)) {
                $sets[] = "$f = :$f";
                $p[$f] = substr((string)$d[$f], 0, 150);
            }
        }
        if (array_key_exists('status', $d) && in_array($d['status'], ['active', 'beta', 'coming_soon', 'deprecated'], true)) {
            $sets[] = 'status = :status';
            $p['status'] = $d['status'];
        }
        if (!$sets) return ['ok' => false, 'message' => 'Nada que actualizar'];
        $stmt = Database::instance()->prepare("UPDATE wwi_templates SET " . implode(', ', $sets) . " WHERE id = :id AND site_id = @site_id");
        $stmt->execute($p);
        return ['ok' => $stmt->rowCount() > 0, 'message' => 'Plantilla actualizada'];
    }

    public function trackEdits(int $sectionId, string $widgetType, ?int $userId, array $fields): void
    {
        if (!$fields) return;
        try {
            $this->ensureTables();
            $db = Database::instance();
            $stmt = $db->prepare("INSERT INTO wwi_edit_events (site_id, user_id, section_id, widget_type, field) VALUES (@site_id, :u, :s, :w, :f)");
            foreach (array_slice(array_values(array_unique($fields)), 0, 30) as $f) {
                $stmt->execute(['u' => $userId, 's' => $sectionId, 'w' => substr($widgetType, 0, 100), 'f' => substr((string)$f, 0, 120)]);
            }
        } catch (\Throwable $e) {
        }
    }

    public function searchSections(int $pageId, string $q): array
    {
        $q = trim($q);
        if ($q === '' || mb_strlen($q) < 2) return [];
        $this->ensureTables();
        $stmt = Database::instance()->prepare("SELECT s.id, s.title, s.subtitle, s.content, s.config, s.widget_type FROM sections s JOIN pages p ON p.id = s.page_id WHERE s.page_id = :p AND p.site_id = @site_id ORDER BY s.sort_order ASC");
        $stmt->execute(['p' => $pageId]);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $fields = [];
            foreach (['title' => 'Título', 'subtitle' => 'Subtítulo', 'content' => 'Contenido'] as $k => $label) {
                if (mb_stripos((string)$row[$k], $q) !== false) $fields[] = ['field' => $k, 'label' => $label, 'snippet' => $this->snippet((string)$row[$k], $q)];
            }
            $cfg = json_decode((string)$row['config'], true);
            if (is_array($cfg)) {
                foreach ($cfg as $k => $v) {
                    if (is_string($v) && mb_stripos($v, $q) !== false) {
                        $fields[] = ['field' => (string)$k, 'label' => (string)$k, 'snippet' => $this->snippet($v, $q)];
                    } elseif (is_array($v)) {
                        foreach ($v as $i => $item) {
                            if (!is_array($item)) continue;
                            foreach ($item as $sk => $sv) {
                                if (is_string($sv) && mb_stripos($sv, $q) !== false) {
                                    $fields[] = ['field' => $k . '[' . $i . '].' . $sk, 'label' => $k . ' #' . ($i + 1) . ' · ' . $sk, 'snippet' => $this->snippet($sv, $q)];
                                }
                            }
                        }
                    }
                }
            }
            if ($fields) $out[] = ['section_id' => (int)$row['id'], 'title' => $row['title'] ?: $row['widget_type'], 'widget' => $row['widget_type'], 'matches' => array_slice($fields, 0, 6)];
        }
        return array_slice($out, 0, 30);
    }

    private function snippet(string $text, string $q): string
    {
        $pos = mb_stripos($text, $q);
        if ($pos === false) return mb_substr($text, 0, 80);
        $start = max(0, $pos - 30);
        return ($start > 0 ? '…' : '') . mb_substr($text, $start, 90) . '…';
    }

    public function telemetry(): array
    {
        $this->ensureTables();
        $db = Database::instance();
        $top = $db->query("SELECT widget_type, field, COUNT(*) AS c FROM wwi_edit_events WHERE site_id = @site_id GROUP BY widget_type, field ORDER BY c DESC LIMIT 12")->fetchAll();
        $widgets = $db->query("SELECT widget_type, COUNT(*) AS c FROM wwi_edit_events WHERE site_id = @site_id GROUP BY widget_type ORDER BY c DESC LIMIT 10")->fetchAll();
        return ['top_fields' => $top, 'widgets' => $widgets];
    }

    public static function sanitizeRichHtml(string $html): string
    {
        $allowed = '<b><strong><i><em><u><a><ul><ol><li><br><p><span>';
        $html = strip_tags($html, $allowed);
        $html = preg_replace('/\s(on\w+)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
        $html = preg_replace('/javascript\s*:/i', '', $html);
        $html = preg_replace('/<a\s+(?![^>]*href=)[^>]*>/i', '<a>', $html);
        return trim((string)$html);
    }

    public function variantTrack(int $id, string $type): bool
    {
        $this->ensureTables();
        $col = $type === 'click' ? 'clicks' : 'views';
        $stmt = Database::instance()->prepare("UPDATE wwi_section_variants SET $col = $col + 1 WHERE id = :id AND site_id = @site_id");
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
