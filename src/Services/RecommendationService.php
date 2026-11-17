<?php
namespace App\Services;

use App\Core\Database;

class RecommendationService
{
    private const DEFAULTS = [
        'enabled' => true,
        'title' => 'Completa tu sonido',
        'subtitle' => 'Productos que combinan con tu compra',
        'max' => 3,
        'max_more' => 12,
        'mode' => 'smart',
        'show_reasons' => true,
        'hide_out_of_stock' => true,
        'no_repeat' => true,
        'fill_fallback' => true,
        'disabled_slots' => [],
        'discount_enabled' => true,
        'rates' => [0.5, 1.0, 1.5],
        'rounding' => 100,
        'min_discount' => 100,
        'stackable' => true,
    ];

    private static ?array $candidateCache = null;

    public function settings(): array
    {
        $raw = '';
        try {
            $store = (new StoreService())->settings();
            $raw = (string)($store['store_recommendations'] ?? '');
        } catch (\Throwable $e) {
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) $data = [];
        return $this->normalizeSettings($data);
    }

    public function publicConfig(): array
    {
        $s = $this->settings();
        $currency = 'COP';
        try {
            $store = (new StoreService())->settings();
            $currency = (string)($store['store_currency'] ?? 'COP') ?: 'COP';
        } catch (\Throwable $e) {
        }
        return [
            'enabled' => (bool)$s['enabled'],
            'title' => (string)$s['title'],
            'subtitle' => (string)$s['subtitle'],
            'max' => (int)$s['max'],
            'max_more' => (int)$s['max_more'],
            'show_reasons' => (bool)$s['show_reasons'],
            'currency' => $currency,
            'discount' => [
                'enabled' => (bool)$s['discount_enabled'],
                'rates' => array_values($s['rates']),
                'rounding' => (int)$s['rounding'],
            ],
        ];
    }

    public function forCart(array $items, int $limit = 6, array $history = []): array
    {
        $s = $this->settings();
        $config = $this->publicConfig();
        if (!$s['enabled']) return ['ok' => true, 'enabled' => false, 'items' => []] + $config;

        $items = $this->normalizeCart($items);
        if (!$items) return ['ok' => true, 'enabled' => true, 'items' => []] + $config;

        $limit = max(1, min(12, $limit > 0 ? $limit : (int)$s['max_more']));
        $candidates = $this->candidates();
        $index = $this->candidateIndex($candidates);
        $cartKeys = $this->cartKeys($items, $index);
        $affinity = $this->affinity($history, $index);
        $sourceFamilies = [];

        foreach ($items as $item) {
            $product = $index[$item['ref']] ?? null;
            $family = $this->familyOf($product ?: ['name' => $item['title'], 'product_type' => '', 'category_name' => '']);
            if (!isset($sourceFamilies[$family])) $sourceFamilies[$family] = $item;
        }

        $recipes = $this->recipes();
        $selected = [];
        $selectedKeys = [];
        foreach ($sourceFamilies as $sourceFamily => $sourceItem) {
            $sourceProduct = $index[$sourceItem['ref']] ?? null;
            $sourcePrice = (int)($sourceProduct['price_cents'] ?? 0);
            $sourceRecipes = $recipes[$sourceFamily] ?? $recipes['other'];
            foreach ($sourceRecipes as $recipe) {
                if ($s['mode'] === 'essentials' && empty($recipe['essential'])) continue;
                $slot = (string)$recipe['slot'];
                if (in_array($slot, $s['disabled_slots'], true)) continue;
                if (isset($selectedKeys['slot:' . $slot])) continue;
                $candidate = $this->bestForRecipe($candidates, $recipe, $cartKeys, $sourcePrice, $selectedKeys, $s, $sourceFamily, $affinity);
                if (!$candidate) continue;
                $key = strtolower((string)$candidate['slug']);
                if (isset($selectedKeys['item:' . $key])) continue;
                $selectedKeys['slot:' . $slot] = true;
                $selectedKeys['item:' . $key] = true;
                $candidate['_reason'] = (string)$recipe['reason'];
                $candidate['_label'] = (string)$recipe['label'];
                $candidate['_slot'] = $slot;
                $candidate['_source_family'] = $sourceFamily;
                $selected[] = $candidate;
                if (count($selected) >= $limit) break 2;
            }
        }

        if (count($selected) < $limit && $s['fill_fallback']) {
            foreach ($candidates as $candidate) {
                if (count($selected) >= $limit) break;
                $key = strtolower((string)$candidate['slug']);
                if (isset($cartKeys[$key]) || isset($selectedKeys['item:' . $key])) continue;
                if (!$this->available($candidate, $s)) continue;
                if ($candidate['family'] !== 'accessory') continue;
                $selectedKeys['item:' . $key] = true;
                $candidate['_reason'] = 'Un complemento útil para tu compra';
                $candidate['_label'] = 'Complemento';
                $candidate['_slot'] = 'fallback';
                $candidate['_source_family'] = 'other';
                $selected[] = $candidate;
            }
        }

        $out = [];
        foreach ($selected as $c) {
            $out[] = $this->card($c, $s);
        }
        return ['ok' => true, 'enabled' => true, 'items' => $out] + $config;
    }

    /**
     * Returns the refs (lowercase) of recommended products that are eligible for
     * the cross-sell discount. Only products suggested for the non-recommended
     * cart can receive the benefit.
     */
    public function eligibleRefs(array $items): array
    {
        $base = [];
        $recommended = [];
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $ref = strtolower(trim((string)($item['ref'] ?? ($item['handle'] ?? ''))));
            if ($ref === '') continue;
            $rec = $item['rec'] ?? null;
            if (is_array($rec) && (string)($rec['source'] ?? '') === 'recommendations') $recommended[$ref] = true;
            else $base[] = $item;
        }
        if (!$recommended || !$base) return [];
        $suggest = $this->forCart($base, 12);
        $allowed = [];
        foreach ((array)($suggest['items'] ?? []) as $card) {
            $handle = strtolower((string)($card['handle'] ?? ''));
            if ($handle !== '') $allowed[$handle] = true;
        }
        $out = [];
        foreach ($recommended as $ref => $_) {
            if (isset($allowed[$ref])) $out[$ref] = true;
        }
        return $out;
    }

    private function normalizeSettings(array $data): array
    {
        $s = self::DEFAULTS;
        foreach (['enabled', 'show_reasons', 'hide_out_of_stock', 'no_repeat', 'fill_fallback', 'discount_enabled', 'stackable'] as $key) {
            if (array_key_exists($key, $data)) $s[$key] = (bool)$data[$key];
        }
        foreach (['title', 'subtitle'] as $key) {
            if (array_key_exists($key, $data)) {
                $v = trim((string)$data[$key]);
                if ($v !== '') $s[$key] = mb_substr($v, 0, 120);
            }
        }
        $s['max'] = max(1, min(3, (int)($data['max'] ?? $s['max'])));
        $s['max_more'] = max($s['max'], min(12, (int)($data['max_more'] ?? $s['max_more'])));
        $mode = (string)($data['mode'] ?? $s['mode']);
        $s['mode'] = in_array($mode, ['smart', 'essentials', 'mix'], true) ? $mode : 'smart';
        $s['rounding'] = in_array((int)($data['rounding'] ?? $s['rounding']), [10, 50, 100], true) ? (int)($data['rounding'] ?? $s['rounding']) : 100;
        $s['min_discount'] = max(0, (int)($data['min_discount'] ?? $s['min_discount']));
        $rates = [];
        foreach ((array)($data['rates'] ?? $s['rates']) as $rate) {
            $rate = (float)$rate;
            if ($rate >= 0 && $rate <= 10) $rates[] = $rate;
        }
        if (count($rates) >= 3) $s['rates'] = array_slice($rates, 0, 3);
        $slots = [];
        foreach ((array)($data['disabled_slots'] ?? []) as $slot) {
            $slot = preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)$slot));
            if ($slot !== '') $slots[] = $slot;
        }
        $s['disabled_slots'] = array_values(array_unique($slots));
        return $s;
    }

    private function normalizeCart(array $items): array
    {
        $out = [];
        foreach (array_slice($items, 0, 50) as $item) {
            if (!is_array($item)) continue;
            $ref = trim((string)($item['ref'] ?? ($item['handle'] ?? ($item['slug'] ?? ''))));
            if ($ref === '') continue;
            $out[] = [
                'ref' => strtolower($ref),
                'title' => trim((string)($item['title'] ?? '')),
                'qty' => max(1, min(99, (int)($item['qty'] ?? 1))),
                'rec' => is_array($item['rec'] ?? null) ? $item['rec'] : null,
            ];
        }
        return $out;
    }

    private function candidates(): array
    {
        if (self::$candidateCache !== null) return self::$candidateCache;
        $out = [];
        try {
            $sql = "SELECT p.id, p.name, p.slug, p.catalog_handle, p.price_cents, p.stock, p.track_stock,
                    p.images, p.vendor, p.product_type, p.is_featured, c.name AS category_name
                    FROM store_products p
                    LEFT JOIN store_categories c ON c.id = p.category_id
                    WHERE p.site_id = @site_id AND p.status = 'active'
                      AND (p.visibility IS NULL OR p.visibility = '' OR p.visibility = 'public')
                    ORDER BY p.is_featured DESC, p.sort_order ASC, p.id DESC
                    LIMIT 5000";
            $stmt = Database::instance()->query($sql);
            foreach ($stmt->fetchAll() as $row) {
                $images = json_decode((string)($row['images'] ?? ''), true);
                $row['images'] = is_array($images) ? array_values(array_filter($images, 'is_string')) : [];
                $row['id'] = (int)$row['id'];
                $row['price_cents'] = (int)$row['price_cents'];
                $row['stock'] = (int)$row['stock'];
                $row['track_stock'] = (int)$row['track_stock'];
                $row['is_featured'] = (int)$row['is_featured'];
                $row['family'] = $this->familyOf($row);
                $row['name_hay'] = $this->fold((string)($row['name'] ?? ''));
                $row['hay'] = $row['name_hay'];
                if ($row['price_cents'] > 0) $out[] = $row;
            }
        } catch (\Throwable $e) {
        }
        return self::$candidateCache = $out;
    }

    private function candidateIndex(array $candidates): array
    {
        $idx = [];
        foreach ($candidates as $candidate) {
            foreach (['slug', 'catalog_handle'] as $key) {
                $ref = strtolower(trim((string)($candidate[$key] ?? '')));
                if ($ref !== '') $idx[$ref] = $candidate;
            }
            $idx[(string)$candidate['id']] = $candidate;
        }
        return $idx;
    }

    private function cartKeys(array $items, array $index): array
    {
        $keys = [];
        foreach ($items as $item) {
            $keys[$item['ref']] = true;
            if (isset($index[$item['ref']])) {
                $c = $index[$item['ref']];
                $keys[strtolower((string)$c['slug'])] = true;
                $keys[strtolower((string)($c['catalog_handle'] ?? ''))] = true;
                $keys[(string)$c['id']] = true;
            }
        }
        unset($keys['']);
        return $keys;
    }

    private function bestForRecipe(array $candidates, array $recipe, array $cartKeys, int $sourcePrice, array $selectedKeys, array $settings, string $sourceFamily = '', array $affinity = []): ?array
    {
        $best = null;
        $bestScore = -1;
        $match = (string)$recipe['match'];
        $targetFamily = (string)$recipe['family'];
        foreach ($candidates as $candidate) {
            if ($candidate['family'] !== $targetFamily) continue;
            if (!$this->available($candidate, $settings)) continue;
            $key = strtolower((string)$candidate['slug']);
            if (isset($cartKeys[$key]) || isset($selectedKeys['item:' . $key])) continue;
            if ($match !== '' && !preg_match($match, $candidate['hay'])) continue;
            if (!empty($recipe['exclude']) && preg_match((string)$recipe['exclude'], $candidate['hay'])) continue;
            if ($sourceFamily === 'guitar_electric' && ($recipe['slot'] ?? '') === 'strings' && preg_match('/(nylon|clasica|clasico|espanol|bajo|bass|violin)/u', $candidate['hay'])) continue;
            if ($sourceFamily === 'guitar_acoustic' && ($recipe['slot'] ?? '') === 'strings' && preg_match('/(nylon|clasica|clasico|espanol|bajo|bass|violin)/u', $candidate['hay'])) continue;
            if (in_array($sourceFamily, ['guitar_electric', 'guitar_acoustic', 'guitar_classical'], true) && ($recipe['slot'] ?? '') === 'in_ears' && preg_match('/(stand|base|soporte|atril|partitura)/u', $candidate['hay'])) continue;
            if (in_array($sourceFamily, ['guitar_electric', 'guitar_acoustic', 'guitar_classical'], true) && ($recipe['slot'] ?? '') === 'case' && preg_match('/(stand|atril|partitura|soporte)/u', $candidate['hay'])) continue;
            if ($sourceFamily === 'guitar_acoustic' && ($recipe['slot'] ?? '') === 'pedal' && preg_match('/(teclado|piano|sustain)/u', $candidate['hay'])) continue;
            if ($sourceFamily === 'guitar_acoustic' && ($recipe['slot'] ?? '') === 'mic' && preg_match('/(solapa|lavalier|camara|usb|diadema|inalambrico|inhalambrico)/u', $candidate['hay'])) continue;
            if ($sourceFamily === 'guitar_acoustic' && ($recipe['slot'] ?? '') === 'case' && preg_match('/(ukelele|ukulele)/u', $candidate['hay'])) continue;
            if ($sourceFamily === 'guitar_acoustic' && ($recipe['slot'] ?? '') === 'amp' && !preg_match('/(acoustic|acustic|guitarra|guitar)/u', $candidate['hay'])) continue;
            if (in_array($sourceFamily, ['guitar_electric', 'guitar_acoustic', 'guitar_classical'], true) && ($recipe['slot'] ?? '') === 'stand' && preg_match('/(teclado|piano|microfono|mic|bateria|percusion|trompeta|corneta|saxo)/u', $candidate['hay'])) continue;
            if ($sourceFamily === 'piano' && ($recipe['slot'] ?? '') === 'stand' && !preg_match('/(teclado|piano|keyboard)/u', $candidate['hay'])) continue;
            if ($sourceFamily === 'bass' && ($recipe['slot'] ?? '') === 'amp' && !preg_match('/(bajo|bass)/u', $candidate['hay'])) continue;
            if ($sourceFamily === 'piano' && ($recipe['slot'] ?? '') === 'cover' && preg_match('/(ukelele|ukulele|guitarra|bajo|bass)/u', $candidate['hay'])) continue;
            if ($sourceFamily === 'mic' && ($recipe['slot'] ?? '') === 'mic_stand' && !preg_match('/(microfono|mic|pie)/u', $candidate['hay'])) continue;
            if ($sourceFamily === 'bass' && ($recipe['slot'] ?? '') === 'strings' && preg_match('/(guitarra|guitar|clea|cleaner|sordina|fretwrap|nylon|violin|viola|chelo|cello)/u', $candidate['hay'])) continue;
            if ($sourceFamily === 'bass' && ($recipe['slot'] ?? '') === 'strap' && !preg_match('/(bajo|bass)/u', $candidate['hay'])) continue;
            if (($recipe['slot'] ?? '') === 'picks' && preg_match('/(correa|strap|pin|clavija|funda)/u', $candidate['hay'])) continue;
            if (in_array($sourceFamily, ['guitar_electric', 'guitar_acoustic', 'guitar_classical'], true) && ($recipe['slot'] ?? '') === 'amp' && preg_match('/(ukelele|ukulele|bajo|bass|teclado|piano)/u', $candidate['hay'])) continue;
            $score = (int)$recipe['priority'] * 10;
            if (!empty($candidate['is_featured'])) $score += 5;
            if ((int)($candidate['stock'] ?? 0) > 0) $score += 2;
            if ($sourcePrice > 0 && $candidate['price_cents'] <= $sourcePrice * 2) $score += 2;
            $brand = strtolower(trim((string)($candidate['vendor'] ?? '')));
            $category = strtolower(trim((string)($candidate['category_name'] ?? '')));
            if ($brand !== '' && isset($affinity['brands'][$brand])) $score += 12;
            if ($category !== '' && isset($affinity['categories'][$category])) $score += 5;
            if (isset($affinity['families'][(string)$candidate['family']])) $score += 2;
            $termHits = 0;
            foreach ((array)($affinity['terms'] ?? []) as $term) {
                if ($term !== '' && str_contains((string)$candidate['hay'], $term)) $termHits++;
            }
            $score += min(6, $termHits * 2);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $candidate;
            }
        }
        return $best;
    }

    private function affinity(array $history, array $index): array
    {
        $out = ['brands' => [], 'categories' => [], 'families' => [], 'terms' => []];
        foreach ((array)($history['products'] ?? []) as $entry) {
            if (!is_array($entry)) continue;
            $ref = strtolower(trim((string)($entry['handle'] ?? $entry['ref'] ?? '')));
            if ($ref !== '' && isset($index[$ref])) {
                $candidate = $index[$ref];
                $vendor = strtolower(trim((string)($candidate['vendor'] ?? '')));
                $category = strtolower(trim((string)($candidate['category_name'] ?? '')));
                $family = (string)($candidate['family'] ?? '');
                if ($vendor !== '') $out['brands'][$vendor] = true;
                if ($category !== '') $out['categories'][$category] = true;
                if ($family !== '') $out['families'][$family] = true;
            }
            $title = strtolower(trim((string)($entry['title'] ?? '')));
            foreach (preg_split('/\s+/', $title) ?: [] as $word) {
                if (mb_strlen($word) >= 4 && !is_numeric($word)) $out['terms'][$word] = true;
            }
        }
        foreach ((array)($history['categories'] ?? []) as $entry) {
            if (!is_array($entry)) continue;
            foreach (['name', 'slug'] as $field) {
                $category = strtolower(trim((string)($entry[$field] ?? '')));
                if ($category !== '') $out['categories'][$category] = true;
            }
        }
        foreach ((array)($history['searches'] ?? []) as $entry) {
            $query = is_array($entry) ? (string)($entry['q'] ?? '') : (string)$entry;
            foreach (preg_split('/\s+/', strtolower(trim($query))) ?: [] as $word) {
                if (mb_strlen($word) >= 3 && !is_numeric($word)) $out['terms'][$word] = true;
            }
        }
        $out['terms'] = array_slice(array_keys($out['terms']), 0, 12);
        return $out;
    }

    private function available(array $candidate, array $settings): bool
    {
        if ((int)$candidate['price_cents'] <= 0) return false;
        if (!empty($settings['hide_out_of_stock']) && (int)$candidate['track_stock'] === 1 && (int)$candidate['stock'] <= 0) return false;
        return true;
    }

    private function card(array $c, array $s): array
    {
        $image = (string)($c['images'][0] ?? '');
        return [
            'handle' => (string)$c['slug'],
            'id' => (int)$c['id'],
            'title' => (string)$c['name'],
            'price_cents' => (int)$c['price_cents'],
            'image' => $image,
            'url' => '/producto/' . rawurlencode((string)$c['slug']),
            'vendor' => (string)($c['vendor'] ?? ''),
            'family' => (string)($c['family'] ?? ''),
            'source_family' => (string)($c['_source_family'] ?? ''),
            'slot' => (string)($c['_slot'] ?? ''),
            'label' => (string)($c['_label'] ?? ''),
            'reason' => $s['show_reasons'] ? (string)($c['_reason'] ?? '') : '',
        ];
    }

    private function familyOf(array $p): string
    {
        $name = $this->fold((string)($p['name'] ?? ''));
        $type = $this->fold((string)($p['product_type'] ?? ''));
        $hay = $this->fold($name . ' ' . $type);
        $cat = $this->fold((string)($p['category_name'] ?? ''));

        // El nombre comercial del instrumento manda si abre el título.
        if (preg_match('/^(bajo|bass|contrabajo)\b/u', $name)) return 'bass';
        if (preg_match('/^(bateria|percusion|timbal|conga|bongo|djembe|cajon|redoblante|tarola)\b/u', $name)) return 'drums';

        // El tipo de producto manda cuando identifica un instrumento completo.
        if (preg_match('/(^|\s)(pianos?|teclados?|keyboard|sintetizador|synthesizer|organos?|organ)(\s|$)/u', $type)) return 'piano';
        if (preg_match('/(^|\s)(percusion|baterias?|tambor|redoblante|tarola)(\s|$)/u', $type)) return 'drums';
        if (preg_match('/(^|\s)(bajos?|bass|contrabajo)(\s|$)/u', $type)) return 'bass';
        if (preg_match('/(^|\s)(guitarras?|guitar)(\s|$)/u', $type)) {
            return preg_match('/(acustic|electroacustic|acoustic|clasic|espanol|nylon)/u', $hay) ? 'guitar_acoustic' : 'guitar_electric';
        }
        if (preg_match('/(^|\s)(vientos?|acordeon|accordion)(\s|$)/u', $type)) {
            return preg_match('/(acordeon|accordion)/u', $hay) ? 'accordion' : 'wind';
        }
        if (preg_match('/(^|\s)(violin|viola|violonchelo|cello|chelo)(\s|$)/u', $type)) return 'violin';
        if (preg_match('/(^|\s)(microfonos?|microphones?|mic)(\s|$)/u', $type)) return 'mic';
        if (preg_match('/(^|\s)(amplificadores?|amplifier|preamp|cabina|cabinete|combo)(\s|$)/u', $type)) return 'amp';
        // El nombre comercial del instrumento manda si abre el título. Así
        // "MICRÓFONO CON CABLE" no se confunde con un accesorio.
        if (preg_match('/^(microfono|microfone|mic)\b/u', $name)) return 'mic';
        if (preg_match('/^(teclado|piano|sintetizador|organo)\b/u', $name)) return 'piano';
        if (preg_match('/^(guitarra|guitar)\b/u', $name)) {
            if (preg_match('/(acustic|electroacustic|acoustic|clasic|espanol|nylon)/u', $name)) {
                return preg_match('/(clasic|espanol|nylon)/u', $name) ? 'guitar_classical' : 'guitar_acoustic';
            }
            return 'guitar_electric';
        }
        if (preg_match('/^(bajo|bass|contrabajo)\b/u', $name)) return 'bass';
        if (preg_match('/^(bateria|percusion|timbal|conga|bongo|djembe|cajon|redoblante|tarola)\b/u', $name)) return 'drums';
        if (preg_match('/^(amplificador|amplifier|combo)\b/u', $name)) return 'amp';
        if (preg_match('/^(consola|mezcladora|mixer|parlante|bafle|altavoz|monitor|subwoofer|interfaz|audio)\b/u', $name)) return 'audio';
        if (preg_match('/^(acordeon|accordion)\b/u', $name)) return 'accordion';
        if (preg_match('/^(violin|viola|violonchelo|cello|chelo)\b/u', $name)) return 'violin';
        if (preg_match('/^(saxo|saxofon|trompeta|clarinete|flauta|trombon|corneta|tuba|oboe|fagot)\b/u', $name)) return 'wind';
        if (preg_match('/(^|\s)(accesorios?|accessories)(\s|$)/u', $type)) return 'accessory';

        // Un accesorio suele empezar por su nombre comercial.
        if (preg_match('/^(stand|base|soporte|atril|funda|forro|estuche|correa|pin|pua|pick|cable|capo|afinador|banco|trono|audifono|auricular|metronomo|fuente|baqueta|baquetero|parche|llave|pie)\b/u', $name)) return 'accessory';
        if (preg_match('/\b(pedal|distorsion|distortion|overdrive|fuzz|chorus|delay|reverb|wah|compresor|compressor)\b/u', $hay)) return 'pedal';
        if (preg_match('/\b(accesorio|funda|forro|estuche|correa|cable|soporte|atril|baqueta|baquetero|repuesto|herraje|stand|cuerda|encordado|capo|capodastro|pua|afinador|banco|trono|auricular|audifono|pop filter|shock mount|pie de micro|pie micro)\b/u', $hay)) return 'accessory';
        if (preg_match('/\b(cuerdas?|encordados?|cables?|correas?|fundas?|estuches?|soportes?|bases?|púas?|puas?|afinadores?)\b/u', $hay)) return 'accessory';
        if (preg_match('/\b(amplificador|amplifier|preamplificador|preamp|cabina|cabinete|combo)\b/u', $hay)) return 'amp';
        if (preg_match('/\b(acordeon|accordion)\b/u', $hay)) return 'accordion';
        if (preg_match('/\b(bajo|bass|contrabajo)\b/u', $hay)) return 'bass';
        if (preg_match('/\b(guitarra|guitar)\b/u', $hay)) {
            if (preg_match('/(acustic|electroacustic|acoustic|clasic|espanol|nylon)/u', $hay)) {
                return preg_match('/(clasic|espanol|nylon)/u', $hay) ? 'guitar_classical' : 'guitar_acoustic';
            }
            return 'guitar_electric';
        }
        if (preg_match('/\b(dj|disc jockey|controlador dj)\b/u', $hay)) return 'dj';
        if (preg_match('/\b(piano|teclado|keyboard|sintetizador|synthesizer|organo|organ)\b/u', $hay)) return 'piano';
        if (preg_match('/\b(bateria|percusion|tambor|timbal|conga|bongo|djembe|cajon|redoblante|tarola|platillo|cymbal|drum)\b/u', $hay)) return 'drums';
        if (preg_match('/\b(violin|viola|violonchelo|cello|chelo)\b/u', $hay)) return 'violin';
        if (preg_match('/\b(saxo|saxofon|trompeta|clarinete|flauta|trombon|corneta|vientos|tuba|oboe|fagot)\b/u', $hay)) return 'wind';
        if (preg_match('/\b(microfono|microfone|microphone|mic)\b/u', $hay)) return 'mic';
        if (preg_match('/\b(audio|parlante|bafle|altavoz|consola|mezcladora|mixer|monitor|subwoofer|interfaz|interface|multicore)\b/u', $hay)) return 'audio';
        if (preg_match('/\b(iluminacion|luz|luces|led|laser|spot|reflector|cabezal movil)\b/u', $hay)) return 'lighting';
        if (str_contains($cat, 'accesorio')) return 'accessory';
        if (str_contains($cat, 'bajo')) return 'bass';
        if (str_contains($cat, 'guitarra')) return 'guitar_electric';
        if (str_contains($cat, 'piano') || str_contains($cat, 'teclado')) return 'piano';
        if (str_contains($cat, 'percusion') || str_contains($cat, 'bateria')) return 'drums';
        if (str_contains($cat, 'viento')) return 'wind';
        return 'other';
    }

    private function fold(string $value): string
    {
        $value = mb_strtolower($value, 'UTF-8');
        $value = strtr($value, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }

    private function recipes(): array
    {
        return [
            'guitar_electric' => [
                ['slot' => 'strings', 'label' => 'Cuerdas', 'exclude' => '/(clea|cleaner|limpiador|limpieza|sordina|fretwrap|mute|silenciador|protector|manija|ajusta|clavija|maquina|maquinaria|repuesto|selector|violin|viola|chelo|cello|piano|stand|base|soporte|pin|abrazadera|clamp|unidad|atril|herraje)/u', 'family' => 'accessory', 'match' => '/(cuerda|encordado|string)/u', 'reason' => 'Cuerdas nuevas para tu guitarra', 'priority' => 100, 'essential' => true],
                ['slot' => 'strap', 'label' => 'Correa', 'exclude' => '/(pin|clavija|abrazadera|clamp|unidad|herraje|saxo|saxofon|clarinete|trompeta|flauta|trombon)/u', 'family' => 'accessory', 'match' => '/(correa|strap)/u', 'reason' => 'Toca cómodo con una correa', 'priority' => 96, 'essential' => true],
                ['slot' => 'picks', 'label' => 'Púas', 'family' => 'accessory', 'match' => '/(pua|púas|pick|plectro)/u', 'reason' => 'Púas para tu forma de tocar', 'priority' => 94, 'essential' => true],
                ['slot' => 'cable', 'label' => 'Cable', 'exclude' => '/(abrazadera|clamp|unidad|organizador|soporte|stand|base|microfono|mic|parlante|bafle)/u', 'family' => 'accessory', 'match' => '/(cable|jack|plug)/u', 'reason' => 'Conecta tu guitarra sin interrupciones', 'priority' => 90, 'essential' => true],
                ['slot' => 'tuner', 'label' => 'Afinador', 'family' => 'accessory', 'match' => '/(afinador|tuner)/u', 'reason' => 'Mantén la afinación en cada ensayo', 'priority' => 86, 'essential' => true],
                ['slot' => 'stand', 'label' => 'Base', 'exclude' => '/(trompeta|corneta|saxo|saxofon|clarinete|flauta|trombon|microfono|mic|parlante|bafle|bateria|percusion)/u', 'family' => 'accessory', 'match' => '/(base|stand|soporte)/u', 'reason' => 'Un lugar seguro para tu instrumento', 'priority' => 82],
                ['slot' => 'amp', 'label' => 'Amplificador', 'family' => 'amp', 'match' => '/(amplificador|amplifier|combo)/u', 'reason' => 'Amplifica el sonido de tu guitarra', 'priority' => 78],
                ['slot' => 'pedal', 'label' => 'Pedal', 'family' => 'pedal', 'match' => '/(pedal|distorsion|overdrive|fuzz)/u', 'reason' => 'Explora nuevos tonos y efectos', 'priority' => 74],
                ['slot' => 'mic', 'label' => 'Micrófono', 'family' => 'mic', 'match' => '/(microfono|mic)/u', 'reason' => 'Graba o amplifica tu sonido', 'priority' => 70],
                ['slot' => 'case', 'label' => 'Funda', 'family' => 'accessory', 'match' => '/(funda|forro|estuche|gig bag)/u', 'reason' => 'Protege tu guitarra cuando sales', 'priority' => 66],
                ['slot' => 'power', 'label' => 'Fuente', 'family' => 'accessory', 'match' => '/(fuente|power supply|adaptador)/u', 'reason' => 'Alimenta tus pedales', 'priority' => 62],
            ],
            'guitar_acoustic' => [
                ['slot' => 'strings', 'label' => 'Cuerdas', 'exclude' => '/(clea|cleaner|limpiador|limpieza|sordina|fretwrap|mute|silenciador|protector|manija|ajusta|clavija|maquina|maquinaria|repuesto|selector|violin|viola|chelo|cello|piano|stand|base|soporte|pin|abrazadera|clamp|unidad|atril|herraje)/u', 'family' => 'accessory', 'match' => '/(cuerda|encordado|string)/u', 'reason' => 'Cuerdas acústicas para renovar tu sonido', 'priority' => 100, 'essential' => true],
                ['slot' => 'capo', 'label' => 'Capo', 'family' => 'accessory', 'match' => '/(capo|capodastro)/u', 'reason' => 'Cambia de tonalidad con facilidad', 'priority' => 96, 'essential' => true],
                ['slot' => 'strap', 'label' => 'Correa', 'exclude' => '/(pin|clavija|abrazadera|clamp|unidad|herraje|saxo|saxofon|clarinete|trompeta|flauta|trombon)/u', 'family' => 'accessory', 'match' => '/(correa|strap)/u', 'reason' => 'Toca de pie con comodidad', 'priority' => 94, 'essential' => true],
                ['slot' => 'picks', 'label' => 'Púas', 'family' => 'accessory', 'match' => '/(pua|púas|pick|plectro)/u', 'reason' => 'Púas para acompañar tus canciones', 'priority' => 90],
                ['slot' => 'tuner', 'label' => 'Afinador', 'family' => 'accessory', 'match' => '/(afinador|tuner)/u', 'reason' => 'Afinación precisa en segundos', 'priority' => 86],
                ['slot' => 'stand', 'label' => 'Base', 'exclude' => '/(trompeta|corneta|saxo|saxofon|clarinete|flauta|trombon|microfono|mic|parlante|bafle|bateria|percusion)/u', 'family' => 'accessory', 'match' => '/(base|stand|soporte)/u', 'reason' => 'Protege tu guitarra entre canciones', 'priority' => 82],
                ['slot' => 'humidifier', 'label' => 'Humidificador', 'family' => 'accessory', 'match' => '/(humidificador|humidity)/u', 'reason' => 'Cuida la madera de tu guitarra', 'priority' => 76],
                ['slot' => 'amp', 'label' => 'Amplificador', 'family' => 'amp', 'match' => '/(amplificador|amplifier|acoustic)/u', 'reason' => 'Amplifica tu guitarra acústica', 'priority' => 72],
                ['slot' => 'cable', 'label' => 'Cable', 'exclude' => '/(abrazadera|clamp|unidad|organizador|soporte|stand|base|microfono|mic|parlante|bafle)/u', 'family' => 'accessory', 'match' => '/(cable|jack|plug)/u', 'reason' => 'Conecta tu guitarra electroacústica', 'priority' => 68],
                ['slot' => 'mic', 'label' => 'Micrófono', 'family' => 'mic', 'match' => '/(microfono|mic)/u', 'reason' => 'Graba tu guitarra con claridad', 'priority' => 64],
                ['slot' => 'case', 'label' => 'Funda', 'family' => 'accessory', 'match' => '/(funda|forro|estuche|gig bag)/u', 'reason' => 'Transporta tu guitarra protegida', 'priority' => 60],
                ['slot' => 'in_ears', 'label' => 'In-ears', 'exclude' => '/(cable|repuesto|adaptador|forro|funda)/u', 'family' => 'accessory', 'match' => '/(in.?ear|inear|iem|monitor|auricular|audifono)/u', 'reason' => 'Escúchate con claridad mientras tocas', 'priority' => 58],
                ['slot' => 'pedal', 'label' => 'Pedal / efectos', 'family' => 'pedal', 'match' => '/(pedal|chorus|delay|reverb|overdrive|distorsion)/u', 'reason' => 'Explora efectos para tu guitarra acústica', 'priority' => 56],
            ],
            'guitar_classical' => [
                ['slot' => 'strings', 'label' => 'Cuerdas de nylon', 'family' => 'accessory', 'match' => '/(cuerda|encordado|string|nylon)/u', 'reason' => 'Cuerdas de nylon para tu guitarra', 'priority' => 100, 'essential' => true],
                ['slot' => 'footstool', 'label' => 'Reposapié', 'family' => 'accessory', 'match' => '/(reposapie|footstool|apoyapie)/u', 'reason' => 'Mejora tu postura al tocar', 'priority' => 94, 'essential' => true],
                ['slot' => 'tuner', 'label' => 'Afinador', 'family' => 'accessory', 'match' => '/(afinador|tuner)/u', 'reason' => 'Afinación estable para estudiar', 'priority' => 90, 'essential' => true],
                ['slot' => 'capo', 'label' => 'Capo', 'family' => 'accessory', 'match' => '/(capo|capodastro)/u', 'reason' => 'Explora otras tonalidades', 'priority' => 84],
                ['slot' => 'stand', 'label' => 'Atril', 'family' => 'accessory', 'match' => '/(atril|stand|soporte)/u', 'reason' => 'Ten tus partituras a la vista', 'priority' => 78],
                ['slot' => 'metronome', 'label' => 'Metrónomo', 'family' => 'accessory', 'match' => '/(metronomo|metronome)/u', 'reason' => 'Practica con tiempo constante', 'priority' => 74],
                ['slot' => 'case', 'label' => 'Funda', 'family' => 'accessory', 'match' => '/(funda|forro|estuche|case)/u', 'reason' => 'Protege tu guitarra clásica', 'priority' => 70],
            ],
            'bass' => [
                ['slot' => 'strings', 'label' => 'Cuerdas de bajo', 'exclude' => '/(clea|cleaner|sordina|fretwrap|manija|nylon|violin|viola|chelo|cello|guitarra|guitar)/u', 'family' => 'accessory', 'match' => '/(cuerda|encordado|string)/u', 'reason' => 'Cuerdas para recuperar el pulso de tu bajo', 'priority' => 100, 'essential' => true],
                ['slot' => 'strap', 'label' => 'Correa ancha', 'exclude' => '/(pin|clavija|abrazadera|clamp|unidad|herraje|saxo|saxofon)/u', 'family' => 'accessory', 'match' => '/(correa|strap)/u', 'reason' => 'Soporte cómodo para un bajo pesado', 'priority' => 95, 'essential' => true],
                ['slot' => 'cable', 'label' => 'Cable', 'exclude' => '/(abrazadera|clamp|unidad|organizador|soporte|stand|base|microfono|mic|parlante|bafle)/u', 'family' => 'accessory', 'match' => '/(cable|jack|plug)/u', 'reason' => 'Conecta tu bajo al amplificador', 'priority' => 92, 'essential' => true],
                ['slot' => 'tuner', 'label' => 'Afinador', 'family' => 'accessory', 'match' => '/(afinador|tuner)/u', 'reason' => 'Afinación precisa para el bajo', 'priority' => 86],
                ['slot' => 'amp', 'label' => 'Amplificador', 'family' => 'amp', 'match' => '/(amplificador|amplifier|combo)/u', 'reason' => 'El amplificador para tu bajo', 'priority' => 82],
                ['slot' => 'di', 'label' => 'DI / preamplificador', 'family' => 'audio', 'match' => '/\b(di|preamp|preamplificador|interfaz|interface)\b/u', 'reason' => 'Envía tu bajo a consola o estudio', 'priority' => 78],
                ['slot' => 'compressor', 'label' => 'Compresor', 'family' => 'pedal', 'match' => '/(compresor|compressor)/u', 'reason' => 'Controla la dinámica de tu bajo', 'priority' => 74],
                ['slot' => 'pedal', 'label' => 'Pedal', 'family' => 'pedal', 'match' => '/(pedal|overdrive|fuzz|chorus)/u', 'reason' => 'Amplía tu paleta de sonidos', 'priority' => 70],
                ['slot' => 'stand', 'label' => 'Base', 'exclude' => '/(trompeta|corneta|saxo|saxofon|clarinete|flauta|trombon|microfono|mic|parlante|bafle|bateria|percusion)/u', 'family' => 'accessory', 'match' => '/(base|stand|soporte)/u', 'reason' => 'Un soporte firme para tu bajo', 'priority' => 66],
                ['slot' => 'case', 'label' => 'Funda', 'family' => 'accessory', 'match' => '/(funda|forro|estuche|gig bag)/u', 'reason' => 'Transporta tu bajo protegido', 'priority' => 62],
            ],
            'piano' => [
                ['slot' => 'bench', 'label' => 'Banco', 'family' => 'accessory', 'match' => '/(banco|bench|silla)/u', 'reason' => 'Toca con una postura cómoda', 'priority' => 100, 'essential' => true],
                ['slot' => 'sustain', 'label' => 'Pedal sustain', 'family' => 'pedal', 'match' => '/(pedal|sustain|sostenido)/u', 'reason' => 'Controla la resonancia de tu piano', 'priority' => 96, 'essential' => true],
                ['slot' => 'headphones', 'label' => 'Audífonos', 'exclude' => '/(cable|repuesto|adaptador|forro|funda)/u', 'family' => 'accessory', 'match' => '/(audifono|auricular|headphone)/u', 'reason' => 'Practica sin molestar a los demás', 'priority' => 92, 'essential' => true],
                ['slot' => 'stand', 'label' => 'Base', 'exclude' => '/(trompeta|corneta|saxo|saxofon|clarinete|flauta|trombon|microfono|mic|parlante|bafle|bateria|percusion)/u', 'family' => 'accessory', 'match' => '/(base|stand|soporte)/u', 'reason' => 'Un soporte estable para tu teclado', 'priority' => 86],
                ['slot' => 'cover', 'label' => 'Cubierta', 'exclude' => '/(stand|atril|lampara|clip|led|luz|timbal|bateria|percusion|tambor|conga|bongo|platillo|sabian)/u', 'family' => 'accessory', 'match' => '/(cubierta|cover|forro|funda)/u', 'reason' => 'Protege tu piano del polvo', 'priority' => 80],
                ['slot' => 'music_stand', 'label' => 'Atril', 'exclude' => '/(lampara|clip|led|luz)/u', 'family' => 'accessory', 'match' => '/(atril|music stand)/u', 'reason' => 'Ten tus partituras a la vista', 'priority' => 76],
                ['slot' => 'midi', 'label' => 'MIDI / interfaz', 'family' => 'audio', 'match' => '/(midi|interfaz|interface)/u', 'reason' => 'Conecta tu piano a tu computador', 'priority' => 72],
                ['slot' => 'metronome', 'label' => 'Metrónomo', 'family' => 'accessory', 'match' => '/(metronomo|metronome)/u', 'reason' => 'Estudia con un pulso constante', 'priority' => 68],
            ],
            'drums' => [
                ['slot' => 'sticks', 'label' => 'Baquetas', 'family' => 'accessory', 'match' => '/(baqueta|baquetero|stick)/u', 'reason' => 'Baquetas de repuesto para tu batería', 'priority' => 100, 'essential' => true],
                ['slot' => 'practice_pad', 'label' => 'Practice pad', 'family' => 'accessory', 'match' => '/(practice|pad|almohadilla)/u', 'reason' => 'Practica en casa sin hacer ruido', 'priority' => 96, 'essential' => true],
                ['slot' => 'ear_protection', 'label' => 'Protección auditiva', 'family' => 'accessory', 'match' => '/(proteccion|auditiva|orejera|ear protection|tapones)/u', 'reason' => 'Cuida tu audición en cada ensayo', 'priority' => 92, 'essential' => true],
                ['slot' => 'throne', 'label' => 'Trono', 'family' => 'accessory', 'match' => '/(trono|throne|silla)/u', 'reason' => 'Toca cómodo durante horas', 'priority' => 86],
                ['slot' => 'metronome', 'label' => 'Metrónomo', 'family' => 'accessory', 'match' => '/(metronomo|metronome)/u', 'reason' => 'Mejora tu tempo', 'priority' => 82],
                ['slot' => 'drum_key', 'label' => 'Llave de batería', 'family' => 'accessory', 'match' => '/(llave|drum key)/u', 'reason' => 'Ajusta y afina tu set', 'priority' => 78],
                ['slot' => 'heads', 'label' => 'Parches', 'family' => 'accessory', 'match' => '/(parche|drumhead|head)/u', 'reason' => 'Renueva el sonido de tus tambores', 'priority' => 74],
                ['slot' => 'cymbal_bag', 'label' => 'Bolso de platillos', 'family' => 'accessory', 'match' => '/(bolso|bag|case)/u', 'reason' => 'Protege tus platillos al transportarlos', 'priority' => 70],
            ],
            'mic' => [
                ['slot' => 'xlr', 'label' => 'Cable XLR', 'exclude' => '/(abrazadera|clamp|unidad|soporte|mount|kit|pedal|interpedal|guitarra)/u', 'family' => 'accessory', 'match' => '/(xlr|cable|canon)/u', 'reason' => 'Conecta tu micrófono con seguridad', 'priority' => 100, 'essential' => true],
                ['slot' => 'mic_stand', 'label' => 'Pie de micrófono', 'exclude' => '/(trompeta|corneta|saxo|saxofon|clarinete|flauta|trombon|guitarra|teclado|piano|bateria|percusion)/u', 'family' => 'accessory', 'match' => '/(pie|stand|soporte)/u', 'reason' => 'Coloca el micrófono a tu altura', 'priority' => 96, 'essential' => true],
                ['slot' => 'clip', 'label' => 'Clip', 'exclude' => '/(cable|unidad|clamp)/u', 'family' => 'accessory', 'match' => '/(clip|abrazadera|pinza)/u', 'reason' => 'Sujeta tu micrófono con firmeza', 'priority' => 92, 'essential' => true],
                ['slot' => 'pop_filter', 'label' => 'Pop filter', 'family' => 'accessory', 'match' => '/(pop|filtro|windscreen|espuma)/u', 'reason' => 'Reduce golpes de aire al hablar', 'priority' => 86],
                ['slot' => 'shock_mount', 'label' => 'Shock mount', 'family' => 'accessory', 'match' => '/(shock|suspension|mount)/u', 'reason' => 'Aísla el micrófono de vibraciones', 'priority' => 82],
                ['slot' => 'interface', 'label' => 'Interfaz de audio', 'family' => 'audio', 'match' => '/(interfaz|interface|preamp)/u', 'reason' => 'Conecta tu micrófono al computador', 'priority' => 78],
                ['slot' => 'headphones', 'label' => 'Audífonos', 'family' => 'accessory', 'match' => '/(audifono|auricular|headphone)/u', 'reason' => 'Monitorea lo que estás grabando', 'priority' => 74],
                ['slot' => 'monitor', 'label' => 'Monitor', 'family' => 'audio', 'match' => '/(monitor|parlante|bafle)/u', 'reason' => 'Escucha tu mezcla con claridad', 'priority' => 70],
            ],
            'audio' => [
                ['slot' => 'cables', 'label' => 'Cables', 'family' => 'accessory', 'match' => '/(cable|xlr|trs|speakon)/u', 'reason' => 'Conecta todo tu sistema de audio', 'priority' => 100, 'essential' => true],
                ['slot' => 'stands', 'label' => 'Pies', 'family' => 'accessory', 'match' => '/(pie|stand|soporte)/u', 'reason' => 'Ubica tus parlantes a la altura ideal', 'priority' => 96, 'essential' => true],
                ['slot' => 'mixer', 'label' => 'Mixer / DI', 'family' => 'audio', 'match' => '/(mixer|mezcladora|consola|di)/u', 'reason' => 'Controla varias fuentes de audio', 'priority' => 92, 'essential' => true],
                ['slot' => 'interface', 'label' => 'Interfaz', 'family' => 'audio', 'match' => '/(interfaz|interface)/u', 'reason' => 'Conecta tu audio al computador', 'priority' => 86],
                ['slot' => 'headphones', 'label' => 'Audífonos', 'family' => 'accessory', 'match' => '/(audifono|auricular|headphone)/u', 'reason' => 'Monitorea sin ruido externo', 'priority' => 82],
                ['slot' => 'monitors', 'label' => 'Monitores', 'family' => 'audio', 'match' => '/(monitor|parlante|bafle)/u', 'reason' => 'Escucha tu mezcla con precisión', 'priority' => 78],
                ['slot' => 'cases', 'label' => 'Cases', 'family' => 'accessory', 'match' => '/(case|funda|bolso|estuche)/u', 'reason' => 'Protege tu equipo de audio', 'priority' => 74],
            ],
            'amp' => [
                ['slot' => 'instrument_cable', 'label' => 'Cable de instrumento', 'family' => 'accessory', 'match' => '/(cable|jack|instrumento)/u', 'reason' => 'Conecta tu instrumento al amplificador', 'priority' => 100, 'essential' => true],
                ['slot' => 'speaker_cable', 'label' => 'Cable de parlante', 'family' => 'accessory', 'match' => '/(speaker|parlante|cable)/u', 'reason' => 'Conecta la cabina correctamente', 'priority' => 94, 'essential' => true],
                ['slot' => 'footswitch', 'label' => 'Footswitch', 'family' => 'accessory', 'match' => '/(footswitch|pedal|switch)/u', 'reason' => 'Controla canales sin soltar la guitarra', 'priority' => 90, 'essential' => true],
                ['slot' => 'cover', 'label' => 'Cubierta', 'family' => 'accessory', 'match' => '/(cubierta|cover|funda)/u', 'reason' => 'Protege tu amplificador', 'priority' => 84],
                ['slot' => 'stand', 'label' => 'Base', 'exclude' => '/(trompeta|corneta|saxo|saxofon|clarinete|flauta|trombon|microfono|mic|parlante|bafle|bateria|percusion)/u', 'family' => 'accessory', 'match' => '/(base|stand|soporte)/u', 'reason' => 'Inclina el amplificador hacia ti', 'priority' => 78],
            ],
            'pedal' => [
                ['slot' => 'patch', 'label' => 'Patch cables', 'family' => 'accessory', 'match' => '/(patch|cable|jumper)/u', 'reason' => 'Conecta tus pedales en cadena', 'priority' => 100, 'essential' => true],
                ['slot' => 'power', 'label' => 'Fuente', 'family' => 'accessory', 'match' => '/(fuente|power|adaptador)/u', 'reason' => 'Alimenta tu pedalera sin ruido', 'priority' => 96, 'essential' => true],
                ['slot' => 'pedalboard', 'label' => 'Pedalboard', 'family' => 'accessory', 'match' => '/(pedalboard|board|estuche)/u', 'reason' => 'Organiza y transporta tus pedales', 'priority' => 92, 'essential' => true],
                ['slot' => 'tuner', 'label' => 'Afinador', 'family' => 'accessory', 'match' => '/(afinador|tuner)/u', 'reason' => 'Afina antes de cada presentación', 'priority' => 86],
                ['slot' => 'expression', 'label' => 'Pedal de expresión', 'family' => 'pedal', 'match' => '/(expresion|expression|volumen)/u', 'reason' => 'Controla el efecto en tiempo real', 'priority' => 80],
                ['slot' => 'bag', 'label' => 'Bolso', 'family' => 'accessory', 'match' => '/(bolso|bag|case)/u', 'reason' => 'Lleva tus pedales protegidos', 'priority' => 74],
            ],
            'accordion' => [
                ['slot' => 'straps', 'label' => 'Correas', 'family' => 'accessory', 'match' => '/(correa|strap)/u', 'reason' => 'Sujeta tu acordeón con comodidad', 'priority' => 100, 'essential' => true],
                ['slot' => 'case', 'label' => 'Funda', 'family' => 'accessory', 'match' => '/(funda|forro|estuche|case)/u', 'reason' => 'Protege tu acordeón', 'priority' => 96, 'essential' => true],
                ['slot' => 'music_stand', 'label' => 'Atril', 'family' => 'accessory', 'match' => '/(atril|stand|soporte)/u', 'reason' => 'Ten tu música a la vista', 'priority' => 90, 'essential' => true],
                ['slot' => 'metronome', 'label' => 'Metrónomo', 'family' => 'accessory', 'match' => '/(metronomo|metronome)/u', 'reason' => 'Practica con un pulso estable', 'priority' => 84],
                ['slot' => 'cleaning', 'label' => 'Limpieza', 'family' => 'accessory', 'match' => '/(limpieza|paño|clean)/u', 'reason' => 'Mantén tu acordeón en buen estado', 'priority' => 78],
                ['slot' => 'mic', 'label' => 'Micrófono', 'family' => 'mic', 'match' => '/(microfono|mic)/u', 'reason' => 'Amplifica tu acordeón', 'priority' => 72],
            ],
            'wind' => [
                ['slot' => 'reeds', 'label' => 'Cañas', 'family' => 'accessory', 'match' => '/(caña|cañas|reed)/u', 'reason' => 'Cañas de repuesto para tu instrumento', 'priority' => 100, 'essential' => true],
                ['slot' => 'mouthpiece', 'label' => 'Boquilla', 'family' => 'accessory', 'match' => '/(boquilla|mouthpiece|abrazadera|ligature)/u', 'reason' => 'Mejora la respuesta de tu sonido', 'priority' => 94, 'essential' => true],
                ['slot' => 'cleaning', 'label' => 'Kit de limpieza', 'family' => 'accessory', 'match' => '/(limpieza|cleaning|paño|escobilla)/u', 'reason' => 'Cuida la higiene de tu instrumento', 'priority' => 90, 'essential' => true],
                ['slot' => 'stand', 'label' => 'Soporte', 'family' => 'accessory', 'match' => '/(soporte|stand|atril)/u', 'reason' => 'Apoya tu instrumento con seguridad', 'priority' => 84],
                ['slot' => 'case', 'label' => 'Estuche', 'family' => 'accessory', 'match' => '/(estuche|case|funda)/u', 'reason' => 'Transporta tu instrumento protegido', 'priority' => 78],
                ['slot' => 'metronome', 'label' => 'Metrónomo', 'family' => 'accessory', 'match' => '/(metronomo|metronome)/u', 'reason' => 'Estudia con tempo constante', 'priority' => 72],
            ],
            'violin' => [
                ['slot' => 'strings', 'label' => 'Cuerdas', 'exclude' => '/(clea|cleaner|limpiador|limpieza|sordina|fretwrap|mute|silenciador|protector|manija|ajusta|clavija|maquina|maquinaria|repuesto|selector|violin|viola|chelo|cello|piano|stand|base|soporte|pin|abrazadera|clamp|unidad|atril|herraje)/u', 'family' => 'accessory', 'match' => '/(cuerda|string)/u', 'reason' => 'Cuerdas de repuesto para tu instrumento', 'priority' => 100, 'essential' => true],
                ['slot' => 'rosin', 'label' => 'Resina', 'family' => 'accessory', 'match' => '/(resina|rosin|brea)/u', 'reason' => 'Mejora el agarre del arco', 'priority' => 96, 'essential' => true],
                ['slot' => 'stand', 'label' => 'Atril', 'family' => 'accessory', 'match' => '/(atril|stand|soporte)/u', 'reason' => 'Ten tus partituras a la vista', 'priority' => 90, 'essential' => true],
                ['slot' => 'shoulder_rest', 'label' => 'Hombrera', 'family' => 'accessory', 'match' => '/(hombrera|shoulder rest)/u', 'reason' => 'Toca con mayor comodidad', 'priority' => 84],
                ['slot' => 'tuner', 'label' => 'Afinador', 'family' => 'accessory', 'match' => '/(afinador|tuner)/u', 'reason' => 'Afinación precisa para estudiar', 'priority' => 78],
                ['slot' => 'case', 'label' => 'Estuche', 'family' => 'accessory', 'match' => '/(estuche|case|funda)/u', 'reason' => 'Protege tu instrumento', 'priority' => 72],
            ],
            'dj' => [
                ['slot' => 'headphones', 'label' => 'Audífonos', 'family' => 'accessory', 'match' => '/(audifono|auricular|headphone)/u', 'reason' => 'Monitorea tu mezcla', 'priority' => 100, 'essential' => true],
                ['slot' => 'cables', 'label' => 'Cables', 'family' => 'accessory', 'match' => '/(cable|rca|xlr|jack)/u', 'reason' => 'Conecta tu controlador y parlantes', 'priority' => 96, 'essential' => true],
                ['slot' => 'laptop_stand', 'label' => 'Soporte laptop', 'family' => 'accessory', 'match' => '/(soporte|laptop|stand)/u', 'reason' => 'Ubica tu laptop a la altura ideal', 'priority' => 90, 'essential' => true],
                ['slot' => 'controller', 'label' => 'Controlador', 'family' => 'dj', 'match' => '/(controlador|controller)/u', 'reason' => 'Amplía tu setup de DJ', 'priority' => 84],
                ['slot' => 'speakers', 'label' => 'Parlantes', 'family' => 'audio', 'match' => '/(parlante|bafle|speaker)/u', 'reason' => 'Lleva tu mezcla al público', 'priority' => 78],
                ['slot' => 'stands', 'label' => 'Pies', 'family' => 'accessory', 'match' => '/(pie|stand|soporte)/u', 'reason' => 'Eleva tus parlantes', 'priority' => 72],
                ['slot' => 'lights', 'label' => 'Luces', 'family' => 'lighting', 'match' => '/(luz|luces|light|led)/u', 'reason' => 'Crea una experiencia visual', 'priority' => 68],
            ],
            'lighting' => [
                ['slot' => 'dmx', 'label' => 'Cable DMX', 'family' => 'accessory', 'match' => '/(dmx|cable)/u', 'reason' => 'Conecta tus luces', 'priority' => 100, 'essential' => true],
                ['slot' => 'controller', 'label' => 'Controlador', 'family' => 'accessory', 'match' => '/(controlador|controller)/u', 'reason' => 'Controla todos los efectos', 'priority' => 94, 'essential' => true],
                ['slot' => 'stands', 'label' => 'Pies', 'family' => 'accessory', 'match' => '/(pie|stand|soporte)/u', 'reason' => 'Eleva tus luces con seguridad', 'priority' => 88, 'essential' => true],
                ['slot' => 'clamps', 'label' => 'Clamps', 'family' => 'accessory', 'match' => '/(clamp|abrazadera)/u', 'reason' => 'Sujeta las luces a estructuras', 'priority' => 82],
                ['slot' => 'safety', 'label' => 'Cable de seguridad', 'family' => 'accessory', 'match' => '/(seguridad|safety)/u', 'reason' => 'Refuerza la instalación', 'priority' => 76],
            ],
            'other' => [
                ['slot' => 'tuner', 'label' => 'Afinador', 'family' => 'accessory', 'match' => '/(afinador|tuner)/u', 'reason' => 'Mantén todo afinado', 'priority' => 100, 'essential' => true],
                ['slot' => 'cable', 'label' => 'Cable', 'exclude' => '/(abrazadera|clamp|unidad|organizador|soporte|stand|base|microfono|mic|parlante|bafle)/u', 'family' => 'accessory', 'match' => '/(cable|jack|xlr)/u', 'reason' => 'Conecta tu equipo', 'priority' => 92, 'essential' => true],
                ['slot' => 'stand', 'label' => 'Soporte', 'family' => 'accessory', 'match' => '/(soporte|stand|base|atril)/u', 'reason' => 'Ubica tu equipo con comodidad', 'priority' => 84],
                ['slot' => 'case', 'label' => 'Funda', 'family' => 'accessory', 'match' => '/(funda|forro|estuche|case)/u', 'reason' => 'Protege tu compra', 'priority' => 76],
            ],
        ];
    }
}
