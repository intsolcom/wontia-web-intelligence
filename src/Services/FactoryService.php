<?php
namespace App\Services;

use App\Core\Database;

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
