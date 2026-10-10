<?php
namespace App\Services;

use App\Core\Config;
use App\Core\Database;

/**
 * AuthSecurityService — base de la seguridad de acceso de WWI:
 *  - Migración idempotente (columnas 2FA en users + tablas auth_otps/auth_events)
 *  - Códigos OTP de un solo uso enviados por email (recuperación de contraseña)
 *  - Auditoría de eventos de autenticación
 *  - Throttle anti-abuso por IP + email
 *  - Política de contraseña
 */
class AuthSecurityService
{
    private static bool $ensured = false;

    // ─────────────────────────────────────────────
    // Migración idempotente
    // ─────────────────────────────────────────────

    public function ensureTables(): array
    {
        if (self::$ensured) return ['ok' => true, 'cached' => true];
        $db = Database::instance();
        $columns = [
            'twofa_enabled' => "ALTER TABLE users ADD COLUMN twofa_enabled TINYINT NOT NULL DEFAULT 0",
            'twofa_secret' => "ALTER TABLE users ADD COLUMN twofa_secret VARCHAR(255) NULL",
            'twofa_recovery' => "ALTER TABLE users ADD COLUMN twofa_recovery JSON NULL",
            'twofa_confirmed_at' => "ALTER TABLE users ADD COLUMN twofa_confirmed_at DATETIME NULL",
        ];
        foreach ($columns as $col => $sql) {
            try {
                $exists = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = ?");
                $exists->execute([$col]);
                if ((int)$exists->fetchColumn() === 0) {
                    $db->exec($sql);
                }
            } catch (\Throwable $e) {
            }
        }

        $db->exec("CREATE TABLE IF NOT EXISTS auth_otps (
            id INT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL DEFAULT 1,
            user_id INT NULL,
            email VARCHAR(255) NOT NULL,
            purpose VARCHAR(32) NOT NULL DEFAULT 'password_reset',
            code_hash VARCHAR(255) NOT NULL,
            attempts INT NOT NULL DEFAULT 0,
            max_attempts INT NOT NULL DEFAULT 5,
            ip VARCHAR(64) NULL,
            user_agent VARCHAR(255) NULL,
            consumed_at DATETIME NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_lookup (site_id, email, purpose, created_at),
            KEY idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE IF NOT EXISTS auth_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL DEFAULT 1,
            user_id INT NULL,
            email VARCHAR(255) NULL,
            event VARCHAR(48) NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'ok',
            ip VARCHAR(64) NULL,
            user_agent VARCHAR(255) NULL,
            meta JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_site_created (site_id, created_at),
            KEY idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        self::$ensured = true;
        return ['ok' => true, 'cached' => false];
    }

    // ─────────────────────────────────────────────
    // Auditoría
    // ─────────────────────────────────────────────

    public function audit(string $event, string $status = 'ok', ?int $userId = null, ?string $email = null, array $meta = [], ?string $ip = null, ?string $ua = null): void
    {
        try {
            $this->ensureTables();
            $stmt = Database::instance()->prepare('INSERT INTO auth_events (site_id, user_id, email, event, status, ip, user_agent, meta) VALUES (@site_id, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $userId,
                $email !== null ? substr($email, 0, 255) : null,
                substr($event, 0, 48),
                substr($status, 0, 16),
                $ip !== null ? substr($ip, 0, 64) : null,
                $ua !== null ? substr($ua, 0, 255) : null,
                $meta ? json_encode($meta) : null,
            ]);
        } catch (\Throwable $e) {
        }
    }

    public function recentEvents(int $limit = 30): array
    {
        try {
            $this->ensureTables();
            $stmt = Database::instance()->prepare('SELECT id, user_id, email, event, status, ip, created_at FROM auth_events WHERE site_id = @site_id ORDER BY id DESC LIMIT ' . (int)$limit);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
    }

    // ─────────────────────────────────────────────
    // OTP por email (recuperación de contraseña)
    // ─────────────────────────────────────────────

    public function createOtp(int $userId, string $email, string $purpose = 'password_reset', int $ttlMinutes = 10): string
    {
        $this->ensureTables();
        $db = Database::instance();
        // Invalida OTPs previos no consumidos del mismo propósito
        $db->prepare('UPDATE auth_otps SET consumed_at = NOW() WHERE site_id = @site_id AND email = ? AND purpose = ? AND consumed_at IS NULL')
            ->execute([$email, $purpose]);

        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $secret = (string)Config::get('JWT_SECRET', 'wwi');
        $hash = hash_hmac('sha256', $purpose . '|' . $email . '|' . $code, $secret);
        $expiresAt = date('Y-m-d H:i:s', time() + max(1, $ttlMinutes) * 60);
        $stmt = $db->prepare('INSERT INTO auth_otps (site_id, user_id, email, purpose, code_hash, ip, user_agent, expires_at) VALUES (@site_id, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $userId,
            $email,
            $purpose,
            $hash,
            substr($this->ip(), 0, 64),
            substr((string)($this->userAgent()), 0, 255),
            $expiresAt,
        ]);
        return $code;
    }

    public function verifyOtp(string $email, string $code, string $purpose = 'password_reset'): array
    {
        $this->ensureTables();
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== 6) return ['ok' => false, 'error' => 'invalid'];
        $db = Database::instance();
        $stmt = $db->prepare('SELECT * FROM auth_otps WHERE site_id = @site_id AND email = ? AND purpose = ? AND consumed_at IS NULL ORDER BY id DESC LIMIT 1');
        $stmt->execute([$email, $purpose]);
        $row = $stmt->fetch();
        if (!$row) return ['ok' => false, 'error' => 'expired'];
        if (strtotime((string)$row['expires_at']) < time()) {
            return ['ok' => false, 'error' => 'expired'];
        }
        if ((int)$row['attempts'] >= (int)$row['max_attempts']) {
            return ['ok' => false, 'error' => 'locked'];
        }
        $secret = (string)Config::get('JWT_SECRET', 'wwi');
        $expected = hash_hmac('sha256', $purpose . '|' . $email . '|' . $code, $secret);
        if (!hash_equals((string)$row['code_hash'], $expected)) {
            $db->prepare('UPDATE auth_otps SET attempts = attempts + 1 WHERE id = ?')->execute([$row['id']]);
            $left = max(0, (int)$row['max_attempts'] - ((int)$row['attempts'] + 1));
            return ['ok' => false, 'error' => 'invalid', 'attempts_left' => $left];
        }
        $db->prepare('UPDATE auth_otps SET consumed_at = NOW() WHERE id = ?')->execute([$row['id']]);
        return ['ok' => true, 'user_id' => (int)$row['user_id'], 'email' => (string)$row['email']];
    }

    public function purgeExpiredOtps(): int
    {
        try {
            $this->ensureTables();
            return (int)Database::instance()->exec('DELETE FROM auth_otps WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY) AND (consumed_at IS NOT NULL OR expires_at < NOW())');
        } catch (\Throwable $e) {
            return 0;
        }
    }

    // ─────────────────────────────────────────────
    // Throttle por IP + identificador
    // ─────────────────────────────────────────────

    public function throttle(string $scope, string $identifier, int $max, int $windowSeconds): array
    {
        $dir = ROOT_DIR . '/cache/security';
        if (!is_dir($dir)) @mkdir($dir, 0770, true);
        $file = $dir . '/' . preg_replace('/[^a-z0-9_]/', '', $scope) . '_' . md5($identifier) . '.json';
        $now = time();
        $data = ['count' => 0, 'first' => $now];
        if (file_exists($file)) {
            $prev = json_decode((string)file_get_contents($file), true);
            if (is_array($prev) && $now - (int)($prev['first'] ?? $now) <= $windowSeconds) {
                $data = ['count' => (int)($prev['count'] ?? 0), 'first' => (int)($prev['first'] ?? $now)];
            }
        }
        if ($data['count'] >= $max) {
            return ['allowed' => false, 'retry_after' => max(1, $windowSeconds - ($now - $data['first']))];
        }
        $data['count']++;
        @file_put_contents($file, json_encode($data), LOCK_EX);
        return ['allowed' => true, 'remaining' => max(0, $max - $data['count'])];
    }

    public function throttleClear(string $scope, string $identifier): void
    {
        $dir = ROOT_DIR . '/cache/security';
        $file = $dir . '/' . preg_replace('/[^a-z0-9_]/', '', $scope) . '_' . md5($identifier) . '.json';
        @unlink($file);
    }

    // ─────────────────────────────────────────────
    // Política de contraseña
    // ─────────────────────────────────────────────

    public function validatePassword(string $password): array
    {
        if (strlen($password) < 10) return ['ok' => false, 'message' => 'La contraseña debe tener al menos 10 caracteres.'];
        if (!preg_match('/[A-Z]/', $password)) return ['ok' => false, 'message' => 'Debe incluir al menos una letra mayúscula.'];
        if (!preg_match('/[a-z]/', $password)) return ['ok' => false, 'message' => 'Debe incluir al menos una letra minúscula.'];
        if (!preg_match('/\d/', $password)) return ['ok' => false, 'message' => 'Debe incluir al menos un número.'];
        $common = ['password', 'contrasena', 'contrasena123', 'admin12345', '1234567890', 'qwertyuiop'];
        if (in_array(strtolower($password), $common, true)) return ['ok' => false, 'message' => 'Esa contraseña es demasiado común.'];
        return ['ok' => true];
    }

    // ─────────────────────────────────────────────
    // Email OTP
    // ─────────────────────────────────────────────

    public function sendOtpEmail(string $to, string $code, string $siteName, int $ttlMinutes = 10): bool
    {
        $subject = 'Código de recuperación — ' . $siteName;
        $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
        $safeSite = htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8');
        $html = '<div style="font-family:Inter,Arial,sans-serif;max-width:520px;margin:auto;background:#0d1220;border:1px solid #1e293b;border-radius:14px;padding:32px;color:#e6edf7">'
            . '<div style="font-size:13px;letter-spacing:.08em;text-transform:uppercase;color:#67e8f9;font-weight:700;margin-bottom:12px">' . $safeSite . '</div>'
            . '<h2 style="margin:0 0 10px;font-size:20px;color:#fff">Recuperación de contraseña</h2>'
            . '<p style="font-size:14px;line-height:1.6;color:#94a3b8;margin:0 0 18px">Usa este código de verificación de un solo uso para restablecer tu contraseña. Caduca en ' . (int)$ttlMinutes . ' minutos.</p>'
            . '<div style="font-family:\'JetBrains Mono\',Consolas,monospace;font-size:34px;font-weight:800;letter-spacing:10px;text-align:center;background:#0a0e18;border:1px solid #1e293b;border-radius:12px;padding:18px;color:#22d3ee">' . $safeCode . '</div>'
            . '<p style="font-size:12px;color:#64748b;margin:18px 0 0">Si no solicitaste este código, ignora este correo. Nunca compartas este código con nadie.</p>'
            . '</div>';
        try {
            return (new EmailService())->sendHtml($to, $subject, $html);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function mailConfigured(): bool
    {
        try {
            return (new EmailService())->configured();
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function ip(): string
    {
        return $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    private function userAgent(): string
    {
        return (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    }
}
