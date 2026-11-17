<?php
namespace App\Services;

class TiaNudgeService
{
    public function forPage(string $page = '', string $query = ''): array
    {
        $path = (string)parse_url($page ?: '/', PHP_URL_PATH);
        $path = '/' . trim($path, '/');
        $query = trim($query);
        if ($path === '/' || $path === '') {
            $type = 'home';
            $search = 'guitarra';
            $message = '¿Buscas tu primer instrumento o quieres subir de nivel? Te dejo opciones para que compares.';
        } elseif (str_starts_with($path, '/producto/') || str_starts_with($path, '/cuenta') || str_starts_with($path, '/pqrf') || str_starts_with($path, '/soporte')) {
            return ['ok' => true, 'show' => false, 'reason' => 'contexto_no_apto'];
        } elseif ($path === '/carrito') {
            $type = 'cart';
            $search = '';
            $message = 'Puedo ayudarte a terminar la compra o resolver una duda antes de pagar.';
        } elseif ($path === '/checkout') {
            $type = 'checkout';
            $search = '';
            $message = 'Estoy aquí si necesitas ayuda con el pago. Recuerda que Bre-B tiene descuento.';
        } else {
            $type = 'category';
            $search = $query;
            if ($search === '' && preg_match('~^/(guitarras|bajos|acordeon|pianos|teclados|percusion|microfonos|audio|iluminacion|tienda|ofertas)(/|$)~i', $path, $m)) {
                $map = ['guitarras' => 'guitarra', 'bajos' => 'bajo', 'acordeon' => 'acordeon', 'pianos' => 'piano', 'teclados' => 'teclado', 'percusion' => 'bateria', 'microfonos' => 'microfono', 'audio' => 'amplificador', 'iluminacion' => 'iluminacion'];
                $search = $map[strtolower($m[1])] ?? '';
            }
            if ($search === '') $search = trim(preg_replace('~^/~', '', $path));
            $clean = trim($search);
            $message = $clean !== '' ? 'Veo que estás explorando ' . mb_substr($clean, 0, 60) . '. Te dejo opciones para que compares.' : '¿Quieres que te ayude a encontrar algo? Te dejo algunas opciones para comparar.';
        }

        $cards = [];
        if (in_array($type, ['home', 'category'], true)) {
            $ciu = new CiuService();
            $terms = $search !== '' ? [$search, 'guitarra', 'acordeon', 'piano'] : ['guitarra', 'acordeon', 'piano'];
            $terms = array_values(array_unique(array_filter($terms, fn($v) => trim($v) !== '')));
            foreach ($terms as $term) {
                foreach ($ciu->productCandidates($term, 1000) as $product) {
                    $title = strtolower((string)$product['title']);
                    if (preg_match('/\b(stand|soporte|correa|forro|funda|cable|pua|pick|estuche|atril|banqueta|afinador|capodastro|metronomo|amplificador|parlante|pedal|clavijero|puente|pastilla|selector)\b/', $title)) continue;
                    $foldedTitle = $this->fold($title);
                    $primary = [
                        'guitarra' => ['guitarra', 'combo guitarra', 'kit guitarra'],
                        'bajo' => ['bajo', 'combo bajo', 'kit bajo'],
                        'acordeon' => ['acordeon', 'combo acordeon', 'kit acordeon'],
                        'piano' => ['piano', 'teclado', 'combo piano', 'kit piano'],
                        'bateria' => ['bateria', 'combo bateria', 'kit bateria'],
                    ];
                    if (isset($primary[$this->fold($search)])) {
                        $ok = false;
                        foreach ($primary[$this->fold($search)] as $prefix) if (str_starts_with($foldedTitle, $prefix)) { $ok = true; break; }
                        if (!$ok) continue;
                    }
                    if ($search !== '' && !str_contains($foldedTitle, $this->fold($search))) continue;
                    $cards[(int)$product['id']] = [
                        'title' => (string)$product['title'],
                        'price_cents' => (int)($product['metadata']['price_cents'] ?? 0),
                        'available' => !empty($product['metadata']['available']),
                        'url' => (string)$product['url'],
                        'image' => (string)($product['metadata']['image'] ?? ''),
                        'brand' => (string)($product['metadata']['vendor'] ?? ''),
                        'ref' => (string)$product['ref'],
                    ];
                    if (count($cards) >= 12) break 2;
                }
            }
            $cards = array_values($cards);
            usort($cards, fn($a, $b) => ((int)$b['available']) <=> ((int)$a['available']));
            $cards = array_slice($cards, 0, 3);
        }

        $response = [
            'ok' => true,
            'show' => true,
            'type' => $type,
            'message' => $message,
            'cards' => $cards,
            'cta' => 'Hablar con TIA',
        ];
        $experiment = (new TiaExperimentService())->assign('nudge_cta');
        if ($experiment) {
            $response['experiment'] = $experiment;
            $response['cta'] = (string)$experiment['cta'];
            (new TiaExperimentService())->recordEvent('nudge_cta', (string)$experiment['variant'], 'impression');
        }
        return $response;
    }

    private function fold(string $value): string
    {
        return strtr(mb_strtolower($value), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    }
}
