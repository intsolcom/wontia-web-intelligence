<?php
namespace App\Core;

class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started) return;
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        self::$started = true;
    }

    public static function set(string $key, $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        self::start();
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    public static function destroy(): void
    {
        self::start();
        session_unset();
        session_destroy();
        self::$started = false;
    }

    public static function flash(string $key, $value = null): mixed
    {
        self::start();
        if ($value !== null) {
            $_SESSION['_flash'][$key] = $value;
            return null;
        }
        $val = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $val;
    }

    public static function user(): ?array
    {
        return self::get('user');
    }

    public static function isLoggedIn(): bool
    {
        return self::has('user');
    }

    public static function userId(): ?int
    {
        $user = self::user();
        return $user['id'] ?? null;
    }

    public static function userRole(): ?string
    {
        $user = self::user();
        return $user['role'] ?? null;
    }

    public static function login(array $user): void
    {
        self::set('user', $user);
    }

    public static function logout(): void
    {
        self::destroy();
    }
}
