<?php
namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\StoreService;
use App\Services\OrderOperationsService;
use App\Services\ReviewService;
use App\Services\ReturnService;
use App\Services\ShippingService;
use App\Services\ProductContentService;
use App\Services\CheckoutPaymentMethodService;
use App\Services\AffiliateService;

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

    public function overview(Request $req): void
    {
        $svc = $this->svc();
        if (!$svc->ready()) {
            Response::json(['ok' => true, 'data' => ['ready' => false, 'stats' => [], 'settings' => [], 'methods' => []]]);
            return;
        }
        $includeTests = (string)$req->get('include_tests', '0') === '1';
        $settings = $svc->settings();
        unset($settings['store_wompi_integrity_key'], $settings['store_wompi_events_key']);
        Response::json(['ok' => true, 'data' => [
            'ready' => true,
            'stats' => $svc->stats($includeTests),
            'settings' => $settings,
            'methods' => $svc->paymentMethods(),
        ]]);
    }

    // ── Products ──

    public function products(Request $req): void
    {
        $svc = $this->svc();
        if (!$svc->ready()) Response::json(['ok' => true, 'data' => [], 'total' => 0]);
        try { (new \App\Services\CatalogProductService())->syncIfNeeded(); (new \App\Services\CatalogCategoryService())->syncIfNeeded(); } catch (\Throwable $e) {}
        $filters = [
            'status' => $req->get('status', ''),
            'category_id' => $req->get('category_id', ''),
            'search' => $req->get('search', ''),
            'limit' => $req->get('limit', 100),
            'offset' => $req->get('offset', 0),
            'include_private' => true,
        ];
        Response::json(['ok' => true, 'data' => $svc->productsAdmin($filters), 'total' => $svc->productsCount($filters)]);
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

    /** IP pública actual del administrador, para autorizar productos privados. */
    public function currentIp(Request $req): void
    {
        $ip = \App\Services\IpAccessService::clientIp();
        Response::json(['ok' => true, 'data' => [
            'ip' => $ip,
            'masked' => \App\Services\IpAccessService::maskIp($ip),
        ]]);
    }

    /** Configuración fiscal que usa el editor de producto. */
    public function taxConfig(Request $req): void
    {
        $s = (new \App\Services\BrebService())->settings();
        Response::json(['ok' => true, 'data' => [
            'tax_rate' => (float)($s['tax_rate'] ?? 19),
            'prices_include_tax' => !empty($s['prices_include_tax']),
            'currency' => (string)($s['currency'] ?? 'COP'),
        ]]);
    }

    // ── Categories ──

    public function categories(): void
    {
        $svc = $this->svc();
        if ($svc->ready()) { try { (new \App\Services\CatalogCategoryService())->syncIfNeeded(); } catch (\Throwable $e) {} }
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

    public function catalogCategories(): void
    {
        try {
            $theme = (string)(Database::instance()->query("SELECT theme FROM sites WHERE id = @site_id")->fetchColumn() ?: 'default');
            $theme = preg_replace('/[^a-z0-9_-]/i', '', $theme) ?: 'default';
            $file = ROOT_DIR . '/templates/themes/' . $theme . '/lib.php';
            if (is_file($file)) require_once $file;
            $rows = function_exists('sonika_categories') ? sonika_categories() : [];
            $out = [];
            foreach ($rows as $row) {
                $out[] = ['slug' => (string)$row['slug'], 'name' => (string)$row['name']];
            }
            Response::json(['ok' => true, 'data' => $out]);
        } catch (\Throwable $e) {
            Response::json(['ok' => true, 'data' => []]);
        }
    }

    public function catalogBrands(): void
    {
        try {
            $db = Database::instance();
            $stmt = $db->query("SELECT vendor, COUNT(*) AS total FROM store_products WHERE site_id = @site_id AND status = 'active' AND vendor IS NOT NULL AND vendor <> '' GROUP BY vendor ORDER BY vendor ASC");
            $rows = [];
            foreach ($stmt->fetchAll() as $row) {
                $rows[] = ['name' => (string)$row['vendor'], 'count' => (int)$row['total']];
            }
            Response::json(['ok' => true, 'data' => $rows, 'total' => count($rows)]);
        } catch (\Throwable $e) {
            Response::error('No se pudieron cargar las marcas', 500);
        }
    }

    // ── Contenido editorial ──

    public function contentAudit(Request $req): void
    {
        @set_time_limit(120);
        try {
            $path = '';
            foreach ([
                ROOT_DIR . '/public/assets/uploads/_catalog/catalog.json',
                ROOT_DIR . '/templates/themes/sonika/catalog.json',
            ] as $candidate) {
                if (is_file($candidate)) { $path = $candidate; break; }
            }
            if ($path === '') {
                Response::json(['ok' => false, 'message' => 'No se encontró el catálogo de productos.'], 404);
                return;
            }

            $svc = new ProductContentService();
            $audit = $svc->auditCatalog($path);
            $rows = (array)($audit['rows'] ?? []);

            $q = trim((string)$req->get('q', ''));
            $issue = preg_replace('/[^a-z_]/', '', (string)$req->get('issue', ''));
            $format = preg_replace('/[^a-z_]/', '', (string)$req->get('format', ''));
            $sort = (string)$req->get('sort', 'score');
            $allowedSort = ['score', 'chars_desc', 'chars_asc', 'title', 'recent'];
            if (!in_array($sort, $allowedSort, true)) $sort = 'score';
            $allowedFormats = ['only_text', 'video', 'image', 'table', 'list', 'styled'];
            if ($format !== '' && !in_array($format, $allowedFormats, true)) $format = '';

            if ($q !== '') {
                $needle = mb_strtolower($q, 'UTF-8');
                $rows = array_values(array_filter($rows, static function ($row) use ($needle) {
                    $haystack = mb_strtolower((string)$row['title'] . ' ' . (string)$row['handle'] . ' ' . (string)$row['vendor'], 'UTF-8');
                    return mb_strpos($haystack, $needle) !== false;
                }));
            }
            if ($issue !== '') {
                $rows = array_values(array_filter($rows, static fn($row) => in_array($issue, (array)$row['issue_keys'], true)));
            }
            if ($format !== '') {
                $rows = array_values(array_filter($rows, static function ($row) use ($format) {
                    if ($format === 'video') return !empty($row['has_video']);
                    if ($format === 'image') return !empty($row['has_image']);
                    if ($format === 'table') return !empty($row['has_table']);
                    if ($format === 'list') return !empty($row['has_list']);
                    if ($format === 'styled') return !empty($row['inline_styles']);
                    return !empty($row['only_text']);
                }));
            }

            usort($rows, static function ($a, $b) use ($sort) {
                if ($sort === 'chars_desc') return $b['chars'] <=> $a['chars'];
                if ($sort === 'chars_asc') return $a['chars'] <=> $b['chars'];
                if ($sort === 'title') return strcasecmp((string)$a['title'], (string)$b['title']);
                if ($sort === 'recent') return strcmp((string)$b['handle'], (string)$a['handle']);
                return $a['score'] <=> $b['score'] ?: $b['chars'] <=> $a['chars'];
            });

            $total = count($rows);
            $limit = max(10, min(200, (int)$req->get('limit', 50)));
            $offset = max(0, (int)$req->get('offset', 0));
            $page = array_slice($rows, $offset, $limit);

            Response::json(['ok' => true, 'data' => [
                'summary' => $audit['summary'] ?? [],
                'rows' => $page,
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'enabled' => ProductContentService::enabled(),
                'generated_at' => (string)($audit['generated_at'] ?? ''),
                'source' => basename($path),
            ]]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'message' => 'No se pudo auditar el contenido: ' . $e->getMessage()], 500);
        }
    }

    // ── Orders ──

    public function orders(Request $req): void
    {
        $svc = $this->svc();
        if (!$svc->ready()) Response::json(['ok' => true, 'data' => [], 'counts' => ['active' => 0, 'archived' => 0, 'total' => 0]]);
        Response::json([
            'ok' => true,
            'data' => $svc->orders([
                'payment_status' => $req->get('payment_status', ''),
                'fulfillment_status' => $req->get('fulfillment_status', ''),
                'archived' => $req->get('archived', ''),
                'is_test' => $req->get('is_test', ''),
                'payment_method' => $req->get('payment_method', ''),
                'date_from' => $req->get('date_from', ''),
                'date_to' => $req->get('date_to', ''),
                'min_cents' => $req->get('min_cents', ''),
                'max_cents' => $req->get('max_cents', ''),
                'search' => $req->get('search', ''),
                'limit' => $req->get('limit', 100),
            ]),
            'counts' => $svc->orderCounts(),
        ]);
    }

    public function orderArchive(Request $req): void
    {
        $d = $req->json();
        $ids = $d['ids'] ?? [];
        if (!is_array($ids)) $ids = [$ids];
        if (!$ids && !empty($d['id'])) $ids = [$d['id']];
        $archived = !empty($d['archived']);
        $reason = mb_substr(trim((string)($d['reason'] ?? '')), 0, 40);
        $userId = \App\Core\Session::userId();
        if (!empty($d['all'])) {
            $updated = $this->svc()->archiveOrdersByFilters((array)($d['filters'] ?? []), $archived, $reason, $userId);
        } else {
            $updated = $this->svc()->archiveOrders($ids, $archived, $reason, $userId);
        }
        Response::json(['ok' => true, 'data' => ['updated' => $updated]]);
    }

    public function orderTest(Request $req): void
    {
        $d = $req->json();
        $ids = $d['ids'] ?? [];
        if (!is_array($ids)) $ids = [$ids];
        $updated = $this->svc()->markOrdersTest($ids, !empty($d['is_test']));
        Response::json(['ok' => true, 'data' => ['updated' => $updated]]);
    }

    public function orderDetectTests(): void
    {
        Response::json(['ok' => true, 'data' => $this->svc()->detectTestOrders(300)]);
    }

    public function orderExportCsv(Request $req): void
    {
        $filters = [
            'payment_status' => $req->get('payment_status', ''),
            'fulfillment_status' => $req->get('fulfillment_status', ''),
            'archived' => $req->get('archived', ''),
            'is_test' => $req->get('is_test', ''),
            'payment_method' => $req->get('payment_method', ''),
            'date_from' => $req->get('date_from', ''),
            'date_to' => $req->get('date_to', ''),
            'min_cents' => $req->get('min_cents', ''),
            'max_cents' => $req->get('max_cents', ''),
            'search' => $req->get('search', ''),
        ];
        $rows = $this->svc()->ordersExport($filters);
        $safe = function ($value): string {
            $value = (string)$value;
            return preg_match('/^[=+\-@]/', $value) ? "'" . $value : $value;
        };
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="pedidos-' . date('Ymd-His') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Pedido', 'Cliente', 'Email', 'Teléfono', 'Total', 'Moneda', 'Método', 'Pago', 'Estado', 'Prueba', 'Archivado', 'Motivo', 'Archivado por', 'Fecha'], ';');
        foreach ($rows as $row) {
            fputcsv($out, [
                $safe($row['order_number'] ?? $row['uuid'] ?? ''),
                $safe($row['customer_name'] ?? ''),
                $safe($row['customer_email'] ?? ''),
                $safe($row['customer_phone'] ?? ''),
                number_format(((int)($row['total_cents'] ?? 0)) / 100, 2, ',', ''),
                $safe($row['currency'] ?? 'COP'),
                $safe($row['payment_method'] ?? ''),
                $safe($row['payment_status'] ?? ''),
                $safe($row['fulfillment_status'] ?? ''),
                !empty($row['is_test']) ? 'Sí' : 'No',
                $safe($row['archived_at'] ?? ''),
                $safe($row['archive_reason'] ?? ''),
                $safe($row['archived_by_name'] ?? $row['archived_by'] ?? ''),
                $safe($row['created_at'] ?? ''),
            ], ';');
        }
        fclose($out);
        exit;
    }

    public function orderShow(Request $req, string $id): void
    {
        $detail = (new OrderOperationsService())->detail((int)$id);
        if (!$detail) Response::error('Pedido no encontrado', 404);
        $detail['events'] = $this->svc()->orderEvents((int)$id, 50);
        Response::json(['ok' => true, 'data' => $detail]);
    }

    public function orderUpdate(Request $req, string $id): void
    {
        $this->svc()->updateOrder((int)$id, $req->json());
        Response::json(['ok' => true]);
    }

    public function orderChecklistPayment(Request $req, string $id): void
    {
        if (!in_array((string)\App\Core\Session::userRole(), ['superadmin', 'admin'], true)) {
            Response::error('No autorizado para confirmar el abono bancario.', 403);
        }
        $d = $req->json();
        $settled = !empty($d['settled']);
        $reference = mb_substr(trim((string)($d['reference'] ?? '')), 0, 190);
        if ($settled && $reference === '') {
            Response::error('Escribe la referencia del abono bancario.', 422);
        }
        $r = (new OrderOperationsService())->setPaymentSettled((int)$id, $settled, $reference, (int)\App\Core\Session::userId());
        Response::json($r);
    }

    public function orderReportProvider(Request $req, string $id): void
    {
        if (!in_array((string)\App\Core\Session::userRole(), ['superadmin', 'admin'], true)) {
            Response::error('No autorizado para reportar el pedido al proveedor.', 403);
        }
        $r = (new OrderOperationsService())->reportToProvider((int)$id, (int)\App\Core\Session::userId());
        Response::json($r, !empty($r['ok']) ? 200 : 422);
    }

    /**
     * Atribuye manualmente un pedido a un partner. Sirve para ventas asistidas
     * que quedaron marcadas como internas o que perdieron la cookie de origen.
     */
    public function orderAttributePartner(Request $req, string $id): void
    {
        if (!in_array((string)\App\Core\Session::userRole(), ['superadmin', 'admin'], true)) {
            Response::error('No autorizado para atribuir pedidos a un partner.', 403);
        }
        $order = $this->svc()->orderById((int)$id);
        if (!$order) Response::error('Pedido no encontrado.', 404);
        $d = $req->json();
        $code = trim((string)($d['code'] ?? ''));
        if ($code === '') $code = trim((string)($order['affiliate_ref'] ?? ''));
        if ($code === '') Response::error('Indica el código del partner.', 422);
        $affSvc = new AffiliateService();
        $link = $affSvc->resolveLink($code);
        if (!$link) Response::error('Código de partner no válido.', 422);
        $aff = $affSvc->getById((int)$link['affiliate_id']);
        if (!$aff || (string)($aff['status'] ?? '') !== 'APPROVED') Response::error('El partner no está aprobado.', 422);
        $base = max(0.0, ((float)($order['subtotal_cents'] ?? 0) - (float)($order['discount_cents'] ?? 0)) / 100);
        $maxItem = 0.0;
        foreach ((array)($order['items'] ?? []) as $it) {
            $maxItem = max($maxItem, ((float)($it['price_cents'] ?? 0)) / 100);
        }
        $db = Database::instance();
        $r = $affSvc->attributeOrder((string)$order['uuid'], $base, null, (string)$link['code'], 0.0, $maxItem);
        if (!$r || empty($r['ok'])) {
            Response::error('No se pudo atribuir. Verifica que el partner tenga un clic válido en los últimos 30 días.', 422);
        }
        $db->prepare('UPDATE store_orders SET is_internal=0, internal_reason=NULL, affiliate_ref=? WHERE site_id=@site_id AND id=?')
            ->execute([(string)$link['code'], (int)$id]);
        try {
            $affSvc->log((int)$link['affiliate_id'], 'manual_attribution', [
                'order' => (string)$order['uuid'], 'order_number' => (string)($order['order_number'] ?? ''),
                'by' => 'admin:' . (int)\App\Core\Session::userId(), 'base' => $base, 'max_item' => $maxItem,
            ]);
        } catch (\Throwable $e) {}
        try {
            $this->svc()->logOrderEvent((int)$id, 'partner_attributed', '', (string)$link['code'], 'Atribución manual a partner', (int)\App\Core\Session::userId());
        } catch (\Throwable $e) {}
        Response::json([
            'ok' => true,
            'data' => $r,
            'affiliate' => [
                'id' => (int)$link['affiliate_id'],
                'name' => trim((string)($aff['full_name'] ?? $aff['public_name'] ?? $aff['artist_name'] ?? '')),
                'code' => (string)$link['code'],
            ],
        ]);
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

    // ── Shipping ──

    private function shipping(): ShippingService
    {
        return new ShippingService();
    }

    public function shippingOverview(): void
    {
        try {
            Response::json(['ok' => true, 'data' => $this->shipping()->overview()]);
        } catch (\Throwable $e) {
            Response::error('No se pudo cargar el panel de envíos: ' . $e->getMessage(), 500);
        }
    }

    public function shippingMunicipalities(Request $req): void
    {
        try {
            Response::json(['ok' => true, 'data' => $this->shipping()->municipalitiesWithRates((string)$req->get('department', ''))]);
        } catch (\Throwable $e) {
            Response::error('No se pudieron cargar los municipios: ' . $e->getMessage(), 500);
        }
    }

    public function shippingRateSave(Request $req, ?string $id = null): void
    {
        $data = $req->json();
        $rateId = !empty($data['id']) ? (int)$data['id'] : ($id !== null ? (int)$id : null);
        try {
            Response::json(['ok' => true, 'data' => ['id' => $this->shipping()->saveRate($data, $rateId)]]);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Response::error('No se pudo guardar la tarifa: ' . $e->getMessage(), 500);
        }
    }

    public function shippingRateDelete(Request $req, string $id): void
    {
        $this->shipping()->deleteRate((int)$id);
        Response::json(['ok' => true]);
    }

    private function coordinadora(): \App\Services\CoordinadoraService
    {
        return new \App\Services\CoordinadoraService();
    }

    public function shippingCoordinadora(Request $req): void
    {
        $svc = $this->coordinadora();
        Response::json(['ok' => true, 'data' => [
            'settings' => $svc->publicSettings(),
            'stats' => $svc->stats(),
            'latest_run' => $svc->latestRun(),
        ]]);
    }

    public function shippingCoordinadoraSave(Request $req): void
    {
        $in = $req->json();
        if (!is_array($in)) $in = $_POST;
        $r = $this->coordinadora()->saveSettings($in);
        Response::json($r, !empty($r['ok']) ? 200 : 400);
    }

    public function shippingCoordinadoraTest(Request $req): void
    {
        $r = $this->coordinadora()->testConnection();
        Response::json($r, !empty($r['ok']) ? 200 : 400);
    }

    public function shippingCoordinadoraQuote(Request $req): void
    {
        $in = $req->json();
        if (!is_array($in)) $in = $_POST;
        $r = $this->coordinadora()->quote([
            'destination_code' => (string)($in['destination_code'] ?? $in['municipality_code'] ?? ''),
            'municipality_code' => (string)($in['municipality_code'] ?? ''),
            'weight_grams' => (int)($in['weight_grams'] ?? 1000),
            'declared_value_cents' => (int)($in['declared_value_cents'] ?? 5000000),
            'qty' => (int)($in['qty'] ?? 1),
        ]);
        Response::json($r, !empty($r['ok']) ? 200 : 400);
    }

    public function shippingCoordinadoraSync(Request $req): void
    {
        $in = $req->json();
        if (!is_array($in)) $in = $_POST;
        $mode = (string)($in['mode'] ?? 'missing');
        $limit = (int)($in['limit'] ?? 0);
        $brackets = (int)($in['brackets'] ?? 0);
        if (!empty($in['run_now']) && $limit > 0) {
            $r = $this->coordinadora()->syncRates($mode, $limit, $brackets);
        } else {
            $r = $this->coordinadora()->requestSync('sync');
        }
        Response::json($r, !empty($r['ok']) ? 200 : 400);
    }

    public function shippingCoordinadoraDiagnose(Request $req): void
    {
        $in = $req->json();
        if (!is_array($in)) $in = $_POST;
        $limit = (int)($in['limit'] ?? 80);
        if (!empty($in['run_now'])) {
            $r = $this->coordinadora()->diagnose($limit);
        } else {
            $r = $this->coordinadora()->requestSync('diagnose');
        }
        Response::json($r, !empty($r['ok']) ? 200 : 400);
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
        $sp = new \App\Services\SocialProofService();
        $s['store_social_proof'] = $sp->settingsJson();
        $s['social_proof_stats'] = $sp->stats();
        $s['store_impact_note'] = (new \App\Services\ImpactNoteService())->settingsJson();
        $s['checkout_payment_methods'] = (new CheckoutPaymentMethodService())->adminConfig();
        Response::json(['ok' => true, 'data' => $s, 'methods' => $svc->paymentMethods()]);
    }

    public function checkoutPaymentMethods(): void
    {
        Response::json(['ok' => true, 'data' => (new CheckoutPaymentMethodService())->adminConfig()]);
    }

    public function checkoutPaymentMethodsSave(Request $req): void
    {
        $result = (new CheckoutPaymentMethodService())->saveConfig($req->json());
        Response::json($result, !empty($result['ok']) ? 200 : 422);
    }

    public function paymentLogoMethods(): void
    {
        $svc = $this->svc();
        $methods = [];
        $add = function (string $id, string $label, string $source, bool $available = true, bool $disabled = false) use (&$methods): void {
            $id = preg_replace('/[^a-z0-9_-]/i', '', $id);
            $label = trim($label);
            if ($id === '' || $label === '') return;
            if (!isset($methods[$id])) $methods[$id] = ['id' => $id, 'label' => $label, 'source' => $source, 'available' => $available, 'disabled' => $disabled];
            else $methods[$id]['available'] = $methods[$id]['available'] || $available;
        };
        $nativeLabels = ['wompi' => 'Wompi', 'cod' => 'Contra entrega', 'manual' => 'Pago asistido'];
        $enabledNative = $svc->paymentMethods();
        foreach ($nativeLabels as $id => $label) {
            // Solo se listan los métodos nativos que están activos en la tienda.
            if (in_array($id, $enabledNative, true)) $add($id, $label, 'tienda', true);
        }
        try {
            $breb = (new \App\Services\BrebService())->publicConfig();
            foreach ((array)($breb['methods'] ?? []) as $m) {
                $add((string)($m['id'] ?? ''), (string)($m['label'] ?? ''), 'breb', true, false);
            }
        } catch (\Throwable $e) {
        }
        try {
            $addi = (new \App\Services\AddiService())->publicMethod();
            if ($addi) $add((string)($addi['id'] ?? 'addi'), (string)($addi['label'] ?? 'Addi'), 'addi', true, false);
        } catch (\Throwable $e) {
        }
        try {
            $mp = new \App\Services\MercadoPagoService();
            $method = $mp->publicMethod();
            if ($method) $add((string)($method['id'] ?? 'mercadopago'), (string)($method['label'] ?? 'Mercado Pago'), 'mercadopago', true, false);
        } catch (\Throwable $e) {
        }
        try {
            $sc = (new \App\Services\SistecreditoService())->publicMethod();
            $add((string)($sc['id'] ?? 'sistecredito'), (string)($sc['label'] ?? 'Sistecrédito'), 'sistecredito', false, true);
        } catch (\Throwable $e) {
        }
        $custom = [];
        try {
            $raw = json_decode((string)($svc->settings()['store_payment_logo_methods'] ?? '[]'), true);
            foreach ((array)$raw as $m) {
                if (!is_array($m)) continue;
                $id = preg_replace('/[^a-z0-9_-]/i', '', (string)($m['id'] ?? ''));
                $label = trim((string)($m['label'] ?? ''));
                if ($id === '' || $label === '' || isset($methods[$id])) continue;
                $custom[] = ['id' => $id, 'label' => mb_substr($label, 0, 80)];
                $add($id, $label, 'manual', true, false);
            }
        } catch (\Throwable $e) {
        }
        Response::json(['ok' => true, 'data' => ['methods' => array_values($methods), 'custom' => $custom]]);
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
        if (array_key_exists('store_payment_logo_methods', $d)) {
            $raw = is_array($d['store_payment_logo_methods']) ? $d['store_payment_logo_methods'] : json_decode((string)$d['store_payment_logo_methods'], true);
            $custom = [];
            foreach ((array)$raw as $m) {
                if (!is_array($m)) continue;
                $id = preg_replace('/[^a-z0-9_-]/i', '', (string)($m['id'] ?? ''));
                $label = mb_substr(trim((string)($m['label'] ?? '')), 0, 80);
                if ($id === '' || $label === '') continue;
                $custom[$id] = ['id' => $id, 'label' => $label];
                if (count($custom) >= 100) break;
            }
            $d['store_payment_logo_methods'] = json_encode(array_values($custom), JSON_UNESCAPED_UNICODE);
        }
        if (array_key_exists('store_social_proof', $d)) {
            $d['store_social_proof'] = (new \App\Services\SocialProofService())->normalizeJson($d['store_social_proof']);
        }
        if (array_key_exists('store_impact_note', $d)) {
            $d['store_impact_note'] = (new \App\Services\ImpactNoteService())->normalizeJson($d['store_impact_note']);
        }
        $this->svc()->saveSettings($d);
        Response::json(['ok' => true, 'data' => $this->svc()->paymentMethods()]);
    }

    // ── Reseñas ──

    public function reviews(Request $req): void
    {
        $svc = new ReviewService();
        Response::json(['ok' => true, 'data' => [
            'items' => $svc->listAll((string)$req->get('status', ''), (string)$req->get('ref', ''), 300),
            'pending' => $svc->pendingCount(),
        ]]);
    }

    public function reviewModerate(Request $req, string $id): void
    {
        $r = (new ReviewService())->moderate((int)$id, (string)$req->input('status', ''));
        if (empty($r['ok'])) Response::error($r['message'] ?? 'Error', 422, $r);
        Response::json($r);
    }

    public function reviewDelete(Request $req, string $id): void
    {
        Response::json((new ReviewService())->delete((int)$id));
    }

    // ── Devoluciones ──

    public function returns(Request $req): void
    {
        $svc = new ReturnService();
        Response::json(['ok' => true, 'data' => [
            'items' => $svc->listAll((string)$req->get('status', '')),
            'requested' => $svc->requestedCount(),
        ]]);
    }

    public function returnModerate(Request $req, string $id): void
    {
        $r = (new ReturnService())->setStatus((int)$id, (string)$req->input('status', ''), (bool)$req->input('restock', false), (string)$req->input('note', ''));
        if (empty($r['ok'])) Response::error($r['message'] ?? 'Error', 422, $r);
        Response::json($r);
    }

    // ── Cupones ──

    public function coupons(Request $req): void
    {
        Response::json(['ok' => true, 'data' => (new \App\Services\StoreCouponService())->list()]);
    }

    public function couponSave(Request $req, ?string $id = null): void
    {
        $r = (new \App\Services\StoreCouponService())->save($req->json(), $id ? (int)$id : null);
        if (empty($r['ok'])) Response::error($r['message'] ?? 'Error', 422, $r);
        Response::json($r);
    }

    public function couponDelete(Request $req, string $id): void
    {
        Response::json((new \App\Services\StoreCouponService())->delete((int)$id));
    }
}
