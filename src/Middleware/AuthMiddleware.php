<?php
namespace App\Middleware;

use App\Core\Response;
use App\Core\Session;
use App\Core\Config;

class AuthMiddleware
{
    public function handle($request = null): void
    {
        if (Session::isLoggedIn()) return;

        $token = null;
        if ($request && method_exists($request, 'bearerToken')) {
            $token = $request->bearerToken();
        }
        if (!$token) {
            $token = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
            if ($token && str_starts_with($token, 'Bearer ')) {
                $token = substr($token, 7);
            }
        }

        if ($token) {
            $payload = self::validateJwt($token);
            if ($payload) {
                Session::login([
                    'id' => $payload['sub'],
                    'username' => $payload['username'] ?? '',
                    'email' => $payload['email'] ?? '',
                    'role' => $payload['role'] ?? 'admin',
                    'site_id' => $payload['site_id'] ?? 1,
                ]);
                return;
            }
        }

        if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
            Response::error('Unauthorized', 401);
        } else {
            Response::redirect('/admin.php');
        }
    }

    public static function validateJwt(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        if (!$payload || !isset($payload['exp'])) return null;
        if ($payload['exp'] < time()) return null;
        $secret = Config::get('JWT_SECRET', 'wontia-jwt-secret-change-me');
        $sig = hash_hmac('sha256', "$parts[0].$parts[1]", $secret, true);
        $sigB64 = rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');
        if (!hash_equals($sigB64, $parts[2])) return null;
        return $payload;
    }

    public static function generateJwt(array $user, int $expHours = 24): string
    {
        $secret = Config::get('JWT_SECRET', 'wontia-jwt-secret-change-me');
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode([
            'sub' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role'],
            'site_id' => $user['site_id'] ?? 1,
            'iat' => time(),
            'exp' => time() + ($expHours * 3600),
        ])), '+/', '-_'), '=');
        $sig = hash_hmac('sha256', "$header.$payload", $secret, true);
        $sigB64 = rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');
        return "$header.$payload.$sigB64";
    }
}
