<?php
namespace App\Services;

use App\Core\Config;
use App\Core\Database;

/**
 * SONIKA Artist Partners — Fase 1.
 * Atribución (último clic válido 30d) + comisiones progresivas en MODO SIMULACIÓN.
 * No ejecuta pagos reales. Datos separados de pedidos históricos.
 */
class AffiliateService
{
    public const STATUSES = ['DRAFT', 'EMAIL_PENDING', 'PHONE_PENDING', 'DOCUMENTS_PENDING', 'REVIEW_REQUIRED', 'APPROVED', 'REJECTED', 'SUSPENDED', 'TERMINATED'];
    public const TTL_DAYS = 30;
    // Tramos progresivos (tasa marginal por tramo de ventas mensuales elegibles).
    public const TIERS = [
        [0, 499999.99, 0.0025],
        [500000, 1499999.99, 0.0050],
        [1500000, 2999999.99, 0.0075],
        [3000000, 5999999.99, 0.0100],
        [6000000, 9999999.99, 0.0125],
        [10000000, PHP_FLOAT_MAX, 0.0150],
    ];

    public function ready(): bool
    {
        try { Database::instance()->query('SELECT 1 FROM affiliate_profiles LIMIT 1'); return true; }
        catch (\Throwable $e) { return false; }
    }

    public function slugify(string $s): string
    {
        $s = strtolower(trim($s));
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
        return trim(substr($s, 0, 100), '-');
    }

    public function log(?int $affiliateId, string $action, array $meta = []): void
    {
        try {
            Database::instance()->prepare('INSERT INTO affiliate_audit (site_id, affiliate_id, actor, action, meta, created_at) VALUES (@site_id, :a, :actor, :act, :m, NOW())')
                ->execute(['a' => $affiliateId, 'actor' => 'system', 'act' => $action, 'm' => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null]);
        } catch (\Throwable $e) {}
    }

    public function login(string $email, string $password): array
    {
        $email = strtolower(trim($email));
        if ($email === '' || $password === '') return ['ok' => false, 'message' => 'Completa correo y contraseña.'];
        $prof = $this->profileByEmail($email);
        if (!$prof || empty($prof['password_hash']) || !password_verify($password, (string)$prof['password_hash'])) {
            return ['ok' => false, 'message' => 'Correo o contraseña incorrectos.'];
        }
        $sent = $this->sendOtp($email);
        return ['ok' => true, 'otp' => true, 'sent' => !empty($sent['sent']), 'message' => 'Te enviamos un código al correo.'];
    }

    public function loginVerify(string $email, string $code): ?array
    {
        $email = strtolower(trim($email));
        $r = $this->verifyOtp($email, $code);
        if (empty($r['ok'])) return null;
        $prof = $this->profileByEmail($email);
        if (!$prof) return null;
        try { Database::instance()->prepare('UPDATE affiliate_profiles SET last_login_at = NOW() WHERE id = :id AND site_id = @site_id')->execute(['id' => (int)$prof['id']]); } catch (\Throwable $e) {}
        $name = trim((string)(($prof['artist_name'] ?: $prof['public_name']) ?: $prof['full_name']));
        return ['id' => (int)$prof['id'], 'slug' => (string)$prof['slug'], 'name' => $name];
    }

    public function setPassword(int $affiliateId, string $password): bool
    {
        if (strlen($password) < 8) return false;
        try { Database::instance()->prepare('UPDATE affiliate_profiles SET password_hash = :h WHERE id = :id AND site_id = @site_id')->execute(['h' => password_hash($password, PASSWORD_DEFAULT), 'id' => $affiliateId]); $this->log($affiliateId, 'password_set'); return true; }
        catch (\Throwable $e) { return false; }
    }

    public function createProfile(array $in): array
    {
        if (!$this->ready()) return ['ok' => false, 'message' => 'Tablas de afiliados no inicializadas.'];
        $email = strtolower(trim((string)($in['email'] ?? '')));
        $name = trim((string)($in['full_name'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'message' => 'Correo inválido.'];
        if ($name === '') return ['ok' => false, 'message' => 'Nombre obligatorio.'];
        $pass = (string)($in['password'] ?? '');
        if ($pass !== '' && strlen($pass) < 8) return ['ok' => false, 'message' => 'La contraseña debe tener al menos 8 caracteres.'];
        $slug = $this->slugify((string)($in['artist_name'] ?? $in['public_name'] ?? $name));
        try {
            $db = Database::instance();
            $st = $db->prepare('INSERT INTO affiliate_profiles (site_id, email, password_hash, full_name, doc_type, doc_number, public_name, artist_name, slug, status, music_experience, music_instrument, music_level, music_genre, music_desired, music_role, socials, created_at)
                VALUES (@site_id, :email, :ph, :name, :dt, :dn, :pn, :an, :slug, :status, :me, :mi, :ml, :mg, :md, :mr, :soc, NOW())');
            $st->execute([
                'email' => $email, 'ph' => $pass !== '' ? password_hash($pass, PASSWORD_DEFAULT) : null, 'name' => $name,
                'dt' => substr((string)($in['doc_type'] ?? ''), 0, 16),
                'dn' => substr((string)($in['doc_number'] ?? ''), 0, 60),
                'pn' => substr((string)($in['public_name'] ?? $name), 0, 120),
                'an' => substr((string)($in['artist_name'] ?? ''), 0, 120),
                'slug' => $slug,
                'status' => 'EMAIL_PENDING',
                'me' => $in['music_experience'] ?? null, 'mi' => $in['music_instrument'] ?? null,
                'ml' => $in['music_level'] ?? null, 'mg' => $in['music_genre'] ?? null,
                'md' => $in['music_desired'] ?? null, 'mr' => $in['music_role'] ?? null,
                'soc' => !empty($in['socials']) ? json_encode($in['socials'], JSON_UNESCAPED_UNICODE) : null,
            ]);
            $id = (int)$db->lastInsertId();
            $this->log($id, 'profile_created', ['email' => $email]);
            if (!empty($in['accept_contract'])) {
                $c = $this->currentContract();
                if ($c) $this->acceptContract($id, (int)$c['id']);
            }
            // Confirmación de correo obligatoria por OTP al registrarse.
            $this->sendOtp($email);
            return ['ok' => true, 'id' => $id, 'slug' => $slug];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo crear (¿correo ya registrado?).'];
        }
    }

    public function setStatus(int $id, string $status): bool
    {
        if (!in_array($status, self::STATUSES, true)) return false;
        try {
            Database::instance()->prepare('UPDATE affiliate_profiles SET status = :s WHERE id = :id AND site_id = @site_id')->execute(['s' => $status, 'id' => $id]);
            $this->log($id, 'status_' . strtolower($status));
            return true;
        } catch (\Throwable $e) { return false; }
    }

    public function listProfiles(int $limit = 200): array
    {
        if (!$this->ready()) return [];
        try { return Database::instance()->query('SELECT * FROM affiliate_profiles WHERE site_id = @site_id ORDER BY id DESC LIMIT ' . max(1, min(500, $limit)))->fetchAll(); }
        catch (\Throwable $e) { return []; }
    }

    public function getById(int $id): ?array
    {
        try { $st = Database::instance()->prepare('SELECT * FROM affiliate_profiles WHERE id = :id AND site_id = @site_id LIMIT 1'); $st->execute(['id' => $id]); return $st->fetch() ?: null; }
        catch (\Throwable $e) { return null; }
    }

    public function getBySlug(string $slug): ?array
    {
        try { $st = Database::instance()->prepare('SELECT * FROM affiliate_profiles WHERE slug = :s AND site_id = @site_id AND status = "APPROVED" LIMIT 1'); $st->execute(['s' => $slug]); return $st->fetch() ?: null; }
        catch (\Throwable $e) { return null; }
    }

    public function collections(int $affiliateId): array
    {
        try {
            $st = Database::instance()->prepare('SELECT * FROM affiliate_collections WHERE affiliate_id = :a AND site_id = @site_id ORDER BY sort_order, id');
            $st->execute(['a' => $affiliateId]);
            return $st->fetchAll();
        } catch (\Throwable $e) { return []; }
    }

    public function ensureLink(int $affiliateId): string
    {
        try {
            $db = Database::instance();
            $st = $db->prepare('SELECT code FROM affiliate_links WHERE affiliate_id = :a AND site_id = @site_id LIMIT 1');
            $st->execute(['a' => $affiliateId]);
            $code = $st->fetchColumn();
            if ($code) return (string)$code;
            $code = substr(bin2hex(random_bytes(5)), 0, 10);
            $db->prepare('INSERT INTO affiliate_links (site_id, affiliate_id, code, target_url, created_at) VALUES (@site_id, :a, :c, "/", NOW())')->execute(['a' => $affiliateId, 'c' => $code]);
            return $code;
        } catch (\Throwable $e) { return ''; }
    }

    /** Registra un clic de afiliado (llamado desde la ruta /r/{code}). */
    public function recordClick(string $code, string $sessionId = ''): ?array
    {
        if (!$this->ready()) return null;
        try {
            $db = Database::instance();
            $st = $db->prepare('SELECT * FROM affiliate_links WHERE code = :c AND site_id = @site_id LIMIT 1');
            $st->execute(['c' => $code]);
            $link = $st->fetch();
            if (!$link) return null;
            $aff = $this->getById((int)$link['affiliate_id']);
            if (!$aff || $aff['status'] !== 'APPROVED') return null;
            $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
            $isBot = (bool)preg_match('/bot|crawl|spider|slurp|facebookexternalhit|preview|headless/i', $ua);
            $db->prepare('INSERT INTO affiliate_clicks (site_id, affiliate_id, code, session_id, page_url, referrer, device, country, ip_hash, is_bot, clicked_at) VALUES (@site_id, :a, :c, :s, :p, :r, :d, :co, :ip, :b, NOW())')
                ->execute([
                    'a' => (int)$aff['id'], 'c' => $code, 's' => substr($sessionId, 0, 64),
                    'p' => mb_substr((string)($_SERVER['REQUEST_URI'] ?? ''), 0, 500),
                    'r' => mb_substr((string)($_SERVER['HTTP_REFERER'] ?? ''), 0, 500),
                    'd' => (int)preg_match('/mobile|android|iphone/i', $ua) ? 'mobile' : 'desktop',
                    'co' => strtoupper(substr((string)($_SERVER['HTTP_CF_IPCOUNTRY'] ?? ''), 0, 2)) ?: null,
                    'ip' => hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? '')),
                    'b' => $isBot ? 1 : 0,
                ]);
            return ['affiliate_id' => (int)$aff['id'], 'code' => $code, 'artist' => $aff['artist_name'] ?: $aff['public_name'] ?: $aff['full_name'], 'slug' => $aff['slug']];
        } catch (\Throwable $e) { return null; }
    }

    /** Comisión progresiva del monto sobre la base acumulada del mes (simulación). */
    public function progressiveCommission(float $cumulativeBefore, float $base): array
    {
        $base = max(0.0, $base);
        $cum = max(0.0, $cumulativeBefore);
        $rem = $base;
        $total = 0.0;
        $lastRate = 0.0;
        foreach (self::TIERS as [$lo, $hi, $rate]) {
            if ($cum >= $hi) continue;
            $start = max($cum, (float)$lo);
            $room = $hi - $start;
            if ($room <= 0) continue;
            $portion = min($rem, $room);
            if ($portion <= 0) continue;
            $total += $portion * $rate;
            $rem -= $portion;
            $cum += $portion;
            $lastRate = $rate;
            if ($rem <= 0) break;
        }
        return ['amount' => round($total, 2), 'rate' => $lastRate];
    }

    public function monthSales(int $affiliateId): float
    {
        try {
            $st = Database::instance()->prepare('SELECT COALESCE(SUM(base_amount),0) FROM affiliate_commissions WHERE affiliate_id = :a AND site_id = @site_id AND status NOT IN ("REVERSED","REJECTED") AND created_at >= DATE_FORMAT(NOW(), "%Y-%m-01")');
            $st->execute(['a' => $affiliateId]);
            return (float)$st->fetchColumn();
        } catch (\Throwable $e) { return 0.0; }
    }

    /** Fija la atribución de un pedido + comisión estimada (idempotente). */
    public function attributeOrder(string $orderRef, float $baseAmount, ?int $affiliateId = null, ?string $code = null): ?array
    {
        if (!$this->ready()) return null;
        try {
            $db = Database::instance();
            if ($affiliateId === null && $code) {
                $st = $db->prepare('SELECT affiliate_id FROM affiliate_links WHERE code = :c AND site_id = @site_id LIMIT 1');
                $st->execute(['c' => $code]);
                $affiliateId = (int)$st->fetchColumn() ?: null;
            }
            if (!$affiliateId) return null;
            $aff = $this->getById($affiliateId);
            if (!$aff || $aff['status'] !== 'APPROVED') return null;
            // ¿clic válido en ventana de 30 días?
            $st = $db->prepare('SELECT COUNT(*) FROM affiliate_clicks WHERE affiliate_id = :a AND site_id = @site_id AND is_bot = 0 AND clicked_at >= (NOW() - INTERVAL ' . self::TTL_DAYS . ' DAY)');
            $st->execute(['a' => $affiliateId]);
            if ((int)$st->fetchColumn() === 0) return null;
            $cum = $this->monthSales($affiliateId);
            $calc = $this->progressiveCommission($cum, $baseAmount);
            $db->prepare('INSERT IGNORE INTO affiliate_order_attributions (site_id, order_ref, affiliate_id, code, model, fixed_at) VALUES (@site_id, :o, :a, :c, "last_click_30d", NOW())')
                ->execute(['o' => $orderRef, 'a' => $affiliateId, 'c' => $code]);
            $db->prepare('INSERT IGNORE INTO affiliate_commissions (site_id, affiliate_id, order_ref, base_amount, tier_rate, amount, status, meta, created_at) VALUES (@site_id, :a, :o, :b, :r, :amt, "ESTIMATED", :m, NOW())')
                ->execute(['a' => $affiliateId, 'o' => $orderRef, 'b' => $baseAmount, 'r' => $calc['rate'], 'amt' => $calc['amount'], 'm' => json_encode(['simulation' => true, 'cumulative_before' => $cum], JSON_UNESCAPED_UNICODE)]);
            $this->log($affiliateId, 'order_attributed', ['order' => $orderRef, 'amount' => $calc['amount'], 'simulation' => true]);
            return ['ok' => true, 'affiliate_id' => $affiliateId, 'commission' => $calc['amount'], 'rate' => $calc['rate'], 'simulation' => true];
        } catch (\Throwable $e) { return null; }
    }

    public function statusLookup(string $email, string $doc): ?array
    {
        $email = strtolower(trim($email));
        $doc = preg_replace('/\D/', '', $doc) ?? '';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($doc) < 4) return null;
        try {
            $st = Database::instance()->prepare('SELECT id, full_name, artist_name, public_name, status, doc_number, created_at FROM affiliate_profiles WHERE email = :e AND site_id = @site_id LIMIT 1');
            $st->execute(['e' => $email]);
            $r = $st->fetch();
            if (!$r) return null;
            $docDb = preg_replace('/\D/', '', (string)($r['doc_number'] ?? ''));
            if ($docDb === '' || substr($docDb, -4) !== substr($doc, -4)) return null;
            return [
                'id' => (int)$r['id'],
                'name' => (string)(($r['artist_name'] ?: $r['public_name']) ?: $r['full_name']),
                'status' => (string)$r['status'],
                'created_at' => (string)$r['created_at'],
            ];
        } catch (\Throwable $e) { return null; }
    }

    public function dashboard(int $affiliateId): array
    {
        $out = ['clicks' => 0, 'sales' => 0.0, 'commission_total' => 0.0, 'by_status' => [], 'recent' => []];
        try {
            $db = Database::instance();
            $st = $db->prepare('SELECT COUNT(*) FROM affiliate_clicks WHERE affiliate_id = :a AND site_id = @site_id AND is_bot = 0');
            $st->execute(['a' => $affiliateId]);
            $out['clicks'] = (int)$st->fetchColumn();
            $st = $db->prepare('SELECT COUNT(*) c, COALESCE(SUM(base_amount),0) s, COALESCE(SUM(amount),0) t FROM affiliate_commissions WHERE affiliate_id = :a AND site_id = @site_id');
            $st->execute(['a' => $affiliateId]);
            $r = $st->fetch() ?: [];
            $out['sales'] = (float)($r['s'] ?? 0);
            $out['commission_total'] = (float)($r['t'] ?? 0);
            $st = $db->prepare('SELECT status, COALESCE(SUM(amount),0) amt, COUNT(*) c FROM affiliate_commissions WHERE affiliate_id = :a AND site_id = @site_id GROUP BY status');
            $st->execute(['a' => $affiliateId]);
            foreach ($st->fetchAll() as $row) $out['by_status'][(string)$row['status']] = ['amount' => (float)$row['amt'], 'count' => (int)$row['c']];
            $st = $db->prepare('SELECT order_ref, base_amount, tier_rate, amount, status, created_at FROM affiliate_commissions WHERE affiliate_id = :a AND site_id = @site_id ORDER BY id DESC LIMIT 10');
            $st->execute(['a' => $affiliateId]);
            $out['recent'] = $st->fetchAll();
        } catch (\Throwable $e) {
        }
        $out['league'] = $this->computeLeague($affiliateId);
        return $out;
    }

    private function profileByEmail(string $email): ?array
    {
        try { $st = Database::instance()->prepare('SELECT * FROM affiliate_profiles WHERE email = :e AND site_id = @site_id LIMIT 1'); $st->execute(['e' => $email]); return $st->fetch() ?: null; }
        catch (\Throwable $e) { return null; }
    }

    public function currentContract(): ?array
    {
        try { $st = Database::instance()->query("SELECT * FROM affiliate_contracts WHERE site_id = @site_id AND code = 'artist_partners' AND status = 'published' ORDER BY id DESC LIMIT 1"); return $st->fetch() ?: null; }
        catch (\Throwable $e) { return null; }
    }

    public function acceptContract(int $affiliateId, int $contractId): bool
    {
        try {
            $db = Database::instance();
            $st = $db->prepare('SELECT code, version FROM affiliate_contracts WHERE id = :id AND site_id = @site_id LIMIT 1');
            $st->execute(['id' => $contractId]);
            $c = $st->fetch();
            if (!$c) return false;
            $db->prepare('INSERT IGNORE INTO affiliate_acceptances (site_id, affiliate_id, contract_id, contract_code, contract_version, ip_hash, user_agent) VALUES (@site_id, :a, :c, :code, :ver, :ip, :ua)')
                ->execute(['a' => $affiliateId, 'c' => $contractId, 'code' => $c['code'], 'ver' => $c['version'], 'ip' => hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? '')), 'ua' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 300)]);
            $db->prepare('UPDATE affiliate_profiles SET contract_version = :v WHERE id = :a AND site_id = @site_id')->execute(['v' => $c['version'], 'a' => $affiliateId]);
            $this->log($affiliateId, 'contract_accepted', ['version' => $c['version']]);
            return true;
        } catch (\Throwable $e) { return false; }
    }

    public function sendOtp(string $email): array
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'message' => 'Correo inválido.'];
        $prof = $this->profileByEmail($email);
        if (!$prof) return ['ok' => false, 'message' => 'No hay solicitud con ese correo.'];
        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        try {
            $sent = false;
            Database::instance()->prepare('INSERT INTO affiliate_otps (site_id, email, code_hash, expires_at) VALUES (@site_id, :e, :h, DATE_ADD(NOW(), INTERVAL 10 MINUTE))')->execute(['e' => $email, 'h' => hash('sha256', $code)]);
            if (class_exists('\\App\\Services\\EmailService')) {
                try {
                    $es = new \App\Services\EmailService();
                    $sent = $es->sendHtml($email, 'Tu código de verificación — SONIKA Artist', '<div style="font-family:Arial,sans-serif"><p>Tu código de verificación es:</p><p style="font-size:28px;font-weight:800;letter-spacing:4px">' . $code . '</p><p>Vence en 10 minutos. Si no lo solicitaste, ignora este correo.</p></div>');
                } catch (\Throwable $x) {}
            }
            return ['ok' => true, 'sent' => $sent];
        } catch (\Throwable $e) { return ['ok' => false, 'message' => 'No se pudo generar el código.']; }
    }

    public function verifyOtp(string $email, string $code): array
    {
        $email = strtolower(trim($email));
        $code = preg_replace('/\D/', '', $code) ?? '';
        if ($code === '') return ['ok' => false, 'message' => 'Código inválido.'];
        try {
            $db = Database::instance();
            $st = $db->prepare("SELECT id, code_hash FROM affiliate_otps WHERE email = :e AND site_id = @site_id AND used = 0 AND expires_at >= NOW() ORDER BY id DESC LIMIT 1");
            $st->execute(['e' => $email]);
            $o = $st->fetch();
            if (!$o || !hash_equals((string)$o['code_hash'], hash('sha256', $code))) {
                $db->prepare('UPDATE affiliate_otps SET attempts = attempts + 1 WHERE email = :e AND site_id = @site_id AND used = 0')->execute(['e' => $email]);
                return ['ok' => false, 'message' => 'Código incorrecto o vencido.'];
            }
            $db->prepare('UPDATE affiliate_otps SET used = 1 WHERE id = :id')->execute(['id' => $o['id']]);
            $prof = $this->profileByEmail($email);
            if ($prof && in_array($prof['status'], ['DRAFT', 'EMAIL_PENDING'], true)) $this->setStatus((int)$prof['id'], 'REVIEW_REQUIRED');
            return ['ok' => true, 'verified' => true];
        } catch (\Throwable $e) { return ['ok' => false, 'message' => 'Error al verificar.']; }
    }

    public function createPayoutBatch(string $period = '', string $by = 'admin'): array
    {
        if (!$this->ready()) return ['ok' => false, 'message' => 'No inicializado.'];
        $period = preg_match('/^\d{4}-\d{2}$/', $period) ? $period : date('Y-m');
        try {
            $db = Database::instance();
            $st = $db->prepare("SELECT c.id, c.affiliate_id, c.amount FROM affiliate_commissions c LEFT JOIN affiliate_payout_items i ON i.commission_id = c.id AND i.site_id = c.site_id WHERE c.site_id = @site_id AND c.status = 'APPROVED' AND i.id IS NULL AND DATE_FORMAT(c.created_at, '%Y-%m') = :p");
            $st->execute(['p' => $period]);
            $rows = $st->fetchAll();
            if (!$rows) return ['ok' => false, 'message' => 'No hay comisiones aprobadas pendientes para ' . $period . '.'];
            $total = 0.0;
            foreach ($rows as $r) $total += (float)$r['amount'];
            $db->beginTransaction();
            $db->prepare("INSERT INTO affiliate_payout_batches (site_id, period, total_amount, items_count, status, created_by, created_at) VALUES (@site_id, :p, :t, :n, 'PENDING_APPROVAL', :by, NOW())")
                ->execute(['p' => $period, 't' => round($total, 2), 'n' => count($rows), 'by' => $by]);
            $bid = (int)$db->lastInsertId();
            $ins = $db->prepare('INSERT INTO affiliate_payout_items (site_id, batch_id, affiliate_id, commission_id, amount) VALUES (@site_id, :b, :a, :c, :amt)');
            $upd = $db->prepare("UPDATE affiliate_commissions SET status = 'PAYMENT_QUEUED' WHERE id = :id AND site_id = @site_id");
            foreach ($rows as $r) {
                $ins->execute(['b' => $bid, 'a' => (int)$r['affiliate_id'], 'c' => (int)$r['id'], 'amt' => (float)$r['amount']]);
                $upd->execute(['id' => (int)$r['id']]);
            }
            $db->commit();
            $this->log(null, 'payout_batch_created', ['batch' => $bid, 'period' => $period, 'total' => $total, 'items' => count($rows), 'simulation' => true]);
            return ['ok' => true, 'batch' => $bid, 'total' => round($total, 2), 'items' => count($rows)];
        } catch (\Throwable $e) {
            try { if (Database::instance()->inTransaction()) Database::instance()->rollBack(); } catch (\Throwable $x) {}
            return ['ok' => false, 'message' => 'No se pudo crear el lote.'];
        }
    }

    public function approvePayoutBatch(int $id, string $by = 'admin'): bool
    {
        try {
            $st = Database::instance()->prepare("UPDATE affiliate_payout_batches SET status = 'APPROVED', approved_by = :by, approved_at = NOW() WHERE id = :id AND site_id = @site_id AND status = 'PENDING_APPROVAL'");
            $st->execute(['by' => $by, 'id' => $id]);
            if ($st->rowCount() > 0) { $this->log(null, 'payout_batch_approved', ['batch' => $id, 'simulation' => true]); return true; }
            return false;
        } catch (\Throwable $e) { return false; }
    }

    public function payPayoutBatch(int $id, string $by = 'admin'): bool
    {
        try {
            $db = Database::instance();
            $st = $db->prepare("UPDATE affiliate_payout_batches SET status = 'PAID', paid_by = :by, paid_at = NOW() WHERE id = :id AND site_id = @site_id AND status = 'APPROVED'");
            $st->execute(['by' => $by, 'id' => $id]);
            if ($st->rowCount() === 0) return false;
            $db->prepare("UPDATE affiliate_commissions c JOIN affiliate_payout_items i ON i.commission_id = c.id AND i.site_id = c.site_id SET c.status = 'PAID' WHERE i.batch_id = :b AND c.site_id = @site_id")->execute(['b' => $id]);
            $this->log(null, 'payout_batch_paid', ['batch' => $id, 'simulation' => true]);
            return true;
        } catch (\Throwable $e) { return false; }
    }

    public function listPayoutBatches(int $limit = 50): array
    {
        try { return Database::instance()->query('SELECT * FROM affiliate_payout_batches WHERE site_id = @site_id ORDER BY id DESC LIMIT ' . max(1, min(200, $limit)))->fetchAll(); }
        catch (\Throwable $e) { return []; }
    }

    public function leagueLevels(): array
    {
        try { return Database::instance()->query('SELECT code, name, min_amount FROM affiliate_levels WHERE site_id = @site_id ORDER BY sort_order, min_amount')->fetchAll(); }
        catch (\Throwable $e) { return []; }
    }

    /** Nivel actual del Sound League según ventas elegibles del mes (progresión configurable). */
    public function computeLeague(int $affiliateId): array
    {
        $levels = $this->leagueLevels();
        $sales = $this->monthSales($affiliateId);
        $clicks = 0;
        try { $st = Database::instance()->prepare('SELECT COUNT(*) FROM affiliate_clicks WHERE affiliate_id = :a AND site_id = @site_id AND is_bot = 0'); $st->execute(['a' => $affiliateId]); $clicks = (int)$st->fetchColumn(); } catch (\Throwable $e) {}
        if (!$levels) {
            return ['level' => '—', 'code' => null, 'sales' => $sales, 'clicks' => $clicks, 'next' => null, 'next_min' => null, 'progress' => 0];
        }
        $cur = null; $next = null;
        foreach ($levels as $l) {
            if ($sales >= (float)$l['min_amount']) $cur = $l;
            elseif ($next === null) $next = $l;
        }
        if ($cur === null) $cur = $levels[0];
        $progress = 100;
        if ($next !== null) {
            $lo = (float)$cur['min_amount']; $hi = (float)$next['min_amount'];
            $progress = $hi > $lo ? (int)max(0, min(100, round(($sales - $lo) / ($hi - $lo) * 100))) : 0;
        }
        return [
            'level' => (string)$cur['name'], 'code' => (string)$cur['code'],
            'sales' => $sales, 'clicks' => $clicks,
            'next' => $next ? (string)$next['name'] : null,
            'next_min' => $next ? (float)$next['min_amount'] : null,
            'progress' => $progress,
        ];
    }

    public function postsFor(int $affiliateId): array
    {
        try {
            $st = Database::instance()->prepare("SELECT * FROM affiliate_posts WHERE affiliate_id = :a AND site_id = @site_id AND status = 'published' ORDER BY id DESC LIMIT 12");
            $st->execute(['a' => $affiliateId]);
            return $st->fetchAll();
        } catch (\Throwable $e) { return []; }
    }

    public function cvFor(int $affiliateId): ?array
    {
        try { $st = Database::instance()->prepare('SELECT * FROM affiliate_cv WHERE affiliate_id = :a AND site_id = @site_id LIMIT 1'); $st->execute(['a' => $affiliateId]); return $st->fetch() ?: null; }
        catch (\Throwable $e) { return null; }
    }

    public function tipAdd(int $affiliateId, array $in): array
    {
        $amount = (float)($in['amount'] ?? 0);
        if ($amount <= 0 || $amount > 5000000) return ['ok' => false, 'message' => 'Monto inválido.'];
        try {
            Database::instance()->prepare('INSERT INTO affiliate_tips (site_id, affiliate_id, sender_name, sender_email, amount, message, status, created_at) VALUES (@site_id, :a, :n, :e, :amt, :m, "simulated", NOW())')
                ->execute([
                    'a' => $affiliateId,
                    'n' => mb_substr((string)($in['name'] ?? ''), 0, 120),
                    'e' => mb_substr((string)($in['email'] ?? ''), 0, 190),
                    'amt' => round($amount, 2),
                    'm' => mb_substr((string)($in['message'] ?? ''), 0, 500),
                ]);
            $this->log($affiliateId, 'tip_received', ['amount' => $amount, 'simulation' => true]);
            return ['ok' => true, 'message' => '¡Gracias por tu aporte de agradecimiento! (simulación)'];
        } catch (\Throwable $e) { return ['ok' => false, 'message' => 'No se pudo registrar el aporte.']; }
    }

    public function tipsFor(int $affiliateId): array
    {
        try { $st = Database::instance()->prepare('SELECT * FROM affiliate_tips WHERE affiliate_id = :a AND site_id = @site_id ORDER BY id DESC LIMIT 50'); $st->execute(['a' => $affiliateId]); return $st->fetchAll(); }
        catch (\Throwable $e) { return []; }
    }

    public function tipsTotal(int $affiliateId): float
    {
        try { $st = Database::instance()->prepare("SELECT COALESCE(SUM(amount),0) FROM affiliate_tips WHERE affiliate_id = :a AND site_id = @site_id AND status <> 'rejected'"); $st->execute(['a' => $affiliateId]); return (float)$st->fetchColumn(); }
        catch (\Throwable $e) { return 0.0; }
    }

    public function contractRequestAdd(int $affiliateId, array $in): array
    {
        $name = trim((string)($in['name'] ?? ''));
        $email = strtolower(trim((string)($in['email'] ?? '')));
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'message' => 'Nombre y correo válidos son obligatorios.'];
        try {
            Database::instance()->prepare('INSERT INTO affiliate_contract_requests (site_id, affiliate_id, name, email, phone, event_type, event_date, place, budget, message, status, created_at) VALUES (@site_id, :a, :n, :e, :p, :t, :d, :pl, :b, :m, "new", NOW())')
                ->execute([
                    'a' => $affiliateId, 'n' => mb_substr($name, 0, 120), 'e' => mb_substr($email, 0, 190),
                    'p' => mb_substr((string)($in['phone'] ?? ''), 0, 40),
                    't' => mb_substr((string)($in['event_type'] ?? ''), 0, 40),
                    'd' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($in['event_date'] ?? '')) ? (string)$in['event_date'] : null,
                    'pl' => mb_substr((string)($in['place'] ?? ''), 0, 190),
                    'b' => mb_substr((string)($in['budget'] ?? ''), 0, 40),
                    'm' => mb_substr((string)($in['message'] ?? ''), 0, 2000),
                ]);
            $this->log($affiliateId, 'contract_request', ['from' => $email]);
            return ['ok' => true, 'message' => 'Solicitud enviada. El artista te contactará.'];
        } catch (\Throwable $e) { return ['ok' => false, 'message' => 'No se pudo enviar la solicitud.']; }
    }

    public function contractRequestsFor(int $affiliateId): array
    {
        try { $st = Database::instance()->prepare('SELECT * FROM affiliate_contract_requests WHERE affiliate_id = :a AND site_id = @site_id ORDER BY id DESC LIMIT 50'); $st->execute(['a' => $affiliateId]); return $st->fetchAll(); }
        catch (\Throwable $e) { return []; }
    }

    public function awardsFor(int $affiliateId): array
    {
        try {
            $st = Database::instance()->prepare("SELECT * FROM affiliate_awards WHERE affiliate_id = :a AND site_id = @site_id AND status = 'granted' ORDER BY id DESC");
            $st->execute(['a' => $affiliateId]);
            return $st->fetchAll();
        } catch (\Throwable $e) { return []; }
    }

    public function statsFor(int $affiliateId): array
    {
        try {
            $db = Database::instance();
            $st = $db->prepare('SELECT COALESCE(SUM(amount),0) total, COALESCE(SUM(CASE WHEN status="PAID" THEN amount ELSE 0 END),0) paid FROM affiliate_commissions WHERE affiliate_id = :a AND site_id = @site_id');
            $st->execute(['a' => $affiliateId]);
            $row = $st->fetch() ?: ['total' => 0, 'paid' => 0];
            $st2 = $db->prepare('SELECT COUNT(*) FROM affiliate_clicks WHERE affiliate_id = :a AND site_id = @site_id AND is_bot = 0');
            $st2->execute(['a' => $affiliateId]);
            return ['commission_total' => (float)$row['total'], 'commission_paid' => (float)$row['paid'], 'clicks' => (int)$st2->fetchColumn()];
        } catch (\Throwable $e) { return ['commission_total' => 0, 'commission_paid' => 0, 'clicks' => 0]; }
    }
}
