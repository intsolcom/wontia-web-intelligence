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

    public function createProfile(array $in): array
    {
        if (!$this->ready()) return ['ok' => false, 'message' => 'Tablas de afiliados no inicializadas.'];
        $email = strtolower(trim((string)($in['email'] ?? '')));
        $name = trim((string)($in['full_name'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'message' => 'Correo inválido.'];
        if ($name === '') return ['ok' => false, 'message' => 'Nombre obligatorio.'];
        $slug = $this->slugify((string)($in['artist_name'] ?? $in['public_name'] ?? $name));
        try {
            $db = Database::instance();
            $st = $db->prepare('INSERT INTO affiliate_profiles (site_id, email, full_name, doc_type, doc_number, public_name, artist_name, slug, status, music_experience, music_instrument, music_level, music_genre, music_desired, music_role, socials, created_at)
                VALUES (@site_id, :email, :name, :dt, :dn, :pn, :an, :slug, :status, :me, :mi, :ml, :mg, :md, :mr, :soc, NOW())');
            $st->execute([
                'email' => $email, 'name' => $name,
                'dt' => substr((string)($in['doc_type'] ?? ''), 0, 16),
                'dn' => substr((string)($in['doc_number'] ?? ''), 0, 60),
                'pn' => substr((string)($in['public_name'] ?? $name), 0, 120),
                'an' => substr((string)($in['artist_name'] ?? ''), 0, 120),
                'slug' => $slug,
                'status' => 'DRAFT',
                'me' => $in['music_experience'] ?? null, 'mi' => $in['music_instrument'] ?? null,
                'ml' => $in['music_level'] ?? null, 'mg' => $in['music_genre'] ?? null,
                'md' => $in['music_desired'] ?? null, 'mr' => $in['music_role'] ?? null,
                'soc' => !empty($in['socials']) ? json_encode($in['socials'], JSON_UNESCAPED_UNICODE) : null,
            ]);
            $id = (int)$db->lastInsertId();
            $this->log($id, 'profile_created', ['email' => $email]);
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
            $st = Database::instance()->prepare('SELECT full_name, artist_name, public_name, status, doc_number, created_at FROM affiliate_profiles WHERE email = :e AND site_id = @site_id LIMIT 1');
            $st->execute(['e' => $email]);
            $r = $st->fetch();
            if (!$r) return null;
            $docDb = preg_replace('/\D/', '', (string)($r['doc_number'] ?? ''));
            if ($docDb === '' || substr($docDb, -4) !== substr($doc, -4)) return null;
            return [
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
        return $out;
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
