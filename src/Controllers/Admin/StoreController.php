<?php
namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\StoreService;

class StoreController
{
    private function svc(): StoreService
    {
        return new StoreService();
    }

    public function ensureTables(): void
    {
        Response::json(['ok' => true, 'data' => $this->svc()->ensureTables()]);
    }

    public function overview(): void
    {
        $svc = $this->svc();
        if (!$svc->ready()) {
            Response::json(['ok' => true, 'data' => ['ready' => false, 'stats' => [], 'settings' => [], 'methods' => []]]);
            return;
        }
        Response::json(['ok' => true, 'data' => [
            'ready' => true,
            'stats' => $svc->stats(),
            'settings' => $svc->settings(),
            'methods' => $svc->paymentMethods(),
        ]]);
    }

    // ── Products ──

    public function products(Request $req): void
    {
        $svc = $this->svc();
        if (!$svc->ready()) Response::json(['ok' => true, 'data' => [], 'total' => 0]);
        $filters = [
            'status' => $req->get('status', ''),
            'category_id' => $req->get('category_id', ''),
            'search' => $req->get('search', ''),
            'limit' => $req->get('limit', 100),
            'offset' => $req->get('offset', 0),
        ];
        Response::json(['ok' => true, 'data' => $svc->products($filters), 'total' => $svc->productsCount($filters)]);
    }

    public function productShow(Request $req, string $id): void
    {
        $svc = $this->svc();
        $p = $svc->product((int)$id);
        if (!$p) Response::error('Producto no encontrado', 404);
        $p['variants'] = $svc->variants((int)$id);
        Response::json(['ok' => true, 'data' => $p]);
    }

    public function productSave(Request $req, ?string $id = null): void
    {
        $d = $req->json();
        $pid = !empty($d['id']) ? (int)$d['id'] : ($id !== null ? (int)$id : null);
        try {
            $saved = $this->svc()->saveProduct($d, $pid);
            Response::json(['ok' => true, 'data' => ['id' => $saved]], $pid ? 200 : 201);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        } catch (\Throwable $e) {
            Response::error('No se pudo guardar: ' . $e->getMessage(), 500);
        }
    }

    public function productDelete(Request $req, string $id): void
    {
        $this->svc()->deleteProduct((int)$id);
        Response::json(['ok' => true]);
    }

    // ── Categories ──

    public function categories(): void
    {
        $svc = $this->svc();
        Response::json(['ok' => true, 'data' => $svc->ready() ? $svc->categories() : []]);
    }

    public function categorySave(Request $req, ?string $id = null): void
    {
        $d = $req->json();
        $cid = !empty($d['id']) ? (int)$d['id'] : ($id !== null ? (int)$id : null);
        try {
            Response::json(['ok' => true, 'data' => ['id' => $this->svc()->saveCategory($d, $cid)]]);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function categoryDelete(Request $req, string $id): void
    {
        $this->svc()->deleteCategory((int)$id);
        Response::json(['ok' => true]);
    }

    // ── Orders ──

    public function orders(Request $req): void
    {
        $svc = $this->svc();
        if (!$svc->ready()) Response::json(['ok' => true, 'data' => []]);
        Response::json(['ok' => true, 'data' => $svc->orders([
            'payment_status' => $req->get('payment_status', ''),
            'fulfillment_status' => $req->get('fulfillment_status', ''),
            'search' => $req->get('search', ''),
            'limit' => $req->get('limit', 100),
        ])]);
    }

    public function orderShow(Request $req, string $id): void
    {
        $o = $this->svc()->orderById((int)$id);
        if (!$o) Response::error('Pedido no encontrado', 404);
        Response::json(['ok' => true, 'data' => $o]);
    }

    public function orderUpdate(Request $req, string $id): void
    {
        $this->svc()->updateOrder((int)$id, $req->json());
        Response::json(['ok' => true]);
    }

    // ── Zones ──

    public function zones(): void
    {
        $svc = $this->svc();
        Response::json(['ok' => true, 'data' => $svc->ready() ? $svc->zones() : []]);
    }

    public function zoneSave(Request $req, ?string $id = null): void
    {
        $d = $req->json();
        $zid = !empty($d['id']) ? (int)$d['id'] : ($id !== null ? (int)$id : null);
        try {
            Response::json(['ok' => true, 'data' => ['id' => $this->svc()->saveZone($d, $zid)]]);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function zoneDelete(Request $req, string $id): void
    {
        $this->svc()->deleteZone((int)$id);
        Response::json(['ok' => true]);
    }

    public function maintenance(): void
    {
        $siteId = (int)Database::instance()->query("SELECT @site_id")->fetchColumn();
        Response::json(['ok' => true, 'data' => $this->svc()->releaseExpiredOrders($siteId ?: null, 60)]);
    }

    // ── Settings ──

    public function settings(): void
    {
        $svc = $this->svc();
        $s = $svc->settings();
        $s['store_wompi_public_key_set'] = $s['store_wompi_public_key'] !== '';
        $s['store_wompi_integrity_key_set'] = $s['store_wompi_integrity_key'] !== '';
        $s['store_wompi_events_key_set'] = $s['store_wompi_events_key'] !== '';
        unset($s['store_wompi_integrity_key'], $s['store_wompi_events_key']);
        Response::json(['ok' => true, 'data' => $s, 'methods' => $svc->paymentMethods()]);
    }

    public function settingsSave(Request $req): void
    {
        $d = $req->json();
        if (array_key_exists('store_payment_methods', $d)) {
            $d['store_payment_methods'] = array_values(array_intersect((array)$d['store_payment_methods'], StoreService::PAYMENT_METHODS));
        }
        foreach (['store_wompi_integrity_key', 'store_wompi_events_key'] as $secret) {
            if (array_key_exists($secret, $d) && trim((string)$d[$secret]) === '') unset($d[$secret]);
        }
        $this->svc()->saveSettings($d);
        Response::json(['ok' => true, 'data' => $this->svc()->paymentMethods()]);
    }
}
