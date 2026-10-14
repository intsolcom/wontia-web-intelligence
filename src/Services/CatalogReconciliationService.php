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

    /** Clasificación esperada (investigación/curación): fold(tag-categoría) => categoría legacy esperada. */
    private const EXPECTED = [
        'acordeon'=>'acordeon','armonica'=>'acordeon','acordeones'=>'acordeon','saxofon'=>'acordeon','trompeta'=>'acordeon','flauta'=>'acordeon','clarinete'=>'acordeon','gaita'=>'acordeon','melodica'=>'acordeon','ocarin'=>'acordeon','vientos'=>'acordeon',
        'guitarra'=>'guitarras','guitarras'=>'guitarras','bajo'=>'guitarras','bajos'=>'guitarras','ukelele'=>'guitarras','violin'=>'guitarras','viola'=>'guitarras','violonchelo'=>'guitarras','cuerdas'=>'guitarras',
        'piano'=>'pianos','teclado'=>'pianos','sintetizador'=>'pianos','synth'=>'pianos',
        'amplificador'=>'amplificadores','cabina'=>'amplificadores','mixer'=>'amplificadores','parlante'=>'amplificadores','consola'=>'amplificadores','audifono'=>'amplificadores','audifonos'=>'amplificadores','audio'=>'amplificadores',
        'microfono'=>'microfono-miche','microfonos'=>'microfono-miche','micro'=>'microfono-miche',
        'cabeza'=>'cabeza-movil','par led'=>'cabeza-movil','led'=>'cabeza-movil','reflector'=>'cabeza-movil','iluminacion'=>'cabeza-movil',
        'parche'=>'percusion','parches'=>'percusion','platillo'=>'percusion','conga'=>'percusion','bateria'=>'percusion','baqueta'=>'percusion','baquetas'=>'percusion','tambor'=>'percusion','redoblante'=>'percusion','caja'=>'percusion','timbal'=>'percusion','percusion'=>'percusion',
        'repuestos'=>'cuerdas-guitarras','stand'=>'cuerdas-guitarras','forro'=>'cuerdas-guitarras','cable'=>'cuerdas-guitarras','correa'=>'cuerdas-guitarras','pick'=>'cuerdas-guitarras','acople'=>'cuerdas-guitarras','adaptador'=>'cuerdas-guitarras','afinador'=>'cuerdas-guitarras','soporte'=>'cuerdas-guitarras','funda'=>'cuerdas-guitarras','accesorios'=>'cuerdas-guitarras','fuelle'=>'acordeon','cuerpo'=>'acordeon','cajas'=>'percusion','botones'=>'acordeon','teclas'=>'acordeon','palancas'=>'acordeon','boquilla'=>'acordeon','tapas'=>'acordeon','set'=>'cuerdas-guitarras',
    ];

    public function reportPath(): string
    {
        return dirname(__DIR__, 2) . '/public/assets/uploads/_catalog/reconciliation.json';
    }

    public function fold(string $s): string
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

    /** @param array $legacy sonika_categories()  @param array $products sonika_products() */
    public function analyze(array $legacy, array $products): array
    {
        $legacySlugs = array_map(fn($c) => (string)($c['slug'] ?? ''), $legacy);
        $stat = ['products' => count($products), 'with_tag' => 0, 'without_tag' => 0, 'legacy_assignments' => 0];
        $noTag = [];
        $overlap = [];
        $mismatch = [];
        $dupValues = [];

        foreach ($products as $p) {
            $title = (string)($p['title'] ?? '');
            // categoría real (tag nivel 1)
            $tagCat = '';
            $foldVals = [];
            foreach ((array)($p['tags'] ?? []) as $tg) {
                if (!is_string($tg)) continue;
                if (preg_match('/^(\d+)\)\s*[^_]+_(.+)$/u', trim($tg), $m)) {
                    $lv = (int)$m[1];
                    $val = trim($m[2]);
                    if ($lv === 1 && $tagCat === '') $tagCat = $val;
                    if ($lv <= 4 && $val !== '') $foldVals[] = ['lv' => $lv, 'val' => $val, 'fold' => $this->fold($val)];
                }
            }
            if ($tagCat === '') $noTag[] = $title; else $stat['with_tag']++;

            // categorías legacy que "matchean" (coincidencia difusa)
            $matched = [];
            foreach ($legacy as $c) {
                if ($this->legacyMatch($p, (array)($c['kw'] ?? []))) $matched[] = (string)$c['slug'];
            }
            $stat['legacy_assignments'] += count($matched);
            if (count($matched) > 1) $overlap[] = ['title' => $title, 'cats' => $matched];
            if (count($matched) === 0 && $tagCat !== '') $mismatch[] = ['title' => $title, 'legacy' => [], 'tag' => $tagCat, 'expected' => $this->expected($tagCat)];
            if ($tagCat !== '') {
                $exp = $this->expected($tagCat);
                if ($exp !== '' && !in_array($exp, $matched, true)) {
                    $mismatch[] = ['title' => $title, 'legacy' => $matched, 'tag' => $tagCat, 'expected' => $exp];
                }
            }
            // duplicados por escritura (nivel 1..4)
            foreach ($foldVals as $fv) $dupValues[$fv['lv']][$fv['fold']][$fv['val']] = true;
        }

        $dups = [];
        foreach ($dupValues as $lv => $groups) {
            foreach ($groups as $fold => $variants) {
                if (count($variants) > 1) $dups[] = ['level' => $lv, 'fold' => $fold, 'variants' => array_keys($variants)];
            }
        }

        $report = [
            'generated_at' => date('c'),
            'stats' => $stat,
            'severity' => [
                'critical' => count($mismatch),
                'warning' => count($overlap) + count($dups),
                'info' => count($noTag),
            ],
            'issues' => [
                'mismatch' => ['count' => count($mismatch), 'samples' => array_slice($mismatch, 0, 60)],
                'overlap' => ['count' => count($overlap), 'samples' => array_slice($overlap, 0, 60)],
                'no_tag' => ['count' => count($noTag), 'samples' => array_slice($noTag, 0, 60)],
                'duplicates' => ['count' => count($dups), 'samples' => array_slice($dups, 0, 80)],
            ],
        ];
        return $report;
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
        $f = $this->fold($tagCat);
        if (isset(self::EXPECTED[$f])) return self::EXPECTED[$f];
        // coincidencia por prefijo (p. ej. "guitarra clasica" -> guitarra)
        foreach (self::EXPECTED as $k => $v) { if ($k !== '' && str_contains($f, $k)) return $v; }
        return '';
    }
}
