<?php
namespace App\Services;

use App\Core\Database;

/**
 * HeroService — SONIKA HERO BUILDER (Fase 3: base).
 * Slides de 2 zonas (izquierda/derecha) con tipos de contenido extensibles.
 * Borradores/activo, orden, duplicar, publicar, versiones. Multi-tenant (@site_id).
 */
class HeroService
{
    public const CONTENT_TYPES = [
        'text' => ['label' => 'Texto comercial', 'available' => true],
        'image' => ['label' => 'Imagen personalizada', 'available' => true],
        'video' => ['label' => 'Video personalizado', 'available' => true],
        'product' => ['label' => 'Producto del catálogo', 'available' => true],
        'combo' => ['label' => 'Combo o promoción', 'available' => true],
        'guitar' => ['label' => 'Instrumento: Guitarra eléctrica', 'available' => true, 'engine' => 'guitar'],
        'piano' => ['label' => 'Instrumento: Piano Casio', 'available' => false],
        'bass' => ['label' => 'Instrumento: Bajo', 'available' => false],
        'drums' => ['label' => 'Instrumento: Batería', 'available' => false],
        'tumbadora' => ['label' => 'Instrumento: Tumbadora', 'available' => false],
        'timbal' => ['label' => 'Instrumento: Timbal', 'available' => false],
        'sax' => ['label' => 'Instrumento: Saxofón', 'available' => false],
    ];

    private static bool $ensured = false;

    public function ensureTables(): array
    {
        if (self::$ensured) return ['ok' => true];
        $f = dirname(__DIR__, 2) . '/install/hero.sql';
        if (!is_file($f)) return ['ok' => false];
        $sql = preg_replace('/^\s*--.*$/m', '', (string)file_get_contents($f));
        $db = Database::instance();
        $n = 0;
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $s) {
            if ($s === '') continue;
            try { $db->exec($s); $n++; } catch (\Throwable $e) { }
        }
        self::$ensured = true;
        return ['ok' => true, 'statements' => $n];
    }

    public function contentTypes(): array { return self::CONTENT_TYPES; }

    public function all(): array
    {
        $this->ensureTables();
        $st = Database::instance()->prepare("SELECT * FROM wwi_hero_slides WHERE site_id = @site_id ORDER BY sort_order ASC, id ASC");
        $st->execute();
        return array_map([$this, 'hydrate'], $st->fetchAll());
    }

    public function published(): array
    {
        $this->ensureTables();
        $st = Database::instance()->prepare("SELECT * FROM wwi_hero_slides WHERE site_id = @site_id AND status = 'active' ORDER BY sort_order ASC, id ASC");
        $st->execute();
        return array_map([$this, 'hydrate'], $st->fetchAll());
    }

    public function get(int $id): ?array
    {
        $st = Database::instance()->prepare("SELECT * FROM wwi_hero_slides WHERE id = :id AND site_id = @site_id LIMIT 1");
        $st->execute(['id' => $id]);
        $r = $st->fetch();
        return $r ? $this->hydrate($r) : null;
    }

    public function save(array $d): array
    {
        $this->ensureTables();
        $db = Database::instance();
        $id = (int)($d['id'] ?? 0);
        $name = trim((string)($d['name'] ?? 'Slide')) ?: 'Slide';
        $status = (($d['status'] ?? 'draft') === 'active') ? 'active' : 'draft';
        $layout = in_array((string)($d['layout'] ?? 'split'), ['split', 'full', 'left', 'right'], true) ? (string)$d['layout'] : 'split';
        $lt = array_key_exists((string)($d['left_type'] ?? ''), self::CONTENT_TYPES) ? (string)$d['left_type'] : 'text';
        $rt = array_key_exists((string)($d['right_type'] ?? ''), self::CONTENT_TYPES) ? (string)$d['right_type'] : 'text';
        if ($status === 'active' && (!self::CONTENT_TYPES[$lt]['available'] || !self::CONTENT_TYPES[$rt]['available'])) $status = 'draft';
        $ld = json_encode($d['left_data'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $rd = json_encode($d['right_data'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $de = json_encode($d['design'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($id > 0) {
            $cur = $this->get($id);
            if ($cur) $this->snapshot($id, $cur, (string)($d['actor'] ?? ''));
            $db->prepare("UPDATE wwi_hero_slides SET name=:n,status=:s,layout=:l,left_type=:lt,left_data=:ld,right_type=:rt,right_data=:rd,design=:de WHERE id=:id AND site_id=@site_id")
                ->execute(['n' => $name, 's' => $status, 'l' => $layout, 'lt' => $lt, 'ld' => $ld, 'rt' => $rt, 'rd' => $rd, 'de' => $de, 'id' => $id]);
        } else {
            $mx = (int)$db->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM wwi_hero_slides WHERE site_id = @site_id")->fetchColumn();
            $db->prepare("INSERT INTO wwi_hero_slides (site_id,uuid,name,status,sort_order,layout,left_type,left_data,right_type,right_data,design) VALUES (@site_id,:u,:n,:s,:o,:l,:lt,:ld,:rt,:rd,:de)")
                ->execute(['u' => $this->uuid4(), 'n' => $name, 's' => $status, 'o' => $mx, 'l' => $layout, 'lt' => $lt, 'ld' => $ld, 'rt' => $rt, 'rd' => $rd, 'de' => $de]);
            $id = (int)$db->lastInsertId();
        }
        return ['ok' => true, 'slide' => $this->get($id)];
    }

    public function duplicate(int $id): array
    {
        $s = $this->get($id);
        if (!$s) return ['ok' => false, 'message' => 'No existe'];
        $db = Database::instance();
        $db->prepare("INSERT INTO wwi_hero_slides (site_id,uuid,name,status,sort_order,layout,left_type,left_data,right_type,right_data,design) VALUES (@site_id,:u,:n,'draft',:o,:l,:lt,:ld,:rt,:rd,:de)")
            ->execute(['u' => $this->uuid4(), 'n' => $s['name'] . ' (copia)', 'o' => $s['sort_order'] + 1, 'l' => $s['layout'], 'lt' => $s['left_type'], 'ld' => json_encode($s['left_data'], JSON_UNESCAPED_UNICODE), 'rt' => $s['right_type'], 'rd' => json_encode($s['right_data'], JSON_UNESCAPED_UNICODE), 'de' => json_encode($s['design'], JSON_UNESCAPED_UNICODE)]);
        return ['ok' => true, 'id' => (int)$db->lastInsertId()];
    }

    public function delete(int $id): array
    {
        Database::instance()->prepare("DELETE FROM wwi_hero_slides WHERE id = :id AND site_id = @site_id")->execute(['id' => $id]);
        return ['ok' => true];
    }

    public function reorder(array $ids): array
    {
        $db = Database::instance();
        $st = $db->prepare("UPDATE wwi_hero_slides SET sort_order = :o WHERE id = :id AND site_id = @site_id");
        $i = 0;
        foreach ($ids as $id) { $st->execute(['o' => $i++, 'id' => (int)$id]); }
        return ['ok' => true];
    }

    public function publish(int $id): array
    {
        $s = $this->get($id);
        if (!$s) return ['ok' => false, 'message' => 'No existe'];
        foreach ([$s['left_type'], $s['right_type']] as $t) {
            if (!self::CONTENT_TYPES[$t]['available']) return ['ok' => false, 'message' => 'No se puede publicar: el motor de “' . self::CONTENT_TYPES[$t]['label'] . '” no está implementado.'];
        }
        $this->snapshot($id, $s, 'publish');
        Database::instance()->prepare("UPDATE wwi_hero_slides SET status='active' WHERE id = :id AND site_id = @site_id")->execute(['id' => $id]);
        return ['ok' => true];
    }

    public function versions(int $slideId): array
    {
        $st = Database::instance()->prepare("SELECT id, created_at, created_by FROM wwi_hero_versions WHERE slide_id = :s AND site_id = @site_id ORDER BY id DESC LIMIT 30");
        $st->execute(['s' => $slideId]);
        return $st->fetchAll();
    }

    public function restore(int $versionId): array
    {
        $st = Database::instance()->prepare("SELECT slide_id, data FROM wwi_hero_versions WHERE id = :id AND site_id = @site_id LIMIT 1");
        $st->execute(['id' => $versionId]);
        $v = $st->fetch();
        if (!$v) return ['ok' => false, 'message' => 'Versión no encontrada'];
        $x = json_decode((string)$v['data'], true) ?: [];
        $x['id'] = (int)$v['slide_id'];
        return $this->save($x);
    }

    private function snapshot(int $id, array $slide, string $actor): void
    {
        try {
            Database::instance()->prepare("INSERT INTO wwi_hero_versions (site_id, slide_id, data, created_by) VALUES (@site_id, :s, :d, :a)")
                ->execute(['s' => $id, 'd' => json_encode($slide, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'a' => $actor]);
        } catch (\Throwable $e) {
        }
    }

    private function hydrate(array $r): array
    {
        foreach (['left_data', 'right_data', 'design'] as $k) $r[$k] = json_decode((string)($r[$k] ?? ''), true) ?: [];
        $r['id'] = (int)$r['id'];
        $r['sort_order'] = (int)$r['sort_order'];
        return $r;
    }

    private function uuid4(): string
    {
        $d = random_bytes(16);
        $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
        $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
    }
}
