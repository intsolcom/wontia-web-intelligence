<?php
namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Middleware\AuthMiddleware;

class AuthController
{
    public function login(Request $req): void
    {
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
        $stmt = $db->prepare("SELECT * FROM users WHERE (username = :u1 OR email = :u2) AND is_active = 1 LIMIT 1");
        $stmt->execute(['u1' => $username, 'u2' => $username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $this->throttleFail($ip, $username);
            usleep(400000);
            Response::error('Invalid credentials', 401);
        }

        $this->throttleClear($ip, $username);

        $db->prepare("UPDATE users SET last_login = NOW() WHERE id = :id")->execute(['id' => $user['id']]);

        Session::login([
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role'],
            'site_id' => $user['site_id'],
        ]);

        $token = AuthMiddleware::generateJwt($user);

        Response::json([
            'ok' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role'],
            ],
            'token' => $token,
        ]);
    }

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
        Response::json(['ok' => true, 'user' => Session::user()]);
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
