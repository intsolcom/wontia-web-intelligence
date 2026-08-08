<?php
namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

class UserController
{
    public function index(): void
    {
        if (Session::userRole() !== 'superadmin') Response::error('Forbidden', 403);
        $db = Database::instance();
        $users = $db->query("SELECT id, site_id, username, email, role, last_login, is_active, created_at FROM users WHERE site_id = 1 ORDER BY created_at ASC")->fetchAll();
        Response::json(['ok' => true, 'data' => $users]);
    }

    public function store(Request $req): void
    {
        if (Session::userRole() !== 'superadmin') Response::error('Forbidden', 403);
        $username = $req->input('username');
        $email = $req->input('email');
        $password = $req->input('password');
        if (!$username || !$email || !$password) Response::error('Username, email and password required', 400);
        $db = Database::instance();
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $db->prepare("INSERT INTO users (site_id, username, email, password_hash, role) VALUES (1, :u, :e, :h, :r)")
            ->execute(['u' => $username, 'e' => $email, 'h' => $hash, 'r' => $req->input('role', 'admin')]);
        Response::json(['ok' => true, 'data' => ['id' => $db->lastInsertId()]], 201);
    }

    public function update(Request $req, string $id): void
    {
        if (Session::userRole() !== 'superadmin' && (string)Session::userId() !== $id) Response::error('Forbidden', 403);
        $db = Database::instance();
        $sets = [];
        $params = ['id' => $id];
        foreach (['username', 'email', 'role', 'is_active'] as $f) {
            $val = $req->input($f);
            if ($val !== null) { $sets[] = "$f = :$f"; $params[$f] = $f === 'is_active' ? (int)$val : $val; }
        }
        $password = $req->input('password');
        if ($password) { $sets[] = "password_hash = :ph"; $params['ph'] = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]); }
        if (!empty($sets)) $db->prepare("UPDATE users SET " . implode(', ', $sets) . " WHERE id = :id AND site_id = 1")->execute($params);
        Response::json(['ok' => true]);
    }

    public function destroy(Request $req, string $id): void
    {
        if (Session::userRole() !== 'superadmin') Response::error('Forbidden', 403);
        if ((string)Session::userId() === $id) Response::error('Cannot delete yourself', 400);
        $db = Database::instance();
        $db->prepare("DELETE FROM users WHERE id = :id AND site_id = 1")->execute(['id' => $id]);
        Response::json(['ok' => true]);
    }
}
