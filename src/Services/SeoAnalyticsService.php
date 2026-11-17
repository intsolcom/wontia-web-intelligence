<?php
namespace App\Services;

use App\Core\Database;

class SeoAnalyticsService
{
    public function overview(int $days = 30): array
    {
        $days = max(1, min(365, $days));
        $db = Database::instance();
        $since = 'created_at >= (NOW() - INTERVAL ' . $days . ' DAY)';
        $scalar = function (string $sql) use ($db) {
            try { return $db->query($sql)->fetchColumn(); } catch (\Throwable $e) { return 0; }
        };
        $rows = function (string $sql) use ($db) {
            try { return $db->query($sql)->fetchAll(); } catch (\Throwable $e) { return []; }
        };

        $client = 'is_internal = 0 AND is_bot = 0';
        $clientViews = $client . " AND (user_agent IS NULL OR user_agent NOT REGEXP 'bot|crawl|spider|slurp|bing|yandex|ahrefs|semrush|facebookexternalhit|preview|headless')";
        // Fuente canónica de tráfico: monitor de peticiones del servidor.
        $serverPageViews = (int)$scalar("SELECT COUNT(*) FROM seo_live_requests WHERE site_id = @site_id AND $client AND is_page_view = 1 AND $since");
        $serverSessions = (int)$scalar("SELECT COUNT(DISTINCT session_id) FROM seo_live_requests WHERE site_id = @site_id AND $client AND is_page_view = 1 AND $since");
        $uniqueLive = (int)$scalar("SELECT COUNT(DISTINCT visitor_hash) FROM seo_live_requests WHERE site_id = @site_id AND $client AND is_page_view = 1 AND $since");
        $analyticsViews = (int)$scalar("SELECT COUNT(*) FROM analytics_views WHERE site_id = @site_id AND $clientViews AND $since");
        $analyticsSessions = (int)$scalar("SELECT COUNT(DISTINCT session_id) FROM analytics_views WHERE site_id = @site_id AND $clientViews AND $since");
        $unique = $uniqueLive > 0 ? $uniqueLive : (int)$scalar("SELECT COUNT(DISTINCT COALESCE(NULLIF(visitor_hash, ''), ip_hash)) FROM analytics_views WHERE site_id = @site_id AND $clientViews AND $since");
        $views = $serverPageViews > 0 ? $serverPageViews : $analyticsViews;
        // Eventos de comportamiento: solo con consentimiento. No se usan para
        // calcular el total de visitas ni de sesiones.
        $consentPageViews = (int)$scalar("SELECT COUNT(*) FROM seo_tracking_events WHERE site_id = @site_id AND $client AND type = 'page_view' AND $since");
        $consentSessions = (int)$scalar("SELECT COUNT(DISTINCT session_id) FROM seo_tracking_events WHERE site_id = @site_id AND $client AND $since");
        $sessions = $consentSessions > 0 ? $consentSessions : ($serverSessions > 0 ? $serverSessions : $unique);
        $pageViews = $consentPageViews;
        $avgTime = (int)$scalar("SELECT AVG(duration_ms) FROM seo_tracking_events WHERE site_id = @site_id AND $client AND type = 'page_leave' AND duration_ms > 0 AND $since");
        $avgScroll = (int)$scalar("SELECT AVG(scroll_pct) FROM seo_tracking_events WHERE site_id = @site_id AND $client AND scroll_pct IS NOT NULL AND $since");
        $internalSessions = (int)$scalar("SELECT COUNT(DISTINCT session_id) FROM seo_tracking_events WHERE site_id = @site_id AND is_internal = 1 AND is_bot = 0 AND $since");
        $internalViews = (int)$scalar("SELECT COUNT(*) FROM seo_tracking_events WHERE site_id = @site_id AND is_internal = 1 AND is_bot = 0 AND type = 'page_view' AND $since");
        $internalRequests = (int)$scalar("SELECT COUNT(*) FROM seo_live_requests WHERE site_id = @site_id AND is_internal = 1 AND is_page_view = 1 AND $since");

        $orders = $rows("SELECT
            COUNT(*) total,
            SUM(payment_status = 'paid') paid,
            SUM(payment_status = 'pending') pending,
            SUM(payment_status = 'failed') failed,
            SUM(payment_status = 'refunded') refunded,
            SUM(payment_status = 'pending' AND created_at <= (NOW() - INTERVAL 1 HOUR)) abandoned,
            COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total_cents ELSE 0 END), 0) revenue_cents,
            COALESCE(AVG(CASE WHEN payment_status = 'paid' THEN total_cents END), 0) avg_ticket_cents
            FROM store_orders WHERE site_id = @site_id AND is_internal = 0 AND created_at >= (NOW() - INTERVAL $days DAY)");
        $orderStats = $orders[0] ?? [];
        foreach ($orderStats as $k => $v) $orderStats[$k] = (int)$v;

        $topPages = $rows("SELECT path page_url, COUNT(*) views, COUNT(DISTINCT visitor_hash) visitors FROM seo_live_requests WHERE site_id = @site_id AND $client AND is_page_view = 1 AND $since GROUP BY path ORDER BY views DESC LIMIT 15");
        $topLinks = $rows("SELECT target, label, COUNT(*) clicks FROM seo_tracking_events WHERE site_id = @site_id AND $client AND type IN ('link_click','cta_click','outbound') AND target IS NOT NULL AND target <> '' AND $since GROUP BY target, label ORDER BY clicks DESC LIMIT 15");
        $devices = $rows("SELECT device, COUNT(*) visits FROM seo_live_requests WHERE site_id = @site_id AND $client AND is_page_view = 1 AND $since AND device IS NOT NULL AND device <> '' GROUP BY device ORDER BY visits DESC");
        $seoDevices = $rows("SELECT device, COUNT(*) visits FROM seo_tracking_events WHERE site_id = @site_id AND $client AND $since AND device IS NOT NULL AND device <> '' GROUP BY device ORDER BY visits DESC");
        $daily = $rows("SELECT DATE(created_at) day, COUNT(*) views, COUNT(DISTINCT visitor_hash) visitors FROM seo_live_requests WHERE site_id = @site_id AND $client AND is_page_view = 1 AND $since GROUP BY DATE(created_at) ORDER BY day ASC");
        $hourly = $rows("SELECT HOUR(created_at) hour, COUNT(*) views FROM seo_live_requests WHERE site_id = @site_id AND $client AND is_page_view = 1 AND $since GROUP BY HOUR(created_at) ORDER BY hour ASC");
        $weekdays = $rows("SELECT DAYNAME(created_at) day, COUNT(*) views FROM seo_live_requests WHERE site_id = @site_id AND $client AND is_page_view = 1 AND $since GROUP BY DAYNAME(created_at) ORDER BY views DESC");
        $topProducts = $rows("SELECT i.name, SUM(i.qty) qty, COALESCE(SUM(i.subtotal_cents),0) amount_cents FROM store_order_items i JOIN store_orders o ON o.id = i.order_id AND o.site_id = i.site_id WHERE o.site_id = @site_id AND o.is_internal = 0 AND o.payment_status = 'paid' AND o.created_at >= (NOW() - INTERVAL $days DAY) GROUP BY i.name ORDER BY qty DESC, amount_cents DESC LIMIT 15");
        $topCategories = $rows("SELECT COALESCE(c.name, 'Catálogo / sin categoría') category, SUM(i.qty) qty, COALESCE(SUM(i.subtotal_cents),0) amount_cents FROM store_order_items i JOIN store_orders o ON o.id = i.order_id AND o.site_id = i.site_id LEFT JOIN store_products p ON p.id = i.product_id AND p.site_id = i.site_id LEFT JOIN store_categories c ON c.id = p.category_id AND c.site_id = p.site_id WHERE o.site_id = @site_id AND o.is_internal = 0 AND o.payment_status = 'paid' AND o.created_at >= (NOW() - INTERVAL $days DAY) GROUP BY category ORDER BY qty DESC LIMIT 15");

        $cartRows = $rows("SELECT items, updated_at FROM store_carts WHERE site_id = @site_id AND items IS NOT NULL AND items NOT IN ('', '[]', 'null')");
        $carts = 0;
        $cartValueCents = 0;
        $abandonedCarts = 0;
        $now = time();
        foreach ($cartRows as $cart) {
            $items = json_decode((string)$cart['items'], true) ?: [];
            if (!$items) continue;
            $carts++;
            $value = 0;
            foreach ($items as $it) {
                if (is_array($it)) $value += (float)($it['price'] ?? 0) * max(1, (int)($it['qty'] ?? 1));
            }
            $cartValueCents += (int)round($value * 100);
            if (strtotime((string)$cart['updated_at']) < $now - 3600) $abandonedCarts++;
        }

        $guestCarts = 0;
        $guestCartValueCents = 0;
        $guestCartItems = 0;
        $guestRows = $rows("SELECT e.target FROM seo_tracking_events e INNER JOIN (SELECT session_id, MAX(id) id FROM seo_tracking_events WHERE site_id = @site_id AND $client AND type = 'cart_snapshot' AND $since GROUP BY session_id) latest ON latest.id = e.id");
        foreach ($guestRows as $guestRow) {
            $data = json_decode((string)$guestRow['target'], true);
            if (!is_array($data)) continue;
            $guestCarts++;
            $guestCartItems += (int)($data['qty'] ?? 0);
            $guestCartValueCents += (int)($data['value_cents'] ?? 0);
        }
        $checkoutAbandons = (int)$scalar("SELECT COUNT(*) FROM seo_tracking_events WHERE site_id = @site_id AND $client AND type = 'checkout_abandon' AND $since");

        $funnel = [];
        foreach (['product_view', 'cart_add', 'checkout_start', 'purchase'] as $type) {
            $funnel[$type] = (int)$scalar("SELECT COUNT(*) FROM seo_tracking_events WHERE site_id = @site_id AND $client AND type = '$type' AND $since");
        }
        $notFound = (int)$scalar("SELECT COUNT(*) FROM seo_404 WHERE site_id = @site_id");
        $redirects = (int)$scalar("SELECT COUNT(*) FROM seo_redirects WHERE site_id = @site_id AND is_active = 1");
        $broken = (int)$scalar("SELECT COUNT(*) FROM seo_broken_links WHERE site_id = @site_id AND status_code >= 400");
        $missingMeta = (int)$scalar("SELECT COUNT(*) FROM seo_pages WHERE site_id = @site_id AND (seo_title IS NULL OR seo_title = '')");
        $paid = (int)($orderStats['paid'] ?? 0);
        $pending = (int)($orderStats['pending'] ?? 0);
        $failed = (int)($orderStats['failed'] ?? 0);
        $abandonedOrders = (int)($orderStats['abandoned'] ?? 0);
        $abandonmentBase = $paid + $abandonedOrders;
        $abandonmentRate = $abandonmentBase > 0 ? round($abandonedOrders / $abandonmentBase * 100, 1) : 0;

        $sentiment = ['available' => false, 'reason' => 'No hay reseñas aprobadas para analizar.'];
        $reviewRows = $rows("SELECT rating, COUNT(*) c FROM store_product_reviews WHERE site_id = @site_id AND status = 'approved' GROUP BY rating");
        if ($reviewRows) {
            $positive = 0; $neutral = 0; $negative = 0; $totalReviews = 0;
            foreach ($reviewRows as $r) {
                $n = (int)$r['c']; $rating = (int)$r['rating']; $totalReviews += $n;
                if ($rating >= 4) $positive += $n;
                elseif ($rating === 3) $neutral += $n;
                else $negative += $n;
            }
            $sentiment = [
                'available' => $totalReviews > 0,
                'source' => 'store_product_reviews',
                'count' => $totalReviews,
                'positive' => $positive,
                'neutral' => $neutral,
                'negative' => $negative,
                'positive_pct' => $totalReviews ? round($positive / $totalReviews * 100, 1) : 0,
                'negative_pct' => $totalReviews ? round($negative / $totalReviews * 100, 1) : 0,
                'reason' => 'Clasificación basada en la calificación de reseñas aprobadas; no analiza el texto.',
            ];
        }

        return [
            'ok' => true,
            'days' => $days,
            'traffic' => [
                'views' => $views,
                'page_views' => $pageViews,
                'consent_page_views' => $consentPageViews,
                'server_page_views' => $serverPageViews,
                'analytics_views' => $analyticsViews,
                'unique_visitors' => $unique,
                'sessions' => $sessions,
                'server_sessions' => $serverSessions,
                'consent_sessions' => $consentSessions,
                'avg_time_seconds' => (int)round($avgTime / 1000),
                'avg_scroll' => $avgScroll,
                'pages_per_session' => $consentSessions > 0 ? round($consentPageViews / $consentSessions, 2) : 0,
                'internal_sessions' => $internalSessions,
                'internal_page_views' => $internalViews,
                'internal_page_requests' => $internalRequests,
                'excludes_internal' => true,
                'excludes_bots' => true,
                'sessions_source' => 'consent',
                'views_source' => 'server',
                'source_note' => 'Vistas y visitantes: monitor de peticiones del servidor. Sesiones y páginas/sesión: eventos con consentimiento. La cookie de sesión se unifica desde Fase 2.',
            ],
            'devices' => $devices,
            'seo_devices' => $seoDevices,
            'top_pages' => $topPages,
            'top_links' => $topLinks,
            'daily' => $daily,
            'hourly' => $hourly,
            'weekdays' => $weekdays,
            'orders' => $orderStats,
            'products' => $topProducts,
            'categories' => $topCategories,
            'cart' => [
                'tracked_carts' => $carts,
                'value_cents' => $cartValueCents,
                'abandoned_after_1h' => $abandonedCarts,
                'abandonment_rate' => $abandonmentRate,
                'guest_carts' => $guestCarts,
                'guest_items' => $guestCartItems,
                'guest_value_cents' => $guestCartValueCents,
                'checkout_abandons' => $checkoutAbandons,
                'note' => 'Carritos guardados = clientes con sesión. Invitados = último estado del carrito reportado por el navegador en esta sesión, solo con consentimiento.',
            ],
            'funnel' => $funnel,
            'seo' => [
                '404' => $notFound,
                'redirects' => $redirects,
                'broken_links' => $broken,
                'pages_without_meta' => $missingMeta,
            ],
            'sentiment' => $sentiment,
            'abandonment_causes' => [
                'available' => false,
                'reason' => 'Las causas requieren una encuesta de salida o motivos declarados; no se infieren como hechos.',
            ],
        ];
    }

    public function opportunities(int $days = 30): array
    {
        $a = $this->overview($days);
        $db = Database::instance();
        $days = max(1, min(365, $days));
        $items = [];
        $add = static function (string $severity, string $type, string $title, string $detail, string $action, int $count = 0) use (&$items): void {
            $items[] = ['severity' => $severity, 'type' => $type, 'title' => $title, 'detail' => $detail, 'action' => $action, 'count' => $count];
        };

        // 1. 404 reales en páginas (sin estáticos ni sondas de seguridad).
        try {
            $baseWhere = "site_id = @site_id AND status = 404 AND is_internal = 0 AND is_bot = 0
                  AND created_at >= (NOW() - INTERVAL $days DAY)
                  AND path NOT IN (SELECT from_path FROM seo_redirects WHERE site_id = @site_id AND is_active = 1)
                  AND path NOT REGEXP '\\\\.(ico|png|jpe?g|gif|svg|webp|css|js|map|woff2?|ttf|txt|xml)$'";
            $rows = $db->query("SELECT path, COUNT(*) c FROM seo_live_requests
                WHERE $baseWhere
                GROUP BY path ORDER BY c DESC LIMIT 5")->fetchAll();
            if ($rows) {
                $total = (int)$db->query("SELECT COUNT(*) FROM seo_live_requests WHERE $baseWhere")->fetchColumn();
                $top = implode(', ', array_map(static fn($r) => $r['path'] . ' (' . $r['c'] . ')', array_slice($rows, 0, 3)));
                $severity = $total >= 500 ? 'high' : ($total >= 100 ? 'medium' : 'low');
                $add($severity, '404', '404 reales en páginas', $total . ' peticiones a rutas inexistentes en ' . $days . ' días. Principales: ' . $top . '.', 'redirecciones', $total);
            }
        } catch (\Throwable $e) {
        }

        // 2. Enlaces rotos.
        $broken = (int)($a['seo']['broken_links'] ?? 0);
        if ($broken > 0) {
            $add('high', 'broken', 'Enlaces internos rotos', $broken . ' enlaces deben corregirse.', 'enlaces-rotos', $broken);
        }

        // 3. Hallazgos del último scan real, agrupados por tipo.
        try {
            $rows = $db->query("SELECT issue, severity, MIN(issue_type) type, COUNT(*) c, MAX(detail) detail
                FROM seo_audit_issues
                WHERE site_id = @site_id
                GROUP BY issue, severity
                ORDER BY FIELD(severity, 'high', 'medium', 'low'), c DESC
                LIMIT 10")->fetchAll();
            foreach ($rows as $r) {
                $count = (int)$r['c'];
                $issue = (string)$r['issue'];
                $one = $count === 1;
                $detail = '';
                if ($issue === 'Sin descripción propia ni SEO description') {
                    $detail = $count . ($one ? ' ficha sin texto propio' : ' fichas sin texto propio') . '; el meta actual es una plantilla genérica.';
                } elseif ($issue === 'Title largo') {
                    $detail = $count . ' títulos superan 60 caracteres y se truncan en Google.';
                } elseif ($issue === 'Producto sin descripción propia') {
                    $detail = $count . ($one ? ' producto sin descripción' : ' productos sin descripción') . '; la ficha pierde texto indexable.';
                } elseif (str_contains($issue, 'Categoría sin descripción')) {
                    $detail = $count . ($one ? ' categoría usa' : ' categorías usan') . ' solo la descripción plantilla.';
                } elseif ($issue === 'Producto sin imagen') {
                    $detail = $count . ($one ? ' producto sin imagen' : ' productos sin imagen') . '; sin vista previa en Google Shopping ni redes.';
                } elseif ($issue === 'Producto sin precio' || $issue === 'Producto sin SKU') {
                    $detail = $count . ($one ? ' producto con' : ' productos con') . ' datos comerciales incompletos.';
                } elseif ($issue === 'Producto sin categoría') {
                    $detail = $count . ($one ? ' producto fuera' : ' productos fuera') . ' de la taxonomía; no aparece en categorías ni filtros.';
                } elseif ($issue === 'Slug duplicado (-copia)') {
                    $detail = $count . ' URLs con slug de duplicado no coinciden con el nombre del producto; migrar con 301.';
                }
                if ($detail === '') {
                    $detail = $count > 1 ? $count . ' URLs afectadas.' : (trim((string)($r['detail'] ?? '')) ?: '1 URL afectada.');
                }
                $action = str_contains($issue, 'descripción') || $issue === 'Producto sin imagen' ? 'catalogo' : (str_contains($issue, 'Slug') ? 'redirecciones' : 'scan');
                $add((string)$r['severity'], 'audit', $issue, $detail, $action, $count);
            }
        } catch (\Throwable $e) {
        }

        // 4. Búsquedas internas sin resultado: demanda real sin catálogo.
        try {
            $rows = $db->query("SELECT q, COUNT(*) c FROM store_search_log
                WHERE site_id = @site_id AND created_at >= (NOW() - INTERVAL $days DAY)
                  AND q <> '' AND results = 0
                GROUP BY q ORDER BY c DESC LIMIT 5")->fetchAll();
            if ($rows) {
                $total = (int)$db->query("SELECT COUNT(*) FROM store_search_log
                    WHERE site_id = @site_id AND created_at >= (NOW() - INTERVAL $days DAY)
                      AND q <> '' AND results = 0")->fetchColumn();
                $terms = implode(', ', array_map(static fn($r) => '"' . $r['q'] . '" (' . $r['c'] . ')', array_slice($rows, 0, 3)));
                $add('medium', 'search', 'Búsquedas sin resultado', $total . ' búsquedas sin coincidencias. Términos: ' . $terms . '.', 'catalogo', $total);
            }
        } catch (\Throwable $e) {
        }

        // 5. Pedidos y carritos.
        if (($a['orders']['abandoned'] ?? 0) > 0) {
            $add('high', 'checkout', 'Pedidos pendientes de pago', $a['orders']['abandoned'] . ' pedidos superan 1 hora sin pago.', 'pedidos', (int)$a['orders']['abandoned']);
        }
        if (($a['cart']['abandoned_after_1h'] ?? 0) > 0) {
            $add('medium', 'cart', 'Carritos abandonados', $a['cart']['abandoned_after_1h'] . ' carritos guardados llevan más de 1 hora sin compra.', 'carrito', (int)$a['cart']['abandoned_after_1h']);
        }

        // Orden por severidad y sin títulos repetidos.
        $order = ['high' => 0, 'medium' => 1, 'low' => 2];
        usort($items, static fn($x, $y) => ($order[$x['severity']] ?? 3) <=> ($order[$y['severity']] ?? 3));
        $seen = [];
        $items = array_values(array_filter($items, static function ($i) use (&$seen) {
            if (isset($seen[$i['title']])) return false;
            $seen[$i['title']] = true;
            return true;
        }));
        $summary = ['high' => 0, 'medium' => 0, 'low' => 0];
        foreach ($items as $i) $summary[$i['severity']] = ($summary[$i['severity']] ?? 0) + 1;
        return ['ok' => true, 'items' => $items, 'count' => count($items), 'summary' => $summary];
    }
}
