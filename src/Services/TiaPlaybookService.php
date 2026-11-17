<?php
namespace App\Services;

use App\Core\Session;

class TiaPlaybookService
{
    private const SUBJECTS = [
        'guitarra', 'piano', 'teclado', 'bajo', 'bateria', 'acordeon', 'microfono', 'violin', 'ukelele',
        'amplificador', 'iluminacion', 'timbal', 'timbales', 'conga', 'congas', 'bongo', 'bongos', 'cajon',
        'cajones', 'djembe', 'redoblante', 'tarola', 'bombo', 'platillo', 'platillos', 'campana', 'campanas',
        'gong', 'marimba', 'xilofono', 'consola', 'consolas', 'mezcladora', 'interfaz', 'interfaces',
        'controlador', 'controladores', 'cable', 'cables', 'percu', 'percusion',
    ];
    private const SUBJECT_ALIASES = [
        'timbales' => 'timbal', 'congas' => 'conga', 'bongos' => 'bongo', 'cajones' => 'cajon',
        'platillos' => 'platillo', 'campanas' => 'campana', 'percu' => 'percusion',
        'consolas' => 'consola', 'interfaces' => 'interfaz', 'controladores' => 'controlador', 'cables' => 'cable',
    ];

    public function interpret(string $message, array $training = []): array
    {
        $state = Session::get('tia_sales_state', []);
        if (!is_array($state)) $state = [];
        $text = $this->fold($message);
        $subject = '';
        foreach (self::SUBJECTS as $term) {
            if (str_contains($text, $term)) { $subject = self::SUBJECT_ALIASES[$term] ?? $term; break; }
        }
        if ($subject === '') $subject = (string)($state['subject'] ?? '');

        $image = (bool)preg_match('/\b(imagen|imagenes|foto|fotos|fotografia|verlo|verla|ver el producto|no quiero enlaces|no quiero links|no quiero texto|solo fotos|solo imagenes)\b/u', $text);
        $imageOnly = (bool)preg_match('/\b(?:solo|solamente|unicamente|nada mas)\s+(?:la\s+)?(?:imagen|foto)|(?:imagen|foto)\s+(?:del|de\s+el)\s+(?:primer|primero|1)|(?:la|una)\s+imagen\s+de\s+un|no\s+me\s+muestres.*\b(?:3|tres|fichas)\b|solo\s+(?:una|1)\s+(?:imagen|foto)/iu', $text);
        $catalogIntent = (bool)preg_match('/\b(todos|todas|lista|catálogo|catalogo|no son todos|solo 3|solamente 3|3 fichas|categorias?)\b/iu', $text)
            || ($subject !== '' && (bool)preg_match('/\b(enlace|link|url|página|pagina)\b/iu', $text));
        if (preg_match('/\b(este|ese|producto|ficha)\b/iu', $text) && !preg_match('/\b(todos|todas|lista|catálogo|catalogo|categoria)\b/iu', $text)) $catalogIntent = false;
        $catalogRequest = $catalogIntent && !$imageOnly;
        $whyPrice = (bool)preg_match('/(?:por que|porque|porqué).*(?:barato|barata|caro|costoso|precio|precios|cuesta|diferencia)|(?:por que|porque).*(?:sigues|sigue|repites|repitiendo)|(?:eso no fue|no fue lo que pregunte)/u', $text);
        $cheap = (bool)preg_match('/\b(barato|barata|baratos|baratas|economico|economica|economicos|economicas|economicamente|menos caro|mas economico|mas economicos|menor precio|precio mas bajo|lo mas barato|low cost)\b/u', $text) && !$whyPrice;
        $mid = (bool)preg_match('/\b(gama media|rango medio|intermedio|media gama)\b/u', $text);
        $recommend = (bool)preg_match('/\b(recomiend\w*|cual me|cual de|compar\w*|mejor opcion|que me recomiendas)\b/u', $text);
        $compare = (bool)preg_match('/\b(compar\w*|diferenc\w*|vs|versus|cual es mejor|cual de los dos)\b/u', $text);
        $compareLimit = preg_match('/\b(?:3|tres)\b|esas 3|las 3|estas 3/u', $text) ? 3 : 2;
        $models = [];
        if (preg_match_all('/\b(?:[A-Z]{1,8}\s?-?\s?\d{2,5}[A-Z0-9-]*|\d+[A-Z]{1,}\d+[A-Z0-9-]*)\b/u', strtoupper($message), $modelMatches)) {
            $models = array_values(array_unique(array_filter(array_map(fn($m) => trim(preg_replace('/\s+/', ' ', (string)$m) ?? ''), $modelMatches[0]), fn($m) => mb_strlen($m) >= 4)));
        }
        $rejectWhatsapp = (bool)preg_match('/\b(no quiero.*whatsapp|sin whatsapp|no me mandes a whatsapp|no insistas con whatsapp|no quiero ir a whatsapp)\b/u', $text);
        $farewell = (bool)preg_match('/\b(me voy|no sirves|no sirve|adios|hasta luego|no me hagas perder)\b/u', $text);
        $sentiment = (string)($training['sentiment']['slug'] ?? '');
        $priceComplaint = (bool)preg_match('/\b(muy costoso|no es barato|eso no es barato|sigue(s)? mostrando|que caro|no me importa|no me hagas perder)\b/u', $text);
        if ($whyPrice) $priceComplaint = false;
        $frustration = $farewell || in_array($sentiment, ['frustracion', 'enojo', 'decepcion'], true) || $priceComplaint;

        $budgetMax = $this->parseBudget($text);
        if ($cheap || $priceComplaint) {
            $state['budget_band'] = 'cheap';
            $state['budget_max'] = null;
        } elseif ($mid) {
            $state['budget_band'] = 'mid';
            $state['budget_max'] = null;
        } elseif ($budgetMax > 0) {
            $state['budget_band'] = '';
            $state['budget_max'] = $budgetMax;
        }
        if ($subject !== '') $state['subject'] = $subject;
        if ($rejectWhatsapp) {
            $channels = is_array($state['rejected_channels'] ?? null) ? $state['rejected_channels'] : [];
            if (!in_array('whatsapp', $channels, true)) $channels[] = 'whatsapp';
            $state['rejected_channels'] = $channels;
        }
        if ($frustration) $state['frustration_count'] = (int)($state['frustration_count'] ?? 0) + 1;
        Session::set('tia_sales_state', $state);

        return [
            'state' => $state,
            'subject' => $subject,
            'image' => $image,
            'image_only' => $imageOnly,
            'catalog_request' => $catalogRequest,
            'why_price' => $whyPrice,
            'cheap' => $cheap || (string)($state['budget_band'] ?? '') === 'cheap',
            'mid' => $mid || (string)($state['budget_band'] ?? '') === 'mid',
            'recommend' => $recommend,
            'compare' => $compare,
            'compare_limit' => $compareLimit,
            'models' => $models,
            'frustration' => $frustration,
            'farewell' => $farewell,
            'reject_whatsapp' => $rejectWhatsapp,
            'budget_max' => $budgetMax > 0 ? $budgetMax : (int)($state['budget_max'] ?? 0),
            'budget_band' => (string)($state['budget_band'] ?? ''),
            'repeat_count' => (int)($state['repeat_count'] ?? 0),
            'price_mode' => $cheap || $mid || $priceComplaint || $budgetMax > 0 || in_array((string)($state['budget_band'] ?? ''), ['cheap', 'mid'], true),
            'new_price_request' => $cheap || $mid || $priceComplaint || $budgetMax > 0,
        ];
    }

    public function remember(array $cards, string $reply = ''): void
    {
        $state = Session::get('tia_sales_state', []);
        if (!is_array($state)) $state = [];
        $cards = array_values(array_filter($cards, fn($c) => is_array($c) && !empty($c['title'])));
        if ($cards) {
            $state['last_products'] = array_slice($cards, 0, 6);
            $state['last_product'] = $cards[0];
        }
        $refs = array_map(fn($c) => (string)($c['ref'] ?? $c['url'] ?? $c['title'] ?? ''), $cards);
        $signature = $refs ? sha1(implode('|', $refs)) : sha1(mb_substr(trim($reply), 0, 300));
        $state['repeat_count'] = ($signature === (string)($state['last_signature'] ?? '')) ? (int)($state['repeat_count'] ?? 0) + 1 : 1;
        $state['last_signature'] = $signature;
        Session::set('tia_sales_state', $state);
    }

    public function lastProducts(): array
    {
        $state = Session::get('tia_sales_state', []);
        return is_array($state) && is_array($state['last_products'] ?? null) ? $state['last_products'] : [];
    }

    public function rejectedWhatsapp(): bool
    {
        $state = Session::get('tia_sales_state', []);
        return is_array($state) && in_array('whatsapp', (array)($state['rejected_channels'] ?? []), true);
    }

    private function parseBudget(string $text): int
    {
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(millones|millon)\b/u', $text, $m)) {
            return (int)round((float)str_replace(',', '.', $m[1]) * 100000000);
        }
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(mil)\b/u', $text, $m)) {
            return (int)round((float)str_replace(',', '.', $m[1]) * 100000);
        }
        if (preg_match('/(?:hasta|menos de|maximo|presupuesto de|presupuesto:)\s*\$?\s*([\d.]{3,})/u', $text, $m)) {
            return (int)preg_replace('/\D+/', '', $m[1]) * 100;
        }
        if (preg_match('/\b(\d{1,3}(?:[.,]\d{3})+|\d{4,})\b/u', $text, $m)) {
            $digits = (int)preg_replace('/\D+/', '', $m[1]);
            if ($digits >= 1000) return $digits * 100;
        }
        return 0;
    }

    private function fold(string $value): string
    {
        return strtr(mb_strtolower($value), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    }
}
