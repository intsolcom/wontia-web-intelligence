<?php
namespace App\Controllers\Admin;

use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Middleware\AuthMiddleware;
use App\Services\AuthSecurityService;
use App\Services\TwoFactorService;

class AuthController
{
    public function login(Request $req): void
    {
        (new AuthSecurityService())->ensureTables();
        $username = trim((string)$req->input('username', ''));
        $password = (string)$req->input('password', '');

        if ($username === '' || $password === '') {
            Response::error('Username and password are required', 400);
        }

        $ip = $req->ip();
        $lock = $this->throttleCheck($ip, $username);
        if ($lock !== null) {
            usleep(400000);
            Response::error($lock, 429);
        }

        $db = Database::instance();
        $stmt = $db->prepare("SELECT * FROM users WHERE (username = :u1 OR email = :u2) AND is_active = 1 AND site_id = @site_id LIMIT 1");
        $stmt->execute(['u1' => $username, 'u2' => $username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $this->throttleFail($ip, $username);
            (new AuthSecurityService())->audit('login_failed', 'error', $user['id'] ?? null, $user['email'] ?? $username, [], $ip, $req->userAgent());
            usleep(400000);
            Response::error('Invalid credentials', 401);
        }

        // Segundo factor (TOTP / código de recuperación)
        if ((int)($user['twofa_enabled'] ?? 0) === 1 && !empty($user['twofa_secret'])) {
            $this->throttleClear($ip, $username);
            (new AuthSecurityService())->audit('login_2fa_challenge', 'ok', (int)$user['id'], (string)$user['email'], [], $ip, $req->userAgent());
            $challenge = AuthMiddleware::generatePurposeToken('2fa', [
                'sub' => (int)$user['id'],
                'email' => (string)$user['email'],
                'site_id' => (int)$user['site_id'],
            ], 300);
            Response::json([
                'ok' => false,
                'twofa_required' => true,
                'challenge' => $challenge,
                'message' => 'Introduce el código de tu aplicación de autenticación.',
            ]);
        }

        $this->throttleClear($ip, $username);
        $this->completeLogin($user, $req, 'login');
    }

    private function completeLogin(array $user, Request $req, string $event): void
    {
        $db = Database::instance();
        $db->prepare("UPDATE users SET last_login = NOW() WHERE id = :id")->execute(['id' => $user['id']]);

        Session::login([
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role'],
            'site_id' => $user['site_id'],
        ]);

        $token = AuthMiddleware::generateJwt($user);
        (new AuthSecurityService())->audit($event, 'ok', (int)$user['id'], (string)$user['email'], ['twofa' => !empty($user['twofa_enabled'])], $req->ip(), $req->userAgent());

        Response::json([
            'ok' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role'],
                'twofa_enabled' => (int)($user['twofa_enabled'] ?? 0) === 1,
            ],
            'token' => $token,
        ]);
    }

    // ─────────────────────────────────────────────
    // 2FA — verificación durante el login (público, requiere challenge)
    // ─────────────────────────────────────────────

    public function twofaVerify(Request $req): void
    {
        (new AuthSecurityService())->ensureTables();
        $challenge = (string)$req->input('challenge', '');
        $code = (string)$req->input('code', '');
        $recovery = (bool)$req->input('recovery', false);

        $claims = AuthMiddleware::validatePurposeToken($challenge, '2fa');
        if (!$claims) {
            Response::error('El desafío expiró. Vuelve a iniciar sesión.', 401);
        }

        $db = Database::instance();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = :id AND site_id = @site_id AND is_active = 1 LIMIT 1");
        $stmt->execute(['id' => (int)$claims['sub']]);
        $user = $stmt->fetch();
        if (!$user) Response::error('Usuario no válido', 401);

        $sec = new AuthSecurityService();
        $secret = TwoFactorService::decrypt((string)($user['twofa_secret'] ?? ''));

        $ok = false;
        if ($recovery) {
            $res = TwoFactorService::verifyRecoveryCode((string)($user['twofa_recovery'] ?? '[]'), $code);
            if ($res['ok'] ?? false) {
                $ok = true;
                $db->prepare('UPDATE users SET twofa_recovery = :r WHERE id = :id')->execute(['r' => $res['remaining'], 'id' => (int)$user['id']]);
            }
        } else {
            $ok = $secret !== '' && TwoFactorService::verify($secret, $code);
        }

        if (!$ok) {
            $sec->audit('2fa_failed', 'error', (int)$user['id'], (string)$user['email'], ['recovery' => $recovery], $req->ip(), $req->userAgent());
            usleep(300000);
            Response::error('Código incorrecto.', 401);
        }

        $this->completeLogin($user, $req, 'login_2fa');
    }

    // ─────────────────────────────────────────────
    // 2FA — configuración (autenticado)
    // ─────────────────────────────────────────────

    public function twofaStatus(): void
    {
        (new AuthSecurityService())->ensureTables();
        $uid = (int)Session::userId();
        $db = Database::instance();
        $stmt = $db->prepare('SELECT twofa_enabled, twofa_recovery, twofa_confirmed_at FROM users WHERE id = ? AND site_id = @site_id LIMIT 1');
        $stmt->execute([$uid]);
        $row = $stmt->fetch() ?: [];
        Response::json(['ok' => true, 'data' => [
            'enabled' => (int)($row['twofa_enabled'] ?? 0) === 1,
            'confirmed_at' => $row['twofa_confirmed_at'] ?? null,
            'recovery_remaining' => TwoFactorService::remainingRecoveryCodes((string)($row['twofa_recovery'] ?? '[]')),
            'mail_configured' => (new AuthSecurityService())->mailConfigured(),
        ]]);
    }

    public function twofaSetup(Request $req): void
    {
        (new AuthSecurityService())->ensureTables();
        $uid = (int)Session::userId();
        $db = Database::instance();
        $stmt = $db->prepare('SELECT id, email, twofa_enabled FROM users WHERE id = ? AND site_id = @site_id LIMIT 1');
        $stmt->execute([$uid]);
        $user = $stmt->fetch();
        if (!$user) Response::error('Usuario no válido', 401);
        if ((int)$user['twofa_enabled'] === 1) {
            Response::error('2FA ya está activo. Desactívalo antes de reconfigurarlo.', 400);
        }

        $secret = TwoFactorService::generateSecret();
        $db->prepare('UPDATE users SET twofa_secret = :s, twofa_enabled = 0 WHERE id = :id AND site_id = @site_id')
            ->execute(['s' => TwoFactorService::encrypt($secret), 'id' => $uid]);

        $issuer = $this->siteName();
        $account = (string)$user['email'];
        $uri = TwoFactorService::otpauthUrl($secret, $account, $issuer);
        (new AuthSecurityService())->audit('2fa_setup_started', 'ok', $uid, $account, [], $req->ip(), $req->userAgent());

        Response::json(['ok' => true, 'data' => [
            'secret' => $secret,
            'otpauth_uri' => $uri,
            'issuer' => $issuer,
            'account' => $account,
        ]]);
    }

    public function twofaEnable(Request $req): void
    {
        (new AuthSecurityService())->ensureTables();
        $uid = (int)Session::userId();
        $code = (string)$req->input('code', '');
        $db = Database::instance();
        $stmt = $db->prepare('SELECT id, email, twofa_secret FROM users WHERE id = ? AND site_id = @site_id LIMIT 1');
        $stmt->execute([$uid]);
        $user = $stmt->fetch();
        if (!$user) Response::error('Usuario no válido', 401);

        $secret = TwoFactorService::decrypt((string)($user['twofa_secret'] ?? ''));
        if ($secret === '' || !TwoFactorService::verify($secret, $code)) {
            Response::error('Código incorrecto. Verifica la hora de tu dispositivo.', 400);
        }

        [$plain, $stored] = TwoFactorService::generateRecoveryCodes();
        $db->prepare('UPDATE users SET twofa_enabled = 1, twofa_confirmed_at = NOW(), twofa_recovery = :r WHERE id = :id AND site_id = @site_id')
            ->execute(['r' => $stored, 'id' => $uid]);

        (new AuthSecurityService())->audit('2fa_enabled', 'ok', $uid, (string)$user['email'], [], $req->ip(), $req->userAgent());
        Response::json(['ok' => true, 'message' => '2FA activado.', 'data' => ['recovery_codes' => $plain]]);
    }

    public function twofaRecoveryRegenerate(Request $req): void
    {
        (new AuthSecurityService())->ensureTables();
        $uid = (int)Session::userId();
        $code = (string)$req->input('code', '');
        $db = Database::instance();
        $stmt = $db->prepare('SELECT id, email, twofa_enabled, twofa_secret FROM users WHERE id = ? AND site_id = @site_id LIMIT 1');
        $stmt->execute([$uid]);
        $user = $stmt->fetch();
        if (!$user || (int)$user['twofa_enabled'] !== 1) Response::error('2FA no está activo.', 400);
        $secret = TwoFactorService::decrypt((string)($user['twofa_secret'] ?? ''));
        if ($secret === '' || !TwoFactorService::verify($secret, $code)) {
            Response::error('Código incorrecto.', 400);
        }
        [$plain, $stored] = TwoFactorService::generateRecoveryCodes();
        $db->prepare('UPDATE users SET twofa_recovery = :r WHERE id = :id AND site_id = @site_id')->execute(['r' => $stored, 'id' => $uid]);
        (new AuthSecurityService())->audit('2fa_recovery_regenerated', 'ok', $uid, (string)$user['email'], [], $req->ip(), $req->userAgent());
        Response::json(['ok' => true, 'message' => 'Nuevos códigos generados.', 'data' => ['recovery_codes' => $plain]]);
    }

    public function twofaDisable(Request $req): void
    {
        (new AuthSecurityService())->ensureTables();
        $uid = (int)Session::userId();
        $code = (string)$req->input('code', '');
        $password = (string)$req->input('password', '');
        $db = Database::instance();
        $stmt = $db->prepare('SELECT id, email, twofa_enabled, twofa_secret, twofa_recovery, password_hash FROM users WHERE id = ? AND site_id = @site_id LIMIT 1');
        $stmt->execute([$uid]);
        $user = $stmt->fetch();
        if (!$user) Response::error('Usuario no válido', 401);

        $authorized = false;
        if ($password !== '' && password_verify($password, (string)$user['password_hash'])) {
            $authorized = true;
        } else {
            $secret = TwoFactorService::decrypt((string)($user['twofa_secret'] ?? ''));
            if ($secret !== '' && TwoFactorService::verify($secret, $code)) {
                $authorized = true;
            } else {
                $res = TwoFactorService::verifyRecoveryCode((string)($user['twofa_recovery'] ?? '[]'), $code);
                if ($res['ok'] ?? false) $authorized = true;
            }
        }
        if (!$authorized) {
            (new AuthSecurityService())->audit('2fa_disable_failed', 'error', $uid, (string)$user['email'], [], $req->ip(), $req->userAgent());
            Response::error('Verificación fallida. Introduce tu contraseña o un código válido.', 401);
        }

        $db->prepare('UPDATE users SET twofa_enabled = 0, twofa_secret = NULL, twofa_recovery = NULL, twofa_confirmed_at = NULL WHERE id = :id AND site_id = @site_id')
            ->execute(['id' => $uid]);
        (new AuthSecurityService())->audit('2fa_disabled', 'ok', $uid, (string)$user['email'], [], $req->ip(), $req->userAgent());
        Response::json(['ok' => true, 'message' => '2FA desactivado.']);
    }

    // ─────────────────────────────────────────────
    // Recuperación de contraseña por OTP (email) — público
    // ─────────────────────────────────────────────

    public function forgot(Request $req): void
    {
        $identifier = trim((string)$req->input('login', ''));
        $ip = $req->ip();
        $sec = new AuthSecurityService();
        $sec->ensureTables();

        // Respuesta genérica siempre (no revelar si el usuario existe)
        $generic = ['ok' => true, 'message' => 'Si la cuenta existe, enviamos un código de verificación a su correo registrado.'];

        if ($identifier === '') {
            Response::json($generic);
        }

        $ipThrottle = $sec->throttle('forgot_ip', $ip, 8, 900);
        $idThrottle = $sec->throttle('forgot_id', strtolower($identifier), 4, 900);
        if (!$ipThrottle['allowed'] || !$idThrottle['allowed']) {
            Response::error('Demasiadas solicitudes. Espera unos minutos e inténtalo de nuevo.', 429);
        }

        $db = Database::instance();
        $stmt = $db->prepare('SELECT id, site_id, email, username FROM users WHERE (username = :u1 OR email = :u2) AND is_active = 1 AND site_id = @site_id LIMIT 1');
        $stmt->execute(['u1' => $identifier, 'u2' => $identifier]);
        $user = $stmt->fetch();

        if ($user) {
            if (!$sec->mailConfigured()) {
                $sec->audit('password_reset_mail_unconfigured', 'error', (int)$user['id'], (string)$user['email'], [], $ip, $req->userAgent());
                // Se responde genérico igualmente; el operador lo verá en la auditoría.
                Response::json($generic);
            }
            $code = $sec->createOtp((int)$user['id'], (string)$user['email'], 'password_reset', 10);
            $sent = $sec->sendOtpEmail((string)$user['email'], $code, $this->siteName(), 10);
            $sec->audit('password_reset_requested', $sent ? 'ok' : 'error', (int)$user['id'], (string)$user['email'], ['sent' => $sent], $ip, $req->userAgent());
        } else {
            $sec->audit('password_reset_unknown', 'error', null, $identifier, [], $ip, $req->userAgent());
        }

        Response::json($generic);
    }

    public function verifyOtp(Request $req): void
    {
        $identifier = trim((string)$req->input('login', ''));
        $code = (string)$req->input('code', '');
        $ip = $req->ip();
        $sec = new AuthSecurityService();
        $sec->ensureTables();

        $idThrottle = $sec->throttle('verify_id', strtolower($identifier), 12, 900);
        if (!$idThrottle['allowed']) {
            Response::error('Demasiados intentos. Espera unos minutos.', 429);
        }

        $db = Database::instance();
        $stmt = $db->prepare('SELECT id, site_id, email, username FROM users WHERE (username = :u1 OR email = :u2) AND is_active = 1 AND site_id = @site_id LIMIT 1');
        $stmt->execute(['u1' => $identifier, 'u2' => $identifier]);
        $user = $stmt->fetch();
        if (!$user) {
            Response::error('Código inválido o expirado.', 400);
        }

        $res = $sec->verifyOtp((string)$user['email'], $code, 'password_reset');
        if (!$res['ok']) {
            $sec->audit('password_reset_code_failed', 'error', (int)$user['id'], (string)$user['email'], ['reason' => $res['error']], $ip, $req->userAgent());
            Response::error('Código inválido o expirado.', 400, ['reason' => $res['error']]);
        }

        $sec->audit('password_reset_code_ok', 'ok', (int)$user['id'], (string)$user['email'], [], $ip, $req->userAgent());
        $token = AuthMiddleware::generatePurposeToken('reset', [
            'sub' => (int)$user['id'],
            'email' => (string)$user['email'],
            'site_id' => (int)$user['site_id'],
        ], 600);

        Response::json(['ok' => true, 'message' => 'Código verificado.', 'reset_token' => $token]);
    }

    public function resetPassword(Request $req): void
    {
        $token = (string)$req->input('token', '');
        $password = (string)$req->input('password', '');
        $confirm = (string)$req->input('password_confirm', '');
        $sec = new AuthSecurityService();

        $claims = AuthMiddleware::validatePurposeToken($token, 'reset');
        if (!$claims) {
            Response::error('El enlace de restablecimiento expiró. Solicita uno nuevo.', 401);
        }
        if ($password !== $confirm) {
            Response::error('Las contraseñas no coinciden.', 400);
        }
        $policy = $sec->validatePassword($password);
        if (!$policy['ok']) {
            Response::error($policy['message'], 400);
        }

        $db = Database::instance();
        $stmt = $db->prepare('SELECT id, email FROM users WHERE id = :id AND site_id = @site_id AND is_active = 1 LIMIT 1');
        $stmt->execute(['id' => (int)$claims['sub']]);
        $user = $stmt->fetch();
        if (!$user) Response::error('Usuario no válido.', 401);

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $db->prepare('UPDATE users SET password_hash = :h WHERE id = :id AND site_id = @site_id')->execute(['h' => $hash, 'id' => (int)$user['id']]);
        $db->prepare('UPDATE auth_otps SET consumed_at = NOW() WHERE site_id = @site_id AND email = ? AND purpose = ? AND consumed_at IS NULL')->execute([(string)$user['email'], 'password_reset']);

        $sec->audit('password_reset_done', 'ok', (int)$user['id'], (string)$user['email'], [], $req->ip(), $req->userAgent());
        Response::json(['ok' => true, 'message' => 'Contraseña actualizada. Ya puedes iniciar sesión.']);
    }

    // ─────────────────────────────────────────────

    public function logout(): void
    {
        Session::logout();
        Response::json(['ok' => true, 'message' => 'Logged out']);
    }

    public function me(): void
    {
        if (!Session::isLoggedIn()) {
            Response::error('Not authenticated', 401);
        }
        $u = Session::user();
        try {
            $stmt = Database::instance()->prepare('SELECT twofa_enabled FROM users WHERE id = ? AND site_id = @site_id LIMIT 1');
            $stmt->execute([(int)($u['id'] ?? 0)]);
            $row = $stmt->fetch();
            $u['twofa_enabled'] = $row ? (int)$row['twofa_enabled'] === 1 : false;
        } catch (\Throwable $e) {
            $u['twofa_enabled'] = false;
        }
        Response::json(['ok' => true, 'user' => $u]);
    }

    private function siteName(): string
    {
        try {
            $stmt = Database::instance()->prepare('SELECT `value` FROM settings WHERE site_id = @site_id AND `key` = ? LIMIT 1');
            $stmt->execute(['site_name']);
            $v = $stmt->fetchColumn();
            if ($v) return (string)$v;
        } catch (\Throwable $e) {
        }
        return 'Wontia Web Intelligence';
    }

    private function throttleFile(string $ip, string $user): string
    {
        $dir = ROOT_DIR . '/cache/security';
        if (!is_dir($dir)) @mkdir($dir, 0770, true);
        return $dir . '/login_' . md5($ip . '|' . $user) . '.json';
    }

    private function throttleCheck(string $ip, string $user): ?string
    {
        $file = $this->throttleFile($ip, $user);
        if (!file_exists($file)) return null;
        $data = json_decode((string)file_get_contents($file), true);
        if (!is_array($data)) return null;
        $now = time();
        if (!empty($data['until']) && $now < (int)$data['until']) {
            return 'Too many failed attempts. Try again in ' . ceil(((int)$data['until'] - $now) / 60) . ' minutes.';
        }
        return null;
    }

    private function throttleFail(string $ip, string $user): void
    {
        $file = $this->throttleFile($ip, $user);
        $now = time();
        $data = ['fails' => 1, 'first' => $now, 'until' => 0];
        if (file_exists($file)) {
            $prev = json_decode((string)file_get_contents($file), true);
            if (is_array($prev) && $now - (int)($prev['first'] ?? $now) <= 900) {
                $data['fails'] = (int)($prev['fails'] ?? 0) + 1;
                $data['first'] = (int)($prev['first'] ?? $now);
            }
        }
        if ($data['fails'] >= 5) $data['until'] = $now + 900;
        @file_put_contents($file, json_encode($data), LOCK_EX);
    }

    private function throttleClear(string $ip, string $user): void
    {
        @unlink($this->throttleFile($ip, $user));
    }
}
