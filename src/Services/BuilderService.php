<?php
namespace App\Services;

use App\Core\Database;
use App\Core\Session;
use App\Widgets\WidgetRegistry;

class BuilderService
{
    public const TABLES = ['wwi_page_rows', 'wwi_page_columns', 'wwi_page_slots', 'wwi_page_blocks', 'wwi_page_revisions', 'wwi_page_trash'];
    public const ATOMIC = ['text', 'image', 'video', 'button', 'icon', 'spacer', 'divider', 'gallery', 'form', 'html'];

    public function ensureTables(): array
    {
        $db = Database::instance();
        $missing = [];
        foreach (self::TABLES as $t) {
            try { $db->query("SELECT 1 FROM $t LIMIT 1"); } catch (\Exception $e) { $missing[] = $t; }
        }
        if (!$missing) return ['ok' => true, 'created' => 0, 'missing' => []];
        $path = ROOT_DIR . '/install/builder.sql';
        if (!file_exists($path)) return ['ok' => false, 'message' => 'Schema not found', 'missing' => $missing];
        $sql = preg_replace('/^\s*--.*$/m', '', (string)file_get_contents($path));
        $created = 0;
        foreach (array_filter(array_map('trim', explode(';', $sql)), fn($s) => $s !== '') as $stmt) {
            try { $db->exec($stmt . ';'); $created++; } catch (\Exception $e) { return ['ok' => false, 'message' => $e->getMessage(), 'missing' => $missing]; }
        }
        return ['ok' => true, 'created' => $created, 'missing' => $missing];
    }

    public function ready(): bool
    {
        try { Database::instance()->query("SELECT 1 FROM wwi_page_rows LIMIT 1"); return true; } catch (\Exception $e) { return false; }
    }

    private function pageInSite(int $pageId): bool
    {
        $stmt = Database::instance()->prepare("SELECT id FROM pages WHERE id = :id AND site_id = @site_id");
        $stmt->execute(['id' => $pageId]);
        return (bool)$stmt->fetchColumn();
    }

    // ── Árbol ──

    public function tree(int $pageId): array
    {
        if (!$this->pageInSite($pageId)) return ['rows' => []];
        $db = Database::instance();
        $rows = $this->rows($pageId);
        if (!$rows) return ['rows' => []];
        $rowIds = array_column($rows, 'id');
        $cols = $this->byParent('wwi_page_columns', 'row_id', $rowIds);
        $colIds = $cols ? array_column($cols, 'id') : [];
        $slots = $colIds ? $this->byParent('wwi_page_slots', 'column_id', $colIds) : [];
        $slotIds = $slots ? array_column($slots, 'id') : [];
        $blocks = $slotIds ? $this->byParent('wwi_page_blocks', 'slot_id', $slotIds) : [];

        $blocksBySlot = [];
        foreach ($blocks as $b) { $b['props'] = json_decode((string)($b['props'] ?? ''), true) ?: []; $b['styles'] = json_decode((string)($b['styles'] ?? ''), true) ?: []; $b['responsive'] = json_decode((string)($b['responsive'] ?? ''), true) ?: []; $b['visibility'] = json_decode((string)($b['visibility'] ?? ''), true) ?: []; $blocksBySlot[$b['slot_id']][] = $b; }
        $slotsByCol = [];
        foreach ($slots as $s) { $s['layout'] = json_decode((string)($s['layout'] ?? ''), true) ?: []; $s['blocks'] = $blocksBySlot[$s['id']] ?? []; $slotsByCol[$s['column_id']][] = $s; }
        $colsByRow = [];
        foreach ($cols as $c) { $c['layout'] = json_decode((string)($c['layout'] ?? ''), true) ?: []; $c['slots'] = $slotsByCol[$c['id']] ?? []; $colsByRow[$c['row_id']][] = $c; }
        foreach ($rows as &$r) { $r['layout'] = json_decode((string)($r['layout'] ?? ''), true) ?: []; $r['columns'] = $colsByRow[$r['id']] ?? []; }
        return ['page_id' => $pageId, 'rows' => $rows];
    }

    private function rows(int $pageId): array
    {
        $stmt = Database::instance()->prepare("SELECT * FROM wwi_page_rows WHERE page_id = :pid AND site_id = @site_id ORDER BY sort_order ASC, id ASC");
        $stmt->execute(['pid' => $pageId]);
        return $stmt->fetchAll();
    }

    private function byParent(string $table, string $col, array $ids): array
    {
        if (!$ids) return [];
        $db = Database::instance();
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare("SELECT * FROM $table WHERE site_id = @site_id AND $col IN ($in) ORDER BY sort_order ASC, id ASC");
        $stmt->execute(array_values($ids));
        return $stmt->fetchAll();
    }

    public function hasLayout(int $pageId): bool
    {
        if (!$this->ready()) return false;
        $stmt = Database::instance()->prepare("SELECT COUNT(*) FROM wwi_page_rows WHERE page_id = :pid AND site_id = @site_id");
        $stmt->execute(['pid' => $pageId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    // ── Nodos ──

    public function createRow(int $pageId, int $position = -1, array $layout = []): int
    {
        if (!$this->pageInSite($pageId)) throw new \InvalidArgumentException('Pagina no encontrada');
        $db = Database::instance();
        $order = $position >= 0 ? $position : (int)$db->query("SELECT COALESCE(MAX(sort_order), -1) + 1 FROM wwi_page_rows WHERE page_id = " . (int)$pageId . " AND site_id = @site_id")->fetchColumn();
        if ($position >= 0) $db->prepare("UPDATE wwi_page_rows SET sort_order = sort_order + 1 WHERE page_id = :pid AND site_id = @site_id AND sort_order >= :pos")->execute(['pid' => $pageId, 'pos' => $position]);
        $db->prepare("INSERT INTO wwi_page_rows (site_id, page_id, sort_order, layout) VALUES (@site_id, :pid, :ord, :lay)")
            ->execute(['pid' => $pageId, 'ord' => $order, 'lay' => json_encode($layout)]);
        return (int)$db->lastInsertId();
    }

    public function createColumn(int $rowId, int $span = 12, int $position = -1, array $layout = []): int
    {
        $db = Database::instance();
        $row = $this->node('wwi_page_rows', $rowId);
        if (!$row) throw new \InvalidArgumentException('Fila no encontrada');
        $span = max(1, min(12, $span));
        $order = $position >= 0 ? $position : (int)$db->query("SELECT COALESCE(MAX(sort_order), -1) + 1 FROM wwi_page_columns WHERE row_id = " . (int)$rowId . " AND site_id = @site_id")->fetchColumn();
        $db->prepare("INSERT INTO wwi_page_columns (site_id, row_id, sort_order, span, layout) VALUES (@site_id, :rid, :ord, :span, :lay)")
            ->execute(['rid' => $rowId, 'ord' => $order, 'span' => $span, 'lay' => json_encode($layout)]);
        $colId = (int)$db->lastInsertId();
        $this->createSlot($colId, 0);
        return $colId;
    }

    public function createSlot(int $columnId, int $position = -1, array $layout = []): int
    {
        $db = Database::instance();
        $col = $this->node('wwi_page_columns', $columnId);
        if (!$col) throw new \InvalidArgumentException('Columna no encontrada');
        $order = $position >= 0 ? $position : (int)$db->query("SELECT COALESCE(MAX(sort_order), -1) + 1 FROM wwi_page_slots WHERE column_id = " . (int)$columnId . " AND site_id = @site_id")->fetchColumn();
        $db->prepare("INSERT INTO wwi_page_slots (site_id, column_id, sort_order, layout) VALUES (@site_id, :cid, :ord, :lay)")
            ->execute(['cid' => $columnId, 'ord' => $order, 'lay' => json_encode($layout)]);
        return (int)$db->lastInsertId();
    }

    public function createBlock(int $slotId, string $type, ?string $brickSlug = null, int $position = -1, array $props = [], ?int $sectionId = null): int
    {
        $db = Database::instance();
        $slot = $this->node('wwi_page_slots', $slotId);
        if (!$slot) throw new \InvalidArgumentException('Slot no encontrado');
        if ($brickSlug !== null && $brickSlug !== '') {
            if (!WidgetRegistry::get($brickSlug)) throw new \InvalidArgumentException('Brick no encontrado: ' . $brickSlug);
            $type = 'brick';
        } elseif (!in_array($type, self::ATOMIC, true)) {
            throw new \InvalidArgumentException('Tipo de bloque invalido: ' . $type);
        }
        $order = $position >= 0 ? $position : (int)$db->query("SELECT COALESCE(MAX(sort_order), -1) + 1 FROM wwi_page_blocks WHERE slot_id = " . (int)$slotId . " AND site_id = @site_id")->fetchColumn();
        if ($position >= 0) $db->prepare("UPDATE wwi_page_blocks SET sort_order = sort_order + 1 WHERE slot_id = :sid AND site_id = @site_id AND sort_order >= :pos")->execute(['sid' => $slotId, 'pos' => $position]);
        $db->prepare("INSERT INTO wwi_page_blocks (site_id, slot_id, sort_order, type, brick_slug, section_id, props, styles, responsive, visibility) VALUES (@site_id, :sid, :ord, :type, :slug, :sec, :props, '{}', '{}', '{}')")
            ->execute(['sid' => $slotId, 'ord' => $order, 'type' => $type, 'slug' => $brickSlug ?: null, 'sec' => $sectionId, 'props' => json_encode($props, JSON_UNESCAPED_UNICODE)]);
        return (int)$db->lastInsertId();
    }

    public function updateBlock(int $id, array $d): void
    {
        $db = Database::instance();
        $sets = [];
        $params = ['id' => $id];
        foreach (['props', 'styles', 'responsive', 'visibility'] as $k) {
            if (array_key_exists($k, $d)) { $sets[] = "$k = :$k"; $params[$k] = json_encode($d[$k], JSON_UNESCAPED_UNICODE); }
        }
        if (array_key_exists('is_active', $d)) { $sets[] = 'is_active = :act'; $params['act'] = (int)$d['is_active']; }
        if (array_key_exists('sort_order', $d)) { $sets[] = 'sort_order = :ord'; $params['ord'] = (int)$d['sort_order']; }
        if (!$sets) return;
        $db->prepare("UPDATE wwi_page_blocks SET " . implode(', ', $sets) . " WHERE id = :id AND site_id = @site_id")->execute($params);
    }

    public function updateNode(string $type, int $id, array $d): void
    {
        $map = ['row' => 'wwi_page_rows', 'column' => 'wwi_page_columns', 'slot' => 'wwi_page_slots'];
        if (!isset($map[$type])) return;
        $table = $map[$type];
        $db = Database::instance();
        $sets = [];
        $params = ['id' => $id];
        if (array_key_exists('layout', $d)) { $sets[] = 'layout = :lay'; $params['lay'] = json_encode($d['layout'], JSON_UNESCAPED_UNICODE); }
        if (array_key_exists('sort_order', $d)) { $sets[] = 'sort_order = :ord'; $params['ord'] = (int)$d['sort_order']; }
        if ($type === 'column' && array_key_exists('span', $d)) { $sets[] = 'span = :span'; $params['span'] = max(1, min(12, (int)$d['span'])); }
        if (array_key_exists('is_active', $d) && $type !== 'slot') { $sets[] = 'is_active = :act'; $params['act'] = (int)$d['is_active']; }
        if (!$sets) return;
        $db->prepare("UPDATE $table SET " . implode(', ', $sets) . " WHERE id = :id AND site_id = @site_id")->execute($params);
    }

    public function deleteNode(string $type, int $id, int $pageId): void
    {
        $db = Database::instance();
        $payload = [];
        if ($type === 'block') {
            $payload = $this->node('wwi_page_blocks', $id) ?: [];
        } elseif ($type === 'column') {
            $payload = $this->columnTree($id);
        } elseif ($type === 'row') {
            $payload = $this->rowTree($id);
        }
        if ($payload) {
            $db->prepare("INSERT INTO wwi_page_trash (site_id, page_id, node_type, node_id, payload, deleted_by) VALUES (@site_id, :pid, :nt, :nid, :pl, :uid)")
                ->execute(['pid' => $pageId, 'nt' => $type, 'nid' => $id, 'pl' => json_encode($payload, JSON_UNESCAPED_UNICODE), 'uid' => Session::userId()]);
        }
        if ($type === 'block') {
            $db->prepare("DELETE FROM wwi_page_blocks WHERE id = :id AND site_id = @site_id")->execute(['id' => $id]);
        } elseif ($type === 'slot') {
            $db->prepare("DELETE FROM wwi_page_blocks WHERE slot_id = :id AND site_id = @site_id")->execute(['id' => $id]);
            $db->prepare("DELETE FROM wwi_page_slots WHERE id = :id AND site_id = @site_id")->execute(['id' => $id]);
        } elseif ($type === 'column') {
            foreach ($this->slotIds($id) as $sid) {
                $db->prepare("DELETE FROM wwi_page_blocks WHERE slot_id = :id AND site_id = @site_id")->execute(['id' => $sid]);
            }
            $db->prepare("DELETE FROM wwi_page_slots WHERE column_id = :id AND site_id = @site_id")->execute(['id' => $id]);
            $db->prepare("DELETE FROM wwi_page_columns WHERE id = :id AND site_id = @site_id")->execute(['id' => $id]);
        } elseif ($type === 'row') {
            foreach ($this->columnIds($id) as $cid) {
                foreach ($this->slotIds($cid) as $sid) {
                    $db->prepare("DELETE FROM wwi_page_blocks WHERE slot_id = :id AND site_id = @site_id")->execute(['id' => $sid]);
                }
                $db->prepare("DELETE FROM wwi_page_slots WHERE column_id = :id AND site_id = @site_id")->execute(['id' => $cid]);
            }
            $db->prepare("DELETE FROM wwi_page_columns WHERE row_id = :id AND site_id = @site_id")->execute(['id' => $id]);
            $db->prepare("DELETE FROM wwi_page_rows WHERE id = :id AND site_id = @site_id")->execute(['id' => $id]);
        }
    }

    public function duplicateBlock(int $id): int
    {
        $b = $this->node('wwi_page_blocks', $id);
        if (!$b) throw new \InvalidArgumentException('Bloque no encontrado');
        $db = Database::instance();
        $db->prepare("UPDATE wwi_page_blocks SET sort_order = sort_order + 1 WHERE slot_id = :sid AND site_id = @site_id AND sort_order > :ord")
            ->execute(['sid' => $b['slot_id'], 'ord' => $b['sort_order']]);
        $db->prepare("INSERT INTO wwi_page_blocks (site_id, slot_id, sort_order, type, brick_slug, section_id, props, styles, responsive, visibility, is_active)
            VALUES (@site_id, :sid, :ord, :type, :slug, :sec, :props, :styles, :resp, :vis, :act)")
            ->execute([
                'sid' => $b['slot_id'], 'ord' => (int)$b['sort_order'] + 1, 'type' => $b['type'], 'slug' => $b['brick_slug'],
                'sec' => $b['section_id'], 'props' => $b['props'], 'styles' => $b['styles'], 'resp' => $b['responsive'],
                'vis' => $b['visibility'], 'act' => $b['is_active'],
            ]);
        return (int)$db->lastInsertId();
    }

    public function moveBlock(int $id, int $slotId, int $position): void
    {
        $b = $this->node('wwi_page_blocks', $id);
        $slot = $this->node('wwi_page_slots', $slotId);
        if (!$b || !$slot) throw new \InvalidArgumentException('Bloque o slot no encontrado');
        $db = Database::instance();
        $db->prepare("UPDATE wwi_page_blocks SET sort_order = sort_order - 1 WHERE slot_id = :sid AND site_id = @site_id AND sort_order > :ord")
            ->execute(['sid' => $b['slot_id'], 'ord' => $b['sort_order']]);
        $db->prepare("UPDATE wwi_page_blocks SET sort_order = sort_order + 1 WHERE slot_id = :sid AND site_id = @site_id AND sort_order >= :pos")
            ->execute(['sid' => $slotId, 'pos' => $position]);
        $db->prepare("UPDATE wwi_page_blocks SET slot_id = :sid, sort_order = :pos WHERE id = :id AND site_id = @site_id")
            ->execute(['sid' => $slotId, 'pos' => $position, 'id' => $id]);
    }

    public function reorder(string $type, array $items): void
    {
        $map = ['row' => 'wwi_page_rows', 'column' => 'wwi_page_columns', 'slot' => 'wwi_page_slots', 'block' => 'wwi_page_blocks'];
        if (!isset($map[$type])) return;
        $table = $map[$type];
        $db = Database::instance();
        $stmt = $db->prepare("UPDATE $table SET sort_order = :ord WHERE id = :id AND site_id = @site_id");
        foreach ($items as $i => $id) $stmt->execute(['ord' => (int)$i, 'id' => (int)$id]);
    }

    private function node(string $table, int $id): ?array
    {
        $stmt = Database::instance()->prepare("SELECT * FROM $table WHERE id = :id AND site_id = @site_id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function columnIds(int $rowId): array
    {
        $stmt = Database::instance()->prepare("SELECT id FROM wwi_page_columns WHERE row_id = :id AND site_id = @site_id");
        $stmt->execute(['id' => $rowId]);
        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    private function slotIds(int $columnId): array
    {
        $stmt = Database::instance()->prepare("SELECT id FROM wwi_page_slots WHERE column_id = :id AND site_id = @site_id");
        $stmt->execute(['id' => $columnId]);
        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    private function rowTree(int $id): array
    {
        $row = $this->node('wwi_page_rows', $id) ?: [];
        $cols = [];
        foreach ($this->columnIds($id) as $cid) $cols[] = $this->columnTree($cid);
        $row['columns'] = $cols;
        return $row;
    }

    private function columnTree(int $id): array
    {
        $col = $this->node('wwi_page_columns', $id) ?: [];
        $slots = [];
        foreach ($this->slotIds($id) as $sid) {
            $slot = $this->node('wwi_page_slots', $sid) ?: [];
            $stmt = Database::instance()->prepare("SELECT * FROM wwi_page_blocks WHERE slot_id = :id AND site_id = @site_id ORDER BY sort_order ASC, id ASC");
            $stmt->execute(['id' => $sid]);
            $slot['blocks'] = $stmt->fetchAll();
            $slots[] = $slot;
        }
        $col['slots'] = $slots;
        return $col;
    }

    // ── Conversión desde secciones existentes ──

    public function convertPage(int $pageId): array
    {
        if (!$this->ready()) $this->ensureTables();
        if (!$this->pageInSite($pageId)) throw new \InvalidArgumentException('Pagina no encontrada');
        if ($this->hasLayout($pageId)) throw new \RuntimeException('La pagina ya tiene layout de bloques');
        $this->snapshot($pageId, 'Antes de convertir a filas');
        $db = Database::instance();
        $stmt = $db->prepare("SELECT * FROM sections WHERE page_id = :pid ORDER BY sort_order ASC, id ASC");
        $stmt->execute(['pid' => $pageId]);
        $sections = $stmt->fetchAll();
        $count = 0;
        foreach ($sections as $s) {
            $rowId = $this->createRow($pageId, -1, ['source_section' => (int)$s['id']]);
            $colId = $this->createColumn($rowId, 12);
            $slotStmt = $db->prepare("SELECT id FROM wwi_page_slots WHERE column_id = :cid AND site_id = @site_id ORDER BY sort_order ASC LIMIT 1");
            $slotStmt->execute(['cid' => $colId]);
            $slotId = (int)$slotStmt->fetchColumn();
            $widget = (string)($s['widget_type'] ?? '');
            if ($widget !== '' && WidgetRegistry::get($widget)) {
                $this->createBlock($slotId, 'brick', $widget, -1, json_decode((string)($s['config'] ?? '{}'), true) ?: [], (int)$s['id']);
            } else {
                $this->createBlock($slotId, 'html', null, -1, ['html' => (string)($s['content'] ?? '')], (int)$s['id']);
            }
            $count++;
        }
        return ['ok' => true, 'rows' => $count];
    }

    public function snapshot(int $pageId, string $label = ''): int
    {
        $tree = $this->tree($pageId);
        $db = Database::instance();
        $db->prepare("INSERT INTO wwi_page_revisions (site_id, page_id, user_id, username, label, tree) VALUES (@site_id, :pid, :uid, :un, :lb, :tr)")
            ->execute(['pid' => $pageId, 'uid' => Session::userId(), 'un' => (string)(Session::user()['username'] ?? ''), 'lb' => $label, 'tr' => json_encode($tree, JSON_UNESCAPED_UNICODE)]);
        return (int)$db->lastInsertId();
    }

    public function revisions(int $pageId): array
    {
        $stmt = Database::instance()->prepare("SELECT id, user_id, username, label, created_at FROM wwi_page_revisions WHERE page_id = :pid AND site_id = @site_id ORDER BY id DESC LIMIT 30");
        $stmt->execute(['pid' => $pageId]);
        return $stmt->fetchAll();
    }

    public function restoreRevision(int $revisionId): array
    {
        $db = Database::instance();
        $stmt = $db->prepare("SELECT * FROM wwi_page_revisions WHERE id = :id AND site_id = @site_id LIMIT 1");
        $stmt->execute(['id' => $revisionId]);
        $rev = $stmt->fetch();
        if (!$rev) throw new \InvalidArgumentException('Revision no encontrada');
        $pageId = (int)$rev['page_id'];
        $this->snapshot($pageId, 'Antes de restaurar');
        $tree = json_decode((string)$rev['tree'], true) ?: [];
        $this->clearPage($pageId);
        foreach (($tree['rows'] ?? []) as $row) {
            $rowId = $this->createRow($pageId, -1, $row['layout'] ?? []);
            foreach (($row['columns'] ?? []) as $col) {
                $colId = $this->createColumn($rowId, (int)($col['span'] ?? 12), -1, $col['layout'] ?? []);
                $slotStmt = $db->prepare("SELECT id FROM wwi_page_slots WHERE column_id = :cid AND site_id = @site_id ORDER BY sort_order ASC LIMIT 1");
                $slotStmt->execute(['cid' => $colId]);
                $slotId = (int)$slotStmt->fetchColumn();
                foreach (($col['slots'] ?? []) as $slot) {
                    foreach (($slot['blocks'] ?? []) as $b) {
                        $this->createBlock($slotId, (string)$b['type'], $b['brick_slug'] ?? null, -1, is_array($b['props'] ?? null) ? $b['props'] : (json_decode((string)($b['props'] ?? ''), true) ?: []), $b['section_id'] ?? null);
                    }
                }
            }
        }
        return ['ok' => true, 'page_id' => $pageId];
    }

    public function clearPage(int $pageId): void
    {
        foreach ($this->rows($pageId) as $row) {
            $this->deleteNode('row', (int)$row['id'], $pageId);
        }
    }

    public function trash(int $pageId): array
    {
        $stmt = Database::instance()->prepare("SELECT id, node_type, node_id, deleted_at FROM wwi_page_trash WHERE page_id = :pid AND site_id = @site_id ORDER BY id DESC LIMIT 50");
        $stmt->execute(['pid' => $pageId]);
        return $stmt->fetchAll();
    }

    public function restoreTrash(int $id): array
    {
        $db = Database::instance();
        $stmt = $db->prepare("SELECT * FROM wwi_page_trash WHERE id = :id AND site_id = @site_id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $item = $stmt->fetch();
        if (!$item) throw new \InvalidArgumentException('Elemento no encontrado');
        $pageId = (int)$item['page_id'];
        $payload = json_decode((string)$item['payload'], true) ?: [];
        if ($item['node_type'] === 'block' && !empty($payload['slot_id'])) {
            $this->createBlock((int)$payload['slot_id'], (string)$payload['type'], $payload['brick_slug'] ?? null, (int)($payload['sort_order'] ?? -1), json_decode((string)($payload['props'] ?? ''), true) ?: [], $payload['section_id'] ?? null);
        } elseif ($item['node_type'] === 'row' && !empty($payload['id'])) {
            $rowId = $this->createRow($pageId, (int)($payload['sort_order'] ?? -1), json_decode((string)($payload['layout'] ?? ''), true) ?: []);
            foreach (($payload['columns'] ?? []) as $col) {
                $colId = $this->createColumn($rowId, (int)($col['span'] ?? 12), -1, json_decode((string)($col['layout'] ?? ''), true) ?: []);
                $slotStmt = $db->prepare("SELECT id FROM wwi_page_slots WHERE column_id = :cid AND site_id = @site_id ORDER BY sort_order ASC LIMIT 1");
                $slotStmt->execute(['cid' => $colId]);
                $slotId = (int)$slotStmt->fetchColumn();
                foreach (($col['slots'] ?? []) as $slot) {
                    foreach (($slot['blocks'] ?? []) as $b) {
                        $this->createBlock($slotId, (string)$b['type'], $b['brick_slug'] ?? null, -1, json_decode((string)($b['props'] ?? ''), true) ?: [], $b['section_id'] ?? null);
                    }
                }
            }
        }
        $db->prepare("DELETE FROM wwi_page_trash WHERE id = :id AND site_id = @site_id")->execute(['id' => $id]);
        return ['ok' => true, 'page_id' => $pageId];
    }

    // ── Render ──

    public function renderPage(int $pageId): string
    {
        $tree = $this->tree($pageId);
        if (!$tree['rows']) return '';
        $html = $this->baseCss();
        foreach ($tree['rows'] as $row) {
            if ((int)$row['is_active'] !== 1) continue;
            $rowStyle = $this->styleAttr($row['layout'] ?? [], ['background', 'padding_top', 'padding_bottom', 'min_height']);
            $html .= '<div class="wwi-b-row" data-row="' . (int)$row['id'] . '"' . $rowStyle . '>';
            $html .= '<div class="wwi-b-row-inner">';
            foreach ($row['columns'] as $col) {
                $colStyle = 'flex:0 0 ' . (round(((int)$col['span'] / 12) * 100, 4)) . '%;max-width:' . (round(((int)$col['span'] / 12) * 100, 4)) . '%';
                $html .= '<div class="wwi-b-col" data-col="' . (int)$col['id'] . '" data-span="' . (int)$col['span'] . '" style="' . $colStyle . '">';
                foreach ($col['slots'] as $slot) {
                    $html .= '<div class="wwi-b-slot" data-slot="' . (int)$slot['id'] . '">';
                    foreach ($slot['blocks'] as $block) {
                        $html .= $this->renderBlock($block);
                    }
                    $html .= '</div>';
                }
                $html .= '</div>';
            }
            $html .= '</div></div>';
        }
        return $html;
    }

    public function normalizeRow(int $rowId): void
    {
        $db = Database::instance();
        $stmt = $db->prepare("SELECT id, span FROM wwi_page_columns WHERE row_id = :rid AND site_id = @site_id ORDER BY sort_order ASC, id ASC");
        $stmt->execute(['rid' => $rowId]);
        $cols = $stmt->fetchAll();
        $n = count($cols);
        if ($n < 1) return;
        if ($n === 1) {
            $db->prepare("UPDATE wwi_page_columns SET span = 12 WHERE id = :id AND site_id = @site_id")->execute(['id' => (int)$cols[0]['id']]);
            return;
        }
        $total = 0;
        foreach ($cols as $c) $total += max(1, (int)$c['span']);
        $acc = 0;
        $upd = $db->prepare("UPDATE wwi_page_columns SET span = :span WHERE id = :id AND site_id = @site_id");
        foreach ($cols as $i => $c) {
            if ($i === $n - 1) {
                $span = 12 - $acc;
            } else {
                $span = (int)floor((max(1, (int)$c['span']) / max(1, $total)) * 12);
                $span = max(1, min(12 - ($n - 1 - $i) - $acc, $span));
            }
            $span = max(1, min(12, $span));
            $acc += $span;
            $upd->execute(['span' => $span, 'id' => (int)$c['id']]);
        }
    }

    private static bool $baseCssPrinted = false;

    private function baseCss(): string
    {
        if (self::$baseCssPrinted) return '';
        self::$baseCssPrinted = true;
        return '<style id="wwi-b-css">'
            . '.wwi-b-row{width:100%;position:relative}'
            . '.wwi-b-row-inner{display:flex;flex-wrap:wrap;gap:26px;max-width:1200px;margin:0 auto;padding:0 24px;align-items:flex-start}'
            . '.wwi-b-col{min-width:0;flex-grow:0;flex-shrink:0}'
            . '.wwi-b-slot{display:flex;flex-direction:column;gap:18px}'
            . '.wwi-b-block{min-width:0}'
            . '.wwi-b-text>:first-child{margin-top:0}'
            . '.wwi-b-text>:last-child{margin-bottom:0}'
            . '.wwi-b-text h1,.wwi-b-text h2,.wwi-b-text h3{margin:0 0 12px;line-height:1.15;letter-spacing:-.02em}'
            . '.wwi-b-text p{margin:0 0 14px;line-height:1.7}'
            . '.wwi-b-text ul,.wwi-b-text ol{margin:0 0 14px 20px;line-height:1.7}'
            . '.wwi-b-figure{margin:0}'
            . '.wwi-b-figure img{max-width:100%;height:auto;display:block;border-radius:12px}'
            . '.wwi-b-figure figcaption{font-size:12px;opacity:.7;margin-top:8px}'
            . '.wwi-b-divider{border:0;border-top:1px solid rgba(128,128,150,.25);margin:14px 0}'
            . '.wwi-b-video{width:100%}'
            . '.wwi-b-play{cursor:pointer}'
            . '.wwi-b-block .btn{display:inline-block}'
            . '@media(max-width:900px){.wwi-b-row-inner{gap:18px}.wwi-b-col{flex:1 1 100%!important;max-width:100%!important}}'
            . '@media(min-width:901px){.wwi-hide-desktop{display:none!important}}'
            . '@media(min-width:721px) and (max-width:900px){.wwi-hide-tablet{display:none!important}}'
            . '@media(max-width:720px){.wwi-hide-mobile{display:none!important}}'
            . '</style>';
    }

    private function renderBlock(array $b): string
    {
        if ((int)$b['is_active'] !== 1) return '';
        $vis = $b['visibility'] ?? [];
        $cls = 'wwi-b-block';
        if (!empty($vis['hide_desktop'])) $cls .= ' wwi-hide-desktop';
        if (!empty($vis['hide_tablet'])) $cls .= ' wwi-hide-tablet';
        if (!empty($vis['hide_mobile'])) $cls .= ' wwi-hide-mobile';
        $attrs = ' class="' . $cls . '" data-block="' . (int)$b['id'] . '" data-type="' . htmlspecialchars((string)$b['type']) . '"' . (!empty($b['brick_slug']) ? ' data-brick="' . htmlspecialchars((string)$b['brick_slug']) . '"' : '');
        $props = $b['props'] ?? [];
        $inner = '';
        switch ($b['type']) {
            case 'brick':
                $inner = WidgetRegistry::get((string)$b['brick_slug']) ? WidgetRegistry::render((string)$b['brick_slug'], is_array($props) ? $props : []) : '';
                break;
            case 'text':
                $inner = '<div class="wwi-b-text">' . $this->sanitizeHtml((string)($props['html'] ?? '')) . '</div>';
                break;
            case 'image':
                $url = $this->safeUrl((string)($props['url'] ?? ''));
                if ($url !== '') {
                    $alt = htmlspecialchars((string)($props['alt'] ?? ''));
                    $inner = '<figure class="wwi-b-figure"><img src="' . htmlspecialchars($url) . '" alt="' . $alt . '" loading="lazy" decoding="async"/>'
                        . (!empty($props['caption']) ? '<figcaption>' . htmlspecialchars((string)$props['caption']) . '</figcaption>' : '') . '</figure>';
                }
                break;
            case 'button':
                $label = htmlspecialchars((string)($props['label'] ?? 'Botón'));
                $href = htmlspecialchars($this->safeUrl((string)($props['href'] ?? '#')));
                $style = in_array($props['style'] ?? 'primary', ['primary', 'secondary', 'ghost'], true) ? $props['style'] : 'primary';
                $target = !empty($props['new_tab']) ? ' target="_blank" rel="noopener"' : '';
                $inner = '<a class="btn btn-' . $style . '" href="' . $href . '"' . $target . '>' . $label . '</a>';
                break;
            case 'video':
                $inner = $this->renderVideo($props);
                break;
            case 'divider':
                $inner = '<hr class="wwi-b-divider"/>';
                break;
            case 'spacer':
                $h = max(4, min(240, (int)($props['height'] ?? 40)));
                $inner = '<div class="wwi-b-spacer" style="height:' . $h . 'px"></div>';
                break;
            case 'html':
            default:
                $inner = $this->sanitizeHtml((string)($props['html'] ?? ''));
                break;
        }
        $styleAttr = $this->styleAttr($b['styles'] ?? [], ['text_align', 'padding', 'margin', 'max_width', 'background']);
        return '<div' . $attrs . $styleAttr . '>' . $inner . '</div>';
    }

    private function renderVideo(array $p): string
    {
        $url = $this->safeUrl((string)($p['url'] ?? ''));
        if ($url === '') return '';
        if (preg_match('#(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{6,})#i', $url, $m)) {
            $poster = !empty($p['poster']) ? $this->safeUrl((string)$p['poster']) : 'https://i.ytimg.com/vi/' . $m[1] . '/hqdefault.jpg';
            return '<div class="wwi-b-video" data-src="' . htmlspecialchars($m[1]) . '" data-provider="youtube" style="position:relative;aspect-ratio:16/9;border-radius:12px;overflow:hidden;background:#000">'
                . '<img src="' . htmlspecialchars($poster) . '" alt="" loading="lazy" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:.85"/>'
                . '<button type="button" class="wwi-b-play" aria-label="Reproducir" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.25);border:0;cursor:pointer;color:#fff;font-size:34px">▶</button></div>';
        }
        if (preg_match('#vimeo\.com/(?:video/)?(\d{6,})#i', $url, $m)) {
            return '<div class="wwi-b-video" data-src="' . htmlspecialchars($m[1]) . '" data-provider="vimeo" style="position:relative;aspect-ratio:16/9;border-radius:12px;overflow:hidden;background:#000">'
                . '<button type="button" class="wwi-b-play" aria-label="Reproducir" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.25);border:0;cursor:pointer;color:#fff;font-size:34px">▶</button></div>';
        }
        return '<video class="wwi-b-video" src="' . htmlspecialchars($url) . '" controls playsinline preload="metadata" style="width:100%;border-radius:12px"></video>';
    }

    private function styleAttr(array $styles, array $allowed): string
    {
        $css = [];
        if (!empty($styles['text_align']) && in_array($styles['text_align'], ['left', 'center', 'right'], true)) $css[] = 'text-align:' . $styles['text_align'];
        if (!empty($styles['max_width'])) $css[] = 'max-width:' . (int)$styles['max_width'] . 'px';
        if (!empty($styles['padding'])) $css[] = 'padding:' . (int)$styles['padding'] . 'px';
        if (!empty($styles['margin'])) $css[] = 'margin:' . (int)$styles['margin'] . 'px auto';
        if (!empty($styles['background'])) $css[] = 'background:' . $this->safeColor((string)$styles['background']);
        if (!empty($styles['padding_top'])) $css[] = 'padding-top:' . (int)$styles['padding_top'] . 'px';
        if (!empty($styles['padding_bottom'])) $css[] = 'padding-bottom:' . (int)$styles['padding_bottom'] . 'px';
        if (!empty($styles['min_height'])) $css[] = 'min-height:' . (int)$styles['min_height'] . 'px';
        return $css ? ' style="' . htmlspecialchars(implode(';', $css)) . '"' : '';
    }

    private function safeColor(string $c): string
    {
        return preg_match('/^#[0-9a-fA-F]{3,8}$/', $c) ? $c : 'transparent';
    }

    public function safeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') return '';
        if (str_starts_with($url, '/') || str_starts_with($url, '#')) return $url;
        return preg_match('#^https?://#i', $url) ? $url : '';
    }

    public function sanitizeHtml(string $html): string
    {
        $html = preg_replace('#<(script|style|iframe|object|embed|form|input|link|meta)[^>]*>.*?</\1>#is', '', $html) ?? '';
        $html = preg_replace('#<(script|style|iframe|object|embed|form|input|link|meta)[^>]*/?>#is', '', $html) ?? '';
        $html = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';
        $html = preg_replace('/\s(href|src)\s*=\s*("|\')?\s*javascript:[^"\'>\s]*("|\')?/i', '', $html) ?? '';
        return $html;
    }
}
