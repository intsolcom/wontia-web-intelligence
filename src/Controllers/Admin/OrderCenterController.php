<?php
namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\OrderCenterService;

/**
 * OrderCenterController — API de la Central de pedidos.
 * Control de acceso server-side por rol (superadmin | admin | editor) y
 * feature flag `order_center_enabled`. Multi-tenant vía @site_id.
 */
class OrderCenterController
{
    private function guard(): OrderCenterService
    {
        $svc = new OrderCenterService();
        if (!in_array((string)Session::userRole(), OrderCenterService::ROLES, true)) {
            Response::error('Sin permiso para la Central de pedidos.', 403);
        }
        if (!$svc->enabled()) {
            Response::error('La Central de pedidos esta deshabilitada.', 403);
        }
        $svc->ensureTables();
        return $svc;
    }

    public function overview(): void
    {
        $svc = $this->guard();
        Response::json(['ok' => true, 'data' => $svc->overview()]);
    }

    public function orders(Request $req): void
    {
        $svc = $this->guard();
        $filters = [
            'search' => (string)$req->get('search', ''),
            'status' => (string)$req->get('status', ''),
            'limit' => (int)$req->get('limit', 50),
            'offset' => (int)$req->get('offset', 0),
        ];
        Response::json(['ok' => true, 'data' => [
            'items' => $svc->orders($filters),
            'total' => $svc->ordersCount($filters),
            'statuses' => $svc->fulfillmentStatuses(),
        ]]);
    }

    public function orderShow(Request $req, string $id): void
    {
        $svc = $this->guard();
        $order = $svc->orderDetail((int)$id);
        if (!$order) Response::error('Pedido no encontrado', 404);
        Response::json(['ok' => true, 'data' => $order]);
    }
}
