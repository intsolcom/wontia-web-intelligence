<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\StoreRateLimitException;
use App\Services\StoreService;

class StorePublicController
{
    private function svc(): StoreService
    {
        return new StoreService();
    }

    public function config(): void
    {
        $svc = $this->svc();
        if (!$svc->ready()) {
            Response::json(['ok' => true, 'data' => ['enabled' => false]]);
            return;
        }
        $s = $svc->settings();
        Response::json(['ok' => true, 'data' => [
            'enabled' => $s['store_enabled'] !== '0',
            'currency' => $s['store_currency'],
            'payment_methods' => $svc->paymentMethods(),
            'whatsapp' => $s['store_whatsapp'],
            'shipping_note' => $s['store_shipping_note'],
            'min_order_cents' => (int)$s['store_min_order_cents'],
            'terms_url' => $s['store_terms_url'],
        ]]);
    }

    public function products(Request $req): void
    {
        $svc = $this->svc();
        if (!$svc->ready()) {
            Response::json(['ok' => true, 'data' => [], 'total' => 0]);
            return;
        }
        $filters = [
            'status' => 'active',
            'category_id' => $req->get('category_id', ''),
            'search' => $req->get('search', ''),
            'featured' => $req->get('featured', ''),
            'limit' => $req->get('limit', 24),
            'offset' => $req->get('offset', 0),
        ];
        $catSlug = (string)$req->get('category', '');
        if ($catSlug !== '' && $filters['category_id'] === '') {
            foreach ($svc->categories(true) as $c) {
                if ($c['slug'] === $catSlug) { $filters['category_id'] = (int)$c['id']; break; }
            }
        }
        $items = $svc->products($filters);
        foreach ($items as &$p) unset($p['seo_title'], $p['seo_description'], $p['created_at'], $p['updated_at']);
        Response::json(['ok' => true, 'data' => $items, 'total' => $svc->productsCount($filters)]);
    }

    public function product(Request $req, string $slug): void
    {
        $svc = $this->svc();
        if (!$svc->ready()) Response::error('Tienda no disponible', 404);
        $p = $svc->productBySlug($slug);
        if (!$p) Response::error('Producto no encontrado', 404);
        $p['variants'] = array_values(array_filter($svc->variants((int)$p['id']), fn($v) => (int)$v['is_active'] === 1));
        unset($p['seo_title'], $p['seo_description'], $p['created_at'], $p['updated_at']);
        Response::json(['ok' => true, 'data' => $p]);
    }

    public function categories(): void
    {
        $svc = $this->svc();
        Response::json(['ok' => true, 'data' => $svc->ready() ? $svc->categories(true) : []]);
    }

    public function zones(): void
    {
        $svc = $this->svc();
        Response::json(['ok' => true, 'data' => $svc->ready() ? $svc->zones(true) : []]);
    }

    public function createOrder(Request $req): void
    {
        try {
            $result = $this->svc()->createOrder($req->json(), $req->ip());
            Response::json(['ok' => true, 'data' => $result], 201);
        } catch (StoreRateLimitException $e) {
            Response::error($e->getMessage(), 429);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            Response::error($e->getMessage(), 400);
        } catch (\Throwable $e) {
            Response::error('No se pudo crear el pedido', 500);
        }
    }

    public function orderStatus(Request $req, string $uuid): void
    {
        $svc = $this->svc();
        if (!$svc->ready()) Response::error('Tienda no disponible', 404);
        $o = $svc->orderByUuid($uuid);
        if (!$o) Response::error('Pedido no encontrado', 404);
        Response::json(['ok' => true, 'data' => [
            'uuid' => $o['uuid'],
            'order_number' => $o['order_number'],
            'total_cents' => (int)$o['total_cents'],
            'currency' => $o['currency'],
            'payment_method' => $o['payment_method'],
            'payment_status' => $o['payment_status'],
            'fulfillment_status' => $o['fulfillment_status'],
            'items' => $o['items'],
            'created_at' => $o['created_at'],
        ]]);
    }

    public function wompiWebhook(Request $req): void
    {
        $svc = $this->svc();
        if (!$svc->ready()) Response::error('Tienda no disponible', 503);
        $body = $req->json();
        if (!$svc->verifyWompiWebhook($body)) {
            Response::error('Firma invalida', 401);
            return;
        }
        $tx = $body['data']['transaction'] ?? [];
        $reference = (string)($tx['reference'] ?? '');
        $status = strtoupper((string)($tx['status'] ?? ''));
        if ($reference === '') {
            Response::error('Referencia ausente', 400);
            return;
        }
        $order = $svc->orderByUuid($reference);
        if (!$order) {
            Response::error('Pedido no encontrado', 404);
            return;
        }
        if (in_array($status, ['APPROVED'], true)) {
            $svc->markPaid($reference, (string)($tx['id'] ?? ''), 'wompi');
        } elseif (in_array($status, ['DECLINED', 'VOIDED', 'ERROR'], true)) {
            $svc->updateOrder((int)$order['id'], ['payment_status' => 'failed']);
        }
        Response::json(['ok' => true, 'data' => ['order' => $reference, 'status' => $status]]);
    }
}
