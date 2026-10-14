<?php
namespace App\Services;

use App\Core\AiBrick\AiRouter;
use App\Core\Config;

/**
 * CatalogClassifierService — Auto-clasificador con IA (TIA/BRICK) para productos
 * SIN categoría (tag). Propone la categoría (de la taxonomía real), guarda las
 * sugerencias para REVISIÓN y, al aprobarlas, escribe un override persistente que
 * el tema aplica en la clasificación (Tienda/facetas) y en el Mega Menú.
 */
class CatalogClassifierService
{
    public function path(string $name): string
    {
        return dirname(__DIR__, 2) . '/public/assets/uploads/_catalog/' . $name;
    }
    private function overridesPath(): string { return $this->path('classification_overrides.json'); }
    private function suggestionsPath(): string { return $this->path('classification_suggestions.json'); }

    private static ?array $ovCache = null;

    public function overrides(): array
    {
        if (self::$ovCache !== null) return self::$ovCache;
        $f = $this->overridesPath();
        self::$ovCache = is_file($f) ? (json_decode((string)@file_get_contents($f), true) ?: []) : [];
        return self::$ovCache;
    }

    public function suggestions(): array
    {
        $f = $this->suggestionsPath();
        return is_file($f) ? (json_decode((string)@file_get_contents($f), true) ?: []) : [];
    }

    private function handle(array $p): string { return (string)($p['handle'] ?? ''); }

    public function tagCategory(array $p): string
    {
        foreach ((array)($p['tags'] ?? []) as $t) {
            if (preg_match('/^1\)\s*[^_]+_(.+)$/u', trim((string)$t), $m)) return trim($m[1]);
        }
        return '';
    }

    /** Categoría efectiva de un producto (override aprobado > tag > ''). */
    public function effectiveCategory(array $p): string
    {
        $h = $this->handle($p);
        $ov = $this->overrides();
        if ($h !== '' && isset($ov[$h])) return (string)$ov[$h];
        return $this->tagCategory($p);
    }

    public function untagged(array $products): array
    {
        $ov = $this->overrides();
        $out = [];
        foreach ($products as $p) {
            $h = $this->handle($p);
            if ($h === '' || isset($ov[$h]) || $this->tagCategory($p) !== '') continue;
            $out[] = $p;
        }
        return $out;
    }

    public function categories(array $products): array
    {
        $c = [];
        foreach ($products as $p) { $t = $this->tagCategory($p); if ($t !== '') $c[$t] = true; }
        return array_keys($c);
    }

    public function classifyBatch(array $products, int $limit = 20): array
    {
        $untagged = $this->untagged($products);
        $batch = array_slice($untagged, 0, max(1, min(50, $limit)));
        if (!$batch) return ['batch' => 0, 'suggested' => 0, 'remaining' => 0, 'ai' => true];
        $allowed = $this->categories($products);
        $cats = implode(', ', array_slice($allowed, 0, 150));
        $list = [];
        foreach ($batch as $i => $p) {
            $list[] = ($i + 1) . '. ' . ($p['title'] ?? '') . ' | marca: ' . ($p['vendor'] ?? '') . ' | tipo: ' . ($p['product_type'] ?? '');
        }
        $prompt = "Eres un experto en instrumentos musicales y equipo de audio. Clasifica cada producto en UNA categoría EXACTA de esta lista:\n"
            . $cats . "\n\nDevuelve SOLO JSON válido: {\"items\":[{\"n\":NUMERO,\"cat\":\"Categoría\"}]}. Usa el nombre EXACTO de la lista. Si ninguna encaja, usa \"Repuestos\".\n\nProductos:\n"
            . implode("\n", $list);
        $content = $this->ai($prompt);
        $sug = $this->suggestions();
        $count = 0;
        $valid = array_flip($allowed);
        if ($content !== '') {
            foreach ($this->parse($content) as $it) {
                $n = (int)($it['n'] ?? 0);
                $cat = trim((string)($it['cat'] ?? ''));
                if ($n < 1 || $n > count($batch) || $cat === '') continue;
                if (!isset($valid[$cat])) $cat = 'Repuestos';
                $p = $batch[$n - 1];
                $h = $this->handle($p);
                if ($h === '') continue;
                $sug[$h] = ['title' => (string)($p['title'] ?? ''), 'cat' => $cat, 'at' => date('c')];
                $count++;
            }
        }
        $this->writeJson($this->suggestionsPath(), $sug);
        return ['batch' => count($batch), 'suggested' => $count, 'remaining' => max(0, count($untagged) - count($batch)), 'ai' => ($content !== '')];
    }

    public function apply(array $handles): array
    {
        $sug = $this->suggestions();
        $ov = $this->overrides();
        $n = 0;
        foreach ($handles as $h) {
            $h = (string)$h;
            if (isset($sug[$h])) { $ov[$h] = (string)$sug[$h]['cat']; unset($sug[$h]); $n++; }
        }
        $this->writeJson($this->overridesPath(), $ov);
        $this->writeJson($this->suggestionsPath(), $sug);
        self::$ovCache = $ov;
        return ['applied' => $n, 'total_overrides' => count($ov)];
    }

    public function reject(array $handles): array
    {
        $sug = $this->suggestions();
        $n = 0;
        foreach ($handles as $h) { $h = (string)$h; if (isset($sug[$h])) { unset($sug[$h]); $n++; } }
        $this->writeJson($this->suggestionsPath(), $sug);
        return ['rejected' => $n];
    }

    private function ai(string $prompt): string
    {
        try {
            $router = new AiRouter();
            $r = $router->route([
                'system_id' => 'wontia', 'module' => 'catalog', 'function' => 'classify',
                'system_prompt' => 'Devuelve SOLO JSON válido, sin markdown.',
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'temperature' => 0.1, 'max_tokens' => 1200,
            ]);
            if (!empty($r['ok']) && !empty($r['content'])) return trim((string)$r['content']);
        } catch (\Throwable $e) {
        }
        $key = (string)Config::get('DEEPSEEK_API_KEY', '');
        if ($key === '') return '';
        $ch = curl_init('https://api.deepseek.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $key],
            CURLOPT_POSTFIELDS => json_encode(['model' => 'deepseek-chat', 'messages' => [['role' => 'system', 'content' => 'Devuelve SOLO JSON válido.'], ['role' => 'user', 'content' => $prompt]], 'temperature' => 0.1, 'max_tokens' => 1200]),
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 40,
        ]);
        $resp = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($http === 200) {
            $d = json_decode((string)$resp, true);
            return trim((string)($d['choices'][0]['message']['content'] ?? ''));
        }
        return '';
    }

    private function parse(string $content): array
    {
        $content = preg_replace('/^```(?:json)?|```$/m', '', trim($content)) ?: $content;
        $a = strpos($content, '{');
        $b = strrpos($content, '}');
        if ($a === false || $b === false || $b <= $a) return [];
        $d = json_decode(substr($content, $a, $b - $a + 1), true);
        if (!is_array($d)) return [];
        if (isset($d['items']) && is_array($d['items'])) return $d['items'];
        return $d;
    }

    private function writeJson(string $f, $data): bool
    {
        $dir = dirname($f);
        if (!is_dir($dir)) @mkdir($dir, 0777, true);
        return @file_put_contents($f, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) !== false;
    }
}
