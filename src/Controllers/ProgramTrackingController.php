<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\ProgramAnalyticsService;

class ProgramTrackingController
{
    private const ALLOWED = ['view', 'time', 'scroll', 'section_view', 'clicks', 'apply_click', 'exit'];

    public function track(Request $request): void
    {
        $data = $request->json();
        $session = trim((string)($data['session'] ?? ''));
        if ($session === '') {
            Response::error('Missing session', 422);
        }

        $events = [];
        if (isset($data['events']) && is_array($data['events'])) {
            $events = array_slice($data['events'], 0, 10);
        } else {
            $events = [[
                'event' => $data['event'] ?? '',
                'page' => $data['page'] ?? '',
                'section' => $data['section'] ?? '',
                'seconds' => $data['seconds'] ?? 0,
                'clicks' => $data['clicks'] ?? 0,
                'scroll_pct' => $data['scroll_pct'] ?? 0,
                'meta' => $data['meta'] ?? '',
            ]];
        }

        $page = mb_substr((string)($data['page'] ?? ($_SERVER['REQUEST_URI'] ?? '/')), 0, 255);
        $stored = 0;
        foreach ($events as $ev) {
            if (!is_array($ev)) continue;
            $name = (string)($ev['event'] ?? '');
            if (!in_array($name, self::ALLOWED, true)) continue;
            $ok = ProgramAnalyticsService::track($name, $session, (string)($ev['page'] ?? $page), [
                'section' => $ev['section'] ?? '',
                'seconds' => $ev['seconds'] ?? 0,
                'clicks' => $ev['clicks'] ?? 0,
                'scroll_pct' => $ev['scroll_pct'] ?? 0,
                'meta' => $ev['meta'] ?? '',
            ]);
            if ($ok) $stored++;
        }

        Response::json(['ok' => true, 'stored' => $stored]);
    }

    public function analytics(Request $request): void
    {
        $from = (string)$request->get('from', date('Y-m-d', strtotime('-30 days')));
        $to = (string)$request->get('to', date('Y-m-d'));
        Response::json(['ok' => true, 'data' => ProgramAnalyticsService::stats($from, $to)]);
    }

    public function export(Request $request): void
    {
        $from = (string)$request->get('from', date('Y-m-d', strtotime('-30 days')));
        $to = (string)$request->get('to', date('Y-m-d'));
        $stats = ProgramAnalyticsService::stats($from, $to);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="program-analytics-' . $from . '-to-' . $to . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['event', 'page', 'section', 'seconds', 'clicks', 'scroll_pct', 'meta', 'device', 'os', 'browser', 'country', 'created_at']);
        foreach ($stats['recent'] as $r) {
            fputcsv($out, [$r['event'], $r['page'], $r['section'], $r['seconds'], $r['clicks'], $r['scroll_pct'], $r['meta'], $r['device'], $r['os'], $r['browser'], $r['country'], $r['created_at']]);
        }
        fclose($out);
        exit;
    }
}
