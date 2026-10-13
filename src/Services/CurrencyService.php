<?php
namespace App\Services;

use App\Core\Database;

/**
 * CurrencyService — multi-moneda de VISUALIZACIÓN para la tienda.
 * La moneda base es store_currency; las demás son referenciales (conversión por tasa).
 * La selección se guarda en la cookie 'wwi_currency'. Los pedidos SIEMPRE se cobran en la moneda base.
 */
class CurrencyService
{
    private static ?array $map = null;

    public function base(): string
    {
        try {
            $s = (new StoreService())->settings();
            return (($s['store_currency'] ?? '') ?: 'COP');
        } catch (\Throwable $e) {
            return 'COP';
        }
    }

    public function currencies(): array
    {
        if (self::$map !== null) return self::$map;
        $base = $this->base();
        $list = [$base => ['code' => $base, 'symbol' => $base === 'USD' ? 'US$' : '$', 'rate' => 1.0, 'label' => $base]];
        try {
            $st = Database::instance()->prepare("SELECT `value` FROM settings WHERE site_id = @site_id AND `key` = 'store_currencies' LIMIT 1");
            $st->execute();
            $arr = json_decode((string)$st->fetchColumn(), true) ?: [];
            foreach ($arr as $c) {
                $code = strtoupper((string)($c['code'] ?? ''));
                $rate = (float)($c['rate'] ?? 0);
                if ($code === '' || $rate <= 0) continue;
                $list[$code] = ['code' => $code, 'symbol' => (string)($c['symbol'] ?? $code), 'rate' => $rate, 'label' => (string)($c['label'] ?? $code)];
            }
        } catch (\Throwable $e) {
        }
        self::$map = $list;
        return $list;
    }

    public function active(): string
    {
        $list = $this->currencies();
        $c = strtoupper((string)($_COOKIE['wwi_currency'] ?? ''));
        return isset($list[$c]) ? $c : $this->base();
    }

    public function isBase(): bool
    {
        return $this->active() === $this->base();
    }

    public function format(float $amount): string
    {
        $list = $this->currencies();
        $cur = $list[$this->active()];
        $val = $amount * (float)$cur['rate'];
        $dec = ($cur['code'] === 'COP') ? 0 : 2;
        return $cur['symbol'] . number_format($val, $dec, ',', '.');
    }
}
