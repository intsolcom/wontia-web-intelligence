<?php
namespace App\Services;

use App\Core\Database;

/**
 * OrderOperationsService — ficha operativa 360 del pedido.
 *
 * Une el pedido comercial (store_orders) con la operación logística (oc_*):
 * checklist de despacho, confirmación del cliente (Wontia AIP), reporte a
 * proveedor/Miche, notificaciones, envíos y solicitud de reseña.
 *
 * No duplica el pedido ni modifica el checkout. Multi-tenant por @site_id.
 */
class OrderOperationsService
{
    public const CONFIRMATION_PHRASES = [
        'si', 'sí', 'correcto', 'asi es', 'así es', 'perfecto', 'confirmo',
        'confirmado', 'de acuerdo', 'esta bien', 'está bien', 'todo bien',
        'claro', 'exacto', 'si senor', 'sí señor', 'si, correcto', 'sí, correcto',
    ];

    public const REJECTION_PHRASES = [
        'no', 'no confirmo', 'no es correcto', 'incorrecto', 'no hice nada',
        'rechazo', 'no acepto', 'no estoy de acuerdo', 'esta mal', 'está mal',
        'no soy yo', 'no reconozco', 'cancela', 'cancelar', 'no, gracias',
        'para nada', 'no senor', 'no señor',
    ];

    private static bool $ensured = false;

    public function ensureTables(): array
    {
        if (self::$ensured) return ['ok' => true, 'cached' => true];
        (new OrderCenterService())->ensureTables();
        $file = defined('ROOT_DIR') ? ROOT_DIR . '/install/order_operations.sql' : dirname(__DIR__, 2) . '/install/order_operations.sql';
        $n = 0;
        if (is_file($file)) {
            $sql = preg_replace('/^\s*--.*$/m', '', (string)file_get_contents($file));
            $db = Database::instance();
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
                if ($stmt === '') continue;
                try { $db->exec($stmt); $n++; } catch (\Throwable $e) {}
            }
        }
        self::$ensured = true;
        return ['ok' => true, 'statements' => $n];
    }

    public function confirmationPhrases(): array { return self::CONFIRMATION_PHRASES; }
    public function rejectionPhrases(): array { return self::REJECTION_PHRASES; }

    /** Ficha 360: pedido + cliente + productos + pago + envío + operación. */
    public function detail(int $orderId): ?array
    {
        $this->ensureTables();
        $order = (new StoreService())->orderById($orderId);
        if (!$order) return null;

        $order['items'] = $this->enrichItems((array)($order['items'] ?? []));
        $order['order_image'] = '';
        foreach ($order['items'] as $it) {
            if (trim((string)($it['image'] ?? '')) !== '') { $order['order_image'] = (string)$it['image']; break; }
        }
        $addr = is_array($order['shipping_address'] ?? null) ? $order['shipping_address'] : [];
        $order['customer_detail'] = [
            'id' => (int)($order['customer_id'] ?? 0),
            'name' => (string)($order['customer_name'] ?? ''),
            'email' => (string)($order['customer_email'] ?? ''),
            'phone' => (string)($order['customer_phone'] ?? ''),
            'document' => (string)($order['customer_document'] ?? ''),
        ];
        $order['shipping_detail'] = [
            'address' => $addr,
            'line1' => (string)($addr['line1'] ?? ''),
            'city' => (string)($addr['city'] ?? $addr['municipality'] ?? ''),
            'department' => (string)($addr['department'] ?? $addr['region'] ?? ''),
            'municipality' => (string)($addr['municipality'] ?? ''),
            'department_code' => (string)($order['shipping_department_code'] ?? ($addr['department_code'] ?? '')),
            'municipality_code' => (string)($order['shipping_municipality_code'] ?? ($addr['municipality_code'] ?? '')),
            'notes' => (string)($addr['notes'] ?? $order['notes'] ?? ''),
            'carrier' => (string)($order['shipping_carrier'] ?? ''),
            'eta_min_days' => (int)($order['shipping_eta_min_days'] ?? 0),
            'eta_max_days' => (int)($order['shipping_eta_max_days'] ?? 0),
        ];
        $order['payment_detail'] = $this->paymentDetail($order);
        $order['affiliate'] = null;
        try {
            $db = Database::instance();
            $st = $db->prepare('SELECT a.affiliate_id, a.code, a.model, a.fixed_at,
                    p.full_name, p.public_name, p.artist_name, p.email
                FROM affiliate_order_attributions a
                LEFT JOIN affiliate_profiles p ON p.id = a.affiliate_id AND p.site_id = a.site_id
                WHERE a.site_id = @site_id AND a.order_ref = ? LIMIT 1');
            $st->execute([(string)($order['uuid'] ?? '')]);
            $aff = $st->fetch(\PDO::FETCH_ASSOC);
            if ($aff) {
                $aff['name'] = trim((string)($aff['full_name'] ?? ''));
                if ($aff['name'] === '') $aff['name'] = trim((string)($aff['public_name'] ?? ''));
                if ($aff['name'] === '') $aff['name'] = trim((string)($aff['artist_name'] ?? ''));
                $cs = $db->prepare('SELECT amount, status, tier_rate FROM affiliate_commissions WHERE site_id = @site_id AND order_ref = ? LIMIT 1');
                $cs->execute([(string)($order['uuid'] ?? '')]);
                $aff['commission'] = $cs->fetch(\PDO::FETCH_ASSOC) ?: null;
                $order['affiliate'] = $aff;
            }
        } catch (\Throwable $e) {
            $order['affiliate'] = null;
        }
        $checklist = $this->checklist($orderId);
        $provider = $this->providerForOrder($orderId);
        $notifications = $this->notifications($orderId);
        $confirmations = $this->confirmations($orderId);
        $shipment = $this->shipment($orderId);
        $review = $this->reviewRequest($orderId);
        $timeline = $this->timeline($orderId, $provider, $notifications, $confirmations, $shipment, $review, $order);
        $order['operations'] = [
            'checklist' => $checklist,
            'provider' => $provider,
            'notifications' => $notifications,
            'confirmations' => $confirmations,
            'shipment' => $shipment,
            'review' => $review,
            'timeline' => $timeline,
        ];
        return $order;
    }

    public function checklist(int $orderId): array
    {
        $this->ensureTables();
        $db = Database::instance();
        try {
            $db->prepare('INSERT INTO oc_order_checklists (site_id, order_id) VALUES (@site_id, ?) ON DUPLICATE KEY UPDATE order_id = VALUES(order_id)')->execute([$orderId]);
            $st = $db->prepare('SELECT * FROM oc_order_checklists WHERE site_id = @site_id AND order_id = ? LIMIT 1');
            $st->execute([$orderId]);
            $row = $st->fetch() ?: [];
        } catch (\Throwable $e) {
            $row = [];
        }
        $row = array_merge([
            'payment_settled' => 0, 'customer_confirmed' => 0, 'reconfirmed' => 0,
            'provider_reported' => 0, 'confirmation_phrase' => '', 'confirmation_call_id' => '',
            'payment_settlement_ref' => '', 'confirmation_confidence' => null,
        ], $row);
        $missing = [];
        if (empty($row['payment_settled'])) $missing[] = 'Dinero confirmado en cuenta bancaria';
        if (empty($row['customer_confirmed'])) $missing[] = 'Cliente confirmó la información por llamada';
        if (empty($row['reconfirmed'])) $missing[] = 'Reconfirmación del sistema/AIP';
        $row['missing'] = $missing;
        $row['can_report'] = empty($missing) && empty($row['provider_reported']);
        $row['progress'] = (int)!empty($row['payment_settled']) + (int)!empty($row['customer_confirmed']) + (int)!empty($row['reconfirmed']);
        $row['phrases'] = ['confirmation' => self::CONFIRMATION_PHRASES, 'rejection' => self::REJECTION_PHRASES];
        return $row;
    }

    public function setPaymentSettled(int $orderId, bool $settled, string $reference, int $userId): array
    {
        $this->ensureTables();
        $db = Database::instance();
        $this->checklist($orderId);
        $db->prepare('UPDATE oc_order_checklists SET payment_settled = ?, payment_settled_at = ?, payment_settled_by = ?, payment_settlement_ref = ? WHERE site_id = @site_id AND order_id = ?')
            ->execute([$settled ? 1 : 0, $settled ? date('Y-m-d H:i:s') : null, $userId ?: null, mb_substr(trim($reference), 0, 190), $orderId]);
        try { (new StoreService())->logOrderEvent($orderId, 'payment_settled', '', $settled ? '1' : '0', mb_substr(trim($reference), 0, 190), $userId ?: null); } catch (\Throwable $e) {}
        return ['ok' => true, 'checklist' => $this->checklist($orderId)];
    }

    /**
     * Webhook de Wontia AIP. Firma HMAC SHA-256 en X-Wontia-Signature.
     * Payload esperado:
     * {order_uuid|order_id, call_id, status, confirmed, confirmation_phrase,
     *  confidence, transcript, recording_url, reconfirmed}
     */
    public function ingestConfirmation(string $raw, string $signature): array
    {
        $this->ensureTables();
        $secret = $this->setting('aip_confirmation_secret');
        if ($secret === '') return ['ok' => false, 'status' => 503, 'message' => 'Webhook AIP sin secreto configurado.'];
        $expected = hash_hmac('sha256', $raw, $secret);
        if ($signature === '' || !hash_equals($expected, $signature)) {
            return ['ok' => false, 'status' => 401, 'message' => 'Firma AIP inválida.'];
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) return ['ok' => false, 'status' => 400, 'message' => 'Payload AIP inválido.'];
        $order = null;
        if (!empty($data['order_uuid'])) $order = (new StoreService())->orderByUuid((string)$data['order_uuid']);
        elseif (!empty($data['order_id'])) $order = (new StoreService())->orderById((int)$data['order_id']);
        if (!$order) return ['ok' => false, 'status' => 404, 'message' => 'Pedido no encontrado.'];
        $orderId = (int)$order['id'];
        $transcript = (string)($data['transcript'] ?? '');
        $phrase = trim((string)($data['confirmation_phrase'] ?? ''));
        $detected = $this->detectConfirmation($transcript, $phrase, !empty($data['confirmed']));
        $confirmed = $detected['confirmed'] && empty($detected['rejected']);
        $reconfirmed = !empty($data['reconfirmed']) || strtolower((string)($data['status'] ?? '')) === 'reconfirmed';
        $callId = mb_substr((string)($data['call_id'] ?? ''), 0, 100);
        $confidence = isset($data['confidence']) ? max(0, min(1, (float)$data['confidence'])) : null;
        $status = $confirmed ? ($reconfirmed ? 'reconfirmed' : 'confirmed') : (($detected['rejected'] || !empty($data['rejected'])) ? 'rejected' : (string)($data['status'] ?? 'pending'));
        $db = Database::instance();
        $db->prepare('INSERT INTO oc_confirmations (site_id, order_id, call_id, status, confirmed, confirmation_phrase, confidence, transcript, recording_url, payload_json, attempted_at, confirmed_at) VALUES (@site_id, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([
                $orderId, $callId ?: null, mb_substr($status, 0, 30), $confirmed ? 1 : 0,
                mb_substr($detected['phrase'] ?: $phrase, 0, 120), $confidence,
                $transcript !== '' ? $transcript : null, mb_substr((string)($data['recording_url'] ?? ''), 0, 500) ?: null,
                $raw, !empty($data['attempted_at']) ? date('Y-m-d H:i:s', strtotime((string)$data['attempted_at'])) : date('Y-m-d H:i:s'),
                $confirmed ? date('Y-m-d H:i:s') : null,
            ]);
        $this->checklist($orderId);
        $sets = ['customer_confirmed = ?', 'customer_confirmed_at = ?', 'confirmation_call_id = ?', 'confirmation_phrase = ?', 'confirmation_confidence = ?'];
        $params = [$confirmed ? 1 : 0, $confirmed ? date('Y-m-d H:i:s') : null, $callId ?: null, mb_substr($detected['phrase'] ?: $phrase, 0, 120), $confidence];
        if ($reconfirmed && $confirmed) {
            $sets[] = 'reconfirmed = 1'; $sets[] = 'reconfirmed_at = ?'; $sets[] = 'reconfirmed_source = ?';
            $params[] = date('Y-m-d H:i:s'); $params[] = 'wontia_aip';
        } elseif (!$confirmed) {
            $sets[] = 'reconfirmed = 0'; $sets[] = 'reconfirmed_at = NULL'; $sets[] = 'reconfirmed_source = NULL';
        }
        $params[] = $orderId;
        $db->prepare('UPDATE oc_order_checklists SET ' . implode(', ', $sets) . ' WHERE site_id = @site_id AND order_id = ?')->execute($params);
        try {
            (new StoreService())->logOrderEvent($orderId, $confirmed ? 'customer_confirmed' : 'customer_rejected', '', $confirmed ? '1' : '0', $detected['phrase'] ?: $phrase, null);
        } catch (\Throwable $e) {}
        return ['ok' => true, 'status' => 200, 'confirmed' => $confirmed, 'reconfirmed' => $reconfirmed && $confirmed, 'detected_phrase' => $detected['phrase'], 'rejected' => $detected['rejected']];
    }

    public function reportToProvider(int $orderId, int $userId): array
    {
        $this->ensureTables();
        $check = $this->checklist($orderId);
        if (empty($check['can_report'])) {
            return ['ok' => false, 'message' => 'Checklist incompleto para reportar a Miche.', 'missing' => $check['missing'], 'already_reported' => !empty($check['provider_reported'])];
        }
        $order = (new StoreService())->orderById($orderId);
        if (!$order) return ['ok' => false, 'message' => 'Pedido no encontrado.'];
        $db = Database::instance();
        $partner = $this->partner('Miche');
        if (!$partner) return ['ok' => false, 'message' => 'Proveedor Miche no configurado.'];
        $case = $this->caseForOrder($orderId);
        if (!$case) {
            $db->prepare('INSERT INTO oc_cases (site_id, uuid, order_id, order_number, reference, customer_name, customer_phone, status, priority, owner_org, partner_id, opened_at) VALUES (@site_id, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())')
                ->execute([
                    (string)$order['uuid'], $orderId, (string)$order['order_number'], (string)$order['order_number'],
                    (string)$order['customer_name'], (string)$order['customer_phone'], 'received', 'normal', 'sonika', (int)$partner['id'],
                ]);
            $case = $this->caseForOrder($orderId);
        }
        $caseId = (int)($case['id'] ?? 0);
        $task = null;
        $st = $db->prepare("SELECT * FROM oc_tasks WHERE site_id = @site_id AND case_id = ? AND type = 'provider_fulfillment' AND status IN ('open','in_progress','waiting') ORDER BY id DESC LIMIT 1");
        $st->execute([$caseId]);
        $task = $st->fetch() ?: null;
        if (!$task) {
            $db->prepare("INSERT INTO oc_tasks (site_id, case_id, type, title, status, priority, owner_org, assignee_user_id, assignee_partner_id, due_at, created_by) VALUES (@site_id, ?, 'provider_fulfillment', ?, 'open', 'normal', 'miche', NULL, ?, ?, ?)")
                ->execute([$caseId, 'Preparar y despachar pedido ' . (string)$order['order_number'], (int)$partner['id'], date('Y-m-d H:i:s', time() + 86400), $userId ?: null]);
            $taskId = (int)$db->lastInsertId();
        } else {
            $taskId = (int)$task['id'];
        }
        $handoffId = 0;
        try {
            $db->prepare("INSERT INTO oc_handoffs (site_id, case_id, task_id, from_org, to_org, status, sent_at, available_at) VALUES (@site_id, ?, ?, 'sonika', 'miche', 'sent', NOW(), NOW())")
                ->execute([$caseId, $taskId]);
            $handoffId = (int)$db->lastInsertId();
        } catch (\Throwable $e) {}
        $payload = json_encode(['order_number' => $order['order_number'], 'partner' => 'Miche', 'task_id' => $taskId, 'handoff_id' => $handoffId, 'by' => $userId], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        try {
            $db->prepare("INSERT INTO oc_events (site_id, case_id, task_id, type, source, actor_type, actor_id, payload, occurred_at) VALUES (@site_id, ?, ?, 'reported_to_provider', 'sonika', 'user', ?, ?, NOW())")
                ->execute([$caseId, $taskId, $userId ?: null, $payload]);
        } catch (\Throwable $e) {}
        $db->prepare('UPDATE oc_order_checklists SET provider_reported = 1, provider_reported_at = NOW(), provider_id = ? WHERE site_id = @site_id AND order_id = ?')
            ->execute([(int)$partner['id'], $orderId]);
        try { (new StoreService())->updateOrder($orderId, ['fulfillment_status' => 'confirmed']); } catch (\Throwable $e) {}
        try { (new StoreService())->logOrderEvent($orderId, 'reported_to_provider', '', 'Miche', 'Pedido reportado al proveedor', $userId ?: null); } catch (\Throwable $e) {}
        return ['ok' => true, 'case_id' => $caseId, 'task_id' => $taskId, 'handoff_id' => $handoffId, 'partner' => $partner, 'checklist' => $this->checklist($orderId)];
    }

    public function notifications(int $orderId): array
    {
        try {
            $st = Database::instance()->prepare('SELECT id, event, channel, recipient, status, template, provider, provider_message_id, error, scheduled_at, sent_at, created_at FROM oc_notifications WHERE site_id = @site_id AND order_id = ? ORDER BY id DESC LIMIT 80');
            $st->execute([$orderId]);
            return $st->fetchAll();
        } catch (\Throwable $e) { return []; }
    }

    public function confirmations(int $orderId): array
    {
        try {
            $st = Database::instance()->prepare('SELECT id, call_id, status, confirmed, confirmation_phrase, confidence, transcript, recording_url, attempted_at, confirmed_at, created_at FROM oc_confirmations WHERE site_id = @site_id AND order_id = ? ORDER BY id DESC LIMIT 20');
            $st->execute([$orderId]);
            return $st->fetchAll();
        } catch (\Throwable $e) { return []; }
    }

    public function shipment(int $orderId): ?array
    {
        try {
            $st = Database::instance()->prepare('SELECT * FROM oc_shipments WHERE site_id = @site_id AND order_id = ? ORDER BY id DESC LIMIT 1');
            $st->execute([$orderId]);
            return $st->fetch() ?: null;
        } catch (\Throwable $e) { return null; }
    }

    public function reviewRequest(int $orderId): ?array
    {
        try {
            $st = Database::instance()->prepare('SELECT * FROM oc_review_requests WHERE site_id = @site_id AND order_id = ? LIMIT 1');
            $st->execute([$orderId]);
            return $st->fetch() ?: null;
        } catch (\Throwable $e) { return null; }
    }

    public function providerForOrder(int $orderId): array
    {
        $db = Database::instance();
        $out = ['case' => null, 'partner' => null, 'tasks' => [], 'handoffs' => [], 'events' => []];
        try {
            $st = $db->prepare('SELECT * FROM oc_cases WHERE site_id = @site_id AND order_id = ? ORDER BY id DESC LIMIT 1');
            $st->execute([$orderId]);
            $case = $st->fetch() ?: null;
            if (!$case) return $out;
            $out['case'] = $case;
            if (!empty($case['partner_id'])) {
                $p = $db->prepare('SELECT id, name, type, contact_email, contact_phone FROM oc_partners WHERE site_id = @site_id AND id = ? LIMIT 1');
                $p->execute([(int)$case['partner_id']]);
                $out['partner'] = $p->fetch() ?: null;
            }
            $t = $db->prepare('SELECT id, type, title, status, owner_org, due_at, completed_at, created_at FROM oc_tasks WHERE site_id = @site_id AND case_id = ? ORDER BY id DESC LIMIT 20');
            $t->execute([(int)$case['id']]);
            $out['tasks'] = $t->fetchAll();
            $h = $db->prepare('SELECT id, from_org, to_org, status, sent_at, available_at, accepted_at, returned_at FROM oc_handoffs WHERE site_id = @site_id AND case_id = ? ORDER BY id DESC LIMIT 10');
            $h->execute([(int)$case['id']]);
            $out['handoffs'] = $h->fetchAll();
            $e = $db->prepare('SELECT id, type, source, payload, occurred_at FROM oc_events WHERE site_id = @site_id AND case_id = ? ORDER BY id DESC LIMIT 20');
            $e->execute([(int)$case['id']]);
            $out['events'] = $e->fetchAll();
        } catch (\Throwable $e) {}
        return $out;
    }

    private function enrichItems(array $items): array
    {
        $db = Database::instance();
        foreach ($items as &$it) {
            $p = null;
            $pid = (int)($it['product_id'] ?? 0);
            try {
                if ($pid > 0) {
                    $st = $db->prepare('SELECT id, name, slug, sku, vendor, product_type, weight_grams, images FROM store_products WHERE site_id = @site_id AND id = ? LIMIT 1');
                    $st->execute([$pid]);
                    $p = $st->fetch() ?: null;
                }
                if (!$p && trim((string)($it['name'] ?? '')) !== '') {
                    $st = $db->prepare('SELECT id, name, slug, sku, vendor, product_type, weight_grams, images FROM store_products WHERE site_id = @site_id AND name = ? LIMIT 1');
                    $st->execute([(string)$it['name']]);
                    $p = $st->fetch() ?: null;
                }
            } catch (\Throwable $e) {}
            if (trim((string)($it['image'] ?? '')) === '' && $p) {
                $imgs = json_decode((string)($p['images'] ?? ''), true) ?: [];
                $it['image'] = (string)($imgs[0] ?? '');
            }
            $it['product_id'] = $pid ?: (int)($p['id'] ?? 0);
            $it['sku'] = trim((string)($it['sku'] ?? '')) ?: (string)($p['sku'] ?? '');
            $it['product_ref'] = trim((string)($it['product_ref'] ?? '')) ?: (string)($p['slug'] ?? '');
            $it['brand'] = (string)($p['vendor'] ?? '');
            $it['category'] = (string)($p['product_type'] ?? '');
            $it['weight_grams'] = (int)($it['weight_grams'] ?? 0) ?: (int)($p['weight_grams'] ?? 0);
            $it['unit_price_cents'] = (int)($it['price_cents'] ?? 0);
            $it['line_total_cents'] = (int)($it['subtotal_cents'] ?? 0);
            $it['product_url'] = !empty($p['slug']) ? '/producto/' . (string)$p['slug'] : '';
        }
        unset($it);
        return $items;
    }

    private function paymentDetail(array $order): array
    {
        $method = (string)($order['payment_method'] ?? '');
        $meta = json_decode((string)($order['payment_meta'] ?? ''), true) ?: [];
        $out = [
            'method' => $method,
            'status' => (string)($order['payment_status'] ?? ''),
            'reference' => (string)($order['payment_reference'] ?? ''),
            'provider_ref' => (string)($order['provider_ref'] ?? ''),
            'paid_at' => (string)($order['paid_at'] ?? ''),
            'link' => (string)($order['payment_link'] ?? ''),
            'expires_at' => (string)($order['payment_expires_at'] ?? ''),
            'meta' => $meta,
            'mercadopago' => null,
            'addi' => null,
            'breb' => null,
        ];
        if ($method === 'mercadopago') {
            try {
                $st = Database::instance()->prepare('SELECT payment_id, status, status_detail, payment_method_id, payment_type_id, installments, amount_cents, payer_email, raw_last, last_synced_at FROM mercadopago_payments WHERE site_id = @site_id AND order_id = ? ORDER BY id DESC LIMIT 1');
                $st->execute([(int)$order['id']]);
                $mp = $st->fetch() ?: null;
                if ($mp) {
                    $raw = json_decode((string)($mp['raw_last'] ?? ''), true) ?: [];
                    $fee = 0;
                    foreach ((array)($raw['fee_details'] ?? []) as $f) $fee += (int)round(((float)($f['amount'] ?? 0)) * 100);
                    $net = (int)round(((float)($raw['transaction_details']['net_received_amount'] ?? 0)) * 100);
                    $mp['fee_cents'] = $fee;
                    $mp['net_received_cents'] = $net;
                    $mp['money_release_status'] = (string)($raw['money_release_status'] ?? '');
                    $mp['money_release_date'] = (string)($raw['money_release_date'] ?? '');
                    $mp['date_approved'] = (string)($raw['date_approved'] ?? '');
                    unset($mp['raw_last']);
                    $out['mercadopago'] = $mp;
                }
            } catch (\Throwable $e) {}
        } elseif ($method === 'addi') {
            try {
                $st = Database::instance()->prepare('SELECT addi_order_id, application_id, status, amount_cents, approved_amount_cents, redirect_url, error, callback_at, created_at FROM addi_transactions WHERE site_id = @site_id AND order_uuid = ? ORDER BY id DESC LIMIT 1');
                $st->execute([(string)$order['uuid']]);
                $out['addi'] = $st->fetch() ?: null;
            } catch (\Throwable $e) {}
        } elseif ($method === 'breb') {
            $out['breb'] = [
                'proof_status' => (string)($order['payment_proof_status'] ?? 'none'),
                'proof_at' => (string)($order['payment_proof_at'] ?? ''),
                'proof_size' => (int)($order['payment_proof_size'] ?? 0),
            ];
        }
        return $out;
    }

    private function timeline(int $orderId, array $provider, array $notifications, array $confirmations, ?array $shipment, ?array $review, array $order): array
    {
        $items = [];
        try {
            foreach ((new StoreService())->orderEvents($orderId, 80) as $e) {
                $items[] = ['at' => (string)($e['created_at'] ?? ''), 'type' => 'order', 'title' => (string)($e['event'] ?? ''), 'detail' => trim((string)($e['reason'] ?? '') . (($e['to_value'] ?? '') !== '' ? ' · ' . (string)$e['to_value'] : '')), 'actor' => (string)($e['username'] ?? '')];
            }
        } catch (\Throwable $e) {}
        foreach ((array)($provider['events'] ?? []) as $e) {
            $items[] = ['at' => (string)($e['occurred_at'] ?? ''), 'type' => 'provider', 'title' => (string)($e['type'] ?? ''), 'detail' => (string)($e['source'] ?? ''), 'actor' => 'Miche'];
        }
        foreach ($confirmations as $c) {
            $items[] = ['at' => (string)($c['confirmed_at'] ?: $c['attempted_at'] ?: $c['created_at']), 'type' => 'call', 'title' => 'Llamada: ' . (string)($c['status'] ?? ''), 'detail' => (string)($c['confirmation_phrase'] ?? ''), 'actor' => 'Wontia AIP'];
        }
        foreach ($notifications as $n) {
            $items[] = ['at' => (string)($n['sent_at'] ?: $n['created_at']), 'type' => 'notification', 'title' => 'Notificación ' . (string)($n['channel'] ?? ''), 'detail' => (string)($n['event'] ?? '') . ' · ' . (string)($n['status'] ?? ''), 'actor' => ''];
        }
        if ($shipment) {
            $items[] = ['at' => (string)($shipment['updated_at'] ?? ''), 'type' => 'shipment', 'title' => 'Envío: ' . (string)($shipment['status'] ?? ''), 'detail' => trim((string)($shipment['carrier'] ?? '') . ' ' . (string)($shipment['tracking_number'] ?? '')), 'actor' => 'Transportadora'];
        }
        if ($review) {
            $items[] = ['at' => (string)($review['sent_at'] ?: $review['scheduled_at'] ?: $review['created_at']), 'type' => 'review', 'title' => 'Solicitud de reseña', 'detail' => (string)($review['status'] ?? ''), 'actor' => ''];
        }
        $items[] = ['at' => (string)($order['created_at'] ?? ''), 'type' => 'order', 'title' => 'Pedido recibido', 'detail' => 'Pedido ' . (string)($order['order_number'] ?? ''), 'actor' => 'Checkout'];
        usort($items, fn($a, $b) => strcmp((string)$b['at'], (string)$a['at']));
        return array_slice($items, 0, 80);
    }

    private function detectConfirmation(string $transcript, string $phrase, bool $flag): array
    {
        $text = $this->normalize($transcript . ' ' . $phrase);
        $rejected = false;
        $found = '';
        foreach (self::REJECTION_PHRASES as $p) {
            if ($this->containsPhrase($text, $p)) { $rejected = true; $found = $p; break; }
        }
        if (!$rejected) {
            foreach (self::CONFIRMATION_PHRASES as $p) {
                if ($this->containsPhrase($text, $p)) { $found = $p; break; }
            }
        }
        return ['confirmed' => $flag || ($found !== '' && !$rejected), 'rejected' => $rejected, 'phrase' => $found];
    }

    private function containsPhrase(string $text, string $phrase): bool
    {
        $p = $this->normalize($phrase);
        if ($p === '') return false;
        return (bool)preg_match('/(^|[\s,.;:!?])' . preg_quote($p, '/') . '([\s,.;:!?]|$)/u', $text);
    }

    private function normalize(string $s): string
    {
        $s = mb_strtolower(trim($s), 'UTF-8');
        $s = strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
        return preg_replace('/\s+/u', ' ', $s) ?: '';
    }

    private function partner(string $name): ?array
    {
        try {
            $st = Database::instance()->prepare('SELECT * FROM oc_partners WHERE site_id = @site_id AND name = ? ORDER BY active DESC, id ASC LIMIT 1');
            $st->execute([$name]);
            return $st->fetch() ?: null;
        } catch (\Throwable $e) { return null; }
    }

    private function caseForOrder(int $orderId): ?array
    {
        try {
            $st = Database::instance()->prepare('SELECT * FROM oc_cases WHERE site_id = @site_id AND order_id = ? ORDER BY id DESC LIMIT 1');
            $st->execute([$orderId]);
            return $st->fetch() ?: null;
        } catch (\Throwable $e) { return null; }
    }

    private function setting(string $key): string
    {
        try {
            $st = Database::instance()->prepare('SELECT `value` FROM settings WHERE site_id = @site_id AND `key` = ? LIMIT 1');
            $st->execute([$key]);
            return trim((string)$st->fetchColumn());
        } catch (\Throwable $e) { return ''; }
    }
}
