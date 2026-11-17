<?php
namespace App\Services;

class CrossSellDiscountService
{
    /**
     * Calcula el descuento de venta cruzada sobre los artículos recomendados.
     * Los artículos se ordenan por precio descendente para que el mayor
     * porcentaje quede en el artículo de menor valor:
     *   1.º (más caro) 0,5% | 2.º 1,0% | 3.º (más barato) 1,5%.
     */
    public function calculate(array $items, array $eligibleRefs, array $settings = []): array
    {
        $rates = array_values((array)($settings['rates'] ?? [0.5, 1.0, 1.5]));
        $rates = array_map(fn($v) => max(0, (float)$v) / 100, $rates);
        if (count($rates) < 3) $rates = [0.005, 0.01, 0.015];
        $rounding = max(1, (int)($settings['rounding'] ?? 100)) * 100;
        $minimum = max(0, (int)($settings['min_discount'] ?? 100)) * 100;

        $eligible = [];
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $ref = strtolower(trim((string)($item['ref'] ?? ($item['handle'] ?? ''))));
            if ($ref === '' || !isset($eligibleRefs[$ref])) continue;
            $rec = $item['rec'] ?? null;
            if (!is_array($rec) || (string)($rec['source'] ?? '') !== 'recommendations') continue;
            $price = (int)($item['price_cents'] ?? 0);
            if ($price <= 0) continue;
            $eligible[] = [
                'ref' => $ref,
                'title' => (string)($item['title'] ?? ''),
                'price_cents' => $price,
                'rec' => $rec,
            ];
        }
        if (!$eligible) return ['discount_cents' => 0, 'discount_net_cents' => 0, 'count' => 0, 'items' => []];

        // El cupo es de tres artículos distintos, en el orden en que llegaron.
        $eligible = array_slice($eligible, 0, 3);
        usort($eligible, fn($a, $b) => $b['price_cents'] <=> $a['price_cents']);

        $count = count($eligible);
        $out = [];
        $total = 0;
        foreach ($eligible as $i => $item) {
            $rate = $rates[min($i, count($rates) - 1)];
            $raw = (int)round($item['price_cents'] * $rate);
            $discount = (int)round($raw / $rounding) * $rounding;
            if ($discount < $minimum && $raw > 0) $discount = $minimum;
            $discount = max(0, min($discount, $item['price_cents']));
            if ($discount <= 0) continue;
            $out[$item['ref']] = [
                'ref' => $item['ref'],
                'title' => $item['title'],
                'price_cents' => $item['price_cents'],
                'rate' => $rate,
                'rate_label' => rtrim(rtrim(number_format($rate * 100, 2, ',', '.'), '0'), ',') . '%',
                'discount_unit_cents' => $discount,
                'discount_cents' => $discount,
                'slot' => (string)($item['rec']['slot'] ?? ''),
            ];
            $total += $discount;
        }
        return [
            'discount_cents' => $total,
            'count' => count($out),
            'items' => $out,
        ];
    }
}
