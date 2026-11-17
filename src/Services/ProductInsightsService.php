<?php
namespace App\Services;

use App\Core\Database;

class ProductInsightsService
{
    private const PURCHASE_DISPLAY_FACTOR = 0.08;
    private const HIGH_VALUE_CENTS = 100000000;
    private const MEDIUM_VALUE_CENTS = 30000000;
    private static bool $ensured = false;
    private static ?array $paymentSettings = null;
    private array $ratingCache = [];

    public function ensureTables(): void
    {
        if (self::$ensured) return;
        Database::instance()->exec("CREATE TABLE IF NOT EXISTS store_product_rating_sim (
            id INT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL,
            product_ref VARCHAR(255) NOT NULL,
            top_type VARCHAR(40) NOT NULL,
            avg_rating DECIMAL(3,2) NOT NULL DEFAULT 0,
            rating_count INT NOT NULL DEFAULT 0,
            purchases_last_month INT NOT NULL DEFAULT 0,
            dist_json LONGTEXT NULL,
            sim_started_at DATETIME NOT NULL,
            sim_until DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_product_sim (site_id, product_ref),
            KEY idx_product_sim_until (site_id, sim_until)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        (new ReviewService())->ensureTables();
        self::$ensured = true;
    }

    public function topType(array $product, ?float $price = null): string
    {
        $priceCents = $price !== null ? (int)round($price * 100) : (int)round((float)($product['price'] ?? 0) * 100);
        $fold = function (string $v): string {
            return strtr(mb_strtolower(trim($v)), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
        };
        $title = $fold((string)($product['title'] ?? ''));
        $type = $fold((string)($product['product_type'] ?? ''));
        $tags = $fold(implode(' ', array_map('strval', (array)($product['tags'] ?? []))));
        if (preg_match('/^guitarra/', $title) && preg_match('/acustica|electroacustica/', $title . ' ' . $type . ' ' . $tags)) return 'acoustic_guitar';
        if (preg_match('/^guitarra/', $title) && preg_match('/electrica|electric guitar/', $title . ' ' . $type . ' ' . $tags)) return 'electric_guitar';
        if (preg_match('/^(acordeon|accordion)/', $title) || in_array($type, ['acordeon', 'accordion'], true)) return 'accordion';
        if (preg_match('/^tumbadora/', $title)) return 'tumbadora';
        if (preg_match('/^(timbal|timbales)/', $title)) return 'timbales';
        if (preg_match('/^(piano|pianos)/', $title) || $type === 'piano') return 'piano';
        if (preg_match('/^(bajo|bajos)\b/', $title) || in_array($type, ['bajo', 'bajos'], true)) return 'bass';
        if ($priceCents >= 100000000 && (in_array($type, ['audio', 'amplificadores', 'parlantes', 'speakers'], true) || preg_match('/parlante|bafle|speaker|altavoz|equipo.*sonido/', $title . ' ' . $tags))) return 'speaker';
        if ($priceCents >= 100000000) return 'high_value';
        return '';
    }

    public function forProduct(array $product, ?float $price = null): array
    {
        $this->ensureTables();
        $ref = trim((string)($product['handle'] ?? ''));
        if ($ref === '') return $this->emptyInsights();
        $price = $price !== null ? $price : (float)($product['price'] ?? 0);
        $type = $this->topType($product, $price);
        $rating = $this->ratingForRef($ref, true, $type, $price);
        $netCents = !empty($product['tax_exempt']) ? (int)round($price * 100) : $this->netCents($price);
        $payment = $this->paymentSettings();
        $freeThreshold = max(0, (int)$payment['free_shipping_net_cents']);
        $free = !empty($product['free_shipping']) || ($netCents >= $freeThreshold && $freeThreshold > 0);
        $hasRating = (int)($rating['count'] ?? 0) > 0;
        $simulated = !empty($rating['simulated']);
        return [
            'ref' => $ref,
            'top_type' => $type,
            'is_top' => $type !== '',
            'rating' => $rating,
            'has_rating' => $hasRating,
            'show_rating' => $hasRating,
            'show_top_badge' => $type !== '' && $hasRating,
            'rating_label' => $hasRating ? $this->countLabel((int)$rating['count']) : '',
            'purchases_label' => $simulated ? $this->purchaseLabel($this->scaledPurchases((int)($rating['purchases'] ?? 0), (float)$price)) : '',
            'free_shipping' => $free,
            'net_cents' => $netCents,
            'free_threshold_cents' => $freeThreshold,
            'delivery_label' => $free ? $this->deliveryLabel() : '',
        ];
    }

    public function ratingForRef(string $ref, bool $createSimulation = false, string $type = '', ?float $price = null): array
    {
        $this->ensureTables();
        $ref = trim($ref);
        if ($ref === '') return $this->emptyRating();
        if (isset($this->ratingCache[$ref])) return $this->ratingCache[$ref];
        $real = (new ReviewService())->summary($ref);
        if ((int)($real['count'] ?? 0) > 0) {
            $out = [
                'average' => (float)$real['average'],
                'count' => (int)$real['count'],
                'dist' => $real['dist'],
                'simulated' => false,
                'purchases' => 0,
            ];
            $this->ratingCache[$ref] = $out;
            return $out;
        }
        $st = Database::instance()->prepare('SELECT * FROM store_product_rating_sim WHERE site_id = @site_id AND product_ref = ? AND sim_until > NOW() LIMIT 1');
        $st->execute([$ref]);
        $sim = $st->fetch();
        if (!$sim && $createSimulation) {
            $sim = $this->createSimulation($ref, $type);
        }
        if ($sim) {
            $dist = json_decode((string)($sim['dist_json'] ?? '{}'), true) ?: [];
            $out = [
                'average' => round((float)$sim['avg_rating'], 1),
                'count' => (int)$sim['rating_count'],
                'dist' => $dist,
                'simulated' => true,
                'purchases' => (int)$sim['purchases_last_month'],
            ];
            $this->ratingCache[$ref] = $out;
            return $out;
        }
        $out = $this->emptyRating();
        $this->ratingCache[$ref] = $out;
        return $out;
    }

    private function createSimulation(string $ref, string $type): ?array
    {
        $seed = hash('sha256', (string)(\App\Core\Config::get('SITE_ID', '1')) . '|' . $ref . '|rating-sim-v1');
        $avg = 4.2 + (hexdec(substr($seed, 0, 4)) % 8) / 10;
        $h = hexdec(substr($seed, 4, 6));
        $count = ($h % 10 < 7) ? 40 + ($h % 1200) : 1200 + ($h % 18400);
        $purchases = 30 + (hexdec(substr($seed, 10, 4)) % 1171);
        $dist = $this->simulatedDistribution($avg, $count);
        $now = date('Y-m-d H:i:s');
        $until = date('Y-m-d H:i:s', strtotime('+3 months'));
        try {
            Database::instance()->prepare('INSERT IGNORE INTO store_product_rating_sim (site_id, product_ref, top_type, avg_rating, rating_count, purchases_last_month, dist_json, sim_started_at, sim_until) VALUES (@site_id, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$ref, $type, round($avg, 2), $count, $purchases, json_encode($dist, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $now, $until]);
            $st = Database::instance()->prepare('SELECT * FROM store_product_rating_sim WHERE site_id = @site_id AND product_ref = ? AND sim_until > NOW() LIMIT 1');
            $st->execute([$ref]);
            return $st->fetch() ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function simulatedDistribution(float $avg, int $count): array
    {
        if ($avg >= 4.7) $base = [5 => .76, 4 => .16, 3 => .05, 2 => .02, 1 => .01];
        elseif ($avg >= 4.5) $base = [5 => .68, 4 => .22, 3 => .06, 2 => .02, 1 => .02];
        elseif ($avg >= 4.3) $base = [5 => .60, 4 => .25, 3 => .09, 2 => .03, 1 => .03];
        else $base = [5 => .52, 4 => .27, 3 => .12, 2 => .05, 1 => .04];
        $out = [];
        $sum = 0;
        foreach ([5, 4, 3, 2, 1] as $star) {
            $n = (int)round($count * $base[$star]);
            $out[$star] = $n;
            $sum += $n;
        }
        $out[5] += max(0, $count - $sum);
        return $out;
    }

    private function countLabel(int $count): string
    {
        if ($count < 1000) return $count . '+';
        $k = $count / 1000;
        $label = number_format($k, 1, ',', '.');
        if (str_ends_with($label, ',0')) $label = substr($label, 0, -2);
        return $label . ' K+';
    }

    private function scaledPurchases(int $count, float $price = 0): int
    {
        if ($count <= 0) return 0;
        $priceCents = (int)round($price * 100);
        $factor = self::PURCHASE_DISPLAY_FACTOR;
        if ($priceCents >= self::HIGH_VALUE_CENTS) $factor *= 0.6;
        elseif ($priceCents >= self::MEDIUM_VALUE_CENTS) $factor *= 0.8;
        return max(1, (int)floor($count * $factor));
    }

    private function purchaseLabel(int $count): string
    {
        if ($count <= 0) return '';
        if ($count < 1000) return $count . '+ vendidos el mes pasado';
        $k = $count / 1000;
        $label = number_format($k, 1, ',', '.');
        if (str_ends_with($label, ',0')) $label = substr($label, 0, -2);
        return $label . ' K+ vendidos el mes pasado';
    }

    private function netCents(float $price): int
    {
        $gross = max(0, (int)round($price * 100));
        $s = $this->paymentSettings();
        if (!empty($s['prices_include_tax']) && (float)$s['tax_rate'] > 0) {
            return (int)round($gross / (1 + (float)$s['tax_rate'] / 100));
        }
        return $gross;
    }

    private function paymentSettings(): array
    {
        if (self::$paymentSettings !== null) return self::$paymentSettings;
        try {
            self::$paymentSettings = (new BrebService())->settings();
        } catch (\Throwable $e) {
            self::$paymentSettings = ['tax_rate' => 19, 'prices_include_tax' => true, 'free_shipping_net_cents' => 30000000];
        }
        return self::$paymentSettings;
    }

    private function deliveryLabel(): string
    {
        $tz = new \DateTimeZone('America/Bogota');
        $d = new \DateTimeImmutable('now', $tz);
        $added = 0;
        while ($added < 5) {
            $d = $d->modify('+1 day');
            if ((int)$d->format('N') <= 5) $added++;
        }
        $days = [1 => 'lun', 2 => 'mar', 3 => 'mié', 4 => 'jue', 5 => 'vie', 6 => 'sáb', 7 => 'dom'];
        $months = [1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr', 5 => 'may', 6 => 'jun', 7 => 'jul', 8 => 'ago', 9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic'];
        return $days[(int)$d->format('N')] . ', ' . (int)$d->format('j') . ' de ' . $months[(int)$d->format('n')];
    }

    private function emptyRating(): array
    {
        return ['average' => 0.0, 'count' => 0, 'dist' => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0], 'simulated' => false, 'purchases' => 0];
    }

    private function emptyInsights(): array
    {
        return ['ref' => '', 'top_type' => '', 'is_top' => false, 'rating' => $this->emptyRating(), 'has_rating' => false, 'show_rating' => false, 'show_top_badge' => false, 'rating_label' => '', 'purchases_label' => '', 'free_shipping' => false, 'net_cents' => 0, 'free_threshold_cents' => 30000000, 'delivery_label' => ''];
    }
}
