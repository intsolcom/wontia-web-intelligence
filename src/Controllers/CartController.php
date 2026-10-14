<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\CartService;

/** Validación/re-cotización del carrito server-side (seguridad). */
class CartController
{
    public function validate(Request $req): void
    {
        $dir = ROOT_DIR . '/cache/security';
        if (!is_dir($dir)) @mkdir($dir, 0770, true);
        $f = $dir . '/cart_val_' . md5((string)$req->ip()) . '.json';
        $now = time();
        $d = ['c' => 0, 't' => $now];
        if (file_exists($f)) {
            $p = json_decode((string)file_get_contents($f), true);
            if (is_array($p) && $now - (int)($p['t'] ?? $now) < 60) $d = ['c' => (int)($p['c'] ?? 0), 't' => (int)$p['t']];
        }
        if ($d['c'] >= 60) {
            Response::error('Demasiadas solicitudes. Espera un momento.', 429);
            return;
        }
        $d['c']++;
        @file_put_contents($f, json_encode($d), LOCK_EX);

        $items = (array)$req->input('items', []);
        Response::json(['ok' => true, 'data' => (new CartService())->validate($items)]);
    }
}
