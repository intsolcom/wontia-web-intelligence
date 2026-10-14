<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\CartService;
use App\Services\CartPersistenceService;
use App\Services\CustomerService;
use App\Services\StoreCouponService;

/** Carrito: validación/re-cotización, cupones y sincronización por cuenta (seguridad). */
class CartController
{
    private function rateLimit(Request $req, string $scope, int $max): bool
    {
        $dir = ROOT_DIR . '/cache/security';
        if (!is_dir($dir)) @mkdir($dir, 0770, true);
        $f = $dir . '/cart_' . preg_replace('/[^a-z]/', '', $scope) . '_' . md5((string)$req->ip()) . '.json';
        $now = time();
        $d = ['c' => 0, 't' => $now];
        if (file_exists($f)) {
            $p = json_decode((string)file_get_contents($f), true);
            if (is_array($p) && $now - (int)($p['t'] ?? $now) < 60) $d = ['c' => (int)($p['c'] ?? 0), 't' => (int)$p['t']];
        }
        if ($d['c'] >= $max) {
            Response::error('Demasiadas solicitudes. Espera un momento.', 429);
            return false;
        }
        $d['c']++;
        @file_put_contents($f, json_encode($d), LOCK_EX);
        return true;
    }

    public function validate(Request $req): void
    {
        if (!$this->rateLimit($req, 'val', 60)) return;
        $items = (array)$req->input('items', []);
        Response::json(['ok' => true, 'data' => (new CartService())->validate($items)]);
    }

    public function coupon(Request $req): void
    {
        if (!$this->rateLimit($req, 'cpn', 30)) return;
        $code = (string)$req->input('code', '');
        $sub = max(0, (int)$req->input('subtotal_cents', 0));
        Response::json(['ok' => true, 'data' => (new StoreCouponService())->validate($code, $sub)]);
    }

    public function sync(Request $req): void
    {
        if (!$this->rateLimit($req, 'sync', 60)) return;
        $items = (array)$req->input('items', []);
        $coupon = (string)$req->input('coupon', '');
        $me = (new CustomerService())->current();
        if (!$me) {
            Response::json(['ok' => true, 'data' => ['authenticated' => false, 'items' => array_values($items), 'coupon' => $coupon]]);
            return;
        }
        $svc = new CartPersistenceService();
        $server = $svc->load((int)$me['id']);
        $merged = $svc->merge($items, $server['items']);
        $cp = $coupon !== '' ? $coupon : (string)$server['coupon'];
        $svc->save((int)$me['id'], $merged, $cp);
        Response::json(['ok' => true, 'data' => ['authenticated' => true, 'items' => $merged, 'coupon' => $cp]]);
    }
}
