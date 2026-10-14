<?php
namespace App\Services;

use App\Core\Database;

/**
 * OrderCenterService — Central de pedidos (operación/logística de SONIKA).
 *
 * Fase 1: pantalla inicial de solo lectura sobre el pedido comercial existente
 * (tabla `store_orders`, NO se duplica) + estructura base `oc_*` para las fases
 * siguientes (tareas, eventos, transferencias, evidencias, incidencias y SLA).
 *
 * Aislamiento: toda consulta filtra `site_id = @site_id`. No modifica el checkout,
 * el catálogo, los temas ni la autenticación.
 */
class OrderCenterService
{
    public const ROLES = ['superadmin', 'admin', 'editor'];

    private static bool $tablesEnsured = false;

    public function roles(): array { return self::ROLES; }

    public function enabled(): bool
    {
        try {
            $st = Database::instance()->prepare("SELECT `value` FROM settings WHERE site_id = @site_id AND `key` = 'order_center_enabled' LIMIT 1");
            $st->execute();
            $v = $st->fetchColumn();
            if ($v === false || $v === null || $v === '') return true;
            return (string)$v !== '0';
        } catch (\Throwable $e) {
            return true;
        }
    }

    public function ensureTables(): array
    {
        if (self::$tablesEnsured) return ['ok' => true, 'cached' => true];
        $file = dirname(__DIR__, 2) . '/install/order_center.sql';
        $result = ['ok' => false, 'statements' => 0];
        if (is_file($file)) {
            $sql = (string)file_get_contents($file);
            $sql = preg_replace('/^\s*--.*$/m', '', $sql);
            $db = Database::instance();
            $n = 0;
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
                if ($stmt === '') continue;
                try { $db->exec($stmt); $n++; } catch (\Throwable $e) { }
            }
            $result = ['ok' => true, 'statements' => $n];
        }
        self::$tablesEnsured = true;
        return $result;
    }

    public function overview(): array
    {
        $o = [];
        try {
            $st = Database::instance()->query("SELECT COUNT(*) AS total,
                SUM(fulfillment_status NOT IN ('delivered','cancelled')) AS active,
                SUM(fulfillment_status IN ('new','confirmed')) AS pending,
                SUM(fulfillment_status = 'shipped') AS shipped,
                SUM(fulfillment_status = 'delivered') AS delivered,
                SUM(payment_status = 'paid') AS paid,
                SUM(payment_status = 'pending') AS unpaid,
                COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total_cents ELSE 0 END), 0) AS revenue_cents
                FROM store_orders WHERE site_id = @site_id");
            foreach (($st->fetch() ?: []) as $k => $v) $o[$k] = (int)$v;
        } catch (\Throwable $e) {
        }
        $o += ['total' => 0, 'active' => 0, 'pending' => 0, 'shipped' => 0, 'delivered' => 0, 'paid' => 0, 'unpaid' => 0, 'revenue_cents' => 0];
        $o['cases_total'] = $this->scalar("SELECT COUNT(*) FROM oc_cases WHERE site_id = @site_id");
        $o['tasks_open'] = $this->scalar("SELECT COUNT(*) FROM oc_tasks WHERE site_id = @site_id AND status IN ('open','in_progress','waiting')");
        $o['incidents_open'] = $this->scalar("SELECT COUNT(*) FROM oc_incidents WHERE site_id = @site_id AND status <> 'resolved'");
        $o['recent'] = $this->orders(['limit' => 8]);
        return $o;
    }

    public function orders(array $f = []): array
    {
        [$sql, $params] = $this->listSql($f, false);
        $stmt = Database::instance()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function ordersCount(array $f = []): int
    {
        [$sql, $params] = $this->listSql($f, true);
        $stmt = Database::instance()->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function orderDetail(int $id): ?array
    {
        $db = Database::instance();
        $st = $db->prepare("SELECT * FROM store_orders WHERE id = :id AND site_id = @site_id LIMIT 1");
        $st->execute(['id' => $id]);
        $o = $st->fetch();
        if (!$o) return null;
        $it = $db->prepare("SELECT name, variant_name, price_cents, qty, subtotal_cents, image FROM store_order_items WHERE order_id = :oid AND site_id = @site_id");
        $it->execute(['oid' => $id]);
        $o['items'] = $it->fetchAll();
        $o['payment_status'] = (string)$o['payment_status'];
        $o['fulfillment_status'] = (string)$o['fulfillment_status'];
        return $o;
    }

    public function fulfillmentStatuses(): array
    {
        return ['new', 'confirmed', 'preparing', 'shipped', 'delivered', 'cancelled'];
    }

    private function listSql(array $f, bool $count): array
    {
        $sql = $count
            ? "SELECT COUNT(*) FROM store_orders WHERE site_id = @site_id"
            : "SELECT id, uuid, order_number, customer_name, customer_email, customer_phone, total_cents, currency,
                payment_method, payment_status, fulfillment_status, created_at
                FROM store_orders WHERE site_id = @site_id";
        $params = [];
        if (!empty($f['status'])) {
            $st = (string)$f['status'];
            if (in_array($st, $this->fulfillmentStatuses(), true)) { $sql .= " AND fulfillment_status = :fs"; $params['fs'] = $st; }
        }
        if (!empty($f['search'])) {
            $sql .= " AND (customer_name LIKE :q1 OR customer_email LIKE :q2 OR order_number LIKE :q3 OR uuid LIKE :q4)";
            $q = '%' . (string)$f['search'] . '%';
            $params['q1'] = $q; $params['q2'] = $q; $params['q3'] = $q; $params['q4'] = $q;
        }
        if (!$count) {
            $sql .= " ORDER BY id DESC LIMIT " . min(max((int)($f['limit'] ?? 50), 1), 200) . " OFFSET " . max((int)($f['offset'] ?? 0), 0);
        }
        return [$sql, $params];
    }

    private function scalar(string $sql): int
    {
        try {
            $st = Database::instance()->prepare($sql);
            $st->execute();
            return (int)$st->fetchColumn();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
