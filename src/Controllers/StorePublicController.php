<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\BrebService;
use App\Services\StoreRateLimitException;
use App\Services\StoreService;
use App\Services\ReviewService;
use App\Services\ShippingService;
use App\Services\SocialProofService;

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

    public function socialProof(): void
    {
        $svc = new SocialProofService();
        $event = $svc->randomEvent();
        if (!$event) {
            Response::json(['ok' => false, 'message' => 'Prueba social no disponible.'], 404);
            return;
        }
        header('Cache-Control: no-store, max-age=0');
        Response::json(['ok' => true, 'data' => $event, 'stats' => $svc->stats()]);
    }

    public function reviews(Request $req): void
    {
        $ref = trim((string)$req->get('ref', ''));
        $svc = new ReviewService();
        if ($ref === '') {
            Response::json(['ok' => true, 'data' => ['summary' => ['count' => 0, 'average' => 0, 'dist' => []], 'items' => []]]);
            return;
        }
        $limit = (int)$req->get('limit', 20);
        Response::json(['ok' => true, 'data' => ['summary' => $svc->summary($ref), 'items' => $svc->forProduct($ref, $limit)]]);
    }

    public function reviewHelpful(Request $req, string $id): void
    {
        Response::json((new ReviewService())->markHelpful((int)$id));
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

    public function shippingDepartments(): void
    {
        try {
            Response::json(['ok' => true, 'data' => (new ShippingService())->departments()]);
        } catch (\Throwable $e) {
            Response::error('No se pudieron cargar los departamentos', 500);
        }
    }

    public function shippingMunicipalities(Request $req): void
    {
        try {
            $department = (string)$req->get('department', '');
            Response::json(['ok' => true, 'data' => (new ShippingService())->municipalities($department)]);
        } catch (\Throwable $e) {
            Response::error('No se pudieron cargar los municipios', 500);
        }
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

    public function catalogOrder(Request $req): void
    {
        try {
            $result = $this->svc()->createCatalogOrder($req->json(), $req->ip());
            Response::json(['ok' => true, 'data' => [
                'order_number' => $result['order']['order_number'] ?? '',
                'uuid' => $result['order']['uuid'] ?? '',
                'payment' => $result['payment'] ?? null,
            ]], 201);
        } catch (StoreRateLimitException $e) {
            Response::error($e->getMessage(), 429);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            Response::error($e->getMessage(), 400);
        } catch (\Throwable $e) {
            Response::error('No se pudo crear el pedido', 500);
        }
    }

    public function quote(Request $req): void
    {
        try {
            $body = $req->json();
            $items = (array)($body['items'] ?? []);
            $method = (string)($body['payment_method'] ?? 'breb');
            $coupon = (string)($body['coupon'] ?? '');
            $points = (int)($body['points'] ?? 0);
            $shipping = (array)($body['shipping'] ?? []);
            $giftCard = (array)($body['gift_card'] ?? []);
            $q = (new BrebService())->quote($items, $method, $coupon, $points, $shipping, $giftCard);
            Response::json(['ok' => (bool)($q['ok'] ?? false), 'data' => $q], !empty($q['ok']) ? 200 : 400);
        } catch (\Throwable $e) {
            Response::error('No se pudo cotizar el pedido', 400);
        }
    }

    public function uploadProof(Request $req, string $uuid): void
    {
        try {
            $file = $_FILES['proof'] ?? $_FILES['file'] ?? [];
            $r = (new BrebService())->uploadProof($uuid, (array)$file, $req->ip());
            $status = (int)($r['status'] ?? (!empty($r['ok']) ? 200 : 422));
            Response::json([
                'ok' => (bool)($r['ok'] ?? false),
                'message' => (string)($r['message'] ?? ''),
                'data' => ['proof_status' => $r['proof_status'] ?? null, 'max_mb' => $r['max_mb'] ?? 2],
            ], $status);
        } catch (\Throwable $e) {
            Response::error('No se pudo recibir el comprobante', 500);
        }
    }

    public function orderStatus(Request $req, string $uuid): void
    {
        $svc = $this->svc();
        if (!$svc->ready()) Response::error('Tienda no disponible', 404);
        $o = $svc->orderByUuid($uuid);
        if (!$o) Response::error('Pedido no encontrado', 404);
        $payment = (new BrebService())->paymentInfo($o);
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
            'payment' => $payment,
        ]]);
    }

    public function brebQrPdf(Request $req, string $uuid): void
    {
        try {
            $svc = $this->svc();
            $order = $svc->ready() ? $svc->orderByUuid($uuid) : null;
            if (!$order || (string)($order['payment_method'] ?? '') !== 'breb') {
                $this->textResponse(404, 'El QR de Bre-B no esta disponible para este pedido.');
                return;
            }
            $info = (new BrebService())->paymentInfo($order);
            $breb = (array)($info['breb'] ?? []);
            if (empty($breb['qr_image'])) {
                $this->textResponse(404, 'El QR de Bre-B no esta configurado.');
                return;
            }
            $service = new \App\Services\BrebQrPdfService();
            $pdf = $service->pdf($order, $breb);
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $service->filename($order) . '"');
            header('Content-Length: ' . strlen($pdf));
            header('Cache-Control: private, no-store, max-age=0');
            header('X-Content-Type-Options: nosniff');
            echo $pdf;
        } catch (\Throwable $e) {
            $this->textResponse(500, 'No se pudo generar el PDF del QR.');
        }
    }

    private function textResponse(int $status, string $message): void
    {
        http_response_code($status);
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
        echo $message;
    }

    public function sistecreditoCallback(Request $req): void
    {
        $raw = (string)file_get_contents('php://input');
        $payload = $req->json();
        if (!$payload) $payload = $_POST ?: $_GET;
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        Response::json((new \App\Services\SistecreditoService())->handleCallback(is_array($payload) ? $payload : [], $raw, $req->ip()));
    }

    public function sistecreditoStatus(Request $req): void
    {
        $uuid = trim((string)$req->get('uuid', ''));
        if ($uuid === '') {
            Response::error('Pedido requerido', 400);
            return;
        }
        header('Cache-Control: no-store');
        Response::json((new \App\Services\SistecreditoService())->statusForOrder($uuid));
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
