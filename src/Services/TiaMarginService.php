<?php
namespace App\Services;

class TiaMarginService
{
    public const GROSS_MARGIN_PCT = 20.0;
    public const NET_TARGET_PCT = 5.0;
    public const AVAILABLE_COST_PCT = 15.0;

    public function policy(): array
    {
        return [
            'gross_margin_pct' => self::GROSS_MARGIN_PCT,
            'net_target_pct' => self::NET_TARGET_PCT,
            'available_cost_pct' => self::AVAILABLE_COST_PCT,
            'breb_included' => true,
            'accordion_excluded' => true,
        ];
    }

    public function isAccordion(?string $text): bool
    {
        $text = $this->fold((string)$text);
        return (bool)preg_match('/\b(acordeon|accordion|acordeones)\b/u', $text);
    }

    public function estimate(int $revenueCents, bool $accordion = false): array
    {
        $revenueCents = max(0, $revenueCents);
        if ($accordion) {
            return [
                'eligible' => false,
                'revenue_cents' => $revenueCents,
                'gross_profit_cents' => 0,
                'estimated_cost_cents' => 0,
                'net_target_cents' => 0,
                'available_cost_cents' => 0,
                'gross_margin_pct' => null,
                'note' => 'Acordeón excluido de la estimación del 20% hasta cargar su costo real.',
            ];
        }
        $gross = (int)round($revenueCents * self::GROSS_MARGIN_PCT / 100);
        $net = (int)round($revenueCents * self::NET_TARGET_PCT / 100);
        return [
            'eligible' => true,
            'revenue_cents' => $revenueCents,
            'gross_profit_cents' => $gross,
            'estimated_cost_cents' => max(0, $revenueCents - $gross),
            'net_target_cents' => $net,
            'available_cost_cents' => max(0, $gross - $net),
            'gross_margin_pct' => self::GROSS_MARGIN_PCT,
            'note' => 'Estimación con política del proveedor: 20% bruto sobre ingreso neto, Bre-B incluido. El 5% neto es objetivo después de costos.',
        ];
    }

    private function fold(string $value): string
    {
        return strtr(mb_strtolower($value), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    }
}
