<?php
namespace App\Services;

use App\Core\Config;
use App\Core\Database;

class StockNotificationService
{
    private static bool $ensured = false;

    public function ensureTables(): void
    {
        if (self::$ensured) return;
        Database::instance()->exec("CREATE TABLE IF NOT EXISTS store_stock_notifications (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL,
            product_ref VARCHAR(255) NOT NULL,
            product_name VARCHAR(255) NULL,
            name VARCHAR(150) NOT NULL,
            email VARCHAR(190) NOT NULL,
            consent_at DATETIME NULL,
            status ENUM('pending','notified') NOT NULL DEFAULT 'pending',
            notified_at DATETIME NULL,
            ip_hash VARCHAR(64) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_stock_notification (site_id, product_ref, email),
            KEY idx_stock_notification_pending (site_id, product_ref, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        self::$ensured = true;
    }

    public function subscribe(string $ref, string $name, string $email, bool $consent, string $ip = ''): array
    {
        $this->ensureTables();
        $ref = trim($ref);
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
        $email = strtolower(trim($email));
        if ($ref === '') throw new \InvalidArgumentException('Producto no válido.');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) throw new \InvalidArgumentException('Escribe tu nombre completo.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) throw new \InvalidArgumentException('Escribe un correo electrónico válido.');
        if (!$consent) throw new \InvalidArgumentException('Debes aceptar la política de tratamiento de datos.');

        $db = Database::instance();
        $ipHash = $ip !== '' ? hash('sha256', $ip) : '';
        if ($ipHash !== '') {
            $limit = $db->prepare("SELECT COUNT(*) FROM store_stock_notifications WHERE site_id = @site_id AND ip_hash = ? AND created_at >= (NOW() - INTERVAL 1 HOUR)");
            $limit->execute([$ipHash]);
            if ((int)$limit->fetchColumn() >= 12) throw new \RuntimeException('Recibimos varias solicitudes desde esta conexión. Intenta más tarde.');
        }

        $productName = '';
        try {
            $p = $db->prepare("SELECT name FROM store_products WHERE site_id = @site_id AND (slug = ? OR catalog_handle = ?) LIMIT 1");
            $p->execute([$ref, $ref]);
            $productName = (string)($p->fetchColumn() ?: '');
        } catch (\Throwable $e) {
        }

        $stmt = $db->prepare("INSERT INTO store_stock_notifications (site_id, product_ref, product_name, name, email, consent_at, status, ip_hash)
            VALUES (@site_id, ?, ?, ?, ?, NOW(), 'pending', ?)
            ON DUPLICATE KEY UPDATE name = VALUES(name), consent_at = NOW(), status = 'pending', notified_at = NULL, ip_hash = VALUES(ip_hash)");
        $stmt->execute([$ref, $productName, $name, $email, $ipHash]);

        try {
            $mail = new EmailService();
            $subject = 'Te avisaremos cuando esté disponible';
            $body = "Hola {$name},\n\nRegistramos tu solicitud para avisarte cuando " . ($productName !== '' ? $productName : 'este producto') . " vuelva a estar disponible en SONIKA.\n\nTambién puedes visitar " . rtrim((string)Config::get('APP_URL', 'https://sonika.com.co'), '/') . "/producto/" . rawurlencode($ref) . "\n\nSONIKA · El Sonido que te mueve";
            $mail->send($email, $subject, $body);
        } catch (\Throwable $e) {
        }

        return ['ok' => true, 'message' => 'Listo. Te avisaremos cuando esté disponible.'];
    }

    public function notifyForProduct(string $ref): int
    {
        $this->ensureTables();
        $ref = trim($ref);
        if ($ref === '') return 0;
        $db = Database::instance();
        $st = $db->prepare("SELECT id, name, email, product_name FROM store_stock_notifications WHERE site_id = @site_id AND product_ref = ? AND status = 'pending' LIMIT 200");
        $st->execute([$ref]);
        $rows = $st->fetchAll(\PDO::FETCH_ASSOC);
        if (!$rows) return 0;
        $mail = new EmailService();
        $sent = 0;
        foreach ($rows as $row) {
            try {
                $body = "Hola {$row['name']},\n\n" . ($row['product_name'] !== '' ? $row['product_name'] : 'El producto que estabas esperando') . " ya está disponible en SONIKA.\n\nVisítalo aquí: " . rtrim((string)Config::get('APP_URL', 'https://sonika.com.co'), '/') . "/producto/" . rawurlencode($ref) . "\n\nSONIKA · El Sonido que te mueve";
                if ($mail->send((string)$row['email'], 'Tu producto ya está disponible', $body)) {
                    $db->prepare("UPDATE store_stock_notifications SET status = 'notified', notified_at = NOW() WHERE id = ? AND site_id = @site_id")->execute([(int)$row['id']]);
                    $sent++;
                }
            } catch (\Throwable $e) {
            }
        }
        return $sent;
    }

    public function pendingCount(string $ref = ''): int
    {
        $this->ensureTables();
        $sql = "SELECT COUNT(*) FROM store_stock_notifications WHERE site_id = @site_id AND status = 'pending'";
        $params = [];
        if ($ref !== '') {
            $sql .= ' AND product_ref = ?';
            $params[] = $ref;
        }
        $st = Database::instance()->prepare($sql);
        $st->execute($params);
        return (int)$st->fetchColumn();
    }
}
