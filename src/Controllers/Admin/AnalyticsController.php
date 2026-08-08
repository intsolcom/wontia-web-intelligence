<?php
namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

class AnalyticsController
{
    public function index(Request $req): void
    {
        $db = Database::instance();
        $from = $req->get('from', date('Y-m-d', strtotime('-30 days')));
        $to = $req->get('to', date('Y-m-d'));

        $total = $db->prepare("SELECT COUNT(*) FROM analytics_views WHERE site_id = 1 AND created_at BETWEEN :from AND :to")->execute(['from' => $from . ' 00:00:00', 'to' => $to . ' 23:59:59'])->fetchColumn();
        $unique = $db->prepare("SELECT COUNT(DISTINCT ip_hash) FROM analytics_views WHERE site_id = 1 AND created_at BETWEEN :from AND :to")->execute(['from' => $from . ' 00:00:00', 'to' => $to . ' 23:59:59'])->fetchColumn();
        $topPages = $db->prepare("SELECT page_url, COUNT(*) as views FROM analytics_views WHERE site_id = 1 AND created_at BETWEEN :from AND :to GROUP BY page_url ORDER BY views DESC LIMIT 10")->execute(['from' => $from . ' 00:00:00', 'to' => $to . ' 23:59:59'])->fetchAll();
        $referrers = $db->prepare("SELECT referrer, COUNT(*) as c FROM analytics_views WHERE site_id = 1 AND referrer IS NOT NULL AND referrer != '' AND created_at BETWEEN :from AND :to GROUP BY referrer ORDER BY c DESC LIMIT 10")->execute(['from' => $from . ' 00:00:00', 'to' => $to . ' 23:59:59'])->fetchAll();
        $devices = $db->prepare("SELECT device, COUNT(*) as c FROM analytics_views WHERE site_id = 1 AND created_at BETWEEN :from AND :to GROUP BY device")->execute(['from' => $from . ' 00:00:00', 'to' => $to . ' 23:59:59'])->fetchAll();
        $daily = $db->prepare("SELECT DATE(created_at) as d, COUNT(*) as c FROM analytics_views WHERE site_id = 1 AND created_at BETWEEN :from AND :to GROUP BY DATE(created_at) ORDER BY d ASC")->execute(['from' => $from . ' 00:00:00', 'to' => $to . ' 23:59:59'])->fetchAll();

        $ga4 = $db->prepare("SELECT value FROM settings WHERE site_id = 1 AND `key` = 'ga_measurement_id'")->execute()->fetchColumn();

        Response::json(['ok' => true, 'data' => [
            'totals' => ['views' => (int)$total, 'unique_visitors' => (int)$unique],
            'top_pages' => $topPages,
            'referrers' => $referrers,
            'devices' => $devices,
            'daily' => $daily,
            'ga4_id' => $ga4 ?: '',
            'from' => $from,
            'to' => $to,
        ]]);
    }

    public function updateGa4(Request $req): void
    {
        $ga4 = $req->input('ga_measurement_id', '');
        $db = Database::instance();
        $db->prepare("INSERT INTO settings (site_id, `key`, `value`) VALUES (1, 'ga_measurement_id', :v) ON DUPLICATE KEY UPDATE `value` = :v2")->execute(['v' => $ga4, 'v2' => $ga4]);
        Response::json(['ok' => true]);
    }
}
