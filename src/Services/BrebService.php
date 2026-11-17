<?php
namespace App\Services;

use App\Core\Config;
use App\Core\Database;

class BrebService
{
    public const METHODS = ['breb', 'mercadopago', 'epayco', 'addi', 'sistecredito', 'request'];
    public const MAX_PROOF_BYTES = 2097152;
    private static bool $ensured = false;
    private static array $cache = [];

    private const DEFAULTS = [
        'breb_enabled' => '1',
        'breb_label' => 'Paga a través de Bre-B',
        'breb_discount_pct' => '3.00',
        'breb_prices_include_tax' => '1',
        'breb_tax_rate' => '19.00',
        'breb_rounding' => '100',
        'breb_free_shipping_net_cents' => '30000000',
        'breb_shipping_cents' => '1500000',
        'breb_qr_image' => '',
        'breb_qr_alt' => 'QR Bre-B de SONIKA',
        'breb_key' => '',
        'breb_account_name' => '',
        'breb_instructions' => 'Abre tu app bancaria, escanea el QR de Bre-B y transfiere el monto exacto. Luego adjunta el comprobante de pago.',
        'breb_proof_max_mb' => '2',
        'breb_payment_minutes' => '120',
        'breb_stackable' => '1',
        'breb_request_enabled' => '1',
        'breb_terms_url' => '/politicas',
        'breb_whatsapp' => '',
        'breb_mp_enabled' => '0',
        'breb_mp_label' => 'Mercado Pago',
        'breb_mp_link' => '',
        'breb_epayco_enabled' => '0',
        'breb_epayco_label' => 'ePayco',
        'breb_epayco_link' => '',
        'store_currency' => 'COP',
        'store_whatsapp' => '',
        'pqrf_whatsapp' => '',
    ];

    public function ensureTables(): array
    {
        if (self::$ensured) return ['ok' => true, 'cached' => true];
        $db = Database::instance();
        $columns = [
            'subtotal_net_cents' => "ALTER TABLE store_orders ADD COLUMN subtotal_net_cents INT NOT NULL DEFAULT 0",
            'tax_cents' => "ALTER TABLE store_orders ADD COLUMN tax_cents INT NOT NULL DEFAULT 0",
            'breb_discount_cents' => "ALTER TABLE store_orders ADD COLUMN breb_discount_cents INT NOT NULL DEFAULT 0",
            'breb_discount_net_cents' => "ALTER TABLE store_orders ADD COLUMN breb_discount_net_cents INT NOT NULL DEFAULT 0",
            'payment_reference' => "ALTER TABLE store_orders ADD COLUMN payment_reference VARCHAR(80) NULL",
            'payment_link' => "ALTER TABLE store_orders ADD COLUMN payment_link VARCHAR(500) NULL",
            'payment_proof_path' => "ALTER TABLE store_orders ADD COLUMN payment_proof_path VARCHAR(255) NULL",
            'payment_proof_mime' => "ALTER TABLE store_orders ADD COLUMN payment_proof_mime VARCHAR(100) NULL",
            'payment_proof_size' => "ALTER TABLE store_orders ADD COLUMN payment_proof_size INT NOT NULL DEFAULT 0",
            'payment_proof_at' => "ALTER TABLE store_orders ADD COLUMN payment_proof_at DATETIME NULL",
            'payment_proof_status' => "ALTER TABLE store_orders ADD COLUMN payment_proof_status VARCHAR(20) NOT NULL DEFAULT 'none'",
            'payment_expires_at' => "ALTER TABLE store_orders ADD COLUMN payment_expires_at DATETIME NULL",
            'payment_verified_at' => "ALTER TABLE store_orders ADD COLUMN payment_verified_at DATETIME NULL",
            'payment_verified_by' => "ALTER TABLE store_orders ADD COLUMN payment_verified_by INT NULL",
            'payment_meta' => "ALTER TABLE store_orders ADD COLUMN payment_meta LONGTEXT NULL",
        ];
        foreach ($columns as $column => $sql) {
            try {
                $st = $db->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
                $st->execute(['store_orders', $column]);
                if ((int)$st->fetchColumn() === 0) $db->exec($sql);
            } catch (\Throwable $e) {
            }
        }
        try {
            $seed = $db->prepare("INSERT IGNORE INTO settings (site_id, `key`, `value`) VALUES (@site_id, ?, ?)");
            foreach (self::DEFAULTS as $k => $v) {
                if (!in_array($k, ['store_currency', 'store_whatsapp', 'pqrf_whatsapp'], true)) $seed->execute([$k, $v]);
            }
        } catch (\Throwable $e) {
        }
        self::$ensured = true;
        return ['ok' => true, 'cached' => false];
    }

    public function settings(): array
    {
        if (self::$cache) return self::$cache;
        $map = [];
        try {
            $keys = array_keys(self::DEFAULTS);
            $ph = implode(',', array_fill(0, count($keys), '?'));
            $st = Database::instance()->prepare("SELECT `key`, `value` FROM settings WHERE site_id = @site_id AND `key` IN ($ph)");
            $st->execute($keys);
            foreach ($st->fetchAll() as $row) $map[(string)$row['key']] = (string)$row['value'];
        } catch (\Throwable $e) {
        }
        $s = [];
        foreach (self::DEFAULTS as $k => $v) $s[$k] = $map[$k] ?? $v;

        $qr = trim($s['breb_qr_image']);
        if ($qr !== '' && !preg_match('~^(https?://|/)~i', $qr)) $qr = '';
        $wa = preg_replace('/\D+/', '', $s['breb_whatsapp']);
        if ($wa === '') $wa = preg_replace('/\D+/', '', $s['pqrf_whatsapp']);
        if ($wa === '') $wa = preg_replace('/\D+/', '', $s['store_whatsapp']);
        $terms = trim($s['breb_terms_url']);
        if ($terms === '' || !preg_match('~^(https?://|/)~i', $terms)) $terms = '/politicas';
        $mpLink = trim($s['breb_mp_link']);
        if ($mpLink !== '' && !preg_match('~^(https?://|/)~i', $mpLink)) $mpLink = '';
        $epLink = trim($s['breb_epayco_link']);
        if ($epLink !== '' && !preg_match('~^(https?://|/)~i', $epLink)) $epLink = '';

        return self::$cache = [
            'enabled' => $s['breb_enabled'] !== '0',
            'label' => trim($s['breb_label']) ?: 'Paga a través de Bre-B',
            'discount_pct' => max(0, min(50, (float)$s['breb_discount_pct'])),
            'prices_include_tax' => true,
            'tax_rate' => max(0, min(30, (float)$s['breb_tax_rate'])),
            'rounding' => in_array((int)$s['breb_rounding'], [0, 100, 1000], true) ? (int)$s['breb_rounding'] : 100,
            'free_shipping_net_cents' => max(0, (int)$s['breb_free_shipping_net_cents']),
            'shipping_cents' => max(0, (int)$s['breb_shipping_cents']),
            'qr_image' => $qr,
            'qr_alt' => trim($s['breb_qr_alt']) ?: 'QR Bre-B de SONIKA',
            'key' => trim($s['breb_key']),
            'account_name' => trim($s['breb_account_name']),
            'instructions' => trim($s['breb_instructions']),
            'proof_max_mb' => min(2, max(1, (int)$s['breb_proof_max_mb'])),
            'payment_minutes' => max(30, min(1440, (int)$s['breb_payment_minutes'])),
            'stackable' => $s['breb_stackable'] !== '0',
            'request_enabled' => $s['breb_request_enabled'] !== '0',
            'terms_url' => $terms,
            'whatsapp' => $wa,
            'mp_enabled' => $s['breb_mp_enabled'] !== '0',
            'mp_label' => trim($s['breb_mp_label']) ?: 'Mercado Pago',
            'mp_link' => $mpLink,
            'epayco_enabled' => $s['breb_epayco_enabled'] !== '0',
            'epayco_label' => trim($s['breb_epayco_label']) ?: 'ePayco',
            'epayco_link' => $epLink,
            'currency' => trim($s['store_currency']) ?: 'COP',
        ];
    }

    public function available(): bool
    {
        $s = $this->settings();
        if (!$s['enabled'] || $s['qr_image'] === '') return false;
        if (str_starts_with($s['qr_image'], '/')) {
            $root = defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 2);
            if (!is_file($root . '/public' . $s['qr_image'])) return false;
        }
        return true;
    }

    public function publicConfig(): array
    {
        $s = $this->settings();
        $available = $this->available();
        $methods = [];
        if ($available) {
            $methods[] = ['id' => 'breb', 'label' => $s['label'], 'primary' => true];
        }
        if ($s['mp_enabled'] && $s['mp_link'] !== '') {
            $methods[] = ['id' => 'mercadopago', 'label' => $s['mp_label'], 'external' => true, 'link' => $s['mp_link']];
        }
        if ($s['epayco_enabled'] && $s['epayco_link'] !== '') {
            $methods[] = ['id' => 'epayco', 'label' => $s['epayco_label'], 'external' => true, 'link' => $s['epayco_link']];
        }
        if ($s['request_enabled']) {
            $methods[] = ['id' => 'request', 'label' => 'Coordinar pago por WhatsApp', 'fallback' => true];
        }
        return [
            'enabled' => $s['enabled'],
            'available' => $available,
            'label' => $s['label'],
            'breb' => [
                'label' => $s['label'],
                'qr_image' => $available ? $s['qr_image'] : '',
                'qr_alt' => $s['qr_alt'],
                'key' => $s['key'],
                'account_name' => $s['account_name'],
                'instructions' => $s['instructions'],
                'whatsapp' => $s['whatsapp'],
                'terms_url' => $s['terms_url'],
                'tax_rate' => $s['tax_rate'],
                'free_shipping_net_cents' => $s['free_shipping_net_cents'],
                'shipping_cents' => $s['shipping_cents'],
                'proof_max_mb' => $s['proof_max_mb'],
                'payment_minutes' => $s['payment_minutes'],
                'stackable' => $s['stackable'],
            ],
            'methods' => $methods,
            'points' => $this->pointsContext(),
            'currency' => $s['currency'],
        ];
    }

    private function pointsContext(): array
    {
        $out = ['enabled' => false, 'balance' => 0, 'redeem_cents' => 0, 'customer' => null];
        try {
            $me = (new CustomerService())->current();
            if (!$me) return $out;
            $loy = (new LoyaltyService())->settings();
            $redeem = max(0, (int)($loy['redeem_cents'] ?? 0));
            $balance = (new LoyaltyService())->balance((int)$me['id']);
            $out = [
                'enabled' => !empty($loy['enabled']) && $redeem > 0 && $balance > 0,
                'balance' => $balance,
                'redeem_cents' => $redeem,
                'customer' => ['name' => (string)($me['name'] ?? ''), 'email' => (string)($me['email'] ?? ''), 'phone' => (string)($me['phone'] ?? '')],
            ];
        } catch (\Throwable $e) {
        }
        return $out;
    }

    public function quote(array $items, string $method = 'breb', string $coupon = '', int $points = 0, array $shippingContext = [], array $giftCard = []): array
    {
        $this->ensureTables();
        $s = $this->settings();
        if (!in_array($method, self::METHODS, true)) $method = 'request';
        $val = (new CartService())->validate($items);
        if (empty($val['items'])) {
            return ['ok' => false, 'message' => 'Tu carrito está vacío.', 'items' => [], 'issues' => []];
        }

        $lines = [];
        $issues = [];
        $sum = [
            'subtotal_before_cents' => 0, 'net_before_cents' => 0, 'tax_before_cents' => 0,
            'discount_cents' => 0, 'discount_net_cents' => 0,
            'recommendation_discount_cents' => 0, 'recommendation_discount_net_cents' => 0,
            'subtotal_after_cents' => 0, 'net_after_cents' => 0, 'tax_after_cents' => 0,
        ];
        foreach ($val['items'] as $item) {
            $line = $this->lineItem(
                (string)($item['ref'] ?? ''),
                (string)($item['title'] ?? ''),
                (string)($item['image'] ?? ''),
                (int)($item['price_cents'] ?? 0),
                (int)($item['qty'] ?? 1),
                (bool)($item['available'] ?? false),
                (string)($item['issue'] ?? ''),
                $method,
                $s,
                (bool)($item['tax_exempt'] ?? false),
                (bool)($item['free_shipping'] ?? false),
                (int)($item['weight_grams'] ?? 0)
            );
            $line['rec'] = is_array($item['rec'] ?? null) ? $item['rec'] : null;
            $line['product_id'] = (int)($item['product_id'] ?? 0);
            $line['variant_id'] = $item['variant_id'] ?? null;
            $line['sku'] = (string)($item['sku'] ?? '');
            $line['product_ref'] = (string)($item['product_ref'] ?? $item['ref'] ?? '');
            $line['brand'] = (string)($item['brand'] ?? '');
            $line['category'] = (string)($item['category'] ?? '');
            $lines[] = $line;
            foreach ($sum as $k => $v) $sum[$k] += (int)$line[$k];
            if (!$line['available'] || $line['issue'] !== 'ok') {
                $issues[] = ['ref' => $line['ref'], 'title' => $line['title'], 'issue' => $line['issue']];
            }
        }

        $recommendationDiscount = 0;
        $recommendationItems = [];
        $hasRecommendations = false;
        foreach ($val['items'] as $item) {
            if (is_array($item['rec'] ?? null) && (string)($item['rec']['source'] ?? '') === 'recommendations') {
                $hasRecommendations = true;
                break;
            }
        }
        if ($hasRecommendations) {
            $recService = new RecommendationService();
            $recSettings = $recService->settings();
            if (!empty($recSettings['enabled']) && !empty($recSettings['discount_enabled'])) {
                $eligibleRefs = $recService->eligibleRefs($val['items']);
                $calc = (new CrossSellDiscountService())->calculate($val['items'], $eligibleRefs, $recSettings);
                $recommendationDiscount = (int)$calc['discount_cents'];
                $recommendationItems = $calc['items'];
                foreach ($lines as &$line) {
                    $ref = strtolower((string)$line['ref']);
                    if (isset($recommendationItems[$ref])) {
                        $line = $this->applyRecommendationDiscount($line, (int)$recommendationItems[$ref]['discount_unit_cents'], (float)$s['tax_rate']);
                        $line['recommendation_rate'] = (float)($recommendationItems[$ref]['rate'] ?? 0);
                    }
                }
                unset($line);
            }
        }

        $sum = [
            'subtotal_before_cents' => 0, 'net_before_cents' => 0, 'tax_before_cents' => 0,
            'discount_cents' => 0, 'discount_net_cents' => 0,
            'recommendation_discount_cents' => 0, 'recommendation_discount_net_cents' => 0,
            'subtotal_after_cents' => 0, 'net_after_cents' => 0, 'tax_after_cents' => 0,
        ];
        $validLines = 0;
        $taxExemptItems = 0;
        $taxExemptSubtotal = 0;
        foreach ($lines as $line) {
            foreach ($sum as $k => $v) $sum[$k] += (int)$line[$k];
            if (($line['issue'] ?? '') === 'ok' && !empty($line['available'])) $validLines++;
            if (!empty($line['tax_exempt'])) {
                $taxExemptItems += max(1, (int)($line['qty'] ?? 1));
                $taxExemptSubtotal += (int)($line['subtotal_after_cents'] ?? 0);
            }
        }
        if ($validLines === 0) {
            return [
                'ok' => false,
                'message' => 'Los productos de tu carrito no están disponibles. Actualiza tu carrito e intenta de nuevo.',
                'items' => [],
                'issues' => $issues,
                'shipping_ok' => false,
            ];
        }

        $shippingNetBefore = 0;
        $shippingWeightGrams = 0;
        $freeShippingItems = 0;
        $freeShippingItemsSubtotal = 0;
        $totalWeightGrams = 0;
        foreach ($lines as $line) {
            $lineQty = max(1, (int)($line['qty'] ?? 1));
            $lineWeight = max(0, (int)($line['weight_grams'] ?? 0));
            $totalWeightGrams += $lineWeight * $lineQty;
            if (!empty($line['free_shipping'])) {
                $freeShippingItems += $lineQty;
                $freeShippingItemsSubtotal += (int)($line['subtotal_after_cents'] ?? 0);
                continue;
            }
            $shippingNetBefore += (int)($line['net_before_cents'] ?? 0);
            $shippingWeightGrams += $lineWeight * $lineQty;
        }
        $freeShippingAllItems = $freeShippingItems > 0 && $shippingNetBefore <= 0;
        // El peso total se usa para la promesa de entrega (ETA); el costo se calcula sobre el subtotal no exento.
        $shippingContext['weight_grams'] = $totalWeightGrams;
        $shipCtx = (new ShippingService())->quote(
            $shippingContext,
            $freeShippingAllItems ? 0 : $shippingNetBefore,
            (int)$s['free_shipping_net_cents']
        );
        if (empty($shipCtx['ok'])) {
            return [
                'ok' => false,
                'message' => (string)($shipCtx['message'] ?? 'No se pudo calcular el envío.'),
                'items' => [],
                'issues' => [],
                'shipping_ok' => false,
                'shipping' => $shipCtx,
            ];
        }
        if ($freeShippingAllItems) {
            $shipCtx['shipping_cents'] = 0;
            $shipCtx['free_shipping'] = true;
            $shipCtx['free_shipping_all_items'] = true;
        }
        $freeShipping = (bool)$shipCtx['free_shipping'];
        $shipping = (int)$shipCtx['shipping_cents'];
        $freeShippingNet = (int)($shipCtx['free_over_cents'] ?? $s['free_shipping_net_cents']);
        $taxNote = $taxExemptItems > 0 ? 'Incluye ' . $taxExemptItems . ' producto(s) sin IVA.' : '';
        $shippingNote = '';
        if ($freeShippingAllItems) {
            $shippingNote = 'Todos los productos de tu pedido tienen envío gratis.';
        } elseif ($freeShippingItems > 0 && $freeShipping) {
            $shippingNote = 'Envío gratis: los productos con envío gratis no suman al umbral y el resto de la compra supera el mínimo.';
        } elseif ($freeShippingItems > 0) {
            $shippingNote = $freeShippingItems . ' producto(s) con envío gratis. El flete se calcula sobre los demás productos.';
        } elseif ($freeShipping) {
            $shippingNote = 'Envío gratis aplicado por superar el monto mínimo de compra.';
        }
        $couponIn = strtoupper(trim($coupon));
        $couponDiscount = 0;
        $couponCode = '';
        $couponId = 0;
        $couponOk = null;
        $couponMessage = '';
        if ($couponIn !== '') {
            $cv = (new StoreCouponService())->validate($couponIn, (int)$sum['subtotal_after_cents']);
            if (empty($cv['ok'])) {
                $couponOk = false;
                $couponMessage = (string)($cv['message'] ?? 'Cupón no válido.');
            } elseif ($method === 'breb' && !$s['stackable']) {
                $couponOk = false;
                $couponMessage = 'Este cupón no se puede combinar con Bre-B.';
            } else {
                $couponDiscount = max(0, min((int)$cv['discount_cents'], (int)$sum['subtotal_after_cents']));
                $couponCode = (string)($cv['code'] ?? $couponIn);
                $couponId = (int)($cv['id'] ?? 0);
                $couponOk = true;
                $couponMessage = 'Cupón aplicado.';
            }
        }
        $pointsReq = max(0, $points);
        $pointsUsed = 0;
        $pointsDiscount = 0;
        $pointsBalance = 0;
        $pointsMessage = '';
        if ($pointsReq > 0) {
            $ctx = $this->pointsContext();
            $pointsBalance = (int)$ctx['balance'];
            if (!$ctx['enabled']) {
                $pointsMessage = 'Inicia sesión con tu cuenta o no tienes puntos disponibles.';
            } elseif ($method === 'breb' && !$s['stackable']) {
                $pointsMessage = 'Los puntos no se pueden combinar con Bre-B.';
            } else {
                $availablePoints = max(0, (int)$sum['subtotal_after_cents'] - $couponDiscount);
                $maxByTotal = (int)$ctx['redeem_cents'] > 0 ? intdiv($availablePoints, (int)$ctx['redeem_cents']) : 0;
                $pointsUsed = max(0, min($pointsReq, (int)$ctx['balance'], $maxByTotal));
                $pointsDiscount = min($availablePoints, $pointsUsed * max(0, (int)$ctx['redeem_cents']));
                if ($pointsUsed > 0) $pointsMessage = 'Puntos aplicados.';
                elseif ($pointsBalance <= 0) $pointsMessage = 'No tienes puntos disponibles.';
                else $pointsMessage = 'No hay puntos suficientes para este pedido.';
            }
        }
        $total = $sum['subtotal_after_cents'] - $couponDiscount - $pointsDiscount + $shipping;
        if ($total < 0) $total = 0;
        $totalBeforeGiftCard = $total;
        $giftCardDiscount = 0;
        $giftCardCode = '';
        $giftCardCodeMasked = '';
        $giftCardOk = null;
        $giftCardMessage = '';
        $giftCardBalance = 0;
        $giftCardApply = 0;
        $giftCardIn = is_array($giftCard) ? $giftCard : [];
        $giftCardCodeIn = trim((string)($giftCardIn['code'] ?? ''));
        $giftCardPinIn = preg_replace('/\D+/', '', (string)($giftCardIn['pin'] ?? ''));
        if ($giftCardCodeIn !== '' && $giftCardPinIn !== '') {
            $gv = (new GiftCardService())->validateCode($giftCardCodeIn, $giftCardPinIn, $totalBeforeGiftCard, false, '');
            if (empty($gv['ok'])) {
                $giftCardOk = false;
                $giftCardMessage = (string)($gv['message'] ?? 'La tarjeta de regalo no es válida.');
            } else {
                $giftCardOk = true;
                $giftCardDiscount = max(0, min((int)($gv['balance_cents'] ?? 0), $totalBeforeGiftCard));
                $giftCardApply = $giftCardDiscount;
                $giftCardCode = (string)($gv['code'] ?? '');
                $giftCardCodeMasked = (string)($gv['code_masked'] ?? '');
                $giftCardBalance = (int)($gv['balance_cents'] ?? 0);
                $giftCardMessage = $giftCardDiscount > 0
                    ? 'Tarjeta aplicada: −$' . number_format($giftCardDiscount / 100, 0, ',', '.') . '.'
                    : 'La tarjeta no tiene saldo disponible.';
                if ($giftCardDiscount <= 0) {
                    $giftCardOk = false;
                    $giftCardMessage = 'La tarjeta no tiene saldo disponible.';
                }
            }
        }
        $total = max(0, $totalBeforeGiftCard - $giftCardDiscount);
        $taxRate = (float)$s['tax_rate'];
        $discountedGross = max(0, (int)$sum['subtotal_after_cents'] - $couponDiscount - $pointsDiscount);
        $taxableGross = max(0, (int)$sum['subtotal_after_cents']);
        // Se conserva el IVA real por línea: los productos exentos siguen aportando $0.
        $taxAfter = $taxableGross > 0 ? (int)round((int)$sum['tax_after_cents'] * ($discountedGross / $taxableGross)) : 0;
        $netAfter = max(0, $discountedGross - $taxAfter);
        $couponNet = max(0, (int)$sum['net_after_cents'] - $netAfter);
        $savings = $sum['discount_cents'] + $recommendationDiscount + $couponDiscount + $pointsDiscount;

        return [
            'ok' => true,
            'method' => $method,
            'currency' => $s['currency'],
            'tax_rate' => $s['tax_rate'],
            'prices_include_tax' => $s['prices_include_tax'],
            'items' => $lines,
            'issue_count' => count($issues),
            'issues' => $issues,
            'subtotal_before_cents' => $sum['subtotal_before_cents'],
            'net_before_cents' => $sum['net_before_cents'],
            'tax_before_cents' => $sum['tax_before_cents'],
            'breb_discount_cents' => $sum['discount_cents'],
            'breb_discount_net_cents' => $sum['discount_net_cents'],
            'recommendation_discount_cents' => $recommendationDiscount,
            'recommendation_discount_net_cents' => $sum['recommendation_discount_net_cents'],
            'recommendation_items' => $recommendationItems,
            'coupon_discount_cents' => $couponDiscount,
            'coupon_code' => $couponCode,
            'coupon_id' => $couponId,
            'coupon_ok' => $couponOk,
            'coupon_message' => $couponMessage,
            'points_requested' => $pointsReq,
            'points_used' => $pointsUsed,
            'points_discount_cents' => $pointsDiscount,
            'points_balance' => $pointsBalance,
            'points_message' => $pointsMessage,
            'gift_card_discount_cents' => $giftCardDiscount,
            'gift_card_code' => $giftCardCode,
            'gift_card_code_masked' => $giftCardCodeMasked,
            'gift_card_ok' => $giftCardOk,
            'gift_card_message' => $giftCardMessage,
            'gift_card_balance_cents' => $giftCardBalance,
            'gift_card_apply_cents' => $giftCardApply,
            'total_before_gift_card_cents' => $totalBeforeGiftCard,
            'discount_cents' => $savings,
            'discount_net_cents' => $sum['discount_net_cents'] + $sum['recommendation_discount_net_cents'] + $couponNet,
            'subtotal_after_cents' => $sum['subtotal_after_cents'],
            'net_after_cents' => $netAfter,
            'tax_after_cents' => $taxAfter,
            'savings_cents' => $savings,
            'shipping_cents' => $shipping,
            'free_shipping' => $freeShipping,
            'free_shipping_net_cents' => $freeShippingNet,
            'shipping_net_before_cents' => $freeShippingAllItems ? 0 : $shippingNetBefore,
            'shipping_weight_grams' => $shippingWeightGrams,
            'free_shipping_items' => $freeShippingItems,
            'free_shipping_all_items' => $freeShippingAllItems,
            'free_shipping_items_subtotal_cents' => $freeShippingItemsSubtotal,
            'shipping_note' => $shippingNote,
            'tax_exempt_items' => $taxExemptItems,
            'tax_exempt_subtotal_cents' => $taxExemptSubtotal,
            'tax_note' => $taxNote,
            'shipping_ok' => true,
            'shipping' => $shipCtx,
            'total_cents' => $total,
            'breb_available' => $this->available(),
            'customer_visible' => [
                'label' => $s['label'],
                'terms_url' => $s['terms_url'],
            ],
        ];
    }

    public function preview(int $grossCents = 50000000): array
    {
        $s = $this->settings();
        $grossCents = max(100, $grossCents);
        $line = $this->lineItem('preview', 'Producto de ejemplo', '', $grossCents, 1, true, 'ok', 'breb', $s);
        $free = $line['net_before_cents'] >= $s['free_shipping_net_cents'];
        $shipping = $free ? 0 : $s['shipping_cents'];
        return [
            'line' => $line,
            'free_shipping' => $free,
            'shipping_cents' => $shipping,
            'total_cents' => $line['subtotal_after_cents'] + $shipping,
            'tax_rate' => $s['tax_rate'],
            'discount_pct' => $s['discount_pct'],
            'rounding' => $s['rounding'],
            'free_shipping_net_cents' => $s['free_shipping_net_cents'],
        ];
    }

    public function paymentInfo(array $order): array
    {
        $s = $this->settings();
        $method = (string)($order['payment_method'] ?? '');
        $meta = json_decode((string)($order['payment_meta'] ?? ''), true) ?: [];
        $brebDiscount = (int)($order['breb_discount_cents'] ?? 0);
        $recommendationDiscount = (int)($order['recommendation_discount_cents'] ?? 0);
        $pointsDiscount = (int)($meta['points_discount_cents'] ?? 0);
        $base = [
            'order_number' => (string)($order['order_number'] ?? ''),
            'uuid' => (string)($order['uuid'] ?? ''),
            'method' => $method,
            'payment_status' => (string)($order['payment_status'] ?? 'pending'),
            'payment_proof_status' => (string)($order['payment_proof_status'] ?? 'none'),
            'payment_proof_at' => $order['payment_proof_at'] ?? null,
            'payment_expires_at' => $order['payment_expires_at'] ?? null,
            'total_cents' => (int)($order['total_cents'] ?? 0),
            'currency' => (string)($order['currency'] ?? 'COP'),
            'subtotal_before_cents' => (int)($order['subtotal_cents'] ?? 0),
            'subtotal_after_cents' => (int)($order['total_cents'] ?? 0) - (int)($order['shipping_cents'] ?? 0),
            'net_after_cents' => (int)($order['subtotal_net_cents'] ?? 0),
            'tax_after_cents' => (int)($order['tax_cents'] ?? 0),
            'tax_rate' => $s['tax_rate'],
            'breb_discount_cents' => $brebDiscount,
            'recommendation_discount_cents' => $recommendationDiscount,
            'coupon_discount_cents' => max(0, (int)($order['discount_cents'] ?? 0) - $brebDiscount - $recommendationDiscount - $pointsDiscount),
            'points_used' => (int)($meta['points_used'] ?? 0),
            'points_discount_cents' => $pointsDiscount,
            'discount_cents' => (int)($order['discount_cents'] ?? 0),
            'shipping_cents' => (int)($order['shipping_cents'] ?? 0),
            'free_shipping' => (int)($order['shipping_cents'] ?? 0) === 0,
        ];
        if ($method === 'breb') {
            $base['breb'] = [
                'label' => $s['label'],
                'qr_image' => $this->available() ? $s['qr_image'] : '',
                'qr_alt' => $s['qr_alt'],
                'key' => $s['key'],
                'account_name' => $s['account_name'],
                'instructions' => $s['instructions'],
                'whatsapp' => $s['whatsapp'],
                'terms_url' => $s['terms_url'],
                'proof_max_mb' => $s['proof_max_mb'],
                'proof_required' => true,
                'payment_minutes' => $s['payment_minutes'],
                'stackable' => $s['stackable'],
            ];
        } elseif ($method === 'mercadopago') {
            $base['mercadopago'] = [
                'label' => $s['mp_label'],
                'redirect_url' => (string)($meta['mercadopago_redirect_url'] ?? ''),
                'mode' => (string)($meta['mercadopago_mode'] ?? 'checkout_pro'),
                'status' => (string)($meta['mercadopago_status'] ?? 'pending'),
                'status_detail' => (string)($meta['mercadopago_status_detail'] ?? ''),
                'payment_id' => (string)($meta['mercadopago_payment_id'] ?? ''),
                'payment_method_id' => (string)($meta['mercadopago_payment_method_id'] ?? ''),
                'installments' => (int)($meta['mercadopago_installments'] ?? 1),
            ];
            $base['external'] = [
                'label' => $s['mp_label'],
                'link' => (string)($order['payment_link'] ?? $s['mp_link']),
                'manual_verification' => true,
            ];
        } elseif ($method === 'epayco') {
            $base['external'] = [
                'label' => $s['epayco_label'],
                'link' => (string)($order['payment_link'] ?? $s['epayco_link']),
                'manual_verification' => true,
            ];
        } elseif ($method === 'addi') {
            $base['addi'] = [
                'label' => 'Addi',
                'redirect_url' => (string)($meta['addi_redirect_url'] ?? ''),
                'addi_order_id' => (string)($meta['addi_order_id'] ?? ''),
                'application_id' => (string)($meta['addi_application_id'] ?? ''),
                'status' => (string)($meta['addi_status'] ?? ''),
            ];
        } elseif ($method === 'sistecredito') {
            $base['sistecredito'] = [
                'label' => 'Sistecrédito',
                'redirect_url' => (string)($meta['sistecredito_redirect_url'] ?? ''),
                'provider_payment_id' => (string)($meta['sistecredito_provider_payment_id'] ?? ''),
                'status' => (string)($meta['sistecredito_status'] ?? 'pending'),
            ];
        } else {
            $base['request'] = [
                'label' => 'Coordinar pago por WhatsApp',
                'whatsapp' => $s['whatsapp'],
            ];
        }
        return $base;
    }

    public function uploadProof(string $uuid, array $file, string $ip = ''): array
    {
        $this->ensureTables();
        $uuid = trim($uuid);
        $order = $this->order($uuid);
        if (!$order) return ['ok' => false, 'status' => 404, 'message' => 'Pedido no encontrado.'];
        if ((string)$order['payment_method'] !== 'breb') return ['ok' => false, 'status' => 422, 'message' => 'Este pedido no usa Bre-B.'];
        if ((string)$order['payment_status'] === 'paid') return ['ok' => false, 'status' => 409, 'message' => 'El pedido ya está pagado.'];
        if (!in_array((string)$order['payment_status'], ['pending'], true)) return ['ok' => false, 'status' => 409, 'message' => 'El pedido no está pendiente de pago.'];

        $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE) return ['ok' => false, 'status' => 422, 'message' => 'Selecciona un comprobante.'];
        if ($err !== UPLOAD_ERR_OK) return ['ok' => false, 'status' => 422, 'message' => 'No pudimos recibir el comprobante. Intenta de nuevo.'];
        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_file($tmp)) return ['ok' => false, 'status' => 422, 'message' => 'No pudimos recibir el comprobante.'];
        $size = (int)@filesize($tmp);
        $max = self::MAX_PROOF_BYTES;
        if ($size <= 0 || $size > $max) return ['ok' => false, 'status' => 422, 'message' => 'El comprobante debe pesar máximo 2 MB.'];
        $orig = $this->safeFileName((string)($file['name'] ?? 'comprobante'));
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        $mime = '';
        try { $mime = (string)(new \finfo(FILEINFO_MIME_TYPE))->file($tmp); } catch (\Throwable $e) {}
        $allowed = ['image/jpeg' => ['jpg', 'jpeg'], 'image/png' => ['png'], 'image/webp' => ['webp'], 'application/pdf' => ['pdf']];
        if (!isset($allowed[$mime]) || !in_array($ext, $allowed[$mime], true)) {
            return ['ok' => false, 'status' => 422, 'message' => 'El comprobante debe ser JPG, PNG, WebP o PDF.'];
        }
        if (str_starts_with($mime, 'image/')) {
            if (!@getimagesize($tmp)) return ['ok' => false, 'status' => 422, 'message' => 'La imagen del comprobante no es válida.'];
        } elseif ($mime === 'application/pdf') {
            $fh = @fopen($tmp, 'rb');
            $head = $fh ? (string)fread($fh, 5) : '';
            if ($fh) fclose($fh);
            if ($head !== '%PDF-') return ['ok' => false, 'status' => 422, 'message' => 'El PDF del comprobante no es válido.'];
        }

        $root = defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 2);
        $siteId = (int)Config::get('SITE_ID', '1');
        $relativeDir = '/assets/uploads/_private/breb/' . $siteId . '/' . preg_replace('/[^a-f0-9-]/i', '', $uuid);
        $dir = $root . '/public' . $relativeDir;
        if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) {
            return ['ok' => false, 'status' => 500, 'message' => 'No pudimos guardar el comprobante.'];
        }
        $stored = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest = $dir . '/' . $stored;
        if (is_uploaded_file($tmp)) {
            if (!@move_uploaded_file($tmp, $dest)) return ['ok' => false, 'status' => 500, 'message' => 'No pudimos guardar el comprobante.'];
        } elseif (PHP_SAPI === 'cli' && is_file($tmp)) {
            if (!@copy($tmp, $dest)) return ['ok' => false, 'status' => 500, 'message' => 'No pudimos guardar el comprobante.'];
        } else {
            return ['ok' => false, 'status' => 422, 'message' => 'El comprobante no es válido.'];
        }
        @chmod($dest, 0640);
        $relative = $relativeDir . '/' . $stored;
        $db = Database::instance();
        $db->prepare("UPDATE store_orders SET payment_proof_path = ?, payment_proof_mime = ?, payment_proof_size = ?, payment_proof_at = NOW(), payment_proof_status = 'pending', payment_verified_at = NULL, payment_verified_by = NULL, updated_at = NOW() WHERE id = ? AND site_id = @site_id")
            ->execute([$relative, $mime, $size, (int)$order['id']]);
        $old = trim((string)($order['payment_proof_path'] ?? ''));
        if ($old !== '' && $old !== $relative) {
            $oldPath = $root . '/public' . $old;
            if (is_file($oldPath)) @unlink($oldPath);
        }
        return ['ok' => true, 'message' => 'Comprobante recibido. Queda pendiente de verificación.', 'proof_status' => 'pending', 'max_mb' => 2];
    }

    public function proofForAdmin(int $orderId): ?array
    {
        $this->ensureTables();
        $st = Database::instance()->prepare('SELECT * FROM store_orders WHERE id = ? AND site_id = @site_id LIMIT 1');
        $st->execute([$orderId]);
        $order = $st->fetch();
        if (!$order) return null;
        $relative = trim((string)($order['payment_proof_path'] ?? ''));
        if ($relative === '') return null;
        $root = defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 2);
        $path = $root . '/public' . $relative;
        if (!is_file($path)) return null;
        $order['full_path'] = $path;
        return $order;
    }

    public function markPaid(int $orderId, int $adminId): array
    {
        $this->ensureTables();
        $order = $this->orderById($orderId);
        if (!$order) return ['ok' => false, 'message' => 'Pedido no encontrado.'];
        if ((string)$order['payment_method'] !== 'breb') return ['ok' => false, 'message' => 'El pedido no es Bre-B.'];
        if ((string)$order['payment_status'] === 'paid') return ['ok' => true, 'message' => 'El pedido ya estaba pagado.'];
        try {
            (new StoreService())->markPaid((string)$order['uuid'], 'BREB-' . (string)$order['order_number'], 'breb');
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo marcar el pago: ' . $e->getMessage()];
        }
        $proofStatus = (string)($order['payment_proof_path'] ?? '') !== '' ? 'approved' : (string)($order['payment_proof_status'] ?? 'none');
        Database::instance()->prepare("UPDATE store_orders SET payment_verified_at = NOW(), payment_verified_by = ?, payment_proof_status = ?, updated_at = NOW() WHERE id = ? AND site_id = @site_id")
            ->execute([$adminId ?: null, $proofStatus, $orderId]);
        return ['ok' => true, 'message' => 'Pago Bre-B verificado.'];
    }

    public function rejectProof(int $orderId, int $adminId, string $note = ''): array
    {
        $this->ensureTables();
        $order = $this->orderById($orderId);
        if (!$order) return ['ok' => false, 'message' => 'Pedido no encontrado.'];
        if ((string)$order['payment_method'] !== 'breb') return ['ok' => false, 'message' => 'El pedido no es Bre-B.'];
        $note = trim(mb_substr($note, 0, 500));
        Database::instance()->prepare("UPDATE store_orders SET payment_proof_status = 'rejected', payment_verified_at = NOW(), payment_verified_by = ?, admin_notes = CONCAT(COALESCE(admin_notes, ''), ?), updated_at = NOW() WHERE id = ? AND site_id = @site_id")
            ->execute([$adminId ?: null, "\n[Bre-B] Comprobante rechazado. " . $note, $orderId]);
        return ['ok' => true, 'message' => 'Comprobante rechazado. El cliente puede enviar otro.'];
    }

    public function cleanupProofs(string $mode): array
    {
        $this->ensureTables();
        $root = defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 2);
        $db = Database::instance();
        $deleted = 0;
        $bytes = 0;
        if ($mode === 'orphans') {
            $referenced = [];
            $st = $db->prepare('SELECT payment_proof_path FROM store_orders WHERE site_id = @site_id AND payment_proof_path IS NOT NULL AND payment_proof_path <> ""');
            $st->execute();
            foreach ($st->fetchAll(\PDO::FETCH_COLUMN) as $p) $referenced[trim((string)$p)] = true;
            $siteId = (int)Config::get('SITE_ID', '1');
            $dir = $root . '/public/assets/uploads/_private/breb/' . $siteId;
            if (is_dir($dir)) {
                $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
                foreach ($it as $file) {
                    if (!$file->isFile()) continue;
                    $relative = '/assets/uploads/_private/breb/' . $siteId . '/' . ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($dir))), '/');
                    if (isset($referenced[$relative])) continue;
                    $bytes += (int)$file->getSize();
                    if (@unlink($file->getPathname())) $deleted++;
                }
            }
            return ['ok' => true, 'message' => $deleted . ' archivo(s) huérfano(s) eliminado(s).', 'deleted' => $deleted, 'bytes' => $bytes];
        }
        $days = $mode === 'rejected' ? 7 : 30;
        $sql = $mode === 'rejected'
            ? "SELECT * FROM store_orders WHERE site_id = @site_id AND payment_method = 'breb' AND payment_proof_status = 'rejected' AND payment_proof_path IS NOT NULL AND payment_proof_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
            : "SELECT * FROM store_orders WHERE site_id = @site_id AND payment_method = 'breb' AND payment_status = 'paid' AND payment_proof_path IS NOT NULL AND payment_proof_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $st = $db->prepare($sql);
        $st->execute();
        foreach ($st->fetchAll() as $order) {
            $relative = (string)$order['payment_proof_path'];
            $path = $root . '/public' . $relative;
            if (is_file($path)) {
                $bytes += (int)filesize($path);
                if (@unlink($path)) $deleted++;
            }
            $db->prepare("UPDATE store_orders SET payment_proof_path = NULL, payment_proof_mime = NULL, payment_proof_size = 0, payment_proof_at = NULL, payment_proof_status = 'purged', updated_at = NOW() WHERE id = ? AND site_id = @site_id")
                ->execute([(int)$order['id']]);
        }
        return ['ok' => true, 'message' => $deleted . ' comprobante(s) depurado(s).', 'deleted' => $deleted, 'bytes' => $bytes, 'days' => $days];
    }

    public function diskUsage(): array
    {
        $root = defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 2);
        $siteId = (int)Config::get('SITE_ID', '1');
        $dir = $root . '/public/assets/uploads/_private/breb/' . $siteId;
        $files = 0;
        $bytes = 0;
        if (is_dir($dir)) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if (!$f->isFile()) continue;
                $files++;
                $bytes += (int)$f->getSize();
            }
        }
        return ['files' => $files, 'bytes' => $bytes];
    }

    public function uploadQr(array $file): array
    {
        $this->ensureTables();
        $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK) return ['ok' => false, 'message' => 'Selecciona una imagen QR.'];
        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_file($tmp)) return ['ok' => false, 'message' => 'No se recibió la imagen.'];
        $size = (int)@filesize($tmp);
        if ($size <= 0 || $size > 5 * 1024 * 1024) return ['ok' => false, 'message' => 'El QR debe pesar máximo 5 MB.'];
        $mime = '';
        try { $mime = (string)(new \finfo(FILEINFO_MIME_TYPE))->file($tmp); } catch (\Throwable $e) {}
        $exts = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
        if (!isset($exts[$mime]) || !@getimagesize($tmp)) return ['ok' => false, 'message' => 'El QR debe ser PNG, JPG o WebP válido.'];
        $root = defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 2);
        $dir = $root . '/public/assets/uploads/breb';
        if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) return ['ok' => false, 'message' => 'No se pudo guardar el QR.'];
        $name = 'breb-qr-' . bin2hex(random_bytes(10)) . '.' . $exts[$mime];
        $dest = $dir . '/' . $name;
        if (!@move_uploaded_file($tmp, $dest)) return ['ok' => false, 'message' => 'No se pudo guardar el QR.'];
        @chmod($dest, 0644);
        $url = '/assets/uploads/breb/' . $name;
        $s = $this->settings();
        $old = $s['qr_image'];
        $this->set('breb_qr_image', $url);
        self::$cache = [];
        if ($old !== '' && str_starts_with($old, '/assets/uploads/breb/')) {
            $oldPath = $root . '/public' . $old;
            if (is_file($oldPath)) @unlink($oldPath);
        }
        return ['ok' => true, 'message' => 'QR Bre-B actualizado.', 'url' => $url];
    }

    public function deleteQr(): array
    {
        $this->ensureTables();
        $s = $this->settings();
        $old = $s['qr_image'];
        $this->set('breb_qr_image', '');
        self::$cache = [];
        if ($old !== '' && str_starts_with($old, '/assets/uploads/breb/')) {
            $root = defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 2);
            $path = $root . '/public' . $old;
            if (is_file($path)) @unlink($path);
        }
        return ['ok' => true, 'message' => 'QR Bre-B eliminado. Bre-B queda oculto hasta cargar uno nuevo.'];
    }

    public function saveSettings(array $d): array
    {
        $this->ensureTables();
        $map = [
            'breb_enabled' => !empty($d['breb_enabled']) ? '1' : '0',
            'breb_label' => trim(mb_substr((string)($d['breb_label'] ?? ''), 0, 80)) ?: 'Paga a través de Bre-B',
            'breb_discount_pct' => (string)max(0, min(50, (float)($d['breb_discount_pct'] ?? 3))),
            'breb_prices_include_tax' => '1',
            'breb_tax_rate' => (string)max(0, min(30, (float)($d['breb_tax_rate'] ?? 19))),
            'breb_rounding' => (string)(in_array((int)($d['breb_rounding'] ?? 100), [0, 100, 1000], true) ? (int)$d['breb_rounding'] : 100),
            'breb_free_shipping_net_cents' => (string)max(0, (int)round((float)($d['breb_free_shipping_cop'] ?? 300000) * 100)),
            'breb_shipping_cents' => (string)max(0, (int)round((float)($d['breb_shipping_cop'] ?? 15000) * 100)),
            'breb_qr_alt' => trim(mb_substr((string)($d['breb_qr_alt'] ?? ''), 0, 190)) ?: 'QR Bre-B de SONIKA',
            'breb_key' => trim(mb_substr((string)($d['breb_key'] ?? ''), 0, 120)),
            'breb_account_name' => trim(mb_substr((string)($d['breb_account_name'] ?? ''), 0, 160)),
            'breb_instructions' => trim(mb_substr((string)($d['breb_instructions'] ?? ''), 0, 800)),
            'breb_payment_minutes' => (string)max(30, min(1440, (int)($d['breb_payment_minutes'] ?? 120))),
            'breb_stackable' => !empty($d['breb_stackable']) ? '1' : '0',
            'breb_request_enabled' => !empty($d['breb_request_enabled']) ? '1' : '0',
            'breb_terms_url' => trim(mb_substr((string)($d['breb_terms_url'] ?? '/informacion-sobre-promociones'), 0, 300)),
            'breb_whatsapp' => preg_replace('/\D+/', '', (string)($d['breb_whatsapp'] ?? '')),
            'breb_mp_enabled' => !empty($d['breb_mp_enabled']) ? '1' : '0',
            'breb_mp_label' => trim(mb_substr((string)($d['breb_mp_label'] ?? 'Mercado Pago'), 0, 60)) ?: 'Mercado Pago',
            'breb_mp_link' => trim(mb_substr((string)($d['breb_mp_link'] ?? ''), 0, 500)),
            'breb_epayco_enabled' => !empty($d['breb_epayco_enabled']) ? '1' : '0',
            'breb_epayco_label' => trim(mb_substr((string)($d['breb_epayco_label'] ?? 'ePayco'), 0, 60)) ?: 'ePayco',
            'breb_epayco_link' => trim(mb_substr((string)($d['breb_epayco_link'] ?? ''), 0, 500)),
        ];
        foreach ($map as $k => $v) $this->set($k, $v);
        self::$cache = [];
        return ['ok' => true, 'message' => 'Configuración Bre-B guardada.'];
    }

    public function saveGateways(array $d): array
    {
        $this->ensureTables();
        $this->set('breb_mp_enabled', !empty($d['breb_mp_enabled']) ? '1' : '0');
        $this->set('breb_mp_label', trim(mb_substr((string)($d['breb_mp_label'] ?? 'Mercado Pago'), 0, 60)) ?: 'Mercado Pago');
        $this->set('breb_mp_link', trim(mb_substr((string)($d['breb_mp_link'] ?? ''), 0, 500)));
        $this->set('breb_epayco_enabled', !empty($d['breb_epayco_enabled']) ? '1' : '0');
        $this->set('breb_epayco_label', trim(mb_substr((string)($d['breb_epayco_label'] ?? 'ePayco'), 0, 60)) ?: 'ePayco');
        $this->set('breb_epayco_link', trim(mb_substr((string)($d['breb_epayco_link'] ?? ''), 0, 500)));
        self::$cache = [];
        return ['ok' => true, 'message' => 'Pasarelas guardadas.'];
    }

    public function orders(int $limit = 120): array
    {
        $this->ensureTables();
        $st = Database::instance()->prepare("SELECT * FROM store_orders WHERE site_id = @site_id AND payment_method = 'breb' ORDER BY id DESC LIMIT " . max(1, min(500, $limit)));
        $st->execute();
        return $st->fetchAll();
    }

    public function orderById(int $id): ?array
    {
        $this->ensureTables();
        $st = Database::instance()->prepare('SELECT * FROM store_orders WHERE id = ? AND site_id = @site_id LIMIT 1');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    private function order(string $uuid): ?array
    {
        $st = Database::instance()->prepare('SELECT * FROM store_orders WHERE uuid = ? AND site_id = @site_id LIMIT 1');
        $st->execute([$uuid]);
        return $st->fetch() ?: null;
    }

    private function lineItem(string $ref, string $title, string $image, int $grossUnit, int $qty, bool $available, string $issue, string $method, array $s, bool $taxExempt = false, bool $freeShipping = false, int $weightGrams = 0): array
    {
        $qty = max(1, min(99, $qty));
        $grossUnit = max(0, $grossUnit);
        $taxRate = $taxExempt ? 0.0 : (float)$s['tax_rate'];
        $netUnit = (int)round($grossUnit / (1 + $taxRate / 100));
        $taxUnit = $grossUnit - $netUnit;
        $newGross = $grossUnit;
        $newNet = $netUnit;
        $newTax = $taxUnit;
        $discountUnit = 0;
        $discountNetUnit = 0;
        if ($method === 'breb' && $s['discount_pct'] > 0 && $grossUnit > 0) {
            $raw = $netUnit * $s['discount_pct'] / 100 * (1 + $taxRate / 100);
            $step = max(1, (int)$s['rounding'] * 100);
            $discountUnit = (int)round((int)round($raw) / $step) * $step;
            if ($discountUnit > $grossUnit) $discountUnit = $grossUnit;
            if ($discountUnit < 0) $discountUnit = 0;
            $newGross = $grossUnit - $discountUnit;
            $newNet = (int)round($newGross / (1 + $taxRate / 100));
            $newTax = $newGross - $newNet;
            $discountNetUnit = max(0, $netUnit - $newNet);
        }
        return [
            'ref' => $ref,
            'title' => $title,
            'image' => $image,
            'qty' => $qty,
            'available' => $available,
            'issue' => $issue,
            'free_shipping' => $freeShipping,
            'tax_exempt' => $taxExempt,
            'weight_grams' => max(0, $weightGrams),
            'original_unit_cents' => $grossUnit,
            'original_line_cents' => $grossUnit * $qty,
            'net_unit_cents' => $netUnit,
            'tax_unit_cents' => $taxUnit,
            'net_before_cents' => $netUnit * $qty,
            'tax_before_cents' => $taxUnit * $qty,
            'new_unit_cents' => $newGross,
            'new_line_cents' => $newGross * $qty,
            'new_net_unit_cents' => $newNet,
            'new_tax_unit_cents' => $newTax,
            'subtotal_after_cents' => $newGross * $qty,
            'net_after_cents' => $newNet * $qty,
            'tax_after_cents' => $newTax * $qty,
            'discount_unit_cents' => $discountUnit,
            'discount_cents' => $discountUnit * $qty,
            'discount_net_cents' => $discountNetUnit * $qty,
            'recommendation_discount_unit_cents' => 0,
            'recommendation_discount_cents' => 0,
            'recommendation_discount_net_unit_cents' => 0,
            'recommendation_discount_net_cents' => 0,
            'recommendation_rate' => 0,
            'subtotal_before_cents' => $grossUnit * $qty,
        ];
    }

    private function applyRecommendationDiscount(array $line, int $discountUnit, float $taxRate): array
    {
        if (!empty($line['tax_exempt'])) $taxRate = 0.0;
        $qty = max(1, (int)($line['qty'] ?? 1));
        $beforeGross = (int)($line['new_unit_cents'] ?? 0);
        $discountUnit = max(0, min($discountUnit, $beforeGross));
        if ($discountUnit <= 0) return $line;
        $newGross = $beforeGross - $discountUnit;
        $newNet = (int)round($newGross / (1 + $taxRate / 100));
        $newTax = $newGross - $newNet;
        $oldNet = (int)($line['new_net_unit_cents'] ?? 0);
        $line['recommendation_discount_unit_cents'] = $discountUnit;
        $line['recommendation_discount_cents'] = $discountUnit;
        $line['recommendation_discount_net_unit_cents'] = max(0, $oldNet - $newNet);
        $line['recommendation_discount_net_cents'] = $line['recommendation_discount_net_unit_cents'];
        $line['new_unit_cents'] = $newGross;
        $line['new_net_unit_cents'] = $newNet;
        $line['new_tax_unit_cents'] = $newTax;
        $line['new_line_cents'] = $newGross * $qty;
        $line['subtotal_after_cents'] = $newGross * $qty;
        $line['net_after_cents'] = $newNet * $qty;
        $line['tax_after_cents'] = $newTax * $qty;
        return $line;
    }

    private function set(string $key, string $value): void
    {
        try {
            $st = Database::instance()->prepare("INSERT INTO settings (site_id, `key`, `value`) VALUES (@site_id, ?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
            $st->execute([$key, $value]);
        } catch (\Throwable $e) {
        }
    }

    private function safeFileName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
        $name = preg_replace('/[^\p{L}\p{N}._\- ()\[\]]/u', '', $name) ?? '';
        $name = trim($name);
        return $name !== '' ? mb_substr($name, 0, 190) : 'comprobante';
    }
}
