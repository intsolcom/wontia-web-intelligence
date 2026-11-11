<?php
namespace App\Services;

use App\Core\Database;

/**
 * Atribución UTM de primera y última interacción.
 * La cookie la escribe tracking-conversion.js; aquí solo se lee y se asocia al pedido.
 */
class TrackingAttributionService
{
    public const COOKIE = 'wwi_attr_v1';

    public static function fromCookie(): array
    {
        $raw = (string)($_COOKIE[self::COOKIE] ?? '');
        if ($raw === '') return ['first' => [], 'last' => []];

        $decoded = json_decode(rawurldecode($raw), true);
        if (!is_array($decoded)) return ['first' => [], 'last' => []];
        return [
            'first' => self::cleanTouch($decoded['first'] ?? []),
            'last' => self::cleanTouch($decoded['last'] ?? []),
        ];
    }

    public static function cleanTouch($touch): array
    {
        if (!is_array($touch)) return [];
        $out = [];
        foreach (['source', 'medium', 'campaign', 'content', 'term', 'fbclid', 'gclid', 'landing', 'referrer'] as $key) {
            $value = isset($touch[$key]) && is_scalar($touch[$key]) ? trim((string)$touch[$key]) : '';
            if ($value === '') continue;
            $max = in_array($key, ['fbclid', 'gclid'], true) ? 255 : 500;
            $out[$key] = mb_substr($value, 0, $max);
        }
        if (isset($touch['ts'])) $out['ts'] = max(0, (int)$touch['ts']);
        return $out;
    }

    public static function saveForOrder(string $orderUuid, string $orderNumber, array $attribution, array $consent): void
    {
        $first = self::cleanTouch($attribution['first'] ?? []);
        $last = self::cleanTouch($attribution['last'] ?? []);
        if (!$first && !$last) return;

        $firstAt = !empty($first['ts']) ? date('Y-m-d H:i:s', (int)$first['ts']) : date('Y-m-d H:i:s');
        $lastAt = !empty($last['ts']) ? date('Y-m-d H:i:s', (int)$last['ts']) : date('Y-m-d H:i:s');

        $db = Database::instance();
        $db->prepare(
            'INSERT INTO tracking_order_attribution (
                site_id, order_uuid, order_number,
                first_source, first_medium, first_campaign, first_content, first_term, first_fbclid, first_gclid, first_landing_url, first_seen_at,
                last_source, last_medium, last_campaign, last_content, last_term, last_fbclid, last_gclid, last_landing_url, last_seen_at,
                consent_analytics, consent_marketing
            ) VALUES (
                @site_id, :uuid, :num,
                :f_source, :f_medium, :f_campaign, :f_content, :f_term, :f_fbclid, :f_gclid, :f_landing, :f_seen,
                :l_source, :l_medium, :l_campaign, :l_content, :l_term, :l_fbclid, :l_gclid, :l_landing, :l_seen,
                :c_analytics, :c_marketing
            ) ON DUPLICATE KEY UPDATE
                order_number = VALUES(order_number),
                first_source = VALUES(first_source), first_medium = VALUES(first_medium), first_campaign = VALUES(first_campaign),
                first_content = VALUES(first_content), first_term = VALUES(first_term), first_fbclid = VALUES(first_fbclid),
                first_gclid = VALUES(first_gclid), first_landing_url = VALUES(first_landing_url), first_seen_at = VALUES(first_seen_at),
                last_source = VALUES(last_source), last_medium = VALUES(last_medium), last_campaign = VALUES(last_campaign),
                last_content = VALUES(last_content), last_term = VALUES(last_term), last_fbclid = VALUES(last_fbclid),
                last_gclid = VALUES(last_gclid), last_landing_url = VALUES(last_landing_url), last_seen_at = VALUES(last_seen_at),
                consent_analytics = VALUES(consent_analytics), consent_marketing = VALUES(consent_marketing)'
        )->execute([
            'uuid' => $orderUuid,
            'num' => $orderNumber !== '' ? $orderNumber : null,
            'f_source' => $first['source'] ?? null,
            'f_medium' => $first['medium'] ?? null,
            'f_campaign' => $first['campaign'] ?? null,
            'f_content' => $first['content'] ?? null,
            'f_term' => $first['term'] ?? null,
            'f_fbclid' => $first['fbclid'] ?? null,
            'f_gclid' => $first['gclid'] ?? null,
            'f_landing' => $first['landing'] ?? null,
            'f_seen' => $firstAt,
            'l_source' => $last['source'] ?? null,
            'l_medium' => $last['medium'] ?? null,
            'l_campaign' => $last['campaign'] ?? null,
            'l_content' => $last['content'] ?? null,
            'l_term' => $last['term'] ?? null,
            'l_fbclid' => $last['fbclid'] ?? null,
            'l_gclid' => $last['gclid'] ?? null,
            'l_landing' => $last['landing'] ?? null,
            'l_seen' => $lastAt,
            'c_analytics' => !empty($consent['analytics']) ? 1 : 0,
            'c_marketing' => !empty($consent['marketing']) ? 1 : 0,
        ]);
    }

    public function recent(int $limit = 25): array
    {
        $limit = max(1, min(100, $limit));
        try {
            $rows = Database::instance()->query(
                "SELECT * FROM tracking_order_attribution WHERE site_id = @site_id ORDER BY id DESC LIMIT $limit"
            )->fetchAll();
            return is_array($rows) ? $rows : [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
