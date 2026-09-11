<?php
namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Session;

class FactoryService
{
    public function plans(): array
    {
        $db = Database::instance();
        $rows = $db->query("SELECT * FROM wwi_plans WHERE site_id = @site_id ORDER BY sort_order ASC")->fetchAll();
        foreach ($rows as &$row) {
            $row['features'] = $this->safeJson($row['features'] ?? '');
            $row['limits'] = $this->safeJson($row['limits'] ?? '');
            $row['margin_cost_items'] = $this->safeJson($row['margin_cost_items'] ?? '');
            $row['price_cop'] = (float)$row['price_cop'];
            $row['price_usd'] = (float)$row['price_usd'];
            $row['margin'] = $this->planMargin($row);
        }
        return $rows;
    }

    public function plan(?int $id): ?array
    {
        if (!$id) return null;
        $stmt = Database::instance()->prepare("SELECT * FROM wwi_plans WHERE id = :id AND site_id = @site_id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) return null;
        $row['features'] = $this->safeJson($row['features'] ?? '');
        $row['limits'] = $this->safeJson($row['limits'] ?? '');
        $row['margin_cost_items'] = $this->safeJson($row['margin_cost_items'] ?? '');
        return $row;
    }

    public function savePlan(array $d, ?int $id = null): array
    {
        $db = Database::instance();
        $slug = trim((string)($d['slug'] ?? ''));
        if ($slug === '') return ['ok' => false, 'message' => 'Slug is required'];
        $data = [
            'slug' => $slug,
            'name_es' => (string)($d['name_es'] ?? ''),
            'name_en' => (string)($d['name_en'] ?? ''),
            'description_es' => (string)($d['description_es'] ?? ''),
            'description_en' => (string)($d['description_en'] ?? ''),
            'price_cop' => (float)($d['price_cop'] ?? 0),
            'price_usd' => (float)($d['price_usd'] ?? 0),
            'billing_type' => in_array($d['billing_type'] ?? '', ['one_time', 'monthly', 'annual'], true) ? $d['billing_type'] : 'one_time',
            'duration_months' => (int)($d['duration_months'] ?? 0),
            'features' => json_encode(is_array($d['features'] ?? null) ? $d['features'] : []),
            'limits' => json_encode(is_array($d['limits'] ?? null) ? $d['limits'] : []),
            'margin_cost_items' => json_encode(is_array($d['margin_cost_items'] ?? null) ? $d['margin_cost_items'] : []),
            'min_margin_pct' => (float)($d['min_margin_pct'] ?? 25),
            'sort_order' => (int)($d['sort_order'] ?? 0),
            'is_active' => !empty($d['is_active']) ? 1 : 0,
        ];
        try {
            if ($id) {
                $sets = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($data)));
                $stmt = $db->prepare("UPDATE wwi_plans SET $sets WHERE id = :id AND site_id = @site_id");
                $stmt->execute($data + ['id' => $id]);
                return ['ok' => true, 'message' => 'Plan updated', 'id' => $id];
            }
            $cols = implode(', ', array_keys($data));
            $ph = implode(', ', array_map(fn($k) => ":$k", array_keys($data)));
            $stmt = $db->prepare("INSERT INTO wwi_plans (site_id, $cols) VALUES (@site_id, $ph)");
            $stmt->execute($data);
            return ['ok' => true, 'message' => 'Plan created', 'id' => (int)$db->lastInsertId()];
        } catch (\PDOException $e) {
            return ['ok' => false, 'message' => $e->getCode() === '23000' ? 'A plan with this slug already exists' : 'Database error'];
        }
    }

    public function deletePlan(int $id): array
    {
        Database::instance()->prepare("DELETE FROM wwi_plans WHERE id = :id AND site_id = @site_id")->execute(['id' => $id]);
        return ['ok' => true, 'message' => 'Plan removed'];
    }

    public function config(): array
    {
        $db = Database::instance();
        $rows = $db->query("SELECT `key`, `value` FROM settings WHERE site_id = @site_id AND `key` LIKE 'wwi.%' ORDER BY `key` ASC")->fetchAll();
        $out = [];
        foreach ($rows as $row) $out[$row['key']] = $row['value'];
        return $out;
    }

    public function saveConfig(array $kv): array
    {
        $db = Database::instance();
        $updated = 0;
        foreach ($kv as $key => $value) {
            if (!str_starts_with((string)$key, 'wwi.')) continue;
            $stmt = $db->prepare("INSERT INTO settings (site_id, `key`, `value`) VALUES (@site_id, :k, :v) ON DUPLICATE KEY UPDATE `value` = :v2");
            $stmt->execute(['k' => $key, 'v' => (string)$value, 'v2' => (string)$value]);
            $updated++;
        }
        return ['ok' => true, 'message' => "$updated configuration values saved"];
    }

    public function marginReport(): array
    {
        $rows = [];
        foreach ($this->plans() as $plan) {
            $margin = $this->planMargin($plan);
            $margin['plan_id'] = (int)$plan['id'];
            $margin['plan'] = $plan['name_es'];
            $margin['slug'] = $plan['slug'];
            $margin['billing'] = $plan['billing_type'];
            $rows[] = $margin;
        }
        return $rows;
    }

    public function planMargin(array $plan): array
    {
        $price = (float)($plan['price_cop'] ?? 0);
        $rate = $this->usdRate();
        $usdCost = 0.0;
        $pctCost = 0.0;
        $items = [];
        foreach (($plan['margin_cost_items'] ?? []) as $item) {
            if (!is_array($item)) continue;
            $label = $item['item'] ?? 'item';
            if (isset($item['usd'])) {
                $cop = (float)$item['usd'] * $rate;
                $usdCost += $cop;
                $items[] = ['item' => $label, 'usd' => (float)$item['usd'], 'cop' => round($cop, 0)];
            } elseif (isset($item['pct'])) {
                $cop = (float)$item['pct'] / 100 * $price;
                $pctCost += $cop;
                $items[] = ['item' => $label, 'pct' => (float)$item['pct'], 'cop' => round($cop, 0)];
            }
        }
        $totalCost = $usdCost + $pctCost;
        $marginCop = $price - $totalCost;
        $marginPct = $price > 0 ? round($marginCop / $price * 100, 1) : 0;
        $min = (float)($plan['min_margin_pct'] ?? 25);
        $status = $marginPct < $min ? 'critical' : ($marginPct < $min + 10 ? 'warning' : 'ok');
        return [
            'price_cop' => round($price, 0),
            'usd_rate' => $rate,
            'items' => $items,
            'total_cost_cop' => round($totalCost, 0),
            'margin_cop' => round($marginCop, 0),
            'margin_pct' => $marginPct,
            'min_margin_pct' => $min,
            'status' => $status,
        ];
    }

    public function usdRate(): float
    {
        $db = Database::instance();
        $stmt = $db->prepare("SELECT `value` FROM settings WHERE site_id = @site_id AND `key` = 'wwi.usd_cop_rate'");
        $stmt->execute();
        $val = $stmt->fetchColumn();
        return $val ? (float)$val : 4000;
    }

    private function safeJson($value): array
    {
        if (is_array($value)) return $value;
        if (is_string($value)) return json_decode($value, true) ?: [];
        return [];
    }

    public function dashboard(): array
    {
        $db = Database::instance();
        $monthStart = date('Y-m-01 00:00:00');
        return [
            'sites_total' => (int)$db->query("SELECT COUNT(*) FROM sites")->fetchColumn(),
            'sites_published' => (int)$db->query("SELECT COUNT(*) FROM sites WHERE status = 'PUBLISHED'")->fetchColumn(),
            'domains_total' => (int)$db->query("SELECT COUNT(*) FROM wwi_domains")->fetchColumn(),
            'domains_active' => (int)$db->query("SELECT COUNT(*) FROM wwi_domains WHERE status IN ('ACTIVE','REGISTERED')")->fetchColumn(),
            'emails_total' => (int)$db->query("SELECT COUNT(*) FROM wwi_email_accounts")->fetchColumn(),
            'orders_total' => (int)$db->query("SELECT COUNT(*) FROM wwi_orders")->fetchColumn(),
            'orders_paid' => (int)$db->query("SELECT COUNT(*) FROM wwi_orders WHERE status IN ('PAID','PROVISIONING','READY')")->fetchColumn(),
            'revenue_total' => round((float)$db->query("SELECT COALESCE(SUM(total),0) FROM wwi_orders WHERE status IN ('PAID','PROVISIONING','READY')")->fetchColumn(), 2),
            'ai_cost_month' => round((float)$db->query("SELECT COALESCE(SUM(estimated_cost),0) FROM ai_usage WHERE created_at >= '$monthStart'")->fetchColumn(), 4),
            'pending_jobs' => (int)$db->query("SELECT COUNT(*) FROM wwi_jobs WHERE status IN ('queued','retrying')")->fetchColumn(),
            'clients' => (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'client'")->fetchColumn(),
        ];
    }

    public function sitesAll(): array
    {
        $db = Database::instance();
        return $db->query("SELECT s.id, s.name, s.domain, s.locale, s.theme, s.status, s.plan_id, s.uuid, s.owner_user_id, s.is_active, s.created_at,
            (SELECT name FROM wwi_domains d WHERE d.site_id = s.id ORDER BY d.id DESC LIMIT 1) AS active_domain,
            p.slug AS plan_slug, p.name_es AS plan_name
            FROM sites s LEFT JOIN wwi_plans p ON p.id = s.plan_id ORDER BY s.id DESC")->fetchAll();
    }

    public function addSite(array $d): array
    {
        $db = Database::instance();
        $name = trim((string)($d['name'] ?? ''));
        if ($name === '') return ['ok' => false, 'message' => 'Name is required'];
        $uuid = isset($d['uuid']) && $d['uuid'] ? (string)$d['uuid'] : bin2hex(random_bytes(18));
        $stmt = $db->prepare("INSERT INTO sites (name, domain, locale, theme, plan_id, status, uuid, owner_user_id) VALUES (:name, :domain, :locale, :theme, :plan, :status, :uuid, :owner)");
        $stmt->execute([
            'name' => $name,
            'domain' => (string)($d['domain'] ?? ''),
            'locale' => (string)($d['locale'] ?? 'es'),
            'theme' => (string)($d['theme'] ?? 'default'),
            'plan' => (int)($d['plan_id'] ?? 0) ?: null,
            'status' => (string)($d['status'] ?? 'DRAFT'),
            'uuid' => $uuid,
            'owner' => (int)($d['owner_user_id'] ?? 0) ?: null,
        ]);
        return ['ok' => true, 'message' => 'Site created', 'id' => (int)$db->lastInsertId()];
    }

    public function updateSiteStatus(int $id, string $status): array
    {
        $allowed = ['DRAFT','GENERATING','READY','PUBLISHED','SUSPENDED','ARCHIVED'];
        if (!in_array($status, $allowed, true)) return ['ok' => false, 'message' => 'Invalid status'];
        Database::instance()->prepare("UPDATE sites SET status = :s WHERE id = :id")->execute(['s' => $status, 'id' => $id]);
        return ['ok' => true, 'message' => 'Site status updated'];
    }

    public function domainsAll(): array
    {
        $db = Database::instance();
        return $db->query("SELECT d.*, s.name AS site_name FROM wwi_domains d LEFT JOIN sites s ON s.id = d.site_id ORDER BY d.id DESC")->fetchAll();
    }

    public function addDomain(array $d): array
    {
        $db = Database::instance();
        $name = strtolower(trim((string)($d['name'] ?? '')));
        if ($name === '') return ['ok' => false, 'message' => 'Domain name is required'];
        $siteId = (int)($d['site_id'] ?? 0);
        $tld = str_contains($name, '.') ? substr($name, strrpos($name, '.') + 1) : '';
        try {
            $stmt = $db->prepare("INSERT INTO wwi_domains (site_id, name, tld, status, provider, registration_cost, renewal_cost, currency) VALUES (:sid, :name, :tld, :status, :provider, :rc, :rn, 'USD')");
            $stmt->execute([
                'sid' => $siteId,
                'name' => $name,
                'tld' => $tld,
                'status' => (string)($d['status'] ?? 'AVAILABLE'),
                'provider' => (string)($d['provider'] ?? ''),
                'rc' => (float)($d['registration_cost'] ?? 0),
                'rn' => (float)($d['renewal_cost'] ?? 0),
            ]);
            return ['ok' => true, 'message' => 'Domain added', 'id' => (int)$db->lastInsertId()];
        } catch (\PDOException $e) {
            return ['ok' => false, 'message' => $e->getCode() === '23000' ? 'Domain already exists' : 'Database error'];
        }
    }

    public function updateDomainStatus(int $id, string $status): array
    {
        $allowed = ['SEARCH','AVAILABLE','REGISTERING','REGISTERED','DNS_PENDING','ACTIVE','EXPIRING','EXPIRED'];
        if (!in_array($status, $allowed, true)) return ['ok' => false, 'message' => 'Invalid status'];
        Database::instance()->prepare("UPDATE wwi_domains SET status = :s WHERE id = :id")->execute(['s' => $status, 'id' => $id]);
        return ['ok' => true, 'message' => 'Domain status updated'];
    }

    public function emailsAll(): array
    {
        $db = Database::instance();
        return $db->query("SELECT e.*, d.name AS domain_name FROM wwi_email_accounts e LEFT JOIN wwi_domains d ON d.id = e.domain_id ORDER BY e.id DESC")->fetchAll();
    }

    public function addEmail(array $d): array
    {
        $db = Database::instance();
        $mailbox = strtolower(trim((string)($d['mailbox'] ?? '')));
        if ($mailbox === '') return ['ok' => false, 'message' => 'Mailbox is required'];
        try {
            $stmt = $db->prepare("INSERT INTO wwi_email_accounts (site_id, domain_id, mailbox, display_name, status, provider) VALUES (:sid, :did, :mb, :dn, :st, :p)");
            $stmt->execute([
                'sid' => (int)($d['site_id'] ?? 0),
                'did' => (int)($d['domain_id'] ?? 0) ?: null,
                'mb' => $mailbox,
                'dn' => (string)($d['display_name'] ?? ''),
                'st' => (string)($d['status'] ?? 'REQUESTED'),
                'p' => (string)($d['provider'] ?? ''),
            ]);
            return ['ok' => true, 'message' => 'Mailbox added', 'id' => (int)$db->lastInsertId()];
        } catch (\PDOException $e) {
            return ['ok' => false, 'message' => $e->getCode() === '23000' ? 'Mailbox already exists' : 'Database error'];
        }
    }

    public function updateEmailStatus(int $id, string $status): array
    {
        $allowed = ['REQUESTED','PROVISIONING','ACTIVE','SUSPENDED','DELETED'];
        if (!in_array($status, $allowed, true)) return ['ok' => false, 'message' => 'Invalid status'];
        Database::instance()->prepare("UPDATE wwi_email_accounts SET status = :s WHERE id = :id")->execute(['s' => $status, 'id' => $id]);
        return ['ok' => true, 'message' => 'Mailbox status updated'];
    }

    public function ordersAll(): array
    {
        $db = Database::instance();
        return $db->query("SELECT o.*, s.name AS site_name, p.slug AS plan_slug, p.name_es AS plan_name
            FROM wwi_orders o
            LEFT JOIN sites s ON s.id = o.tenant_id
            LEFT JOIN wwi_plans p ON p.id = o.plan_id
            ORDER BY o.id DESC LIMIT 200")->fetchAll();
    }

    public function addOrder(array $d): array
    {
        $db = Database::instance();
        $plan = $this->plan((int)($d['plan_id'] ?? 0));
        if (!$plan) return ['ok' => false, 'message' => 'Plan not found'];
        $uuid = isset($d['uuid']) && $d['uuid'] ? (string)$d['uuid'] : bin2hex(random_bytes(16));
        $stmt = $db->prepare("INSERT INTO wwi_orders (site_id, uuid, customer_name, customer_email, customer_phone, plan_id, domain_name, tenant_id, status, subtotal, total, currency, locale) VALUES (@site_id, :uuid, :cn, :ce, :cp, :plan, :dn, :tid, 'CREATED', :sub, :tot, :cur, :loc)");
        $stmt->execute([
            'uuid' => $uuid,
            'cn' => (string)($d['customer_name'] ?? ''),
            'ce' => (string)($d['customer_email'] ?? ''),
            'cp' => (string)($d['customer_phone'] ?? ''),
            'plan' => (int)$plan['id'],
            'dn' => (string)($d['domain_name'] ?? ''),
            'tid' => (int)($d['tenant_id'] ?? 0) ?: null,
            'sub' => $plan['price_cop'],
            'tot' => $plan['price_cop'],
            'cur' => 'COP',
            'loc' => (string)($d['locale'] ?? 'es'),
        ]);
        return ['ok' => true, 'message' => 'Order created', 'id' => (int)$db->lastInsertId(), 'uuid' => $uuid];
    }

    public function transitionOrder(int $id, string $status): array
    {
        $allowed = ['CREATED','PENDING_PAYMENT','PAID','PROVISIONING','READY','FAILED','CANCELLED','REFUNDED'];
        if (!in_array($status, $allowed, true)) return ['ok' => false, 'message' => 'Invalid status'];
        $db = Database::instance();
        $stmt = $db->prepare("SELECT * FROM wwi_orders WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $order = $stmt->fetch();
        if (!$order) return ['ok' => false, 'message' => 'Order not found'];
        $db->prepare("UPDATE wwi_orders SET status = :s WHERE id = :id")->execute(['s' => $status, 'id' => $id]);
        if ($status === 'PAID' && $order['status'] !== 'PAID' && $order['tenant_id']) {
            $this->addLedger(['site_id' => (int)$order['tenant_id'], 'direction' => 'credit', 'amount' => (float)$order['total'], 'reason' => 'Plan payment', 'ref' => 'order:' . $order['uuid']]);
        }
        if ($status === 'REFUNDED' && $order['tenant_id']) {
            $this->addLedger(['site_id' => (int)$order['tenant_id'], 'direction' => 'debit', 'amount' => (float)$order['total'], 'reason' => 'Refund', 'ref' => 'order:' . $order['uuid']]);
        }
        return ['ok' => true, 'message' => 'Order status updated'];
    }

    public function ledgerAll(): array
    {
        $db = Database::instance();
        return $db->query("SELECT l.*, s.name AS site_name FROM wwi_balance_ledger l LEFT JOIN sites s ON s.id = l.site_id ORDER BY l.id DESC LIMIT 200")->fetchAll();
    }

    public function addLedger(array $d): array
    {
        $db = Database::instance();
        $stmt = $db->prepare("INSERT INTO wwi_balance_ledger (site_id, direction, amount, reason, ref, created_by) VALUES (:sid, :dir, :amt, :reason, :ref, :by)");
        $stmt->execute([
            'sid' => (int)($d['site_id'] ?? 0),
            'dir' => in_array($d['direction'] ?? '', ['credit', 'debit'], true) ? $d['direction'] : 'credit',
            'amt' => (float)($d['amount'] ?? 0),
            'reason' => (string)($d['reason'] ?? ''),
            'ref' => (string)($d['ref'] ?? ''),
            'by' => null,
        ]);
        return ['ok' => true, 'message' => 'Ledger entry added', 'id' => (int)$db->lastInsertId()];
    }

    public function balanceFor(int $siteId): float
    {
        $stmt = Database::instance()->prepare("SELECT COALESCE(SUM(CASE WHEN direction='credit' THEN amount ELSE -amount END),0) FROM wwi_balance_ledger WHERE site_id = :sid");
        $stmt->execute(['sid' => $siteId]);
        return round((float)$stmt->fetchColumn(), 2);
    }

    public function aiUsageBySite(): array
    {
        $db = Database::instance();
        return $db->query("SELECT u.site_id, s.name AS site_name, COUNT(*) AS requests, COALESCE(SUM(u.total_tokens),0) AS tokens, COALESCE(SUM(u.estimated_cost),0) AS cost
            FROM ai_usage u LEFT JOIN sites s ON s.id = u.site_id
            WHERE u.created_at >= '" . date('Y-m-01 00:00:00') . "'
            GROUP BY u.site_id, s.name ORDER BY cost DESC LIMIT 50")->fetchAll();
    }

    public function createPublicOrder(array $d): array
    {
        $planId = (int)($d['plan_id'] ?? 0);
        $plan = $this->plan($planId);
        if (!$plan || (int)$plan['is_active'] !== 1) return ['ok' => false, 'message' => 'Plan no disponible'];
        $name = trim((string)($d['customer_name'] ?? ''));
        $email = trim((string)($d['customer_email'] ?? ''));
        if ($name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Nombre y email válido son requeridos'];
        }
        $uuid = bin2hex(random_bytes(16));
        $db = Database::instance();
        $addons = [];
        foreach ((array)($d['addons'] ?? []) as $slug) {
            $addonPlan = $this->planBySlug((string)$slug);
            if ($addonPlan && $addonPlan['slug'] !== $plan['slug']) $addons[] = (string)$slug;
        }
        $addonTotal = 0;
        foreach ($addons as $slug) {
            $addonPlan = $this->planBySlug($slug);
            if ($addonPlan) $addonTotal += (float)$addonPlan['price_cop'];
        }
        $total = (float)$plan['price_cop'] + $addonTotal;
        $stmt = $db->prepare("INSERT INTO wwi_orders (site_id, uuid, customer_name, customer_email, customer_phone, plan_id, domain_name, addons, status, subtotal, total, currency, locale)
            VALUES (@site_id, :uuid, :cn, :ce, :cp, :plan, :dn, :add, 'PENDING_PAYMENT', :sub, :tot, 'COP', :loc)");
        $stmt->execute([
            'uuid' => $uuid,
            'cn' => $name,
            'ce' => $email,
            'cp' => (string)($d['customer_phone'] ?? ''),
            'plan' => $planId,
            'dn' => strtolower(trim((string)($d['domain_name'] ?? ''))),
            'add' => json_encode($addons),
            'sub' => $plan['price_cop'],
            'tot' => $total,
            'loc' => (string)($d['locale'] ?? 'es'),
        ]);
        return [
            'ok' => true,
            'message' => 'Pedido creado',
            'uuid' => $uuid,
            'id' => (int)$db->lastInsertId(),
            'total' => $total,
            'currency' => 'COP',
            'status' => 'PENDING_PAYMENT',
            'plan_name' => $plan['name_es'],
            'addons' => $addons,
        ];
    }

    public function findOrderByUuid(string $uuid): ?array
    {
        $stmt = Database::instance()->prepare("SELECT * FROM wwi_orders WHERE uuid = :uuid AND site_id = @site_id LIMIT 1");
        $stmt->execute(['uuid' => $uuid]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function paymentConfig(): array
    {
        $cfg = $this->config();
        return [
            'provider' => (string)($cfg['wwi.payment_provider'] ?? ''),
            'integrity_key' => (string)($cfg['wwi.wompi_integrity_key'] ?? ''),
        ];
    }

    public function processPaymentWebhook(array $payload): array
    {
        $pc = $this->paymentConfig();
        if ($pc['provider'] === '' || $pc['integrity_key'] === '') {
            return ['ok' => false, 'status' => 503, 'message' => 'Payment provider not configured'];
        }
        $data = is_array($payload['data']['transaction'] ?? null) ? $payload['data']['transaction'] : [];
        $checksum = (string)($payload['signature']['checksum'] ?? '');
        $reference = (string)($data['reference'] ?? '');
        $amountCents = (int)($data['amount_in_cents'] ?? 0);
        $currency = (string)($data['currency'] ?? 'COP');
        $expected = hash('sha256', $reference . $amountCents . $currency . $pc['integrity_key']);
        if ($checksum === '' || !hash_equals($expected, $checksum)) {
            return ['ok' => false, 'status' => 401, 'message' => 'Invalid signature'];
        }
        $order = $reference !== '' ? $this->findOrderByUuid($reference) : null;
        if (!$order) {
            return ['ok' => false, 'status' => 404, 'message' => 'Order not found'];
        }
        $db = Database::instance();
        $status = strtoupper((string)($data['status'] ?? ''));
        $providerRef = (string)($data['id'] ?? $reference);
        $stmt = $db->prepare("INSERT IGNORE INTO wwi_payments (site_id, order_id, provider, provider_ref, amount, currency, status, signature_verified, raw_webhook, verified_at)
            VALUES (@site_id, :oid, 'wompi', :pref, :amt, :cur, :st, 1, :raw, NOW())");
        $stmt->execute([
            'oid' => (int)$order['id'],
            'pref' => $providerRef,
            'amt' => $amountCents / 100,
            'cur' => $currency,
            'st' => $status === 'APPROVED' ? 'approved' : ($status === 'DECLINED' ? 'declined' : 'pending'),
            'raw' => json_encode($payload),
        ]);
        if ($status === 'APPROVED') {
            $this->transitionOrder((int)$order['id'], 'PAID');
            $this->enqueueJob('provision_site', ['order_id' => (int)$order['id'], 'order_uuid' => $order['uuid'], 'tenant_id' => (int)$order['tenant_id'], 'plan_id' => (int)$order['plan_id'], 'domain_name' => $order['domain_name']]);
            return ['ok' => true, 'message' => 'Payment approved — provisioning queued'];
        }
        return ['ok' => true, 'message' => 'Webhook received: ' . $status];
    }

    public function enqueueJob(string $type, array $payload): int
    {
        $db = Database::instance();
        $stmt = $db->prepare("INSERT INTO wwi_jobs (site_id, type, status, payload, scheduled_at) VALUES (@site_id, :type, 'queued', :payload, NOW())");
        $stmt->execute(['type' => $type, 'payload' => json_encode($payload)]);
        return (int)$db->lastInsertId();
    }

    public function dummyPay(string $uuid): array
    {
        $pc = $this->paymentConfig();
        if ($pc['provider'] !== 'dummy') {
            return ['ok' => false, 'status' => 503, 'message' => 'Demo payment disabled (provider is not dummy)'];
        }
        $order = $this->findOrderByUuid($uuid);
        if (!$order) return ['ok' => false, 'status' => 404, 'message' => 'Order not found'];
        if ($order['status'] !== 'PENDING_PAYMENT') {
            return ['ok' => false, 'status' => 400, 'message' => 'Order is not pending payment'];
        }
        $db = Database::instance();
        $stmt = $db->prepare("INSERT IGNORE INTO wwi_payments (site_id, order_id, provider, provider_ref, amount, currency, status, signature_verified, raw_webhook, verified_at)
            VALUES (@site_id, :oid, 'dummy', :pref, :amt, 'COP', 'approved', 1, '{\"simulated\":true}', NOW())");
        $stmt->execute(['oid' => (int)$order['id'], 'pref' => 'dummy_' . $uuid, 'amt' => (float)$order['total']]);
        $this->transitionOrder((int)$order['id'], 'PAID');
        $this->enqueueJob('provision_site', ['order_id' => (int)$order['id'], 'order_uuid' => $order['uuid'], 'tenant_id' => (int)$order['tenant_id'], 'plan_id' => (int)$order['plan_id'], 'domain_name' => $order['domain_name']]);
        return ['ok' => true, 'message' => 'Demo payment approved — provisioning queued'];
    }

    public function jobsList(): array
    {
        $db = Database::instance();
        return $db->query("SELECT j.*, o.uuid AS order_uuid FROM wwi_jobs j LEFT JOIN wwi_orders o ON o.id = JSON_EXTRACT(j.payload, '$.order_id') WHERE j.site_id = @site_id ORDER BY j.id DESC LIMIT 100")->fetchAll();
    }

    public function runDueJobs(int $limit = 5): array
    {
        $db = Database::instance();
        $stmt = $db->prepare("SELECT * FROM wwi_jobs WHERE site_id = @site_id AND status IN ('queued','retrying') AND attempts < max_attempts ORDER BY id ASC LIMIT :lim");
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        $jobs = $stmt->fetchAll();
        $ran = 0;
        foreach ($jobs as $job) {
            $db->prepare("UPDATE wwi_jobs SET status = 'running', locked_at = NOW() WHERE id = :id")->execute(['id' => $job['id']]);
            $payload = json_decode((string)$job['payload'], true) ?: [];
            try {
                $result = $this->dispatchJob($job['type'], $payload);
                $db->prepare("UPDATE wwi_jobs SET status = 'done', completed_at = NOW(), error = NULL WHERE id = :id")->execute(['id' => $job['id']]);
                $ran++;
            } catch (\Exception $e) {
                $attempts = (int)$job['attempts'] + 1;
                $next = date('Y-m-d H:i:s', time() + 300);
                $status = $attempts >= (int)$job['max_attempts'] ? 'failed' : 'retrying';
                $db->prepare("UPDATE wwi_jobs SET status = :st, attempts = :at, error = :err, next_retry_at = :nr WHERE id = :id")
                    ->execute(['st' => $status, 'at' => $attempts, 'err' => mb_substr($e->getMessage(), 0, 900), 'nr' => $next, 'id' => $job['id']]);
            }
        }
        return ['ok' => true, 'message' => "$ran job(s) processed"];
    }

    public function createBrief(array $d): array
    {
        $email = trim((string)($d['customer_email'] ?? ''));
        $story = trim((string)($d['story'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Email válido requerido'];
        }
        if (mb_strlen($story) < 30) {
            return ['ok' => false, 'message' => 'Cuéntanos un poco más sobre tu negocio (mínimo 30 caracteres)'];
        }
        $db = Database::instance();
        $stmt = $db->prepare("INSERT INTO wwi_briefs (site_id, order_id, customer_email, business_name, story, status) VALUES (@site_id, :oid, :ce, :bn, :story, 'new')");
        $stmt->execute([
            'oid' => (int)($d['order_id'] ?? 0) ?: null,
            'ce' => $email,
            'bn' => trim((string)($d['business_name'] ?? '')),
            'story' => $story,
        ]);
        $briefId = (int)$db->lastInsertId();
        $this->enqueueJob('process_brief', ['brief_id' => $briefId]);
        return ['ok' => true, 'message' => 'Brief recibido — TIA lo está analizando', 'id' => $briefId];
    }

    public function briefs(): array
    {
        return Database::instance()->query("SELECT * FROM wwi_briefs WHERE site_id = @site_id ORDER BY id DESC LIMIT 50")->fetchAll();
    }

    public function processBrief(int $briefId): array
    {
        $db = Database::instance();
        $stmt = $db->prepare("SELECT * FROM wwi_briefs WHERE id = :id AND site_id = @site_id");
        $stmt->execute(['id' => $briefId]);
        $brief = $stmt->fetch();
        if (!$brief) throw new \RuntimeException('Brief not found');

        $prompt = "Eres TIA, analista de negocios de Wontia. A partir de la descripción del negocio, extrae un perfil estructurado. Responde SOLO con JSON válido, sin markdown:\n"
            . "{\"historia\":\"...\",\"propuesta_valor\":\"...\",\"servicios\":[\"...\"],\"productos\":[\"...\"],\"clientes\":\"...\",\"ubicacion\":\"...\",\"contacto\":\"...\",\"horarios\":\"...\",\"redes\":\"...\",\"diferenciadores\":[\"...\"],\"pendientes\":[\"datos que faltan\"]}\n"
            . "Regla: NO inventes datos factuales. Si un dato no aparece en la descripción, déjalo vacío y agrégalo a pendientes.\n\n"
            . "Negocio: " . ($brief['business_name'] ?: '(sin nombre)') . "\nDescripción del cliente:\n" . mb_substr((string)$brief['story'], 0, 3000);

        $router = new \App\Core\AiBrick\AiRouter();
        $response = $router->route([
            'system_id' => 'wontia',
            'module' => 'agent',
            'function' => 'brief',
            'system_prompt' => 'Respondes únicamente con JSON válido.',
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'max_tokens' => 900,
            'temperature' => 0.3,
        ]);
        if (empty($response['ok']) || empty($response['content'])) {
            throw new \RuntimeException('TIA no pudo procesar el brief');
        }
        $profile = json_decode((string)$response['content'], true);
        if (!is_array($profile)) {
            $clean = preg_replace('/^```(json)?\s*|\s*```$/m', '', (string)$response['content']);
            $profile = json_decode($clean, true);
        }
        if (!is_array($profile)) throw new \RuntimeException('Perfil inválido generado');

        $db->prepare("UPDATE wwi_briefs SET profile = :p, status = 'ready' WHERE id = :id")
            ->execute(['p' => json_encode($profile, JSON_UNESCAPED_UNICODE), 'id' => $briefId]);
        return ['ok' => true, 'brief_id' => $briefId, 'profile' => $profile];
    }

    private function requestDeploy(array $tenant, array $order): void
    {
        $slug = strtolower(trim((string)preg_replace('/[^a-z0-9]+/i', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $tenant['name'])), '-')) ?: ('cliente' . $tenant['id']);
        $domain = $slug . '.wontia.com';
        $key = bin2hex(random_bytes(16));
        $db = Database::instance();
        $db->prepare("INSERT INTO settings (site_id, `key`, `value`) VALUES (:sid, 'wwi_brick_key', :k) ON DUPLICATE KEY UPDATE `value` = :k2")
            ->execute(['sid' => $tenant['id'], 'k' => $key, 'k2' => $key]);
        $payload = [
            'site_id' => (int)$tenant['id'],
            'name' => $tenant['name'],
            'slug' => $slug,
            'domain' => $domain,
            'brick_key' => $key,
            'app_url' => 'https://' . $domain,
            'user_email' => (string)$order['customer_email'],
            'custom_domain' => (string)($order['domain_name'] ?? ''),
        ];
        $canonical = implode('|', [
            $payload['site_id'], $payload['slug'], $payload['domain'], $payload['brick_key'],
            $payload['name'], $payload['user_email'], $payload['custom_domain'],
        ]);
        $payload['sign'] = hash_hmac('sha256', $canonical, (string)Config::get('JWT_SECRET', 'x'));
        $dir = '/app/deploy-queue';
        @mkdir($dir, 0777, true);
        @file_put_contents($dir . '/' . $slug . '-' . $tenant['id'] . '.json', json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    public function previewAttempts(string $ip): array
    {
        if ($this->isBuilder()) return ['unlimited' => true, 'limit' => null];
        $db = Database::instance();
        $hash = md5($ip);
        $stmt = $db->prepare("SELECT attempts FROM wwi_prompt_attempts WHERE site_id = @site_id AND ip_hash = :h AND day = CURDATE()");
        $stmt->execute(['h' => $hash]);
        $used = (int)$stmt->fetchColumn();
        return ['used' => $used, 'left' => max(0, 2 - $used), 'limit' => 2, 'unlimited' => false];
    }

    private function isBuilder(): bool
    {
        return Session::isLoggedIn() && in_array(Session::userRole(), ['superadmin', 'admin', 'editor'], true);
    }

    public function createPreview(string $prompt, string $ip, bool $skipLimit = false): array
    {
        $prompt = trim($prompt);
        if (mb_strlen($prompt) < 10) {
            return ['ok' => false, 'message' => 'Cuéntame un poco más sobre tu negocio (mínimo 10 caracteres)'];
        }
        $db = Database::instance();
        if (!$skipLimit) {
            $hash = md5($ip);
            $stmt = $db->prepare("INSERT INTO wwi_prompt_attempts (site_id, ip_hash, day, attempts) VALUES (@site_id, :h, CURDATE(), 1) ON DUPLICATE KEY UPDATE attempts = attempts + 1");
            $stmt->execute(['h' => $hash]);
            $attempts = $this->previewAttempts($ip);
            if (($attempts['used'] ?? 0) > 2) {
                $db->prepare("UPDATE wwi_prompt_attempts SET attempts = 2 WHERE site_id = @site_id AND ip_hash = :h AND day = CURDATE()")->execute(['h' => $hash]);
                return ['ok' => false, 'limit_reached' => true, 'message' => 'Ya usaste tus 2 intentos de hoy. Explora el catálogo de plantillas o vuelve mañana.'];
            }
        } else {
            $attempts = ['unlimited' => true, 'limit' => null];
        }
        $uuid = bin2hex(random_bytes(16));
        $db->prepare("INSERT INTO wwi_previews (site_id, uuid, ip_hash, prompt, status) VALUES (@site_id, :u, :h, :p, 'generating')")
            ->execute(['u' => $uuid, 'h' => md5($ip), 'p' => $prompt]);
        $previewId = (int)$db->lastInsertId();
        $this->enqueueJob('generate_preview', ['preview_id' => $previewId]);
        return ['ok' => true, 'uuid' => $uuid, 'attempts' => $attempts];
    }

    public function previewStatus(string $uuid): array
    {
        $stmt = Database::instance()->prepare("SELECT uuid, status, structure, prompt, created_at FROM wwi_previews WHERE uuid = :u AND site_id = @site_id LIMIT 1");
        $stmt->execute(['u' => $uuid]);
        $row = $stmt->fetch();
        if (!$row) return ['ok' => false, 'message' => 'Preview not found'];
        return ['ok' => true, 'data' => [
            'uuid' => $row['uuid'],
            'status' => $row['status'],
            'prompt' => $row['prompt'],
            'structure' => json_decode((string)$row['structure'], true) ?: null,
            'created_at' => $row['created_at'],
        ]];
    }

    public function previewByUuid(string $uuid): ?array
    {
        $stmt = Database::instance()->prepare("SELECT * FROM wwi_previews WHERE uuid = :u AND site_id = @site_id LIMIT 1");
        $stmt->execute(['u' => $uuid]);
        $row = $stmt->fetch();
        if (!$row) return null;
        $row['structure'] = json_decode((string)$row['structure'], true) ?: null;
        return $row;
    }

    public function generatePreview(int $previewId): array
    {
        $db = Database::instance();
        $stmt = $db->prepare("SELECT * FROM wwi_previews WHERE id = :id AND site_id = @site_id");
        $stmt->execute(['id' => $previewId]);
        $preview = $stmt->fetch();
        if (!$preview) throw new \RuntimeException('Preview not found');

        $prompt = "Eres TIA, generadora de sitios web de Wontia. Sigue la ESTRUCTURA FIJA DE PLANTILLA BÁSICA de Wontia (nivel 1) para generar el sitio del cliente.\n"
            . "Estructura obligatoria: Header(nav 4-6 items) · Hero(eyebrow,H1,subtitulo,CTA primario 'Contáctanos' + CTA secundario WhatsApp) · Propuesta de valor(3 bullets) · Servicios(3-6 cards) · Sobre nosotros · Beneficios(4 items) · Testimonios(2-3, marcados como ejemplo) · CTA final · Contacto(placeholders) · Footer.\n"
            . "Responde SOLO con JSON válido, sin markdown, con este contrato:\n"
            . "{\"business_name\":\"...\",\"tagline\":\"...\",\"nav\":[\"Inicio\",\"Servicios\",\"Sobre mi\",\"Contacto\"],"
            . "\"colors\":{\"primary\":\"#hex\",\"secondary\":\"#hex\"},"
            . "\"hero\":{\"eyebrow\":\"...\",\"title\":\"...\",\"subtitle\":\"...\"},"
            . "\"value_prop\":[\"...\",\"...\",\"...\"],"
            . "\"services\":[{\"title\":\"...\",\"desc\":\"...\"}],"
            . "\"about\":\"...\",\"benefits\":[{\"title\":\"...\",\"desc\":\"...\"}],"
            . "\"testimonials\":[{\"quote\":\"...\",\"author\":\"Cliente (ejemplo)\"}],"
            . "\"cta\":{\"title\":\"...\",\"subtitle\":\"...\"},"
            . "\"contact\":{\"phone\":\"{{TELEFONO}}\",\"email\":\"{{EMAIL}}\",\"address\":\"{{DIRECCION}}\",\"whatsapp\":\"{{WHATSAPP}}\"},"
            . "\"footer_note\":\"...\"}\n"
            . "Reglas: colores profesionales por sector; copy de conversión en español; placeholders {{CAMPO}} cuando falten datos reales; NUNCA inventes certificaciones, clientes, precios ni años de experiencia.\n\n"
            . "Pedido del cliente:\n" . mb_substr((string)$preview['prompt'], 0, 2000);

        $router = new \App\Core\AiBrick\AiRouter();
        $response = $router->route([
            'system_id' => 'wontia',
            'module' => 'agent',
            'function' => 'preview',
            'system_prompt' => 'Respondes únicamente con JSON válido según el contrato indicado.',
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'max_tokens' => 2000,
            'temperature' => 0.6,
        ]);
        if (empty($response['ok']) || empty($response['content'])) {
            $db->prepare("UPDATE wwi_previews SET status = 'failed' WHERE id = :id")->execute(['id' => $previewId]);
            throw new \RuntimeException('TIA no pudo generar el preview');
        }
        $structure = json_decode((string)$response['content'], true);
        if (!is_array($structure)) {
            $clean = preg_replace('/^```(json)?\s*|\s*```$/m', '', (string)$response['content']);
            $structure = json_decode($clean, true);
        }
        if (!is_array($structure) || (empty($structure['services']) && empty($structure['sections']) && empty($structure['hero']))) {
            $db->prepare("UPDATE wwi_previews SET status = 'failed' WHERE id = :id")->execute(['id' => $previewId]);
            throw new \RuntimeException('Estructura inválida generada');
        }
        $db->prepare("UPDATE wwi_previews SET structure = :s, status = 'ready' WHERE id = :id")
            ->execute(['s' => json_encode($structure, JSON_UNESCAPED_UNICODE), 'id' => $previewId]);
        return ['ok' => true, 'preview_id' => $previewId];
    }

    public function cleanupPreviews(): int
    {
        return (int)Database::instance()->exec("DELETE FROM wwi_previews WHERE created_at < NOW() - INTERVAL 60 MINUTE");
    }

    public function suggestDomains(string $business): array
    {
        $business = trim($business);
        if ($business === '') return ['ok' => false, 'message' => 'Dime el nombre de tu negocio'];
        $router = new \App\Core\AiBrick\AiRouter();
        $response = $router->route([
            'system_id' => 'wontia',
            'module' => 'agent',
            'function' => 'domains',
            'system_prompt' => 'Respondes únicamente con JSON válido.',
            'messages' => [['role' => 'user', 'content' => "Sugiere 5 nombres de dominio .com cortos y memorables para el negocio: \"$business\". Responde SOLO JSON: {\"domains\":[\"nombre1.com\",\"nombre2.com\",...]}. Sin tildes ni espacios."]],
            'max_tokens' => 300,
            'temperature' => 0.7,
        ]);
        $data = json_decode((string)($response['content'] ?? ''), true);
        if (!is_array($data)) {
            $clean = preg_replace('/^```(json)?\s*|\s*```$/m', '', (string)($response['content'] ?? ''));
            $data = json_decode($clean, true);
        }
        $domains = is_array($data) && isset($data['domains']) ? $data['domains'] : [];
        return ['ok' => true, 'domains' => array_values(array_filter(array_map(function ($d) {
            $d = strtolower(trim((string)$d));
            return preg_match('/^(?!-)[a-z0-9-]{1,63}(?<!-)\.[a-z]{2,24}$/', $d) ? $d : null;
        }, $domains)))];
    }

    public function createTemplatePreview(string $slug): array
    {
        $db = Database::instance();
        $stmt = $db->prepare("SELECT t.*, c.name_es AS cat_es FROM wwi_templates t JOIN wwi_template_categories c ON c.id = t.category_id WHERE t.slug = :s AND t.site_id = @site_id LIMIT 1");
        $stmt->execute(['s' => $slug]);
        $tpl = $stmt->fetch();
        if (!$tpl) return ['ok' => false, 'message' => 'Plantilla no encontrada'];
        $preset = json_decode((string)$tpl['preset'], true) ?: [];
        $color = (string)($preset['color'] ?? '#2563eb');
        $structure = [
            'business_name' => (string)$tpl['name_es'],
            'tagline' => (string)$tpl['description_es'],
            'nav' => ['Inicio', 'Servicios', 'Sobre nosotros', 'Contacto'],
            'colors' => ['primary' => $color, 'secondary' => $this->shadeColor($color)],
            'hero' => ['eyebrow' => (string)$tpl['cat_es'], 'title' => (string)$tpl['name_es'], 'subtitle' => (string)$tpl['description_es']],
            'value_prop' => ['Diseño profesional listo para tu sector', 'Personalizable con TIA', 'Publicado en minutos'],
            'services' => [],
            'about' => 'Plantilla profesional para el sector ' . (string)$tpl['cat_es'] . '. TIA la adaptará a tu negocio: colores, textos, imágenes y secciones.',
            'benefits' => [],
            'testimonials' => [['quote' => 'Quedó perfecta en muy poco tiempo.', 'author' => 'Cliente (ejemplo)']],
            'cta' => ['title' => '¿Listo para lanzar tu sitio?', 'subtitle' => 'Elige tu plan y TIA hace el resto.'],
            'contact' => ['phone' => '{{TELEFONO}}', 'email' => '{{EMAIL}}', 'address' => '{{DIRECCION}}', 'whatsapp' => '{{WHATSAPP}}'],
            'footer_note' => (string)$tpl['name_es'] . ' · sitio generado por TIA — Wontia Web Intelligence',
        ];
        foreach (($preset['sections'] ?? []) as $sec) {
            if (!is_array($sec)) continue;
            $title = (string)($sec['title'] ?? ($sec['widget'] ?? 'Sección'));
            match ((string)($sec['widget'] ?? '')) {
                'features' => $structure['services'][] = ['title' => $title, 'desc' => 'Servicio profesional para tu negocio — personalizable con TIA.'],
                'trust' => $structure['testimonials'][] = ['quote' => 'Excelente servicio y atención.', 'author' => 'Cliente (ejemplo)'],
                'pricing' => $structure['benefits'][] = ['title' => $title, 'desc' => 'Planes claros y sin sorpresas.'],
                'cta' => $structure['cta'] = ['title' => $title, 'subtitle' => 'Contáctanos y empecemos hoy.'],
                'howitworks' => $structure['about'] .= ' Proceso claro: cuéntale a TIA, revisa y publica.',
                default => $structure['benefits'][] = ['title' => $title, 'desc' => 'Incluido en esta plantilla y personalizable.'],
            };
        }
        if (!$structure['services']) $structure['services'] = [['title' => 'Servicios', 'desc' => 'Tus servicios aparecerán aquí.']];
        if (!$structure['benefits']) {
            $structure['benefits'] = [['title' => 'Fácil de usar', 'desc' => 'Editor visual + TIA integrada.'], ['title' => 'SEO incluido', 'desc' => 'Google-ready desde el día uno.']];
        }
        $uuid = bin2hex(random_bytes(16));
        $db->prepare("INSERT INTO wwi_previews (site_id, uuid, ip_hash, prompt, structure, status) VALUES (@site_id, :u, 'tpl', :p, :st, 'ready')")
            ->execute(['u' => $uuid, 'p' => 'Plantilla: ' . $slug, 'st' => json_encode($structure, JSON_UNESCAPED_UNICODE)]);
        return ['ok' => true, 'uuid' => $uuid];
    }

    private function shadeColor(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) < 6) return '#1e3a8a';
        $r = max(0, hexdec(substr($hex, 0, 2)) - 40);
        $g = max(0, hexdec(substr($hex, 2, 2)) - 40);
        $b = max(0, hexdec(substr($hex, 4, 2)) - 40);
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    public function resetClientPassword(int $siteId): array
    {
        $db = Database::instance();
        $stmt = $db->prepare("SELECT id, username, email FROM users WHERE site_id = :sid AND role = 'client' ORDER BY id ASC LIMIT 1");
        $stmt->execute(['sid' => $siteId]);
        $user = $stmt->fetch();
        if (!$user) return ['ok' => false, 'message' => 'Este sitio no tiene usuario cliente aún'];
        $password = bin2hex(random_bytes(6));
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $db->prepare("UPDATE users SET password_hash = :h WHERE id = :id")->execute(['h' => $hash, 'id' => $user['id']]);
        return ['ok' => true, 'username' => $user['username'], 'email' => $user['email'], 'password' => $password];
    }

    public function sendWelcomeEmail(array $payload): array
    {
        $mail = new EmailService();
        $to = (string)($payload['to'] ?? '');
        if ($to === '') return ['ok' => false, 'message' => 'No recipient'];
        $sent = $mail->send(
            $to,
            '¡Tu sitio web está listo! 🚀 Wontia Web Intelligence',
            'Hola ' . (string)($payload['name'] ?? '') . ",\n\nTu sitio web está listo y publicado:\n"
            . (string)($payload['site_url'] ?? '') . "\n\nAccede a tu panel para administrarlo y pedir cambios a TIA.\n\n— Wontia Web Intelligence"
        );
        return ['ok' => true, 'message' => $sent ? 'Welcome email sent' : 'Mail not configured — skipped', 'sent' => $sent];
    }

    private function dispatchJob(string $type, array $payload): array
    {
        return match ($type) {
            'provision_site' => $this->provisionSite($payload),
            'process_brief' => $this->processBrief((int)($payload['brief_id'] ?? 0)),
            'generate_preview' => $this->generatePreview((int)($payload['preview_id'] ?? 0)),
            'send_email' => $this->sendWelcomeEmail($payload),
            default => ['ok' => true],
        };
    }

    public function provisionSite(array $payload): array
    {
        $db = Database::instance();
        $orderId = (int)($payload['order_id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM wwi_orders WHERE id = :id AND site_id = @site_id");
        $stmt->execute(['id' => $orderId]);
        $order = $stmt->fetch();
        if (!$order) throw new \RuntimeException('Order not found');
        if ((int)$order['tenant_id'] > 0) {
            return ['ok' => true, 'message' => 'Already provisioned'];
        }

        $plan = $this->plan((int)$order['plan_id']);
        $tenantName = trim((string)$order['customer_name']) ?: ('Cliente ' . substr($order['uuid'], 0, 6));
        $stmt = $db->prepare("INSERT INTO sites (name, domain, locale, theme, plan_id, status, uuid) VALUES (:name, :domain, :locale, 'default', :plan, 'GENERATING', :uuid)");
        $siteUuid = bin2hex(random_bytes(18));
        $stmt->execute([
            'name' => $tenantName,
            'domain' => (string)$order['domain_name'],
            'locale' => (string)$order['locale'] ?: 'es',
            'plan' => (int)$order['plan_id'] ?: null,
            'uuid' => $siteUuid,
        ]);
        $tenantId = (int)$db->lastInsertId();
        $db->prepare("UPDATE wwi_orders SET tenant_id = :tid WHERE id = :id")->execute(['tid' => $tenantId, 'id' => $orderId]);

        $username = preg_replace('/[^a-z0-9]/', '', strtolower(explode('@', (string)$order['customer_email'])[0])) ?: 'cliente' . $tenantId;
        $password = bin2hex(random_bytes(8));
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $db->prepare("INSERT INTO users (site_id, username, email, password_hash, role) VALUES (:sid, :u, :e, :h, 'client')")
            ->execute(['sid' => $tenantId, 'u' => $username . '_' . $tenantId, 'e' => (string)$order['customer_email'], 'h' => $hash]);

        if (!empty($order['domain_name'])) {
            $stmt = $db->prepare("INSERT IGNORE INTO wwi_domains (site_id, name, tld, status, provider, registration_cost, renewal_cost, currency)
                VALUES (:sid, :name, :tld, 'DNS_PENDING', '', :rc, :rn, 'USD')");
            $tld = substr($order['domain_name'], strrpos($order['domain_name'], '.') + 1);
            $costs = $this->domainCosts();
            $cost = $costs[$tld] ?? ['reg' => 10.97, 'ren' => 10.97];
            $stmt->execute(['sid' => $tenantId, 'name' => (string)$order['domain_name'], 'tld' => $tld, 'rc' => (float)$cost['reg'], 'rn' => (float)$cost['ren']]);
            $domainId = (int)$db->lastInsertId();
            foreach (['contacto', 'info', 'ventas'] as $box) {
                $db->prepare("INSERT IGNORE INTO wwi_email_accounts (site_id, domain_id, mailbox, status, provider) VALUES (:sid, :did, :box, 'REQUESTED', '')")
                    ->execute(['sid' => $tenantId, 'did' => $domainId, 'box' => $box]);
            }
        }

        $db->prepare("INSERT INTO pages (site_id, title, slug, template, meta_title, meta_description, status, sort_order) VALUES (:sid, :t, 'home', 'default', :mt, :md, 'published', 0)")
            ->execute(['sid' => $tenantId, 't' => $tenantName, 'mt' => $tenantName, 'md' => 'Sitio generado automáticamente por WWI']);
        $pageId = (int)$db->lastInsertId();
        $sections = [
            ['widget_type' => 'hero', 'title' => 'Bienvenido', 'config' => '{"title":"' . $tenantName . '","subtitle":"Sitio generado por TIA — edita este contenido desde tu panel."}', 'sort' => 0],
            ['widget_type' => 'features', 'title' => 'Servicios', 'config' => '{}', 'sort' => 1],
            ['widget_type' => 'cta', 'title' => 'Contáctanos', 'config' => '{}', 'sort' => 2],
            ['widget_type' => 'footer', 'title' => 'Footer', 'config' => '{}', 'sort' => 3],
        ];
        foreach ($sections as $sec) {
            $db->prepare("INSERT INTO sections (page_id, type, widget_type, title, config, sort_order, is_active) VALUES (:pid, 'widget', :wt, :t, :cfg, :s, 1)")
                ->execute(['pid' => $pageId, 'wt' => $sec['widget_type'], 't' => $sec['title'], 'cfg' => $sec['config'], 's' => $sec['sort']]);
        }

        $db->prepare("UPDATE sites SET status = 'READY' WHERE id = :id")->execute(['id' => $tenantId]);
        $this->transitionOrder($orderId, 'READY');
        $this->addLedger(['site_id' => $tenantId, 'direction' => 'credit', 'amount' => (float)$order['total'], 'reason' => 'Saldo inicial del plan', 'ref' => 'provision:' . $order['uuid']]);
        $siteUrl = 'https://' . (!empty($order['domain_name']) ? $order['domain_name'] : ('cliente' . $tenantId . '.wontia.com'));
        $this->enqueueJob('send_email', ['to' => (string)$order['customer_email'], 'name' => $tenantName, 'site_url' => $siteUrl]);
        $this->requestDeploy(['id' => $tenantId, 'name' => $tenantName], $order);

        return ['ok' => true, 'tenant_id' => $tenantId, 'site_uuid' => $siteUuid];
    }

    public function planBySlug(string $slug): ?array
    {
        $stmt = Database::instance()->prepare("SELECT * FROM wwi_plans WHERE slug = :s AND site_id = @site_id LIMIT 1");
        $stmt->execute(['s' => $slug]);
        $row = $stmt->fetch();
        if (!$row) return null;
        $row['features'] = $this->safeJson($row['features'] ?? '');
        $row['limits'] = $this->safeJson($row['limits'] ?? '');
        $row['margin_cost_items'] = $this->safeJson($row['margin_cost_items'] ?? '');
        return $row;
    }

    public function domainCosts(): array
    {
        $cfg = $this->config();
        $decoded = json_decode((string)($cfg['wwi.domain_costs'] ?? ''), true);
        return is_array($decoded) ? $decoded : [];
    }

    public function myPortal(): array
    {
        $db = Database::instance();
        $siteId = (int)$db->query("SELECT @site_id AS sid")->fetchColumn();
        $site = $db->prepare("SELECT * FROM sites WHERE id = :sid");
        $site->execute(['sid' => $siteId]);
        $siteRow = $site->fetch() ?: null;
        $plan = null;
        if ($siteRow && $siteRow['plan_id']) $plan = $this->plan((int)$siteRow['plan_id']);
        $domains = $db->prepare("SELECT * FROM wwi_domains WHERE site_id = :sid ORDER BY id DESC");
        $domains->execute(['sid' => $siteId]);
        $emails = $db->prepare("SELECT e.*, d.name AS domain_name FROM wwi_email_accounts e LEFT JOIN wwi_domains d ON d.id = e.domain_id WHERE e.site_id = :sid ORDER BY e.id DESC");
        $emails->execute(['sid' => $siteId]);
        $orders = $db->prepare("SELECT * FROM wwi_orders WHERE tenant_id = :sid ORDER BY id DESC LIMIT 50");
        $orders->execute(['sid' => $siteId]);
        $monthStart = date('Y-m-01 00:00:00');
        $aiStmt = $db->prepare("SELECT COUNT(*) AS requests, COALESCE(SUM(total_tokens),0) AS tokens, COALESCE(SUM(estimated_cost),0) AS cost FROM ai_usage WHERE site_id = :sid AND created_at >= :from");
        $aiStmt->execute(['sid' => $siteId, 'from' => $monthStart]);
        return [
            'site' => $siteRow,
            'plan' => $plan,
            'domains' => $domains->fetchAll(),
            'emails' => $emails->fetchAll(),
            'orders' => $orders->fetchAll(),
            'balance' => $this->balanceFor($siteId),
            'ai_month' => $aiStmt->fetch(),
            'briefs' => $this->briefs(),
        ];
    }

    public function ensureTables(): array
    {
        $db = Database::instance();
        $ddl = [
            "CREATE TABLE IF NOT EXISTS wwi_plans (id INT AUTO_INCREMENT PRIMARY KEY, site_id INT NOT NULL DEFAULT 1, slug VARCHAR(50) NOT NULL, name_es VARCHAR(150) NOT NULL, name_en VARCHAR(150) NOT NULL, description_es VARCHAR(500), description_en VARCHAR(500), price_cop DECIMAL(12,2) NOT NULL DEFAULT 0, price_usd DECIMAL(12,2) NOT NULL DEFAULT 0, billing_type ENUM('one_time','monthly','annual') DEFAULT 'one_time', duration_months INT DEFAULT 0, features JSON, limits JSON, margin_cost_items JSON, min_margin_pct DECIMAL(6,2) DEFAULT 25.00, sort_order INT DEFAULT 0, is_active TINYINT DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY site_plan (site_id, slug)) ENGINE=InnoDB",
            "CREATE TABLE IF NOT EXISTS wwi_orders (id INT AUTO_INCREMENT PRIMARY KEY, site_id INT NOT NULL DEFAULT 1, uuid CHAR(36) NOT NULL, customer_name VARCHAR(255), customer_email VARCHAR(255), customer_phone VARCHAR(50), plan_id INT, domain_name VARCHAR(255), status ENUM('CREATED','PENDING_PAYMENT','PAID','PROVISIONING','READY','FAILED','CANCELLED','REFUNDED') DEFAULT 'CREATED', subtotal DECIMAL(12,2) DEFAULT 0, tax DECIMAL(12,2) DEFAULT 0, total DECIMAL(12,2) DEFAULT 0, currency VARCHAR(5) DEFAULT 'COP', locale VARCHAR(5) DEFAULT 'es', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY site_order_uuid (site_id, uuid), KEY idx_orders_status (site_id, status)) ENGINE=InnoDB",
            "CREATE TABLE IF NOT EXISTS wwi_payments (id INT AUTO_INCREMENT PRIMARY KEY, site_id INT NOT NULL DEFAULT 1, order_id INT, provider VARCHAR(30) NOT NULL, provider_ref VARCHAR(255), amount DECIMAL(12,2) DEFAULT 0, currency VARCHAR(5) DEFAULT 'COP', status ENUM('pending','approved','declined','refunded','failed') DEFAULT 'pending', signature_verified TINYINT DEFAULT 0, raw_webhook TEXT, verified_at DATETIME, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY site_provider_ref (site_id, provider, provider_ref), KEY idx_payments_order (site_id, order_id)) ENGINE=InnoDB",
            "CREATE TABLE IF NOT EXISTS wwi_subscriptions (id INT AUTO_INCREMENT PRIMARY KEY, site_id INT NOT NULL DEFAULT 1, order_id INT, plan_id INT, status ENUM('active','past_due','cancelled','expired') DEFAULT 'active', started_at DATETIME, ends_at DATETIME, renews_at DATETIME, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, KEY idx_subs_site (site_id, status)) ENGINE=InnoDB",
            "CREATE TABLE IF NOT EXISTS wwi_invoices (id INT AUTO_INCREMENT PRIMARY KEY, site_id INT NOT NULL DEFAULT 1, order_id INT, number VARCHAR(50), amount DECIMAL(12,2) DEFAULT 0, currency VARCHAR(5) DEFAULT 'COP', status ENUM('issued','paid','void','overdue') DEFAULT 'issued', issued_at DATETIME, due_at DATETIME, paid_at DATETIME, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY site_invoice (site_id, number)) ENGINE=InnoDB",
            "CREATE TABLE IF NOT EXISTS wwi_jobs (id INT AUTO_INCREMENT PRIMARY KEY, site_id INT NOT NULL DEFAULT 1, type VARCHAR(50) NOT NULL, status ENUM('queued','running','done','failed','retrying') DEFAULT 'queued', attempts INT DEFAULT 0, max_attempts INT DEFAULT 3, payload JSON, error VARCHAR(1000), scheduled_at DATETIME, next_retry_at DATETIME, locked_at DATETIME, completed_at DATETIME, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY idx_jobs_due (status, scheduled_at), KEY idx_jobs_site (site_id, type)) ENGINE=InnoDB",
            "CREATE TABLE IF NOT EXISTS wwi_provisioning_jobs (id INT AUTO_INCREMENT PRIMARY KEY, site_id INT NOT NULL DEFAULT 1, order_id INT, job_id INT, step VARCHAR(50) NOT NULL, status ENUM('queued','running','done','failed') DEFAULT 'queued', payload JSON, error VARCHAR(1000), started_at DATETIME, completed_at DATETIME, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY idx_prov_site (site_id, status)) ENGINE=InnoDB",
            "CREATE TABLE IF NOT EXISTS wwi_domains (id INT AUTO_INCREMENT PRIMARY KEY, site_id INT NOT NULL DEFAULT 1, name VARCHAR(255) NOT NULL, tld VARCHAR(20), status ENUM('SEARCH','AVAILABLE','REGISTERING','REGISTERED','DNS_PENDING','ACTIVE','EXPIRING','EXPIRED') DEFAULT 'AVAILABLE', provider VARCHAR(50), provider_ref VARCHAR(255), registration_cost DECIMAL(12,2) DEFAULT 0, renewal_cost DECIMAL(12,2) DEFAULT 0, currency VARCHAR(5) DEFAULT 'USD', registered_at DATETIME, expires_at DATETIME, dns_config JSON, ssl_status ENUM('none','pending','active','error') DEFAULT 'none', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY site_domain (site_id, name)) ENGINE=InnoDB",
            "CREATE TABLE IF NOT EXISTS wwi_email_accounts (id INT AUTO_INCREMENT PRIMARY KEY, site_id INT NOT NULL DEFAULT 1, domain_id INT, mailbox VARCHAR(150) NOT NULL, display_name VARCHAR(255), status ENUM('REQUESTED','PROVISIONING','ACTIVE','SUSPENDED','DELETED') DEFAULT 'REQUESTED', provider VARCHAR(50), provider_ref VARCHAR(255), password_hash VARCHAR(255), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY site_mailbox (site_id, mailbox)) ENGINE=InnoDB",
            "CREATE TABLE IF NOT EXISTS wwi_template_categories (id INT AUTO_INCREMENT PRIMARY KEY, site_id INT NOT NULL DEFAULT 1, slug VARCHAR(50) NOT NULL, name_es VARCHAR(150) NOT NULL, name_en VARCHAR(150) NOT NULL, icon VARCHAR(10) DEFAULT 'W', sort_order INT DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY site_tplcat (site_id, slug)) ENGINE=InnoDB",
            "CREATE TABLE IF NOT EXISTS wwi_templates (id INT AUTO_INCREMENT PRIMARY KEY, site_id INT NOT NULL DEFAULT 1, category_id INT, slug VARCHAR(80) NOT NULL, name_es VARCHAR(150) NOT NULL, name_en VARCHAR(150) NOT NULL, description_es VARCHAR(500), description_en VARCHAR(500), preview_url VARCHAR(500), preset JSON, status ENUM('active','beta','coming_soon','deprecated') DEFAULT 'active', sort_order INT DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY site_template (site_id, slug)) ENGINE=InnoDB",
            "CREATE TABLE IF NOT EXISTS wwi_ai_actions (id INT AUTO_INCREMENT PRIMARY KEY, site_id INT NOT NULL DEFAULT 1, user_id INT, session_id VARCHAR(64), command TEXT, action VARCHAR(100), target_entity VARCHAR(50), target_id INT, payload JSON, result JSON, model VARCHAR(150), provider VARCHAR(100), cost DECIMAL(12,6) DEFAULT 0, tokens INT DEFAULT 0, status ENUM('executed','preview','cancelled','failed') DEFAULT 'executed', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY idx_aia_site (site_id, created_at), KEY idx_aia_session (session_id)) ENGINE=InnoDB",
            "CREATE TABLE IF NOT EXISTS wwi_audit_logs (id INT AUTO_INCREMENT PRIMARY KEY, site_id INT NOT NULL DEFAULT 1, user_id INT, entity VARCHAR(50), entity_id INT, action VARCHAR(100), before_json JSON, after_json JSON, ip VARCHAR(64), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY idx_audit_site (site_id, created_at)) ENGINE=InnoDB",
            "CREATE TABLE IF NOT EXISTS wwi_site_versions (id INT AUTO_INCREMENT PRIMARY KEY, site_id INT NOT NULL DEFAULT 1, label VARCHAR(100), snapshot JSON, created_by INT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY idx_versions_site (site_id, id)) ENGINE=InnoDB",
        ];
        foreach ($ddl as $sql) $db->exec($sql);

        $db->exec("ALTER TABLE sites ADD COLUMN IF NOT EXISTS plan_id INT NULL");
        $db->exec("ALTER TABLE sites ADD COLUMN IF NOT EXISTS status VARCHAR(30) DEFAULT 'DRAFT'");
        $db->exec("ALTER TABLE sites ADD COLUMN IF NOT EXISTS uuid CHAR(36) NULL");
        $db->exec("ALTER TABLE sites ADD COLUMN IF NOT EXISTS owner_user_id INT NULL");
        $db->exec("ALTER TABLE users MODIFY role ENUM('superadmin','admin','editor','client') DEFAULT 'admin'");
        $db->exec("ALTER TABLE wwi_orders ADD COLUMN IF NOT EXISTS tenant_id INT NULL");
        $db->exec("CREATE TABLE IF NOT EXISTS wwi_balance_ledger (id INT AUTO_INCREMENT PRIMARY KEY, site_id INT NOT NULL DEFAULT 1, direction ENUM('credit','debit') NOT NULL, amount DECIMAL(12,2) NOT NULL DEFAULT 0, reason VARCHAR(200), ref VARCHAR(100), created_by INT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY idx_ledger_site (site_id, created_at)) ENGINE=InnoDB");
        $db->exec("CREATE TABLE IF NOT EXISTS wwi_briefs (id INT AUTO_INCREMENT PRIMARY KEY, site_id INT NOT NULL DEFAULT 1, order_id INT NULL, customer_email VARCHAR(255), business_name VARCHAR(255), story TEXT, documents JSON, profile JSON, status ENUM('new','processing','ready','failed') DEFAULT 'new', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, KEY idx_briefs_site (site_id, status)) ENGINE=InnoDB");
        $db->exec("CREATE TABLE IF NOT EXISTS wwi_previews (id INT AUTO_INCREMENT PRIMARY KEY, site_id INT NOT NULL DEFAULT 1, uuid CHAR(32) NOT NULL, ip_hash VARCHAR(64), prompt TEXT, structure JSON, status ENUM('generating','ready','failed') DEFAULT 'generating', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY site_preview (site_id, uuid), KEY idx_previews_exp (created_at)) ENGINE=InnoDB");
        $db->exec("CREATE TABLE IF NOT EXISTS wwi_prompt_attempts (id INT AUTO_INCREMENT PRIMARY KEY, site_id INT NOT NULL DEFAULT 1, ip_hash VARCHAR(64) NOT NULL, day DATE NOT NULL, attempts INT DEFAULT 0, UNIQUE KEY site_ip_day (site_id, ip_hash, day)) ENGINE=InnoDB");
        $db->exec("ALTER TABLE wwi_orders ADD COLUMN IF NOT EXISTS addons JSON NULL");

        $seeded = false;
        $planCount = (int)$db->query("SELECT COUNT(*) FROM wwi_plans WHERE site_id = @site_id")->fetchColumn();
        if ($planCount === 0) {
            $db->exec("INSERT IGNORE INTO wwi_plans (site_id, slug, name_es, name_en, description_es, description_en, price_cop, price_usd, billing_type, duration_months, features, limits, margin_cost_items, min_margin_pct, sort_order) VALUES
                (@site_id, 'web-starter', 'Web Starter', 'Web Starter', 'Sitio web informativo con dominio, hosting, SSL, 3 correos, SEO basico y TIA integrada.', 'Informational website with domain, hosting, SSL, 3 mailboxes, basic SEO and integrated TIA.', 299000, 75, 'one_time', 12, '[\"domain_1y\",\"hosting\",\"ssl\",\"email_3\",\"responsive\",\"seo_basic\",\"social_links\",\"whatsapp\",\"contact_form\",\"google_maps\",\"analytics\",\"favicon\",\"sitemap\",\"robots\",\"404\",\"editor\",\"tia_agent\",\"backup\"]', '{\"sites\":1,\"mailboxes\":3,\"storage_mb\":1024,\"ai_monthly_usd\":5,\"products\":0}', '[{\"item\":\"domain\",\"usd\":10.97},{\"item\":\"email\",\"usd\":12},{\"item\":\"hosting\",\"usd\":24},{\"item\":\"ai\",\"usd\":5},{\"item\":\"payment_fee\",\"pct\":3.5},{\"item\":\"support\",\"usd\":10}]', 25, 1),
                (@site_id, 'web-business', 'Web Business', 'Web Business', 'Todo Web Starter + secciones avanzadas, blog y SEO optimizado por TIA.', 'Everything in Web Starter + advanced sections, blog and TIA-optimized SEO.', 449000, 115, 'one_time', 12, '[\"web_starter_all\",\"blog\",\"seo_advanced\",\"analytics_bi\",\"extra_sections\",\"ai_seo_agent\"]', '{\"sites\":1,\"mailboxes\":5,\"storage_mb\":5120,\"ai_monthly_usd\":15,\"products\":0}', '[{\"item\":\"domain\",\"usd\":10.97},{\"item\":\"email\",\"usd\":18},{\"item\":\"hosting\",\"usd\":24},{\"item\":\"ai\",\"usd\":15},{\"item\":\"payment_fee\",\"pct\":3.5},{\"item\":\"support\",\"usd\":15}]', 25, 2),
                (@site_id, 'web-catalog', 'Web Catalog', 'Web Catalog', 'Catalogo de productos con hasta 15 productos, carga asistida y optimizacion de imagenes.', 'Product catalog with up to 15 products, assisted loading and image optimization.', 599000, 150, 'one_time', 12, '[\"web_business_all\",\"catalog\",\"product_pages\",\"image_optimization\",\"catalog_import\"]', '{\"sites\":1,\"mailboxes\":5,\"storage_mb\":10240,\"ai_monthly_usd\":25,\"products\":15}', '[{\"item\":\"domain\",\"usd\":10.97},{\"item\":\"email\",\"usd\":18},{\"item\":\"hosting\",\"usd\":30},{\"item\":\"ai\",\"usd\":25},{\"item\":\"payment_fee\",\"pct\":3.5},{\"item\":\"support\",\"usd\":20}]', 25, 3),
                (@site_id, 'web-catalog-pro', 'Web Catalog Pro', 'Web Catalog Pro', 'Catalogo ampliado. Precio variable segun productos y almacenamiento.', 'Expanded catalog. Variable price based on products and storage.', 0, 0, 'one_time', 12, '[\"web_catalog_all\",\"variable_pricing\",\"bulk_import\"]', '{\"sites\":1,\"mailboxes\":10,\"storage_mb\":51200,\"ai_monthly_usd\":50,\"products\":250}', '[{\"item\":\"domain\",\"usd\":10.97},{\"item\":\"email\",\"usd\":30},{\"item\":\"hosting\",\"usd\":60},{\"item\":\"ai\",\"usd\":50},{\"item\":\"payment_fee\",\"pct\":3.5},{\"item\":\"support\",\"usd\":30}]', 30, 4),
                (@site_id, 'web-master', 'Web Master', 'Web Master', 'Suscripcion mensual: mantenimiento, mejoras continuas y soporte TIA prioritario.', 'Monthly subscription: maintenance, continuous improvements and priority TIA support.', 149000, 38, 'monthly', 0, '[\"everything\",\"monthly_improvements\",\"priority_tia\",\"dedicated_support\"]', '{\"sites\":1,\"mailboxes\":25,\"storage_mb\":102400,\"ai_monthly_usd\":100,\"products\":0}', '[{\"item\":\"email\",\"usd\":30},{\"item\":\"hosting\",\"usd\":40},{\"item\":\"ai\",\"usd\":100},{\"item\":\"payment_fee\",\"pct\":3.5},{\"item\":\"support\",\"usd\":40}]', 30, 5)");
            $seeded = true;
        }

        $catCount = (int)$db->query("SELECT COUNT(*) FROM wwi_template_categories WHERE site_id = @site_id")->fetchColumn();
        if ($catCount === 0) {
            $db->exec("INSERT IGNORE INTO wwi_template_categories (site_id, slug, name_es, name_en, sort_order) VALUES
                (@site_id, 'restaurants', 'Restaurantes', 'Restaurants', 1),
                (@site_id, 'legal', 'Abogados', 'Lawyers', 2),
                (@site_id, 'medical', 'Medicos y Odontologia', 'Medical & Dental', 3),
                (@site_id, 'real-estate', 'Inmobiliarias', 'Real Estate', 4),
                (@site_id, 'construction', 'Construccion y Arquitectura', 'Construction & Architecture', 5),
                (@site_id, 'tourism', 'Hoteles y Turismo', 'Hotels & Tourism', 6),
                (@site_id, 'transport', 'Transporte y Logistica', 'Transport & Logistics', 7),
                (@site_id, 'beauty', 'Belleza y Barberia', 'Beauty & Barbershops', 8),
                (@site_id, 'fitness', 'Gimnasios', 'Fitness', 9),
                (@site_id, 'education', 'Educacion', 'Education', 10),
                (@site_id, 'consulting', 'Consultores y Coaches', 'Consultants & Coaches', 11),
                (@site_id, 'agencies', 'Agencias y BPO', 'Agencies & BPO', 12),
                (@site_id, 'tech', 'Tecnologia y Software', 'Tech & Software', 13),
                (@site_id, 'retail', 'Comercio y Tiendas', 'Retail & Stores', 14),
                (@site_id, 'automotive', 'Talleres y Automotores', 'Workshops & Automotive', 15),
                (@site_id, 'fashion', 'Moda', 'Fashion', 16),
                (@site_id, 'agriculture', 'Agricultura y Ganaderia', 'Agriculture & Livestock', 17),
                (@site_id, 'pets', 'Veterinarias y Mascotas', 'Vets & Pets', 18),
                (@site_id, 'events', 'Eventos y Bodas', 'Events & Weddings', 19),
                (@site_id, 'cafes', 'Cafeterias y Panaderias', 'Cafes & Bakeries', 20)");
            $seeded = true;
        }

        $cfgStmt = $db->prepare("INSERT IGNORE INTO settings (site_id, `key`, `value`) VALUES (@site_id, :k, :v)");
        foreach ([
            'wwi.product_load_0_15_cop' => '99000',
            'wwi.product_load_16_30_cop' => '149000',
            'wwi.product_load_31_50_cop' => '249000',
            'wwi.domain_default_tld' => '.com',
            'wwi.domain_cost_usd' => '10.97',
            'wwi.domain_renewal_usd' => '10.97',
            'wwi.usd_cop_rate' => '4000',
            'wwi.margin_guard_enabled' => '1',
            'wwi.min_margin_pct' => '25',
            'wwi.site_promise_hours' => '24',
            'wwi.locales' => '["es","en"]',
            'wwi.domain_costs' => '{"com":{"reg":10.97,"ren":10.97},"net":{"reg":12.98,"ren":12.98},"org":{"reg":12.50,"ren":12.50},"co":{"reg":28.00,"ren":30.00},"com.co":{"reg":25.00,"ren":35.00},"site":{"reg":2.50,"ren":30.00},"info":{"reg":15.00,"ren":20.00}}',
        ] as $key => $value) {
            $cfgStmt->execute(['k' => $key, 'v' => $value]);
        }

        return ['ok' => true, 'message' => $seeded ? 'Factory tables created and seeded' : 'Factory tables already exist', 'data' => ['seeded' => $seeded]];
    }
}
