<?php
namespace App\Services;

use App\Core\Database;

/**
 * BrickShowcaseService — capturas (slide) de un brick en funcionamiento.
 * Catálogo GLOBAL (sin site_id). Solo lectura pública; la escritura se restringe
 * a admin/superadmin en el controlador. Referencia medios de la Media Library.
 */
class BrickShowcaseService
{
    private static bool $ensured = false;
    public const DEVICES = ['all', 'desktop', 'tablet', 'mobile'];
    public const TYPES = ['image', 'video'];
    public const AVAIL = ['available', 'in-development', 'future', 'concept', 'prototype'];
    public const AVAIL_LABELS = ['available' => 'Disponible', 'in-development' => 'En desarrollo', 'future' => 'Futuro', 'concept' => 'Concepto', 'prototype' => 'Prototipo'];

    public function ensureTables(): array
    {
        if (self::$ensured) return ['ok' => true, 'cached' => true];
        Database::instance()->exec("CREATE TABLE IF NOT EXISTS brick_showcase (
            id INT AUTO_INCREMENT PRIMARY KEY, slug VARCHAR(120) NOT NULL, kind VARCHAR(20) NOT NULL DEFAULT 'widget',
            media VARCHAR(500) NOT NULL, media_type ENUM('image','video') NOT NULL DEFAULT 'image',
            device ENUM('all','desktop','tablet','mobile') NOT NULL DEFAULT 'all',
            caption VARCHAR(255) NULL, alt VARCHAR(255) NULL, demo_url VARCHAR(500) NULL,
            availability ENUM('available','in-development','future','concept','prototype') NULL,
            is_cover TINYINT NOT NULL DEFAULT 0, sort_order INT NOT NULL DEFAULT 0, version VARCHAR(40) NULL,
            created_by INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY idx_slug (slug, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        self::$ensured = true;
        return ['ok' => true, 'cached' => false];
    }

    public function list(string $slug): array
    {
        $this->ensureTables();
        $st = Database::instance()->prepare('SELECT * FROM brick_showcase WHERE slug = ? ORDER BY is_cover DESC, sort_order ASC, id ASC');
        $st->execute([$slug]);
        return $st->fetchAll();
    }

    public function count(string $slug): int
    {
        $this->ensureTables();
        $st = Database::instance()->prepare('SELECT COUNT(*) FROM brick_showcase WHERE slug = ?');
        $st->execute([$slug]);
        return (int)$st->fetchColumn();
    }

    private function cleanUrl(string $u): string
    {
        $u = trim($u);
        if ($u === '' || mb_strlen($u) > 500) return '';
        return preg_match('~^(https?://|/)~i', $u) ? $u : '';
    }

    public function add(array $d): array
    {
        $this->ensureTables();
        $slug = preg_replace('/[^a-z0-9_-]/i', '', trim((string)($d['slug'] ?? '')));
        $media = $this->cleanUrl((string)($d['media'] ?? ''));
        if ($slug === '') return ['ok' => false, 'message' => 'Falta el brick.'];
        if ($media === '') return ['ok' => false, 'message' => 'Selecciona una imagen o video (URL de la Biblioteca de Media).'];
        $type = in_array((string)($d['media_type'] ?? 'image'), self::TYPES, true) ? (string)$d['media_type'] : 'image';
        $device = in_array((string)($d['device'] ?? 'all'), self::DEVICES, true) ? (string)$d['device'] : 'all';
        $avail = in_array((string)($d['availability'] ?? ''), self::AVAIL, true) ? (string)$d['availability'] : null;
        $db = Database::instance();
        $n = $this->count($slug);
        $db->prepare('INSERT INTO brick_showcase (slug, kind, media, media_type, device, caption, alt, demo_url, availability, is_cover, sort_order, version, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
           ->execute([
                $slug, (string)($d['kind'] ?? 'widget'), $media, $type, $device,
                (mb_substr(trim((string)($d['caption'] ?? '')), 0, 255) ?: null),
                (mb_substr(trim((string)($d['alt'] ?? '')), 0, 255) ?: null),
                ($this->cleanUrl((string)($d['demo_url'] ?? '')) ?: null),
                $avail, $n === 0 ? 1 : 0, $n,
                (mb_substr(trim((string)($d['version'] ?? '')), 0, 40) ?: null),
                ((int)($d['created_by'] ?? 0) ?: null),
           ]);
        return ['ok' => true, 'id' => (int)$db->lastInsertId()];
    }

    public function update(int $id, array $d): array
    {
        $this->ensureTables();
        $fields = [];
        $params = [];
        foreach (['caption', 'alt'] as $f) {
            if (array_key_exists($f, $d)) { $fields[] = "$f = ?"; $params[] = (mb_substr(trim((string)$d[$f]), 0, 255) ?: null); }
        }
        if (array_key_exists('media', $d)) {
            $u = $this->cleanUrl((string)$d['media']);
            if ($u !== '') { $fields[] = 'media = ?'; $params[] = $u; }
        }
        foreach (['device' => self::DEVICES, 'media_type' => self::TYPES, 'availability' => self::AVAIL] as $f => $allowed) {
            if (array_key_exists($f, $d) && in_array((string)$d[$f], $allowed, true)) { $fields[] = "$f = ?"; $params[] = (string)$d[$f]; }
        }
        if (array_key_exists('demo_url', $d)) { $fields[] = 'demo_url = ?'; $params[] = ($this->cleanUrl((string)$d['demo_url']) ?: null); }
        if (array_key_exists('version', $d)) { $fields[] = 'version = ?'; $params[] = (mb_substr(trim((string)$d['version']), 0, 40) ?: null); }
        if (!$fields) return ['ok' => true];
        $params[] = $id;
        Database::instance()->prepare('UPDATE brick_showcase SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);
        return ['ok' => true];
    }

    public function delete(int $id): array
    {
        $this->ensureTables();
        $db = Database::instance();
        $st = $db->prepare('SELECT slug, is_cover FROM brick_showcase WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch();
        $db->prepare('DELETE FROM brick_showcase WHERE id = ?')->execute([$id]);
        if ($row && (int)$row['is_cover'] === 1) {
            $nxt = $db->prepare('SELECT id FROM brick_showcase WHERE slug = ? ORDER BY sort_order ASC, id ASC LIMIT 1');
            $nxt->execute([(string)$row['slug']]);
            $nid = (int)$nxt->fetchColumn();
            if ($nid) $db->prepare('UPDATE brick_showcase SET is_cover = 1 WHERE id = ?')->execute([$nid]);
        }
        return ['ok' => true];
    }

    public function reorder(string $slug, array $ids): array
    {
        $this->ensureTables();
        $slug = preg_replace('/[^a-z0-9_-]/i', '', $slug);
        $st = Database::instance()->prepare('UPDATE brick_showcase SET sort_order = ? WHERE id = ? AND slug = ?');
        $i = 0;
        foreach ($ids as $id) $st->execute([$i++, (int)$id, $slug]);
        return ['ok' => true];
    }

    public function setCover(string $slug, int $id): array
    {
        $this->ensureTables();
        $db = Database::instance();
        $db->prepare('UPDATE brick_showcase SET is_cover = 0 WHERE slug = ?')->execute([$slug]);
        $db->prepare('UPDATE brick_showcase SET is_cover = 1 WHERE id = ? AND slug = ?')->execute([$id, $slug]);
        return ['ok' => true];
    }

    /** Devuelve un documento HTML autocontenido con el carrusel de capturas (para el Preview). */
    public function renderCarousel(string $slug, string $brickName = ''): string
    {
        $items = $this->list($slug);
        if (!$items) return '';
        $e = fn($s): string => htmlspecialchars((string)$s, ENT_QUOTES);
        $slides = [];
        foreach ($items as $r) {
            $slides[] = [
                'media' => $r['media'],
                'type' => $r['media_type'],
                'device' => $r['device'],
                'caption' => (string)$r['caption'],
                'alt' => (string)$r['alt'],
                'demo' => (string)$r['demo_url'],
                'avail' => (string)$r['availability'],
                'availLabel' => self::AVAIL_LABELS[(string)$r['availability']] ?? '',
                'version' => (string)$r['version'],
                'cover' => (int)$r['is_cover'] === 1,
            ];
        }
        $json = json_encode($slides, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $title = $e($brickName !== '' ? $brickName : $slug);
        $html = '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>'
            . '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>'
            . '<style>'
            . '*{box-sizing:border-box}body{margin:0;background:#06080f;color:#e7ecf3;font-family:Inter,system-ui,sans-serif}'
            . '.sh{max-width:1040px;margin:0 auto;padding:18px}'
            . '.sh-head{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:12px}'
            . '.sh-title{font-size:13px;font-weight:700;color:#9fb0c9}'
            . '.sh-filters{display:flex;gap:6px}'
            . '.sh-filters button{border:1px solid #223;background:#0d1220;color:#9fb0c9;border-radius:8px;padding:5px 10px;font:inherit;font-size:11px;cursor:pointer}'
            . '.sh-filters button.on{background:#F20D16;border-color:#F20D16;color:#fff}'
            . '.sh-stage{position:relative;background:#0b0f1a;border:1px solid #1b2233;border-radius:14px;overflow:hidden}'
            . '.sh-shot{display:block;width:100%;max-height:68vh;object-fit:contain;background:#0b0f1a}'
            . '.sh-nav{position:absolute;top:50%;transform:translateY(-50%);border:0;background:rgba(6,8,15,.6);color:#fff;font-size:26px;line-height:1;width:44px;height:44px;border-radius:50%;cursor:pointer}'
            . '.sh-prev{left:10px}.sh-next{right:10px}'
            . '.sh-badge{position:absolute;top:12px;left:12px;background:rgba(6,8,15,.72);border:1px solid #223;color:#cbd5e1;font-size:11px;padding:4px 10px;border-radius:20px}'
            . '.sh-meta{padding:14px 4px}'
            . '.sh-cap{font-size:15px;font-weight:700}'
            . '.sh-sub{font-size:12px;color:#8a99b3;margin-top:4px;display:flex;gap:10px;flex-wrap:wrap;align-items:center}'
            . '.sh-avail{background:#16233a;border:1px solid #24344f;border-radius:20px;padding:2px 10px;font-size:11px}'
            . '.sh-demo{color:#F20D16;font-weight:600;text-decoration:none}'
            . '.sh-thumbs{display:flex;gap:8px;overflow:auto;padding:6px 0}'
            . '.sh-thumbs img,.sh-thumbs .vt{width:110px;height:64px;object-fit:cover;border-radius:8px;border:2px solid transparent;cursor:pointer;background:#0d1220;display:grid;place-items:center;color:#6b7a95;font-size:11px}'
            . '.sh-thumbs .on{border-color:#F20D16}'
            . '.sh-empty{padding:40px;text-align:center;color:#8a99b3}'
            . '</style></head><body><div class="sh">'
            . '<div class="sh-head"><div class="sh-title">' . $title . ' — capturas</div>'
            . '<div class="sh-filters" id="sh-filters"></div></div>'
            . '<div class="sh-stage" id="sh-stage"></div>'
            . '<div class="sh-meta" id="sh-meta"></div>'
            . '<div class="sh-thumbs" id="sh-thumbs"></div>'
            . '</div>'
            . '<script>var SH=' . $json . ';var idx=0;var dev="all";'
            . 'function vis(){return SH.map(function(s,i){return i}).filter(function(i){return dev==="all"||SH[i].device===dev||SH[i].device==="all"})}'
            . 'function draw(){var v=vis();if(!v.length){document.getElementById("sh-stage").innerHTML=\'<div class="sh-empty">Sin capturas para este dispositivo.</div>\';document.getElementById("sh-meta").innerHTML="";document.getElementById("sh-thumbs").innerHTML="";return}'
            . 'if(v.indexOf(idx)<0)idx=v[0];var s=SH[idx];var stage=document.getElementById("sh-stage");'
            . 'var media=s.type==="video"?\'<video class="sh-shot" src="\'+s.media+\'" controls></video>\':\'<img class="sh-shot" src="\'+s.media+\'" alt="\'+(s.alt||s.caption||"")+\'"/>\';'
            . 'stage.innerHTML=media+(v.length>1?\'<button class="sh-nav sh-prev" onclick="nav(-1)">&#8249;</button><button class="sh-nav sh-next" onclick="nav(1)">&#8250;</button>\':"")'
            . '+((s.device&&s.device!=="all")?\'<span class="sh-badge">\'+s.device+\'</span>\':"");'
            . 'document.getElementById("sh-meta").innerHTML=\'<div class="sh-cap">\'+(s.caption||"")+\'</div><div class="sh-sub">\'+(s.availLabel?\'<span class="sh-avail">\'+s.availLabel+\'</span>\':"")+(s.version?\'<span>v\'+s.version+\'</span>\':"")+(s.demo?\'<a class="sh-demo" href="\'+s.demo+\'" target="_blank" rel="noopener">Ver en vivo →</a>\':"")+\'</div>\';'
            . 'var t=document.getElementById("sh-thumbs");t.innerHTML=v.map(function(i){return SH[i].type==="video"?\'<div class="vt \'+(i===idx?"on":"")+\'" onclick="go(\'+i+\')">▶ video</div>\':\'<img class="\'+(i===idx?"on":"")+\'" src="\'+SH[i].media+\'" alt="" onclick="go(\'+i+\')"/>\'}).join("");}'
            . 'function go(i){idx=i;draw()}function nav(d){var v=vis();var p=v.indexOf(idx);p=(p+d+v.length)%v.length;idx=v[p];draw()}'
            . 'var f=document.getElementById("sh-filters");var opts=["all","desktop","tablet","mobile"].filter(function(d){return d==="all"||SH.some(function(s){return s.device===d})});'
            . 'f.innerHTML=opts.map(function(d){return \'<button class="\'+(d==="all"?"on":"")+\'" onclick="setdev(\\\'\'+d+\'\\\')">\'+({all:"Todo",desktop:"Desktop",tablet:"Tablet",mobile:"Mobile"})[d]+\'</button>\';}).join("");'
            . 'function setdev(d){dev=d;document.querySelectorAll("#sh-filters button").forEach(function(b){b.classList.remove("on")});event.target.classList.add("on");draw()}'
            . 'document.addEventListener("keydown",function(e){if(e.key==="ArrowLeft")nav(-1);if(e.key==="ArrowRight")nav(1)});'
            . 'var cov=SH.map(function(s,i){return s.cover?i:-1}).filter(function(i){return i>=0});if(cov.length)idx=cov[0];draw();'
            . '</script></body></html>';
        return $html;
    }
}
