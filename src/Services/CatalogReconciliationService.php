<?php
namespace App\Services;

/**
 * CatalogReconciliationService — "Conciliación de catálogo".
 *
 * Robot de coherencia: escanea el catálogo completo y detecta inconsistencias
 * entre la clasificación LEGACY del tema (sonika_categories + keywords, usada por
 * /buscar?cat= y Tienda) y la taxonomía REAL del catálogo (tags "N) Kind_Value"),
 * además de duplicados, productos sin categoría y solapamientos.
 *
 * NO modifica el catálogo: produce un INFORME auditable (persistente). El robot lo
 * ejecuta tras cada sincronización para proteger la navegación.
 */
class CatalogReconciliationService
{
    private const FOLD = ['á'=>'a','à'=>'a','ä'=>'a','â'=>'a','ã'=>'a','é'=>'e','è'=>'e','ë'=>'e','ê'=>'e','í'=>'i','ì'=>'i','ï'=>'i','î'=>'i','ó'=>'o','ò'=>'o','ö'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ù'=>'u','ü'=>'u','û'=>'u','ñ'=>'n','ç'=>'c'];

    /** Clasificación (curación/investigación): fold(tag-categoría) => slug legacy. FUENTE ÚNICA. */
    public const LEGACY_MAP = [
        // Percusión
        'parche'=>'percusion','parches'=>'percusion','conga'=>'percusion','congas'=>'percusion','platillo'=>'percusion','platillos'=>'percusion','baqueta'=>'percusion','baquetas'=>'percusion','pandereta'=>'percusion','panderetas'=>'percusion','bongo'=>'percusion','bongos'=>'percusion','bateria'=>'percusion','baterias'=>'percusion','campana'=>'percusion','campanas'=>'percusion','cajon'=>'percusion','cajones'=>'percusion','shaker'=>'percusion','shakers'=>'percusion','guiro'=>'percusion','guiros'=>'percusion','guache'=>'percusion','timbal'=>'percusion','timbales'=>'percusion','caja vallenata'=>'percusion','maracas'=>'percusion','maraca'=>'percusion','jam blocks'=>'percusion','redoblante'=>'percusion','clave'=>'percusion','claves'=>'percusion','tambor'=>'percusion','tambores'=>'percusion','djembe'=>'percusion','vibra'=>'percusion','afuche'=>'percusion','castanuela'=>'percusion','castanuelas'=>'percusion','metalofono'=>'percusion','metalofonos'=>'percusion','tambora'=>'percusion','xilofonos'=>'percusion','xilofono'=>'percusion','escobilla'=>'percusion','guacharaca'=>'percusion','shekere'=>'percusion','triangulo'=>'percusion','cienpies'=>'percusion','palo de lluvia'=>'percusion','cabezote'=>'percusion','caja'=>'percusion','percusion'=>'percusion',
        // Cuerdas
        'guitarra'=>'guitarras','guitarras'=>'guitarras','bajo'=>'guitarras','bajos'=>'guitarras','ukelele'=>'guitarras','ukeleles'=>'guitarras','violin'=>'guitarras','violines'=>'guitarras','combo guitarra'=>'guitarras','combo bajo'=>'guitarras','cuatro'=>'guitarras','tiple'=>'guitarras','violoncello'=>'guitarras','arpas'=>'guitarras','arpa'=>'guitarras','cuerdas'=>'guitarras',
        // Vientos
        'acordeon'=>'acordeon','acordeones'=>'acordeon','armonica'=>'acordeon','armonicas'=>'acordeon','melodica'=>'acordeon','melodicas'=>'acordeon','vientos'=>'acordeon','viento'=>'acordeon','flauta'=>'acordeon','flautas'=>'acordeon','saxofon'=>'acordeon','saxofones'=>'acordeon','trompeta'=>'acordeon','trompetas'=>'acordeon','clarinete'=>'acordeon','clarinetes'=>'acordeon','gaita'=>'acordeon','gaitas'=>'acordeon','trombon'=>'acordeon','trombones'=>'acordeon','fuelle'=>'acordeon','boquilla'=>'acordeon',
        // Teclados
        'piano'=>'pianos','pianos'=>'pianos','controlador'=>'pianos','controladores'=>'pianos','pads'=>'pianos','pad'=>'pianos','teclado'=>'pianos','teclados'=>'pianos','sintetizador'=>'pianos',
        // Audio
        'cabina'=>'amplificadores','cabinas'=>'amplificadores','audifono'=>'amplificadores','audifonos'=>'amplificadores','planta'=>'amplificadores','plantas'=>'amplificadores','mixer'=>'amplificadores','mezclador'=>'amplificadores','parlante'=>'amplificadores','parlantes'=>'amplificadores','monitor'=>'amplificadores','monitores'=>'amplificadores','rack'=>'amplificadores','racks'=>'amplificadores','procesador'=>'amplificadores','procesadores'=>'amplificadores','interfaz'=>'amplificadores','interfaces'=>'amplificadores','reproductor'=>'amplificadores','reproductores'=>'amplificadores','amplificador'=>'amplificadores','amplificadores'=>'amplificadores','auricular'=>'amplificadores','auriculares'=>'amplificadores','driver'=>'amplificadores','drivers'=>'amplificadores','distribuidor'=>'amplificadores','filtro'=>'amplificadores','medusa'=>'amplificadores','medusas'=>'amplificadores','speaker'=>'amplificadores','speakers'=>'amplificadores','plug'=>'amplificadores','audio'=>'amplificadores','consola'=>'amplificadores',
        // Micrófonos
        'microfono'=>'microfono-miche','microfonos'=>'microfono-miche','micro'=>'microfono-miche',
        // Iluminación
        'cabeza movil'=>'cabeza-movil','cabezas moviles'=>'cabeza-movil','par led'=>'cabeza-movil','truss'=>'cabeza-movil','cortina'=>'cabeza-movil','cortinas'=>'cabeza-movil','maquina de humo'=>'cabeza-movil','maquinas de humo'=>'cabeza-movil','tensor'=>'cabeza-movil','tensores'=>'cabeza-movil','luz'=>'cabeza-movil','luces'=>'cabeza-movil','lampara'=>'cabeza-movil','lamparas'=>'cabeza-movil','liquido de humo'=>'cabeza-movil','suspension'=>'cabeza-movil','iluminacion'=>'cabeza-movil',
        // Accesorios
        'repuestos'=>'cuerdas-guitarras','repuesto'=>'cuerdas-guitarras','stand'=>'cuerdas-guitarras','stands'=>'cuerdas-guitarras','correa'=>'cuerdas-guitarras','correas'=>'cuerdas-guitarras','forro'=>'cuerdas-guitarras','forros'=>'cuerdas-guitarras','cable'=>'cuerdas-guitarras','cables'=>'cuerdas-guitarras','pick'=>'cuerdas-guitarras','picks'=>'cuerdas-guitarras','capodastro'=>'cuerdas-guitarras','capodastros'=>'cuerdas-guitarras','silla'=>'cuerdas-guitarras','sillas'=>'cuerdas-guitarras','pedal'=>'cuerdas-guitarras','pedales'=>'cuerdas-guitarras','kit'=>'cuerdas-guitarras','kits'=>'cuerdas-guitarras','adaptador'=>'cuerdas-guitarras','adaptadores'=>'cuerdas-guitarras','conector'=>'cuerdas-guitarras','conectores'=>'cuerdas-guitarras','sordina'=>'cuerdas-guitarras','sordinas'=>'cuerdas-guitarras','soporte'=>'cuerdas-guitarras','soportes'=>'cuerdas-guitarras','slide'=>'cuerdas-guitarras','slides'=>'cuerdas-guitarras','afinador'=>'cuerdas-guitarras','afinadores'=>'cuerdas-guitarras','souvenir'=>'cuerdas-guitarras','souvenirs'=>'cuerdas-guitarras','acople'=>'cuerdas-guitarras','acoples'=>'cuerdas-guitarras','clamp'=>'cuerdas-guitarras','clamps'=>'cuerdas-guitarras','protector'=>'cuerdas-guitarras','protectores'=>'cuerdas-guitarras','baquetero'=>'cuerdas-guitarras','limpiador'=>'cuerdas-guitarras','limpiadores'=>'cuerdas-guitarras','bracket'=>'cuerdas-guitarras','brackets'=>'cuerdas-guitarras','cortaviento'=>'cuerdas-guitarras','esquinera'=>'cuerdas-guitarras','barra'=>'cuerdas-guitarras','manija'=>'cuerdas-guitarras','llave'=>'cuerdas-guitarras','pata'=>'cuerdas-guitarras','pin'=>'cuerdas-guitarras','pines'=>'cuerdas-guitarras','trinche'=>'cuerdas-guitarras','tarima'=>'cuerdas-guitarras','base'=>'cuerdas-guitarras','clavijero'=>'cuerdas-guitarras','clip'=>'cuerdas-guitarras','accesorios'=>'cuerdas-guitarras','botones'=>'acordeon','teclas'=>'acordeon','palancas'=>'acordeon','set'=>'cuerdas-guitarras',
    ];

    public static function legacySlug(string $tagCat): string
    {
        $f = self::fold($tagCat);
        if ($f === '') return '';
        if (isset(self::LEGACY_MAP[$f])) return self::LEGACY_MAP[$f];
        foreach (self::LEGACY_MAP as $k => $v) { if ($k !== '' && str_contains($f, $k)) return $v; }
        return '';
    }

    public function reportPath(): string
    {
        return dirname(__DIR__, 2) . '/public/assets/uploads/_catalog/reconciliation.json';
    }

    public static function fold(string $s): string
    {
        $s = mb_strtolower(trim($s), 'UTF-8');
        $s = strtr($s, self::FOLD);
        return trim(preg_replace('/\s+/u', ' ', $s) ?: '');
    }

    public function load(): ?array
    {
        $f = $this->reportPath();
        if (!is_file($f)) return null;
        $d = json_decode((string)@file_get_contents($f), true);
        return is_array($d) ? $d : null;
    }

    public function statePath(): string
    {
        return dirname(__DIR__, 2) . '/public/assets/uploads/_catalog/reconciliation_state.json';
    }

    public function loadState(): array
    {
        $f = $this->statePath();
        if (!is_file($f)) return [];
        $d = json_decode((string)@file_get_contents($f), true);
        return is_array($d) ? $d : [];
    }

    public function saveState(array $patch): void
    {
        $cur = $this->loadState();
        $merged = array_merge($cur, $patch);
        $f = $this->statePath();
        $dir = dirname($f);
        if (!is_dir($dir)) @mkdir($dir, 0777, true);
        @file_put_contents($f . '.tmp', json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        @rename($f . '.tmp', $f);
    }

    /** @param array $legacy sonika_categories()  @param array $products sonika_products() */
    public function analyze(array $legacy, array $products): array
    {
        $legacySlugs = array_map(fn($c) => (string)($c['slug'] ?? ''), $legacy);
        $stat = ['products' => count($products), 'with_tag' => 0, 'without_tag' => 0, 'legacy_assignments' => 0];
        $noTag = [];
        $overlap = [];
        $mismatch = [];
        $dupValues = [];
        $unmappedCats = [];
        $divergence = [];
        $seenCats = [];
        $covTag = [];
        $covKw = [];
        foreach ($legacySlugs as $s) { $covTag[$s] = 0; $covKw[$s] = 0; }
        $handles = [];
        $orphans = [];
        $brick = ['categorias' => 0, 'advance_search' => 0, 'mega_menu' => 0, 'searchable' => 0];

        foreach ($products as $p) {
            $title = (string)($p['title'] ?? '');
            $tagCat = '';
            $foldVals = [];
            foreach ((array)($p['tags'] ?? []) as $tg) {
                if (!is_string($tg)) continue;
                if (preg_match('/^(\d+)\)\s*([^_]+)_(.+)$/u', trim($tg), $m)) {
                    $lv = (int)$m[1];
                    $kind = trim($m[2]);
                    $val = trim($m[3]);
                    if ($val === '') continue;
                    if ($lv === 1 && $tagCat === '') $tagCat = $val;
                    if ($lv <= 4) $foldVals[] = ['lv' => $lv, 'val' => $val, 'fold' => self::fold($val)];
                    $fk = self::fold($val);
                    if ($fk !== '') {
                        if (!isset($seenCats[$fk])) $seenCats[$fk] = ['name' => $val, 'level' => $lv, 'kind' => $kind, 'count' => 0];
                        $seenCats[$fk]['count']++;
                    }
                }
            }

            $kw = [];
            foreach ($legacy as $c) { if ($this->legacyMatch($p, (array)($c['kw'] ?? []))) $kw[] = (string)$c['slug']; }
            foreach ($kw as $s) { if (isset($covKw[$s])) $covKw[$s]++; }

            $hdl = (string)($p['handle'] ?? '');
            if ($hdl !== '') $handles[$hdl] = true;
            $hasType = trim((string)($p['product_type'] ?? '')) !== '';
            $hasBrand = trim((string)($p['vendor'] ?? '')) !== '';
            $mappable = ($tagCat !== '' && self::legacySlug($tagCat) !== '') || !empty($kw);
            if ($mappable) $brick['categorias']++;
            if ($hasType || $hasBrand) $brick['advance_search']++;
            if ($tagCat !== '' || $hasType) $brick['mega_menu']++;
            if ($title !== '') $brick['searchable']++;
            if ($tagCat === '' && empty($kw) && !$hasType && !$hasBrand) $orphans[] = ['title' => $title, 'handle' => $hdl];

            if ($tagCat === '') {
                $noTag[] = $title;
                if (count($kw) > 1) $overlap[] = ['title' => $title, 'cats' => $kw];
            } else {
                $stat['with_tag']++;
                $mapped = self::legacySlug($tagCat);
                if ($mapped === '') {
                    $fk = self::fold($tagCat);
                    if (!isset($unmappedCats[$fk])) $unmappedCats[$fk] = ['name' => $tagCat, 'count' => 0, 'sample' => $title];
                    $unmappedCats[$fk]['count']++;
                } else {
                    if (isset($covTag[$mapped])) $covTag[$mapped]++;
                    if ($kw && !in_array($mapped, $kw, true)) $divergence[] = ['title' => $title, 'handle' => $hdl, 'tag' => $tagCat, 'tag_slug' => $mapped, 'kw_slugs' => $kw];
                }
                $stat['legacy_assignments']++;
            }
            foreach ($foldVals as $fv) $dupValues[$fv['lv']][$fv['fold']][$fv['val']] = true;
        }
        $stat['without_tag'] = count($noTag);

        foreach ($unmappedCats as $u) $mismatch[] = ['title' => $u['sample'], 'legacy' => [], 'tag' => $u['name'], 'expected' => '', 'count' => $u['count']];
        usort($mismatch, fn($a, $b) => ($b['count'] ?? 0) <=> ($a['count'] ?? 0));
        $stat['unmapped_categories'] = count($unmappedCats);

        $dups = [];
        foreach ($dupValues as $lv => $groups) {
            foreach ($groups as $fold => $variants) {
                if (count($variants) > 1) $dups[] = ['level' => $lv, 'fold' => $fold, 'variants' => array_keys($variants)];
            }
        }

        $coverage = [];
        foreach ($legacySlugs as $s) $coverage[$s] = ['by_tag' => $covTag[$s] ?? 0, 'by_keyword' => $covKw[$s] ?? 0];
        $emptyCats = [];
        foreach ($coverage as $s => $c) { if ($c['by_tag'] === 0) $emptyCats[] = $s; }

        $firstRun = !is_file($this->statePath());
        $state = $this->loadState();
        $baseline = $firstRun ? array_map(fn($i) => $i['name'], $seenCats) : (array)($state['cats'] ?? []);
        $newCats = [];
        foreach ($seenCats as $fk => $info) { if (!isset($baseline[$fk])) $newCats[] = ['fold' => $fk, 'name' => $info['name'], 'level' => $info['level'], 'kind' => $info['kind'], 'count' => $info['count']]; }
        usort($newCats, fn($a, $b) => $b['count'] <=> $a['count']);

        $firstProd = !isset($state['products']);
        $baseProd = (array)($state['products'] ?? []);
        $newProducts = [];
        if (!$firstProd) { foreach ($handles as $hh => $_) { if (!isset($baseProd[$hh])) $newProducts[] = $hh; } }

        $tot = max(1, count($products));
        $brickPct = [
            'searchable' => (int)round($brick['searchable'] / $tot * 100),
            'advance_search' => (int)round($brick['advance_search'] / $tot * 100),
            'categorias' => (int)round($brick['categorias'] / $tot * 100),
            'mega_menu' => (int)round($brick['mega_menu'] / $tot * 100),
        ];
        $history = (array)($state['history'] ?? []);
        $history[] = ['at' => date('c'), 'products' => count($products), 'new' => count($newProducts), 'orphans' => count($orphans), 'unmapped' => count($mismatch), 'divergence' => count($divergence)];
        $history = array_slice($history, -60);
        $cleared = 0;
        if ($newProducts) $cleared = $this->clearIndexCaches();

        $findings = [];
        foreach ($mismatch as $m) $findings[] = ['sig' => 'unmapped:' . self::fold((string)$m['tag']), 'level' => 'critical', 'label' => 'Categoría no mapeada: “' . $m['tag'] . '” (' . $m['count'] . ')'];
        foreach ($emptyCats as $s) $findings[] = ['sig' => 'empty:' . $s, 'level' => 'critical', 'label' => 'Categoría del sitio SIN productos por tag: ' . $s];
        foreach ($newCats as $n) $findings[] = ['sig' => 'new:' . $n['fold'], 'level' => 'warning', 'label' => 'Categoría nueva detectada: “' . $n['name'] . '” (nivel ' . $n['level'] . ', ' . $n['count'] . ')'];
        if ($newProducts) $findings[] = ['sig' => 'new_products:' . count($newProducts), 'level' => 'info', 'label' => count($newProducts) . ' producto(s) NUEVO(S) en el catálogo'];
        if ($orphans) $findings[] = ['sig' => 'orphans:' . count($orphans), 'level' => 'warning', 'label' => count($orphans) . ' producto(s) sin categoría/tipo/marca (no filtrables)'];

        $report = [
            'generated_at' => date('c'),
            'stats' => $stat,
            'severity' => [
                'critical' => count($mismatch) + count($emptyCats),
                'warning' => count($overlap) + count($dups) + count($divergence),
                'info' => count($noTag),
            ],
            'issues' => [
                'mismatch' => ['count' => count($mismatch), 'samples' => array_slice($mismatch, 0, 60)],
                'overlap' => ['count' => count($overlap), 'samples' => array_slice($overlap, 0, 60)],
                'no_tag' => ['count' => count($noTag), 'samples' => array_slice($noTag, 0, 60)],
                'duplicates' => ['count' => count($dups), 'samples' => array_slice($dups, 0, 80)],
                'divergence' => ['count' => count($divergence), 'samples' => array_slice($divergence, 0, 60)],
                'empty_categories' => ['count' => count($emptyCats), 'list' => $emptyCats],
                'new_categories' => ['count' => count($newCats), 'samples' => array_slice($newCats, 0, 60)],
                'new_products' => ['count' => count($newProducts), 'samples' => array_slice($newProducts, 0, 60)],
                'orphans' => ['count' => count($orphans), 'samples' => array_slice($orphans, 0, 60)],
            ],
            'coverage' => $coverage,
            'bricks' => $brick,
            'bricks_pct' => $brickPct,
            'index_cleared' => $cleared,
            'history' => array_slice($history, -30),
            'findings' => array_slice($findings, 0, 200),
        ];

        $this->saveState(['cats' => array_map(fn($i) => $i['name'], $seenCats), 'products' => array_fill_keys(array_keys($handles), 1), 'history' => $history, 'updated_at' => date('c')]);
        return $report;
    }

    /** Devuelve los hallazgos NUEVOS (no avisados antes) y envía email si hay y está configurado. */
    public function notifyNew(array $report, string $email = ''): array
    {
        $findings = (array)($report['findings'] ?? []);
        $state = $this->loadState();
        $prev = (array)($state['notified'] ?? []);
        $new = array_values(array_filter($findings, fn($f) => !in_array((string)($f['sig'] ?? ''), $prev, true)));
        if (!$new) return ['sent' => false, 'count' => 0, 'new' => []];
        $sent = false;
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $mail = new EmailService();
            if ($mail->configured()) {
                $lines = ['Conciliación de catálogo — hallazgos NUEVOS (' . count($new) . '):', ''];
                foreach ($new as $f) $lines[] = '• [' . $f['level'] . '] ' . $f['label'];
                $lines[] = '';
                $lines[] = 'Informe: ' . date('c');
                $sent = $mail->send($email, 'WWI · Conciliación de catálogo — hallazgos nuevos', implode("\n", $lines));
            }
        }
        $sigs = array_values(array_unique(array_merge($prev, array_map(fn($f) => (string)$f['sig'], $new))));
        $this->saveState(['notified' => array_slice($sigs, -500)]);
        return ['sent' => $sent, 'count' => count($new), 'new' => $new];
    }

    /** Limpia las cachés del índice (Mega Menú) para que se reconstruya con productos nuevos. */
    public function clearIndexCaches(): int
    {
        $n = 0;
        $dir = rtrim(sys_get_temp_dir(), '/\\');
        foreach (['wwi_mega_', 'wwi_megatag_'] as $pfx) {
            foreach (glob($dir . DIRECTORY_SEPARATOR . $pfx . '*.json') ?: [] as $f) { if (@unlink($f)) $n++; }
        }
        return $n;
    }

    /** Email de alertas: settings catalog_reconcile_email → wwi.catalog_alert_email → store_notify_email. */
    public function alertEmail(): string
    {
        try {
            $db = \App\Core\Database::instance();
            foreach (['catalog_reconcile_email', 'wwi.catalog_alert_email', 'store_notify_email'] as $k) {
                $st = $db->prepare("SELECT value FROM settings WHERE site_id = @site_id AND `key` = :k LIMIT 1");
                $st->execute(['k' => $k]);
                $v = trim((string)$st->fetchColumn());
                if ($v !== '' && filter_var($v, FILTER_VALIDATE_EMAIL)) return $v;
            }
        } catch (\Throwable $e) {
        }
        return '';
    }

    public function save(array $report): bool
    {
        $f = $this->reportPath();
        $dir = dirname($f);
        if (!is_dir($dir)) @mkdir($dir, 0777, true);
        $tmp = $f . '.tmp';
        if (@file_put_contents($tmp, json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === false) return false;
        return @rename($tmp, $f);
    }

    private function legacyMatch(array $p, array $kw): bool
    {
        if (!$kw) return false;
        $hay = strtoupper((string)($p['title'] ?? '') . ' ' . (string)($p['product_type'] ?? '') . ' ' . (string)($p['vendor'] ?? ''));
        foreach ($kw as $k) { if ($k !== '' && str_contains($hay, strtoupper((string)$k))) return true; }
        return false;
    }

    private function expected(string $tagCat): string
    {
        return self::legacySlug($tagCat);
    }
}
