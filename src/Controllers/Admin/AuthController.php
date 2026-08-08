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
        $username = $req->input('username');
        $password = $req->input('password');

        if (!$username || !$password) {
            Response::error('Username and password are required', 400);
        }

        $db = Database::instance();
        $stmt = $db->prepare("SELECT * FROM users WHERE (username = :u1 OR email = :u2) AND is_active = 1 LIMIT 1");
        $stmt->execute(['u1' => $username, 'u2' => $username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            Response::error('Invalid credentials', 401);
        }

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
}
