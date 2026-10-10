<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Config;
use App\Core\AiBrick\AiRouter;

/**
 * SearchAiController — búsqueda inteligente (TIA): lenguaje natural → filtros.
 * Público (rate-limited). Usa la capa BRICK; fallback a DeepSeek directo.
 */
class SearchAiController
{
    public function suggest(Request $req): void
    {
        $phrase = trim((string)$req->input('phrase', ''));
        if (mb_strlen($phrase) < 3) Response::error('Escribe al menos 3 caracteres.', 400);
        if (!$this->allow((string)$req->ip())) Response::error('Demasiadas solicitudes. Espera un momento.', 429);

        $brands = $this->csv((string)$req->input('brands', ''));
        $types = $this->csv((string)$req->input('types', ''));
        $cats = $this->csv((string)$req->input('cats', ''));

        $prompt = "Convierte esta búsqueda de un cliente en FILTROS para una tienda de instrumentos musicales y audio.\n"
            . "Frase: \"{$phrase}\"\n"
            . "Marcas válidas: " . implode(', ', array_slice($brands, 0, 80)) . "\n"
            . "Tipos válidos: " . implode(', ', array_slice($types, 0, 80)) . "\n"
            . "Categorías válidas (slug): " . implode(', ', array_slice($cats, 0, 40)) . "\n"
            . "Devuelve SOLO un objeto JSON con las claves que apliquen: "
            . "{\"q\":string,\"marca\":string,\"tipo\":string,\"cat\":string,\"min\":number,\"max\":number,"
            . "\"orden\":\"precio_asc|precio_desc|nuevo|descuento\",\"oferta\":true|false,\"nuevo\":true|false,\"disp\":true|false}. "
            . "Usa SOLO valores de las listas dadas. Precios en pesos colombianos (COP). No expliques nada.";

        $content = $this->ai($prompt);
        if ($content === '') { Response::json(['ok' => false, 'message' => 'IA no disponible']); return; }
        $filters = $this->parse($content);
        if ($filters === null) { Response::json(['ok' => false, 'message' => 'No se pudo interpretar la frase']); return; }
        Response::json(['ok' => true, 'filters' => $filters, 'phrase' => $phrase]);
    }

    private function ai(string $prompt): string
    {
        try {
            $router = new AiRouter();
            $r = $router->route([
                'system_id' => 'wontia', 'module' => 'search', 'function' => 'ai-parse',
                'system_prompt' => 'Eres un asistente que devuelve SOLO JSON válido, sin markdown.',
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'temperature' => 0.2, 'max_tokens' => 300,
            ]);
            if (!empty($r['ok']) && !empty($r['content'])) return trim((string)$r['content']);
        } catch (\Throwable $e) {
        }
        $key = (string)Config::get('DEEPSEEK_API_KEY', '');
        if ($key !== '') {
            $ch = curl_init('https://api.deepseek.com/v1/chat/completions');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $key],
                CURLOPT_POSTFIELDS => json_encode(['model' => 'deepseek-chat', 'messages' => [['role' => 'system', 'content' => 'Devuelve SOLO JSON válido.'], ['role' => 'user', 'content' => $prompt]], 'temperature' => 0.2, 'max_tokens' => 300]),
                CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
            ]);
            $resp = curl_exec($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($http === 200) {
                $d = json_decode($resp, true);
                return trim((string)($d['choices'][0]['message']['content'] ?? ''));
            }
        }
        return '';
    }

    private function parse(string $raw): ?array
    {
        $raw = preg_replace('/^```(?:json)?\s*/i', '', trim($raw));
        $raw = preg_replace('/\s*```$/', '', $raw);
        if (preg_match('/\{.*\}/s', $raw, $m)) $raw = $m[0];
        $j = json_decode($raw, true);
        if (!is_array($j)) return null;
        $out = [];
        foreach (['q', 'marca', 'tipo', 'cat', 'orden'] as $k) {
            if (isset($j[$k]) && is_string($j[$k]) && $j[$k] !== '') $out[$k] = substr($j[$k], 0, 80);
        }
        foreach (['min', 'max'] as $k) {
            if (isset($j[$k]) && is_numeric($j[$k]) && (float)$j[$k] > 0) $out[$k] = (float)$j[$k];
        }
        foreach (['oferta', 'nuevo', 'disp'] as $k) {
            if (!empty($j[$k])) $out[$k] = 1;
        }
        return $out;
    }

    private function csv(string $s): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $s)), fn($v) => $v !== ''));
    }

    private function allow(string $ip): bool
    {
        $dir = ROOT_DIR . '/cache/security';
        if (!is_dir($dir)) @mkdir($dir, 0770, true);
        $f = $dir . '/searchai_' . md5($ip) . '.json';
        $now = time();
        $d = ['c' => 0, 't' => $now];
        if (file_exists($f)) {
            $p = json_decode((string)file_get_contents($f), true);
            if (is_array($p) && $now - (int)($p['t'] ?? $now) < 60) $d = ['c' => (int)($p['c'] ?? 0), 't' => (int)$p['t']];
        }
        if ($d['c'] >= 15) return false;
        $d['c']++;
        @file_put_contents($f, json_encode($d), LOCK_EX);
        return true;
    }
}
