<?php
namespace App\Services;

use App\Core\Config;
use App\Core\Database;

/**
 * SONIKA Partners — programa único de distribuidores.
 * Atribución (último clic válido 30d) + Puntos SONIKA (valor + volumen).
 * La categoría interna (artist/influencer/natural) no altera la comisión.
 * No ejecuta pagos reales. Datos separados de pedidos históricos.
 */
class AffiliateService
{
    public const STATUSES = ['DRAFT', 'EMAIL_PENDING', 'PHONE_PENDING', 'DOCUMENTS_PENDING', 'REVIEW_REQUIRED', 'APPROVED', 'REJECTED', 'SUSPENDED', 'TERMINATED'];
    public const TTL_DAYS = 30;
    public const FORMULA_VERSION = 'puntos_sonika_v2';
    public const POINTS_PER_VALUE = 100000.0;
    public const POINTS_PER_ORDER = 1;
    public const MIN_ORDER_VALUE = 1000.0;
    public const OUTLET_RATE = 0.020;
    public const PREMIUM_ITEM_VALUE = 5000000.0;
    public const PREMIUM_POINTS = 20;
    public const STREAK_MIN_WEEKS = 4;
    public const STREAK_BONUS_TIER1 = 0.010;
    public const STREAK_BONUS_TIER2 = 0.020;
    public const CAP_BASE = 0.050;
    public const CAP_STREAK_TIER1 = 0.060;
    public const CAP_STREAK_TIER2 = 0.070;
    public const PROFILE_CATEGORIES = [
        'artist' => 'Artista',
        'influencer' => 'Influencer / creador',
        'natural' => 'Persona natural',
    ];
    // Puntos SONIKA: valor + ventas válidas + premium. La racha suma porcentaje aparte.
    public const POINT_TIERS = [
        [0, 9, 0.030],
        [10, 29, 0.035],
        [30, 59, 0.040],
        [60, 99, 0.045],
        [100, PHP_INT_MAX, 0.050],
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

    private function uniqueProfileSlug(string $base, string $email): string
    {
        $slug = $this->slugify($base);
        if ($slug === '') $slug = 'partner';
        try {
            for ($i = 0; $i < 20; $i++) {
                $candidate = $i === 0 ? $slug : $slug . '-' . substr(hash('sha256', $email . '|' . $i), 0, 4);
                $st = Database::instance()->prepare('SELECT 1 FROM affiliate_profiles WHERE site_id = @site_id AND slug = ? LIMIT 1');
                $st->execute([$candidate]);
                if (!$st->fetchColumn()) return $candidate;
            }
        } catch (\Throwable $e) {
        }
        return $slug . '-' . substr(bin2hex(random_bytes(3)), 0, 5);
    }

    public function log(?int $affiliateId, string $action, array $meta = []): void
    {
        try {
            Database::instance()->prepare('INSERT INTO affiliate_audit (site_id, affiliate_id, actor, action, meta, created_at) VALUES (@site_id, :a, :actor, :act, :m, NOW())')
                ->execute(['a' => $affiliateId, 'actor' => 'system', 'act' => $action, 'm' => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null]);
        } catch (\Throwable $e) {}
    }

    /** Marca un paso del wizard que no se puede deducir de los datos (ej. compartir enlace). */
    public function markWizardStep(int $affiliateId, string $step): array
    {
        if (!in_array($step, ['share'], true)) return ['ok' => false, 'message' => 'Paso no válido.'];
        $this->log($affiliateId, 'wizard_' . $step, []);
        return ['ok' => true];
    }

    public function hasWizardStep(int $affiliateId, string $step): bool
    {
        try {
            $st = Database::instance()->prepare('SELECT COUNT(*) FROM affiliate_audit WHERE site_id = @site_id AND affiliate_id = ? AND action = ?');
            $st->execute([$affiliateId, 'wizard_' . $step]);
            return (int)$st->fetchColumn() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function recoverRequest(string $email): array
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'message' => 'Correo inválido.'];
        $prof = $this->profileByEmail($email);
        if ($prof) $this->sendOtp($email);
        return ['ok' => true, 'message' => 'Si el correo está registrado, enviamos un código.'];
    }

    public function recoverConfirm(string $email, string $code, string $newPassword): array
    {
        $email = strtolower(trim($email));
        if (strlen($newPassword) < 8) return ['ok' => false, 'message' => 'La contraseña debe tener al menos 8 caracteres.'];
        $r = $this->verifyOtp($email, $code);
        if (empty($r['ok'])) return ['ok' => false, 'message' => 'Código incorrecto o vencido.'];
        $prof = $this->profileByEmail($email);
        if (!$prof) return ['ok' => false, 'message' => 'No encontrado.'];
        if (!$this->setPassword((int)$prof['id'], $newPassword)) return ['ok' => false, 'message' => 'No se pudo cambiar la contraseña.'];
        return ['ok' => true, 'message' => 'Contraseña actualizada. Ya puedes iniciar sesión.'];
    }

    public function mediaFor(int $affiliateId): array
    {
        try { $st = Database::instance()->prepare('SELECT * FROM affiliate_media WHERE affiliate_id = :a AND site_id = @site_id LIMIT 1'); $st->execute(['a' => $affiliateId]); return $st->fetch() ?: []; }
        catch (\Throwable $e) { return []; }
    }

    public function mediaSave(int $affiliateId, array $in): array
    {
        $url = function ($v) { $v = trim((string)$v); return ($v !== '' && preg_match('~^(https?://|/)~i', $v)) ? $v : ''; };
        $cur = $this->mediaFor($affiliateId);
        $has = function ($k) use ($in) { return array_key_exists($k, $in); };
        $str = function ($k, $d = '') use ($in, $cur) { return array_key_exists($k, $in) ? (string)$in[$k] : (string)($cur[$k] ?? $d); };
        $cover = $url($str('cover'));
        $avatar = $url($str('avatar'));
        $video = $url($str('video_url'));
        $tiktok = $url($str('tiktok'));
        $insta = preg_replace('/[^A-Za-z0-9_.]/', '', $str('instagram'));
        $hasGal = ($has('gallery0') || $has('gallery1') || $has('gallery2'));
        if ($hasGal) {
            $gallery = [];
            for ($i = 0; $i < 3; $i++) { $g = $url((string)($in['gallery' . $i] ?? '')); if ($g !== '') $gallery[] = $g; }
        } else {
            $gallery = json_decode((string)($cur['gallery'] ?? '[]'), true);
            if (!is_array($gallery)) $gallery = [];
            $gallery = array_slice(array_values(array_filter($gallery)), 0, 3);
        }
        $hasBio = array_key_exists('bio', $in);
        $bio = '';
        if ($hasBio) {
            $bio = strip_tags((string)$in['bio']);
            $bio = trim((string)preg_replace('/\s+/u', ' ', $bio));
            $bio = function_exists('mb_substr') ? mb_substr($bio, 0, 220) : substr($bio, 0, 220);
        }
        try {
            $db = Database::instance();
            $db->prepare('INSERT INTO affiliate_media (site_id, affiliate_id, cover, avatar, gallery, video_url, instagram, tiktok, updated_at) VALUES (@site_id, :a, :cover, :avatar, :gallery, :video, :insta, :tiktok, NOW()) ON DUPLICATE KEY UPDATE cover = VALUES(cover), avatar = VALUES(avatar), gallery = VALUES(gallery), video_url = VALUES(video_url), instagram = VALUES(instagram), tiktok = VALUES(tiktok)')
                ->execute(['a' => $affiliateId, 'cover' => $cover, 'avatar' => $avatar, 'gallery' => json_encode($gallery), 'video' => $video, 'insta' => strtolower((string)$insta), 'tiktok' => $tiktok]);
            if ($hasBio) {
                $st = $db->prepare('SELECT id FROM affiliate_stores WHERE affiliate_id = :a AND site_id = @site_id LIMIT 1');
                $st->execute(['a' => $affiliateId]);
                $sid = (int)($st->fetchColumn() ?: 0);
                if ($sid > 0) {
                    $db->prepare('UPDATE affiliate_stores SET bio = :bio WHERE id = :id')->execute(['bio' => $bio, 'id' => $sid]);
                } else {
                    $sg = $db->prepare('SELECT slug FROM affiliate_profiles WHERE id = :a AND site_id = @site_id LIMIT 1');
                    $sg->execute(['a' => $affiliateId]);
                    $slug = (string)($sg->fetchColumn() ?: ('artista-' . $affiliateId));
                    $db->prepare('INSERT INTO affiliate_stores (site_id, affiliate_id, slug, bio, is_active) VALUES (@site_id, :a, :slug, :bio, 1)')->execute(['a' => $affiliateId, 'slug' => $slug, 'bio' => $bio]);
                }
            }
            $this->log($affiliateId, 'media_saved');
            return ['ok' => true, 'message' => ($hasBio ? 'Perfil y medios guardados.' : 'Medios guardados.')];
        } catch (\Throwable $e) { return ['ok' => false, 'message' => 'No se pudieron guardar los medios.']; }
    }
    private function affLocal(string $p): string
    {
        if ($p === '' || strpos($p, '/assets/uploads/_artist/') !== 0) return '';
        return '/app/public' . $p;
    }

    public function mediaUpload(int $affiliateId, array $files, array $in): array
    {
        $quota = 5 * 1024 * 1024;
        $dir = '/app/public/assets/uploads/_artist/' . $affiliateId;
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $cur = $this->mediaFor($affiliateId);
        $used = 0;
        foreach (['cover', 'avatar'] as $k) { $lp = $this->affLocal((string)($cur[$k] ?? '')); if ($lp !== '' && is_file($lp)) $used += filesize($lp); }
        $gal = json_decode((string)($cur['gallery'] ?? '[]'), true);
        if (!is_array($gal)) $gal = [];
        foreach ($gal as $g) { $lp = $this->affLocal((string)$g); if ($lp !== '' && is_file($lp)) $used += filesize($lp); }
        $out = ['cover' => (string)($cur['cover'] ?? ''), 'avatar' => (string)($cur['avatar'] ?? ''), 'gallery' => $gal];
        $keys = ['cover' => 'cover', 'avatar' => 'avatar', 'gallery0' => 0, 'gallery1' => 1, 'gallery2' => 2];
        $okImg = false;
        foreach ($keys as $fk => $slot) {
            if (empty($files[$fk]) || !is_array($files[$fk]) || ($files[$fk]['error'] ?? 1) !== UPLOAD_ERR_OK) continue;
            $tmp = $files[$fk]['tmp_name'];
            $mime = function_exists('mime_content_type') ? (string)@mime_content_type($tmp) : '';
            if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) continue;
            $im = @imagecreatefromstring((string)@file_get_contents($tmp));
            if (!$im) continue;
            $w = imagesx($im); $h = imagesy($im); $max = 1600;
            if ($w > $max && $w > 0) { $nw = $max; $nh = (int)round($h * $nw / $w); $d = imagecreatetruecolor($nw, $nh); imagecopyresampled($d, $im, 0, 0, 0, 0, $nw, $nh, $w, $h); $im = $d; }
            $name = 'm' . bin2hex(random_bytes(6)) . '.webp';
            $dest = rtrim($dir, '/') . '/' . $name;
            @imagewebp($im, $dest, 82);
            $sz = is_file($dest) ? filesize($dest) : 0;
            if ($sz === 0 || $used + $sz > $quota) { @unlink($dest); continue; }
            $used += $sz;
            $okImg = true;
            $rel = '/assets/uploads/_artist/' . $affiliateId . '/' . $name;
            if ($slot === 'cover' || $slot === 'avatar') $out[$slot] = $rel;
            else $out['gallery'][(int)$slot] = $rel;
        }
        $out['gallery'] = array_values(array_filter($out['gallery']));
        $r = $this->mediaSave($affiliateId, [
            'video_url' => (string)($cur['video_url'] ?? ''), 'instagram' => (string)($cur['instagram'] ?? ''),
            'cover' => $out['cover'], 'avatar' => $out['avatar'],
            'gallery0' => $out['gallery'][0] ?? '', 'gallery1' => $out['gallery'][1] ?? '', 'gallery2' => $out['gallery'][2] ?? '',
        ]);
        if (!$r['ok']) return $r;
        return ['ok' => true, 'message' => $okImg ? 'Medios e imagenes guardados (optimizadas a WebP).' : 'Datos guardados.', 'used' => (int)$used, 'quota' => (int)$quota, 'media' => ['cover' => (string)$out['cover'], 'avatar' => (string)$out['avatar'], 'gallery' => array_values(array_filter($out['gallery']))]];
    }

    public function availableBalance(int $affiliateId): float
    {
        try {
            $st = Database::instance()->prepare("SELECT COALESCE(SUM(c.amount),0) FROM affiliate_commissions c LEFT JOIN affiliate_payout_items i ON i.commission_id = c.id AND i.site_id = c.site_id WHERE c.affiliate_id = :a AND c.site_id = @site_id AND c.status = 'APPROVED' AND i.id IS NULL");
            $st->execute(['a' => $affiliateId]);
            return (float)$st->fetchColumn();
        } catch (\Throwable $e) { return 0.0; }
    }

    public function payoutRequest(int $affiliateId): array
    {
        $min = 100000.0;
        $bal = $this->availableBalance($affiliateId);
        if ($bal < $min) return ['ok' => false, 'message' => 'Saldo mínimo para retirar: $ ' . number_format($min, 0, ',', '.') . '. Tu saldo disponible es $ ' . number_format($bal, 0, ',', '.') . '.'];
        $prof = $this->getById($affiliateId);
        if (!$prof) return ['ok' => false, 'message' => 'Perfil no encontrado.'];
        $this->sendOtp((string)$prof['email']);
        return ['ok' => true, 'otp' => true, 'message' => 'Te enviamos un código al correo para confirmar el retiro de $ ' . number_format($bal, 0, ',', '.') . '.'];
    }

    public function payoutConfirm(int $affiliateId, string $code): array
    {
        $prof = $this->getById($affiliateId);
        if (!$prof) return ['ok' => false, 'message' => 'Perfil no encontrado.'];
        $v = $this->verifyOtp((string)$prof['email'], $code);
        if (empty($v['ok'])) return ['ok' => false, 'message' => 'Código incorrecto o vencido.'];
        $bal = $this->availableBalance($affiliateId);
        if ($bal < 100000.0) return ['ok' => false, 'message' => 'Saldo insuficiente (mínimo $ 100.000).'];
        try {
            Database::instance()->prepare("INSERT INTO affiliate_payout_requests (site_id, affiliate_id, amount, status, created_at) VALUES (@site_id, :a, :m, 'requested', NOW())")->execute(['a' => $affiliateId, 'm' => $bal]);
            $this->log($affiliateId, 'payout_requested', ['amount' => $bal]);
            return ['ok' => true, 'message' => 'Retiro solicitado por $ ' . number_format($bal, 0, ',', '.') . '. Se procesará en el próximo corte.'];
        } catch (\Throwable $e) { return ['ok' => false, 'message' => 'No se pudo registrar el retiro.']; }
    }

    public function listPayoutRequests(int $limit = 100): array
    {
        try {
            return Database::instance()->query('SELECT r.*, p.artist_name, p.public_name, p.full_name FROM affiliate_payout_requests r LEFT JOIN affiliate_profiles p ON p.id = r.affiliate_id AND p.site_id = r.site_id WHERE r.site_id = @site_id ORDER BY r.id DESC LIMIT ' . max(1, min(300, $limit)))->fetchAll();
        } catch (\Throwable $e) { return []; }
    }

    public function payPayoutRequest(int $id, string $by = 'admin'): bool
    {
        try {
            $db = Database::instance();
            $st = $db->prepare("SELECT affiliate_id, amount FROM affiliate_payout_requests WHERE id = :id AND site_id = @site_id AND status = 'requested' LIMIT 1");
            $st->execute(['id' => $id]);
            $r = $st->fetch();
            if (!$r) return false;
            $db->prepare("UPDATE affiliate_payout_requests SET status = 'paid', paid_at = NOW(), note = :n WHERE id = :id AND site_id = @site_id")->execute(['id' => $id, 'n' => 'pagado por ' . $by]);
            $db->prepare("UPDATE affiliate_commissions SET status = 'PAID' WHERE affiliate_id = :a AND site_id = @site_id AND status = 'APPROVED'")->execute(['a' => (int)$r['affiliate_id']]);
            $this->log((int)$r['affiliate_id'], 'payout_request_paid', ['request' => $id, 'amount' => $r['amount'], 'by' => $by]);
            return true;
        } catch (\Throwable $e) { return false; }
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
        $category = strtolower(trim((string)($in['profile_category'] ?? '')));
        if ($category === '' && (!empty($in['artist_name']) || !empty($in['music_instrument']))) $category = 'artist';
        if ($category === '') $category = 'natural';
        if (!isset(self::PROFILE_CATEGORIES[$category])) $category = 'natural';
        $nameBase = $category === 'artist' ? (string)($in['artist_name'] ?? '') : '';
        $slug = $this->uniqueProfileSlug($nameBase !== '' ? $nameBase : (string)($in['public_name'] ?? $name), $email);
        try {
            $db = Database::instance();
            $platforms = $in['platforms'] ?? null;
            if (is_string($platforms)) $platforms = array_values(array_filter(array_map('trim', explode(',', $platforms))));
            $st = $db->prepare('INSERT INTO affiliate_profiles (site_id, email, password_hash, full_name, doc_type, doc_number, public_name, artist_name, slug, status, profile_category, niche, audience_size, platforms, verification_status, public_profile_enabled, payout_method, tax_id, music_experience, music_instrument, music_level, music_genre, music_desired, music_role, socials, created_at)
                VALUES (@site_id, :email, :ph, :name, :dt, :dn, :pn, :an, :slug, :status, :category, :niche, :audience, :platforms, "pending", :public_profile, :payout, :tax, :me, :mi, :ml, :mg, :md, :mr, :soc, NOW())');
            $st->execute([
                'email' => $email, 'ph' => $pass !== '' ? password_hash($pass, PASSWORD_DEFAULT) : null, 'name' => $name,
                'dt' => substr((string)($in['doc_type'] ?? ''), 0, 16),
                'dn' => substr((string)($in['doc_number'] ?? ''), 0, 60),
                'pn' => substr((string)($in['public_name'] ?? $name), 0, 120),
                'an' => substr((string)($in['artist_name'] ?? ''), 0, 120),
                'slug' => $slug,
                'status' => 'EMAIL_PENDING',
                'category' => $category,
                'niche' => isset($in['niche']) ? substr((string)$in['niche'], 0, 120) : null,
                'audience' => isset($in['audience_size']) ? substr((string)$in['audience_size'], 0, 60) : null,
                'platforms' => !empty($platforms) ? json_encode($platforms, JSON_UNESCAPED_UNICODE) : null,
                'public_profile' => $category === 'artist' ? 1 : 0,
                'payout' => isset($in['payout_method']) ? substr((string)$in['payout_method'], 0, 40) : null,
                'tax' => isset($in['tax_id']) ? substr((string)$in['tax_id'], 0, 40) : null,
                'me' => $in['music_experience'] ?? null, 'mi' => $in['music_instrument'] ?? null,
                'ml' => $in['music_level'] ?? null, 'mg' => $in['music_genre'] ?? null,
                'md' => $in['music_desired'] ?? null, 'mr' => $in['music_role'] ?? null,
                'soc' => !empty($in['socials']) ? json_encode($in['socials'], JSON_UNESCAPED_UNICODE) : null,
            ]);
            $id = (int)$db->lastInsertId();
            $this->log($id, 'profile_created', ['email' => $email, 'category' => $category, 'program' => 'sonika_partners']);
            if (!empty($in['accept_contract'])) {
                $c = $this->currentContract();
                if ($c) $this->acceptContract($id, (int)$c['id']);
            }
            // Confirmación de correo obligatoria por OTP al registrarse.
            $this->sendOtp($email);
            // Aviso interno a superadmins/admins por cada nuevo registro.
            $this->notifyNewArtist($id, $in, $email, $slug);
            return ['ok' => true, 'id' => $id, 'slug' => $slug, 'profile_category' => $category, 'program' => 'SONIKA Partners'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo crear (¿correo ya registrado?).'];
        }
    }

    /**
     * Envía un aviso interno (superadmins/admins del sitio y correos de operación)
     * por cada nuevo registro de artista. Nunca bloquea el registro.
     */
    private function notifyNewArtist(int $id, array $in, string $email, string $slug): void
    {
        try {
            $recipients = $this->notificationRecipients();
            if (!$recipients) {
                $this->log($id, 'admin_notify_skipped', ['reason' => 'sin_destinatarios']);
                return;
            }
            $esc = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
            $name = trim((string)($in['full_name'] ?? ''));
            $artist = trim((string)($in['artist_name'] ?? ''));
            $public = trim((string)($in['public_name'] ?? ''));
            $docType = trim((string)($in['doc_type'] ?? ''));
            $doc = preg_replace('/\D+/', '', (string)($in['doc_number'] ?? ''));
            $docMasked = $doc !== '' ? str_repeat('*', max(0, strlen($doc) - 4)) . substr($doc, -4) : '—';
            $status = 'EMAIL_PENDING';
            $createdAt = date('Y-m-d H:i:s');
            $host = (string)($_SERVER['HTTP_HOST'] ?? '');
            if (!preg_match('/^[a-z0-9.\-]+(:\d+)?$/i', $host)) $host = '';
            $base = $host !== '' ? 'https://' . $host : rtrim((string)Config::get('APP_URL', 'https://sonika.com.co'), '/');
            $adminUrl = $base . '/admin-afiliados.php';
            $subject = 'Nuevo registro Partner SONIKA — ' . ($artist !== '' ? $artist : ($name !== '' ? $name : $email));
            $text = "Nuevo registro en SONIKA Partners\n\n"
                . "Nombre completo: $name\n"
                . "Nombre artístico: " . ($artist !== '' ? $artist : '—') . "\n"
                . "Nombre público: " . ($public !== '' ? $public : '—') . "\n"
                . "Correo: $email\n"
                . "Documento: " . ($docType !== '' ? $docType . ' ' : '') . $docMasked . "\n"
                . "Instrumento: " . (string)($in['music_instrument'] ?? '—') . "\n"
                . "Nivel: " . (string)($in['music_level'] ?? '—') . "\n"
                . "Género: " . (string)($in['music_genre'] ?? '—') . "\n"
                . "Rol: " . (string)($in['music_role'] ?? '—') . "\n"
                . "Estado: $status\n"
                . "Fecha: $createdAt\n\n"
                . "Revisa y aprueba la solicitud aquí:\n$adminUrl\n";
            $html = '<div style="font-family:Inter,Arial,sans-serif;max-width:620px;margin:auto;background:#0b0b0b;color:#fff;border-radius:14px;padding:26px">'
                . '<div style="color:#F20D16;font-size:11px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;margin-bottom:10px">SONIKA · Partners</div>'
                . '<h2 style="margin:0 0 14px;font-size:20px;color:#fff">Nuevo registro Partner SONIKA</h2>'
                . '<table role="presentation" style="width:100%;border-collapse:collapse;font-size:13.5px;line-height:1.6;color:#d4d4d4">'
                . '<tr><td style="padding:4px 0;color:#9a9a9a;width:130px">Nombre</td><td style="padding:4px 0;color:#fff">' . $esc($name) . '</td></tr>'
                . '<tr><td style="padding:4px 0;color:#9a9a9a">Nombre artístico</td><td style="padding:4px 0;color:#fff">' . $esc($artist !== '' ? $artist : '—') . '</td></tr>'
                . '<tr><td style="padding:4px 0;color:#9a9a9a">Correo</td><td style="padding:4px 0"><a href="mailto:' . $esc($email) . '" style="color:#FF6B71">' . $esc($email) . '</a></td></tr>'
                . '<tr><td style="padding:4px 0;color:#9a9a9a">Documento</td><td style="padding:4px 0;color:#fff">' . $esc(($docType !== '' ? $docType . ' ' : '') . $docMasked) . '</td></tr>'
                . '<tr><td style="padding:4px 0;color:#9a9a9a">Instrumento</td><td style="padding:4px 0;color:#fff">' . $esc((string)($in['music_instrument'] ?? '—')) . '</td></tr>'
                . '<tr><td style="padding:4px 0;color:#9a9a9a">Nivel</td><td style="padding:4px 0;color:#fff">' . $esc((string)($in['music_level'] ?? '—')) . '</td></tr>'
                . '<tr><td style="padding:4px 0;color:#9a9a9a">Género</td><td style="padding:4px 0;color:#fff">' . $esc((string)($in['music_genre'] ?? '—')) . '</td></tr>'
                . '<tr><td style="padding:4px 0;color:#9a9a9a">Estado</td><td style="padding:4px 0;color:#fff">' . $esc($status) . '</td></tr>'
                . '<tr><td style="padding:4px 0;color:#9a9a9a">Fecha</td><td style="padding:4px 0;color:#fff">' . $esc($createdAt) . '</td></tr>'
                . '</table>'
                . '<p style="margin:22px 0 0"><a href="' . $esc($adminUrl) . '" style="display:inline-block;background:#F20D16;color:#fff;text-decoration:none;font-weight:700;padding:12px 18px;border-radius:9px">Revisar solicitud</a></p>'
                . '<p style="color:#8a8a8a;font-size:11px;margin:18px 0 0">Slug: ' . $esc($slug) . ' · Perfil #' . $id . '</p></div>';
            $sentTo = 0;
            $mailer = new EmailService();
            foreach ($recipients as $to) {
                try {
                    if ($mailer->sendMultipart($to, $subject, $text, $html, ['reply_to' => $email])) $sentTo++;
                } catch (\Throwable $e) {
                }
            }
            $this->log($id, $sentTo > 0 ? 'admin_notified' : 'admin_notify_failed', [
                'recipients' => count($recipients),
                'sent' => $sentTo,
            ]);
        } catch (\Throwable $e) {
            $this->log($id, 'admin_notify_failed', ['error' => 'exception']);
        }
    }

    /** Destinatarios internos: setting explícito, admins del sitio y correos de operación. */
    private function notificationRecipients(): array
    {
        $out = [];
        $add = static function ($value) use (&$out): void {
            foreach (preg_split('/[,;\s]+/', (string)$value) ?: [] as $email) {
                $email = strtolower(trim($email));
                if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) $out[$email] = $email;
            }
        };
        try {
            $db = Database::instance();
            $st = $db->prepare("SELECT `value` FROM settings WHERE site_id = @site_id AND `key` = 'affiliate_notify_email' LIMIT 1");
            $st->execute();
            $add($st->fetchColumn());
            $st = $db->prepare("SELECT email FROM users WHERE site_id = @site_id AND is_active = 1 AND role IN ('superadmin','admin') AND email <> ''");
            $st->execute();
            foreach ($st->fetchAll() as $row) $add($row['email']);
            if (!$out) {
                $st = $db->prepare("SELECT `key`, `value` FROM settings WHERE site_id = @site_id AND `key` IN ('store_notify_email','contact_notify_email','support_notify_email','pqrf_admin_email')");
                $st->execute();
                foreach ($st->fetchAll() as $row) $add($row['value']);
            }
        } catch (\Throwable $e) {
        }
        return array_values($out);
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

    /** Perfil por slug sin filtro de estado: solo para el panel privado del artista autenticado. */
    public function getBySlugAny(string $slug): ?array
    {
        try { $st = Database::instance()->prepare('SELECT * FROM affiliate_profiles WHERE slug = :s AND site_id = @site_id LIMIT 1'); $st->execute(['s' => $slug]); return $st->fetch() ?: null; }
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

    /** Ruta pública del partner: página de artista si la tiene; si no, tienda. */
    public function storePath(array $affiliate): string
    {
        $slug = trim((string)($affiliate['slug'] ?? ''));
        $category = (string)($affiliate['profile_category'] ?? 'natural');
        $public = (int)($affiliate['public_profile_enabled'] ?? 0) === 1;
        if ($category === 'artist' && $public && $slug !== '') return '/artista/' . rawurlencode($slug);
        return '/tienda';
    }

    public function ensureLink(int $affiliateId): string
    {
        try {
            $db = Database::instance();
            $aff = $this->getById($affiliateId);
            $target = $aff ? $this->storePath($aff) : '/artistas';
            $st = $db->prepare('SELECT id, code, target_url FROM affiliate_links WHERE affiliate_id = :a AND site_id = @site_id LIMIT 1');
            $st->execute(['a' => $affiliateId]);
            $row = $st->fetch();
            if ($row) {
                if ((string)$row['target_url'] !== $target) {
                    $db->prepare('UPDATE affiliate_links SET target_url = :t WHERE id = :id AND site_id = @site_id')
                        ->execute(['t' => $target, 'id' => (int)$row['id']]);
                }
                return (string)$row['code'];
            }
            $code = substr(bin2hex(random_bytes(5)), 0, 10);
            $db->prepare('INSERT INTO affiliate_links (site_id, affiliate_id, code, target_url, created_at) VALUES (@site_id, :a, :c, :t, NOW())')
                ->execute(['a' => $affiliateId, 'c' => $code, 't' => $target]);
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
            $storePath = $this->storePath($aff);
            if ((string)($link['target_url'] ?? '') !== $storePath) {
                $db->prepare('UPDATE affiliate_links SET target_url = :t WHERE id = :id AND site_id = @site_id')
                    ->execute(['t' => $storePath, 'id' => (int)$link['id']]);
            }
            return ['affiliate_id' => (int)$aff['id'], 'code' => $code, 'artist' => $aff['artist_name'] ?: $aff['public_name'] ?: $aff['full_name'], 'slug' => $aff['slug'], 'store_path' => $storePath];
        } catch (\Throwable $e) { return null; }
    }

    /** Tasa según puntos SONIKA. */
    public function rateForPoints(float $points): float
    {
        $points = max(0.0, $points);
        foreach (self::POINT_TIERS as [$lo, $hi, $rate]) {
            if ($points >= $lo && $points <= $hi) return (float)$rate;
        }
        return 0.05;
    }

    /** Puntos, ventas, pedidos, racha y tasa final del mes del partner. */
    public function pointsForMonth(int $affiliateId, string $monthKey = ''): array
    {
        $monthKey = $monthKey !== '' ? $monthKey : date('Y-m');
        $default = [
            'month' => $monthKey, 'sales' => 0.0, 'orders' => 0, 'weeks' => 0,
            'premium_orders' => 0, 'premium_points' => 0, 'points' => 0.0,
            'base_rate' => $this->rateForPoints(0), 'streak_level' => 0,
            'streak_bonus' => 0.0, 'cap' => self::CAP_BASE, 'rate' => $this->rateForPoints(0),
        ];
        try {
            $st = Database::instance()->prepare("SELECT
                COALESCE(SUM(base_amount), 0) sales,
                COALESCE(SUM(CASE WHEN base_amount >= :min_order THEN 1 ELSE 0 END), 0) orders,
                COALESCE(SUM(CASE WHEN base_amount >= :min_order2 AND max_item_price >= :premium_value THEN 1 ELSE 0 END), 0) premium_orders,
                COUNT(DISTINCT CASE WHEN base_amount >= :min_order3 THEN FLOOR((DAY(created_at) - 1) / 7) END) weeks
                FROM affiliate_commissions
                WHERE site_id = @site_id AND affiliate_id = :a
                AND COALESCE(month_key, DATE_FORMAT(created_at, '%Y-%m')) = :month
                AND status NOT IN ('REVERSED','REJECTED')");
            $st->execute([
                'a' => $affiliateId, 'month' => $monthKey,
                'min_order' => self::MIN_ORDER_VALUE,
                'min_order2' => self::MIN_ORDER_VALUE,
                'min_order3' => self::MIN_ORDER_VALUE,
                'premium_value' => self::PREMIUM_ITEM_VALUE,
            ]);
            $row = $st->fetch() ?: [];
            $sales = (float)($row['sales'] ?? 0);
            $orders = (int)($row['orders'] ?? 0);
            $weeks = (int)($row['weeks'] ?? 0);
            $premiumOrders = (int)($row['premium_orders'] ?? 0);
            $premiumPoints = $premiumOrders * self::PREMIUM_POINTS;
            $points = floor($sales / self::POINTS_PER_VALUE) + ($orders * self::POINTS_PER_ORDER) + $premiumPoints;
            $baseRate = $this->rateForPoints((float)$points);
            $streakLevel = 0;
            $bonus = 0.0;
            $cap = self::CAP_BASE;
            if ($weeks >= self::STREAK_MIN_WEEKS && $orders >= 6) {
                $streakLevel = 2;
                $bonus = self::STREAK_BONUS_TIER2;
                $cap = self::CAP_STREAK_TIER2;
            } elseif ($weeks >= self::STREAK_MIN_WEEKS && $orders >= 4) {
                $streakLevel = 1;
                $bonus = self::STREAK_BONUS_TIER1;
                $cap = self::CAP_STREAK_TIER1;
            }
            $rate = min($baseRate + $bonus, $cap);
            return [
                'month' => $monthKey, 'sales' => $sales, 'orders' => $orders, 'weeks' => $weeks,
                'premium_orders' => $premiumOrders, 'premium_points' => $premiumPoints,
                'points' => (float)$points, 'base_rate' => $baseRate,
                'streak_level' => $streakLevel, 'streak_bonus' => $bonus,
                'cap' => $cap, 'rate' => $rate,
            ];
        } catch (\Throwable $e) {
            return $default;
        }
    }

    /** Recalcula retroactivamente todas las comisiones del mes con la tasa final. */
    public function recalculateMonth(int $affiliateId, string $monthKey = ''): array
    {
        $calc = $this->pointsForMonth($affiliateId, $monthKey);
        try {
            $db = Database::instance();
            $st = $db->prepare("UPDATE affiliate_commissions SET
                tier_rate = :rate,
                amount = ROUND((base_amount - outlet_amount) * :rate2 + outlet_amount * :outlet_rate, 2),
                points = :points,
                formula_version = :formula
                WHERE site_id = @site_id AND affiliate_id = :a
                AND COALESCE(month_key, DATE_FORMAT(created_at, '%Y-%m')) = :month
                AND status NOT IN ('REVERSED','REJECTED')");
            $st->execute([
                'rate' => $calc['rate'], 'rate2' => $calc['rate'], 'points' => (int)$calc['points'],
                'outlet_rate' => self::OUTLET_RATE,
                'formula' => self::FORMULA_VERSION, 'a' => $affiliateId, 'month' => $calc['month'],
            ]);
            $this->log($affiliateId, 'month_recalculated', $calc);
            return $calc + ['updated' => $st->rowCount()];
        } catch (\Throwable $e) {
            return $calc + ['updated' => 0];
        }
    }

    public function monthSales(int $affiliateId): float
    {
        return (float)$this->pointsForMonth($affiliateId)['sales'];
    }

    /** Fija la atribución de un pedido + comisión con Puntos SONIKA (idempotente). */
    public function attributeOrder(string $orderRef, float $baseAmount, ?int $affiliateId = null, ?string $code = null, float $outletAmount = 0.0, float $maxItemPrice = 0.0): ?array
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
            $monthKey = date('Y-m');
            $baseAmount = max(0.0, $baseAmount);
            $outletAmount = max(0.0, min($baseAmount, $outletAmount));
            $maxItemPrice = max(0.0, $maxItemPrice);
            $premiumPoints = $maxItemPrice >= self::PREMIUM_ITEM_VALUE ? self::PREMIUM_POINTS : 0;
            $db->prepare('INSERT IGNORE INTO affiliate_order_attributions (site_id, order_ref, affiliate_id, code, model, fixed_at) VALUES (@site_id, :o, :a, :c, "last_click_30d", NOW())')
                ->execute(['o' => $orderRef, 'a' => $affiliateId, 'c' => $code]);
            $db->prepare('INSERT IGNORE INTO affiliate_commissions (site_id, affiliate_id, order_ref, base_amount, outlet_amount, max_item_price, tier_rate, amount, status, month_key, formula_version, meta, created_at) VALUES (@site_id, :a, :o, :b, :outlet, :max_item, 0, 0, "ESTIMATED", :month, :formula, :m, NOW())')
                ->execute([
                    'a' => $affiliateId, 'o' => $orderRef, 'b' => $baseAmount, 'outlet' => $outletAmount,
                    'max_item' => $maxItemPrice, 'month' => $monthKey,
                    'formula' => self::FORMULA_VERSION,
                    'm' => json_encode(['program' => 'sonika_partners', 'formula' => self::FORMULA_VERSION, 'min_order' => self::MIN_ORDER_VALUE, 'outlet_amount' => $outletAmount, 'outlet_rate' => self::OUTLET_RATE, 'max_item_price' => $maxItemPrice, 'premium_points' => $premiumPoints], JSON_UNESCAPED_UNICODE),
                ]);
            $calc = $this->recalculateMonth($affiliateId, $monthKey);
            $st = $db->prepare('SELECT amount, tier_rate, points, outlet_amount, max_item_price FROM affiliate_commissions WHERE site_id = @site_id AND order_ref = :o LIMIT 1');
            $st->execute(['o' => $orderRef]);
            $row = $st->fetch() ?: [];
            $amount = (float)($row['amount'] ?? 0);
            $rate = (float)($row['tier_rate'] ?? $calc['rate']);
            $this->log($affiliateId, 'order_attributed', ['order' => $orderRef, 'amount' => $amount, 'rate' => $rate, 'points' => $calc['points'], 'month' => $monthKey, 'outlet_amount' => (float)($row['outlet_amount'] ?? $outletAmount), 'max_item_price' => (float)($row['max_item_price'] ?? $maxItemPrice), 'premium_points' => $premiumPoints, 'simulation' => true]);
            return [
                'ok' => true, 'affiliate_id' => $affiliateId, 'commission' => $amount, 'rate' => $rate,
                'base_rate' => $calc['base_rate'], 'streak_level' => $calc['streak_level'], 'streak_bonus' => $calc['streak_bonus'],
                'cap' => $calc['cap'], 'weeks' => $calc['weeks'], 'points' => $calc['points'], 'orders' => $calc['orders'],
                'month_sales' => $calc['sales'], 'premium_points' => $calc['premium_points'],
                'outlet_amount' => (float)($row['outlet_amount'] ?? $outletAmount), 'outlet_rate' => self::OUTLET_RATE,
                'max_item_price' => (float)($row['max_item_price'] ?? $maxItemPrice),
                'simulation' => true, 'formula' => self::FORMULA_VERSION,
            ];
        } catch (\Throwable $e) { return null; }
    }

    /** Revierte la comisión de un pedido anulado, devuelto o fraudulento. */
    public function reverseOrder(string $orderRef, string $reason = 'Reverso de pedido'): bool
    {
        try {
            $db = Database::instance();
            $st = $db->prepare('SELECT affiliate_id, month_key FROM affiliate_commissions WHERE site_id = @site_id AND order_ref = :o LIMIT 1');
            $st->execute(['o' => $orderRef]);
            $row = $st->fetch();
            if (!$row) return false;
            $db->prepare("UPDATE affiliate_commissions SET status = 'REVERSED', meta = JSON_SET(COALESCE(meta, '{}'), '$.reversal_reason', :r), amount = 0 WHERE site_id = @site_id AND order_ref = :o")
                ->execute(['o' => $orderRef, 'r' => mb_substr($reason, 0, 200)]);
            $month = (string)($row['month_key'] ?? date('Y-m'));
            $this->recalculateMonth((int)$row['affiliate_id'], $month);
            $this->log((int)$row['affiliate_id'], 'order_reversed', ['order' => $orderRef, 'reason' => $reason]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
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

    public function isDemoProfile(int $affiliateId): bool
    {
        try {
            $st = Database::instance()->prepare("SELECT slug FROM affiliate_profiles WHERE id = :id AND site_id = @site_id LIMIT 1");
            $st->execute(['id' => $affiliateId]);
            return (string)$st->fetchColumn() === 'demo-artist';
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function demoDashboard(): array
    {
        return [
            'demo' => true,
            'clicks' => 1248,
            'sales' => 18450000.0,
            'commission_total' => 922500.0,
            'by_status' => [
                'ESTIMATED' => ['amount' => 185000.0, 'count' => 4],
                'PENDING_VALIDATION' => ['amount' => 92500.0, 'count' => 2],
                'PAYMENT_QUEUED' => ['amount' => 75000.0, 'count' => 1],
                'APPROVED' => ['amount' => 410000.0, 'count' => 5],
                'PAID' => ['amount' => 160000.0, 'count' => 2],
            ],
            'recent' => [
                ['order_ref' => 'PED-2610-1048', 'base_amount' => 1249000.0, 'tier_rate' => 0.05, 'amount' => 62450.0, 'status' => 'APPROVED', 'created_at' => '2026-10-24 16:42:00'],
                ['order_ref' => 'PED-2610-1031', 'base_amount' => 689000.0, 'tier_rate' => 0.05, 'amount' => 34450.0, 'status' => 'PAID', 'created_at' => '2026-10-23 11:18:00'],
                ['order_ref' => 'PED-2610-1017', 'base_amount' => 2199000.0, 'tier_rate' => 0.05, 'amount' => 109950.0, 'status' => 'PENDING_VALIDATION', 'created_at' => '2026-10-21 18:06:00'],
                ['order_ref' => 'PED-2610-1004', 'base_amount' => 459000.0, 'tier_rate' => 0.05, 'amount' => 22950.0, 'status' => 'PAYMENT_QUEUED', 'created_at' => '2026-10-19 09:35:00'],
                ['order_ref' => 'PED-2609-0992', 'base_amount' => 1899000.0, 'tier_rate' => 0.05, 'amount' => 94950.0, 'status' => 'ESTIMATED', 'created_at' => '2026-09-28 15:12:00'],
                ['order_ref' => 'PED-2609-0981', 'base_amount' => 899000.0, 'tier_rate' => 0.05, 'amount' => 44950.0, 'status' => 'PAID', 'created_at' => '2026-09-26 12:04:00'],
            ],
            'league' => [
                'level' => 'Corchea',
                'code' => 'corchea',
                'sales' => 18450000.0,
                'clicks' => 1248,
                'next' => 'Negra',
                'next_min' => 25000000.0,
                'progress' => 74,
            ],
        ];
    }

    private function demoSalesStats(): array
    {
        return [
            'orders' => 38,
            'clicks' => 1248,
            'conversion' => 3.0,
            'avg_ticket' => 485526.0,
            'top_sold' => [
                ['name' => 'GUITARRA ELECTRICA FENDER AM PRO II STRATOCASTER BLANCO P/ROSA MOD0113910705', 'qty' => 1, 'amt' => 8199000],
                ['name' => 'BAJO ELEC GRETSCH STRML JET CLB BASS', 'qty' => 2, 'amt' => 3598000],
                ['name' => 'AUDIFONO SHURE SRH240', 'qty' => 12, 'amt' => 1428000],
                ['name' => 'ACORDEON HOHNER REY VALLENATO ADG ROJO A4904S', 'qty' => 2, 'amt' => 3200000],
                ['name' => 'PAR LED BIG DIPPER LP005', 'qty' => 8, 'amt' => 1600000],
            ],
            'top_viewed' => [
                ['page_url' => '/producto/promo-aniversario-guitarra-electrica-fender-american-profesional-ii-stratocaster-hss-color-owt-mod0113910705-diapason-en-palo-de-rosa', 'v' => 186],
                ['page_url' => '/producto/bajo-elec-gretsch-strml-jet-clb-bass', 'v' => 142],
                ['page_url' => '/producto/aud014', 'v' => 97],
                ['page_url' => '/producto/acordeon-hohner-rey-vallenato', 'v' => 84],
                ['page_url' => '/producto/parl08', 'v' => 61],
            ],
        ];
    }

    public function dashboard(int $affiliateId): array
    {
        if ($this->isDemoProfile($affiliateId)) return $this->demoDashboard();
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
        try {
            (new \App\Services\ArtistContractService())->ensure();
            $st = Database::instance()->query("SELECT * FROM affiliate_contracts WHERE site_id = @site_id AND code = 'sonika_partners' AND status = 'published' ORDER BY id DESC LIMIT 1");
            $row = $st->fetch();
            if ($row) return $row;
            $st = Database::instance()->query("SELECT * FROM affiliate_contracts WHERE site_id = @site_id AND code = 'artist_partners' AND status = 'published' ORDER BY id DESC LIMIT 1");
            return $st->fetch() ?: null;
        } catch (\Throwable $e) { return null; }
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
                    $sent = $es->sendHtml($email, 'Tu código de verificación — SONIKA Partners', '<div style="font-family:Arial,sans-serif"><p>Tu código de verificación es:</p><p style="font-size:28px;font-weight:800;letter-spacing:4px">' . $code . '</p><p>Vence en 10 minutos. Si no lo solicitaste, ignora este correo.</p></div>');
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

    public function cvSave(int $affiliateId, array $in): array
    {
        $bio = $this->cvBioHtml((string)($in['bio'] ?? ''));
        $years = preg_replace('/[^0-9]/', '', trim((string)($in['experience_years'] ?? '')));
        $years = $years === '' ? '' : (string)min(60, (int)$years);
        $list = function ($v) {
            $items = is_array($v) ? $v : (preg_split('/\r\n|\r|\n|·/', (string)$v) ?: []);
            $out = [];
            foreach ($items as $it) {
                $it = trim((string)preg_replace('/\s+/u', ' ', strip_tags((string)$it)));
                if ($it === '') continue;
                $out[] = function_exists('mb_substr') ? mb_substr($it, 0, 80) : substr($it, 0, 80);
            }
            return array_slice(array_values(array_unique($out)), 0, 8);
        };
        $bands = $list($in['bands'] ?? []);
        $ach = $list($in['achievements'] ?? []);
        try {
            Database::instance()->prepare('INSERT INTO affiliate_cv (site_id, affiliate_id, bio, experience_years, bands, achievements, updated_at) VALUES (@site_id, :a, :bio, :y, :b, :ach, NOW()) ON DUPLICATE KEY UPDATE bio = VALUES(bio), experience_years = VALUES(experience_years), bands = VALUES(bands), achievements = VALUES(achievements)')
                ->execute(['a' => $affiliateId, 'bio' => $bio, 'y' => $years, 'b' => json_encode($bands), 'ach' => json_encode($ach)]);
            $this->log($affiliateId, 'cv_saved');
            return ['ok' => true, 'message' => 'Trayectoria guardada.', 'experience_years' => $years, 'bands' => $bands, 'achievements' => $ach];
        } catch (\Throwable $e) { return ['ok' => false, 'message' => 'No se pudo guardar la trayectoria.']; }
    }

    public function cvBioHtml(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') return '';
        if (!preg_match('~<(p|div|br|strong|b|em|i|u|h[2-4]|ul|ol|li|blockquote|a)\b~i', $raw)) {
            $raw = $this->markdownLite($raw);
        }
        return $this->sanitizeCvHtml($raw);
    }

    private function markdownLite(string $raw): string
    {
        $t = htmlspecialchars($raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $t = preg_replace('~\r\n?~', "\n", $t);
        $out = ''; $list = ''; $para = [];
        $flushPara = function () use (&$para, &$out) { if ($para) { $out .= '<p>' . implode('<br>', $para) . '</p>'; $para = []; } };
        $closeList = function () use (&$list, &$out) { if ($list !== '') { $out .= '</' . $list . '>'; $list = ''; } };
        foreach (explode("\n", $t) as $ln) {
            $line = trim($ln);
            $line = preg_replace('~\*\*(.+?)\*\*~u', '<strong>$1</strong>', $line);
            $line = preg_replace('~(?<!\*)\*(?!\*)([^*]+?)(?<!\*)\*(?!\*)~u', '<em>$1</em>', $line);
            if ($line === '') { $flushPara(); $closeList(); continue; }
            if (preg_match('~^#{1,4}\s+(.+)$~u', $line, $m)) { $flushPara(); $closeList(); $out .= '<h3>' . $m[1] . '</h3>'; continue; }
            if (preg_match('~^[-–•*]\s+(.+)$~u', $line, $m)) { $flushPara(); if ($list !== 'ul') { $closeList(); $out .= '<ul>'; $list = 'ul'; } $out .= '<li>' . $m[1] . '</li>'; continue; }
            if (preg_match('~^\d+[.)]\s+(.+)$~u', $line, $m)) { $flushPara(); if ($list !== 'ol') { $closeList(); $out .= '<ol>'; $list = 'ol'; } $out .= '<li>' . $m[1] . '</li>'; continue; }
            $closeList();
            $para[] = $line;
        }
        $flushPara(); $closeList();
        return $out;
    }

    private function sanitizeCvHtml(string $html): string
    {
        if ($html === '') return '';
        $html = preg_replace('~<(script|style|iframe|object|embed)\b.*?</\1>~is', '', $html);
        $html = preg_replace('~<!--.*?-->~s', '', (string)$html);
        $allowed = '<p><br><strong><b><em><i><u><h2><h3><h4><ul><ol><li><blockquote><a><div><span>';
        $html = strip_tags((string)$html, $allowed);
        $html = preg_replace_callback('~<a\b[^>]*>~i', function ($m) {
            if (preg_match('~href\s*=\s*["\']?([^"\'\s>]+)~i', $m[0], $h)) {
                $u = trim($h[1]);
                if (preg_match('~^(https?://|/)~i', $u)) {
                    return '<a href="' . htmlspecialchars($u, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" rel="nofollow noopener" target="_blank">';
                }
            }
            return '<a>';
        }, (string)$html);
        $html = preg_replace('~<(p|br|strong|b|em|i|u|h2|h3|h4|ul|ol|li|blockquote|div|span)\b[^>]*>~i', '<$1>', (string)$html);
        $html = preg_replace('~<br\s*/?>~i', '<br>', (string)$html);
        $html = preg_replace('~(<br>\s*){3,}~i', '<br><br>', (string)$html);
        $html = (string)$html;
        return trim(function_exists('mb_substr') ? mb_substr($html, 0, 8000) : substr($html, 0, 8000));
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

    public function salesStats(int $affiliateId): array
    {
        if ($this->isDemoProfile($affiliateId)) return $this->demoSalesStats();
        $out = ['orders' => 0, 'clicks' => 0, 'conversion' => 0.0, 'avg_ticket' => 0.0, 'top_sold' => [], 'top_viewed' => []];
        try {
            $db = Database::instance();
            $st = $db->prepare('SELECT COUNT(*) FROM affiliate_order_attributions WHERE affiliate_id = :a AND site_id = @site_id');
            $st->execute(['a' => $affiliateId]);
            $out['orders'] = (int)$st->fetchColumn();
            $st = $db->prepare('SELECT COUNT(*) FROM affiliate_clicks WHERE affiliate_id = :a AND site_id = @site_id AND is_bot = 0');
            $st->execute(['a' => $affiliateId]);
            $out['clicks'] = (int)$st->fetchColumn();
            $out['conversion'] = $out['clicks'] > 0 ? round($out['orders'] / $out['clicks'] * 100, 1) : 0.0;
            $st = $db->prepare('SELECT COALESCE(SUM(base_amount),0) FROM affiliate_commissions WHERE affiliate_id = :a AND site_id = @site_id AND status <> "REVERSED"');
            $st->execute(['a' => $affiliateId]);
            $sales = (float)$st->fetchColumn();
            $out['avg_ticket'] = $out['orders'] > 0 ? round($sales / $out['orders'], 0) : 0.0;
            try {
                $st = $db->prepare('SELECT i.name, SUM(i.qty) qty, SUM(i.subtotal_cents)/100 amt FROM affiliate_order_attributions a JOIN store_orders o ON o.uuid = a.order_ref AND o.site_id = a.site_id JOIN store_order_items i ON i.order_id = o.id AND i.site_id = o.site_id WHERE a.affiliate_id = :a AND a.site_id = @site_id GROUP BY i.name ORDER BY qty DESC LIMIT 10');
                $st->execute(['a' => $affiliateId]);
                $out['top_sold'] = $st->fetchAll();
            } catch (\Throwable $e) {}
            try {
                $st = $db->prepare('SELECT e.page_url, COUNT(*) v FROM seo_tracking_events e WHERE e.site_id = @site_id AND e.type = "page_view" AND e.page_url LIKE "/producto/%" AND e.session_id IN (SELECT session_id FROM affiliate_clicks WHERE affiliate_id = :a AND site_id = @site_id AND session_id <> "") GROUP BY e.page_url ORDER BY v DESC LIMIT 10');
                $st->execute(['a' => $affiliateId]);
                $out['top_viewed'] = $st->fetchAll();
            } catch (\Throwable $e) {}
        } catch (\Throwable $e) {}
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
