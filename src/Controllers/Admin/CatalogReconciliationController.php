<?php
namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Response;
use App\Core\Session;
use App\Services\CatalogReconciliationService;

/**
 * CatalogReconciliationController — "Conciliación de catálogo".
 * Informe de coherencia del catálogo (robot). Acceso: superadmin | admin | editor.
 */
class CatalogReconciliationController
{
    private function guard(): CatalogReconciliationService
    {
        if (!in_array((string)Session::userRole(), ['superadmin', 'admin', 'editor'], true)) {
            Response::error('Sin permiso para la conciliación de catálogo.', 403);
        }
        return new CatalogReconciliationService();
    }

    private function inputs(): array
    {
        $theme = 'default';
        try {
            $theme = (string)(Database::instance()->query("SELECT theme FROM sites WHERE id = @site_id")->fetchColumn() ?: 'default');
        } catch (\Throwable $e) {
        }
        $theme = preg_replace('/[^a-z0-9_-]/i', '', $theme) ?: 'default';
        $lib = (defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 4)) . '/templates/themes/' . $theme . '/lib.php';
        $cats = [];
        $products = [];
        if (is_file($lib)) {
            require_once $lib;
            if (function_exists('sonika_categories')) $cats = sonika_categories();
            if (function_exists('sonika_products')) $products = sonika_products();
        }
        return [$cats, $products];
    }

    public function report(): void
    {
        $svc = $this->guard();
        $report = $svc->load();
        if (!$report) {
            [$cats, $products] = $this->inputs();
            $report = $svc->analyze($cats, $products);
            $svc->save($report);
        }
        Response::json(['ok' => true, 'data' => $report]);
    }

    public function scan(): void
    {
        $svc = $this->guard();
        [$cats, $products] = $this->inputs();
        $report = $svc->analyze($cats, $products);
        $svc->save($report);
        Response::json(['ok' => true, 'data' => $report]);
    }
}
