<?php
namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Bricks\SeoGlobalLaunch\SeoGlobalLaunchBrick;

/**
 * SEO Global Launch — endpoints del brick en el admin WWI.
 * Protegidos por AuthMiddleware (grupo /api/v1/admin).
 */
class SeoGlobalLaunchController
{
    public function overview(): void
    {
        Response::json(SeoGlobalLaunchBrick::overview());
    }

    public function activate(): void
    {
        $result = SeoGlobalLaunchBrick::activate();
        Response::json(['ok' => $result['ok'], 'message' => $result['message'] ?? '', 'data' => $result]);
    }

    public function deactivate(): void
    {
        $result = SeoGlobalLaunchBrick::deactivate();
        Response::json(['ok' => $result['ok'], 'message' => $result['message'] ?? '', 'data' => $result]);
    }

    public function installTables(): void
    {
        Response::json(SeoGlobalLaunchBrick::installTables());
    }

    public function scan(): void
    {
        Response::json(SeoGlobalLaunchBrick::scan());
    }

    public function generate(): void
    {
        Response::json(SeoGlobalLaunchBrick::generate());
    }

    public function autofix(): void
    {
        Response::json(SeoGlobalLaunchBrick::autofix());
    }

    public function deepfix(): void
    {
        Response::json(SeoGlobalLaunchBrick::deepfix());
    }

    public function pages(): void
    {
        Response::json(SeoGlobalLaunchBrick::pages());
    }

    public function issues(): void
    {
        Response::json(SeoGlobalLaunchBrick::issues());
    }

    public function scores(): void
    {
        Response::json(SeoGlobalLaunchBrick::scores());
    }

    public function bots(): void
    {
        Response::json(SeoGlobalLaunchBrick::bots());
    }

    public function keywords(Request $req): void
    {
        $service = new \App\Services\SeoKeywordService();
        $list = $service->listRecords(
            trim((string)$req->get('q', '')),
            trim((string)$req->get('status', '')),
            trim((string)$req->get('type', '')),
            (int)$req->get('page', 1),
            (int)$req->get('per_page', 50)
        );
        Response::json(['ok' => true, 'data' => array_merge($service->overview(), $list)]);
    }

    public function saveKeyword(Request $req): void
    {
        $user = \App\Core\Session::user();
        $result = (new \App\Services\SeoKeywordService())->saveManual($req->json(), (string)($user['username'] ?? 'admin'));
        Response::json($result, !empty($result['ok']) ? 200 : 422);
    }

    public function generateKeywords(Request $req): void
    {
        $data = $req->json();
        $service = new \App\Services\SeoKeywordService();
        if (!empty($data['preview'])) {
            Response::json(['ok' => true, 'data' => $service->generatePreview((int)($data['limit'] ?? 20))]);
            return;
        }
        $user = \App\Core\Session::user();
        Response::json($service->generateBatch((int)($data['after_id'] ?? 0), (int)($data['limit'] ?? 50), (string)($user['username'] ?? 'admin')));
    }

    public function approveKeywords(Request $req): void
    {
        $ids = (array)($req->json()['ids'] ?? []);
        $user = \App\Core\Session::user();
        $count = (new \App\Services\SeoKeywordService())->approve($ids, (string)($user['username'] ?? 'admin'));
        Response::json(['ok' => true, 'updated' => $count]);
    }

    public function rejectKeywords(Request $req): void
    {
        $ids = (array)($req->json()['ids'] ?? []);
        $user = \App\Core\Session::user();
        $count = (new \App\Services\SeoKeywordService())->reject($ids, (string)($user['username'] ?? 'admin'));
        Response::json(['ok' => true, 'updated' => $count]);
    }

    public function config(): void
    {
        Response::json(['ok' => true, 'data' => SeoGlobalLaunchBrick::config()]);
    }

    public function saveConfig(Request $req): void
    {
        Response::json(SeoGlobalLaunchBrick::saveConfig($req->json()));
    }

    public function technical(): void
    {
        Response::json(['ok' => true, 'data' => SeoGlobalLaunchBrick::technical()]);
    }

    public function saveTechnical(Request $req): void
    {
        $result = SeoGlobalLaunchBrick::savePageTechnical($req->json());
        Response::json($result, !empty($result['ok']) ? 200 : 422);
    }

    public function clearCache(): void
    {
        Response::json(SeoGlobalLaunchBrick::clearCache());
    }

    public function redirects(): void
    {
        Response::json(SeoGlobalLaunchBrick::redirects());
    }

    public function saveRedirect(Request $req): void
    {
        $result = SeoGlobalLaunchBrick::saveRedirect($req->json());
        Response::json($result, !empty($result['ok']) ? 200 : 422);
    }

    public function deleteRedirect(Request $req, string $id): void
    {
        $result = SeoGlobalLaunchBrick::deleteRedirect((int)$id);
        Response::json($result, !empty($result['ok']) ? 200 : 422);
    }

    public function importRedirects(Request $req): void
    {
        Response::json(SeoGlobalLaunchBrick::importRedirects((string)$req->input('csv', '')));
    }

    public function notFound(): void
    {
        Response::json(SeoGlobalLaunchBrick::notFound());
    }

    public function delete404(Request $req, string $id): void
    {
        $result = SeoGlobalLaunchBrick::delete404((int)$id);
        Response::json($result, !empty($result['ok']) ? 200 : 422);
    }

    public function brokenLinks(): void
    {
        Response::json(SeoGlobalLaunchBrick::brokenLinks());
    }

    public function scanBrokenLinks(): void
    {
        Response::json(SeoGlobalLaunchBrick::scanBrokenLinks());
    }

    public function analytics(Request $req): void
    {
        Response::json((new \App\Services\SeoAnalyticsService())->overview((int)$req->get('days', 30)));
    }

    public function opportunities(Request $req): void
    {
        Response::json((new \App\Services\SeoAnalyticsService())->opportunities((int)$req->get('days', 30)));
    }

    public function daily(Request $req): void
    {
        Response::json(['ok' => true, 'data' => (new \App\Services\SeoDailyMetricsService())->series((int)$req->get('days', 30))]);
    }

    public function vitals(Request $req): void
    {
        Response::json(['ok' => true, 'data' => (new \App\Services\SeoVitalsService())->summary((int)$req->get('days', 30))]);
    }

    public function live(Request $req): void
    {
        $data = (new \App\Services\SeoLiveMonitorService())->overview(
            (int)$req->get('after_id', 0),
            (int)$req->get('limit', 100),
            (string)$req->get('audience', 'client')
        );
        Response::json(['ok' => true, 'data' => $data]);
    }

    // ─────────────────────────────────────────────
    // Tráfico propio ("yo") y pauta Meta
    // ─────────────────────────────────────────────

    public function internal(Request $req): void
    {
        \App\Services\SeoTrafficSchemaService::ensure();
        $data = (new \App\Services\SeoInternalTrafficService())->summary((int)$req->get('days', 7));
        Response::json(['ok' => true, 'data' => $data]);
    }

    public function markDevice(Request $req): void
    {
        \App\Services\SeoTrafficSchemaService::ensure();
        $user = \App\Core\Session::user();
        $label = trim((string)$req->input('label', ''));
        if ($label === '') $label = 'Este equipo · ' . gmdate('Y-m-d H:i');
        $result = \App\Services\SeoInternalTrafficService::markDevice($label, (string)($user['username'] ?? 'admin'));
        Response::json($result, !empty($result['ok']) ? 200 : 422);
    }

    public function unmarkDevice(): void
    {
        $result = \App\Services\SeoInternalTrafficService::unmarkDevice();
        Response::json($result, !empty($result['ok']) ? 200 : 422);
    }

    public function addNetwork(Request $req): void
    {
        \App\Services\SeoTrafficSchemaService::ensure();
        $cidr = trim((string)$req->input('cidr', ''));
        if (!empty($req->input('current', false)) || $cidr === '') $cidr = (string)\App\Services\SeoInternalTrafficService::clientIp();
        $label = trim((string)$req->input('label', ''));
        $user = \App\Core\Session::user();
        $result = \App\Services\SeoInternalTrafficService::addNetwork($cidr, $label, (string)($user['username'] ?? 'admin'));
        Response::json($result, !empty($result['ok']) ? 200 : 422);
    }

    public function deleteNetwork(Request $req, string $id): void
    {
        $result = \App\Services\SeoInternalTrafficService::removeNetwork((int)$id);
        Response::json($result, !empty($result['ok']) ? 200 : 422);
    }

    public function revokeDevice(Request $req, string $id): void
    {
        $result = \App\Services\SeoInternalTrafficService::revokeDevice((int)$id);
        Response::json($result, !empty($result['ok']) ? 200 : 422);
    }

    public function backfill(Request $req): void
    {
        \App\Services\SeoTrafficSchemaService::ensure();
        $dryRun = !empty($req->input('dry_run', false));
        $result = (new \App\Services\SeoInternalTrafficService())->backfill($dryRun);
        Response::json($result, !empty($result['ok']) ? 200 : 422);
    }

    public function meta(Request $req): void
    {
        \App\Services\SeoTrafficSchemaService::ensure();
        Response::json((new \App\Services\SeoMetaAdsService())->report((int)$req->get('days', 30)));
    }

    public function metaBackfill(Request $req): void
    {
        \App\Services\SeoTrafficSchemaService::ensure();
        $result = (new \App\Services\SeoMetaAdsService())->backfillAttribution((int)$req->input('limit', 2000));
        Response::json($result, !empty($result['ok']) ? 200 : 422);
    }

    public function integrations(): void
    {
        Response::json(['ok' => true, 'data' => (new \App\Services\SeoIntegrationService())->publicConfig()]);
    }

    public function saveIntegrations(Request $req): void
    {
        $result = (new \App\Services\SeoIntegrationService())->save($req->json());
        Response::json($result, !empty($result['ok']) ? 200 : 422);
    }

    public function integrationRun(Request $req): void
    {
        $service = new \App\Services\SeoIntegrationService();
        $provider = (string)$req->input('provider', '');
        switch ($provider) {
            case 'indexnow':
                $result = $service->indexNow();
                break;
            case 'pagespeed':
                $result = $service->pageSpeed((string)$req->input('strategy', 'mobile'));
                break;
            case 'gsc_test':
                $result = $service->gscTest();
                break;
            case 'gsc_sitemap':
                $result = $service->gscSubmitSitemap();
                break;
            case 'bing_test':
                $result = $service->bingTest();
                break;
            case 'bing_sitemap':
                $result = $service->bingSubmitSitemap();
                break;
            default:
                $result = ['ok' => false, 'status' => 'error', 'message' => 'Integración no reconocida.'];
        }
        Response::json($result, !empty($result['ok']) || (($result['status'] ?? '') === 'skipped') ? 200 : 422);
    }

    public function indexNowKey(): void
    {
        $key = (new \App\Services\SeoIntegrationService())->indexNowKey();
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
        echo $key;
        exit;
    }

    public function security(Request $req): void
    {
        Response::json((new \App\Services\SeoIpSecurityService())->threats((int)$req->get('window', 3600)));
    }

    public function ipScan(Request $req): void
    {
        $result = (new \App\Services\SeoIpSecurityService())->scan((string)$req->get('ip', ''));
        Response::json($result, !empty($result['ok']) ? 200 : 422);
    }

    public function blockIp(Request $req): void
    {
        $user = (string)($_SESSION['user']['username'] ?? 'admin');
        $result = (new \App\Services\SeoIpSecurityService())->block((string)$req->input('ip', ''), (string)$req->input('reason', ''), $user, (int)$req->input('hours', 0));
        Response::json($result, !empty($result['ok']) ? 200 : 422);
    }

    public function unblockIp(Request $req): void
    {
        $result = (new \App\Services\SeoIpSecurityService())->unblock((string)$req->input('ip', ''));
        Response::json($result, !empty($result['ok']) ? 200 : 422);
    }

    public function blockBulk(Request $req): void
    {
        $ips = $req->input('ips', []);
        if (!is_array($ips)) $ips = [];
        $ips = array_slice(array_values(array_unique(array_filter(array_map('strval', $ips)))), 0, 50);
        $service = new \App\Services\SeoIpSecurityService();
        $user = (string)($_SESSION['user']['username'] ?? 'admin');
        $blocked = 0;
        foreach ($ips as $ip) {
            $r = $service->block($ip, (string)$req->input('reason', 'Campaña distribuida detectada'), $user, (int)$req->input('hours', 0));
            if (!empty($r['ok'])) $blocked++;
        }
        Response::json(['ok' => $blocked > 0, 'blocked' => $blocked, 'total' => count($ips), 'message' => 'IPs bloqueadas: ' . $blocked]);
    }
}
