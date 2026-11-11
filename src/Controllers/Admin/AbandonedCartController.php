<?php
namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\BannerService;
use App\Services\CartAbandonmentService;
use App\Services\CartRecoveryService;
use App\Services\SeoAnalyticsService;

class AbandonedCartController
{
    public function overview(Request $req): void
    {
        if (!$this->canRead()) return;
        $days = max(1, min(365, (int)$req->get('days', 30)));
        $status = trim((string)$req->get('status', ''));
        $carts = new CartAbandonmentService();
        $metrics = $carts->metrics($days);
        $list = $carts->list(['days' => $days, 'status' => $status, 'limit' => 150]);
        $analytics = [];
        try {
            $analytics = (new SeoAnalyticsService())->overview($days);
        } catch (\Throwable $e) {
        }
        Response::json(['ok' => true, 'data' => [
            'days' => $days,
            'metrics' => $metrics,
            'carts' => $list,
            'traffic' => [
                'views' => (int)($analytics['traffic']['views'] ?? 0),
                'page_views' => (int)($analytics['traffic']['page_views'] ?? 0),
                'unique_visitors' => (int)($analytics['traffic']['unique_visitors'] ?? 0),
                'sessions' => (int)($analytics['traffic']['sessions'] ?? 0),
                'pages_per_session' => (float)($analytics['traffic']['pages_per_session'] ?? 0),
                'avg_time_seconds' => (int)($analytics['traffic']['avg_time_seconds'] ?? 0),
            ],
            'funnel' => [
                'product_view' => (int)($analytics['funnel']['product_view'] ?? 0),
                'cart_add' => (int)($analytics['funnel']['cart_add'] ?? 0),
                'checkout_start' => (int)($analytics['funnel']['checkout_start'] ?? 0),
                'purchase' => (int)($analytics['funnel']['purchase'] ?? 0),
            ],
            'orders' => [
                'paid' => (int)($analytics['orders']['paid'] ?? 0),
                'revenue_cents' => (int)($analytics['orders']['revenue_cents'] ?? 0),
                'abandoned' => (int)($analytics['orders']['abandoned'] ?? 0),
            ],
            'legacy_cart' => is_array($analytics['cart'] ?? null) ? $analytics['cart'] : [],
            'strategy' => [
                'banner_preset' => true,
                'banner_url' => '#banners',
            ],
        ]]);
    }

    public function runRecovery(): void
    {
        if (!$this->canWrite()) return;
        try {
            $result = (new CartRecoveryService())->run();
            Response::json(['ok' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            Response::error('No se pudo ejecutar la recuperación', 500);
        }
    }

    public function status(Request $req, string $id = ''): void
    {
        if (!$this->canWrite()) return;
        $status = trim((string)$req->input('status', ''));
        $channel = trim((string)$req->input('channel', 'manual'));
        $result = (new CartAbandonmentService())->updateStatus((int)$id, $status, $channel);
        Response::json($result, !empty($result['ok']) ? 200 : 422);
    }

    public function delete(Request $req, string $id = ''): void
    {
        if (!$this->canWrite()) return;
        Response::json((new CartAbandonmentService())->delete((int)$id));
    }

    public function export(Request $req): void
    {
        if (!$this->canRead()) return;
        $days = max(1, min(365, (int)$req->get('days', 30)));
        $rows = (new CartAbandonmentService())->list(['days' => $days, 'limit' => 300]);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="carritos-abandonados-' . date('Ymd') . '.csv"');
        header('Cache-Control: no-store');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['id', 'cliente', 'email', 'items', 'qty', 'valor_cop', 'estado', 'fuente', 'campana', 'dispositivo', 'ultima_actividad', 'pedido_recuperado']);
        foreach ($rows as $row) {
            fputcsv($out, [
                $row['id'] ?? '',
                $row['customer_name'] ?? 'Invitado',
                $row['customer_email'] ?? '',
                count((array)($row['items'] ?? [])),
                $row['qty'] ?? 0,
                number_format(((int)($row['value_cents'] ?? 0)) / 100, 0, ',', '.'),
                $row['status'] ?? '',
                $row['source'] ?? '',
                $row['campaign'] ?? '',
                $row['device'] ?? '',
                $row['updated_at'] ?? '',
                $row['recovered_order_uuid'] ?? '',
            ]);
        }
        fclose($out);
        exit;
    }

    public function bannerPreset(): void
    {
        if (!$this->canWrite()) return;
        try {
            $svc = new BannerService();
            $created = $svc->create('Recuperación de carrito');
            if (empty($created['ok']) || empty($created['id'])) {
                Response::error('No se pudo crear el aviso', 500);
                return;
            }
            $id = (int)$created['id'];
            $cfg = $svc->defaults();
            $cfg['mode'] = 'modal';
            $cfg['animation'] = 'fade';
            $cfg['preset'] = 'dark';
            $cfg['bg_color'] = '#111111';
            $cfg['text_color'] = '#ffffff';
            $cfg['bare'] = false;
            $cfg['frequency'] = 'session';
            $cfg['exit_intent'] = true;
            $cfg['exit_intent_min_time'] = 5;
            $cfg['exit_intent_min_scroll'] = 10;
            $cfg['exit_intent_cart_only'] = true;
            $cfg['exit_intent_exclude_checkout'] = true;
            $cfg['exit_intent_mobile'] = false;
            $cfg['behavior_tracking'] = true;
            $cfg['show_cart_summary'] = true;
            $cfg['cart_storage_key'] = 'sonika_cart';
            $cfg['cart_cta_label'] = 'Terminar mi compra';
            $cfg['cart_cta_url'] = '/checkout';
            $cfg['items'] = [[
                'icon' => '🛒',
                'text' => 'Tu carrito sigue aquí. Termina tu compra antes de que se agoten tus productos.',
                'text_b' => '',
                'image' => '',
                'link' => '',
                'link_label' => '',
            ]];
            $cfg['close_label'] = 'Seguir explorando';
            $cfg['dismissible'] = true;
            $svc->save($id, 'Recuperación de carrito', $cfg);
            $svc->setRecovery($id, true);
            Response::json(['ok' => true, 'id' => $id, 'message' => 'Aviso creado y activado como recuperación global. Revísalo en Banners.']);
        } catch (\Throwable $e) {
            Response::error('No se pudo crear el aviso de recuperación', 500);
        }
    }

    private function canRead(): bool
    {
        if (in_array((string)Session::userRole(), ['superadmin', 'admin', 'editor'], true)) return true;
        Response::error('Sin permiso para ver carritos abandonados', 403);
        return false;
    }

    private function canWrite(): bool
    {
        if (in_array((string)Session::userRole(), ['superadmin', 'admin'], true)) return true;
        Response::error('Solo superadmin o admin pueden modificar carritos abandonados', 403);
        return false;
    }
}
