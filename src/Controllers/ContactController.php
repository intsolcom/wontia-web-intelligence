<?php
namespace App\Controllers;

use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

class ContactController
{
    public function submit(Request $request): void
    {
        $data = $request->json();

        if (trim((string)($data['website'] ?? '')) !== '') {
            Response::json(['ok' => true, 'message' => 'Thank you.']);
            return;
        }

        $name = trim(mb_substr((string)($data['name'] ?? ''), 0, 120));
        $email = trim(mb_substr((string)($data['email'] ?? ''), 0, 190));
        $company = trim(mb_substr((string)($data['company'] ?? ''), 0, 190));
        $phone = trim(mb_substr((string)($data['phone'] ?? ''), 0, 40));
        $service = trim(mb_substr((string)($data['service'] ?? ''), 0, 120));
        $message = trim(mb_substr((string)($data['message'] ?? ''), 0, 4000));

        if ($name === '' || $email === '' || $message === '') {
            Response::error('Please complete the required fields.', 422);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('Please enter a valid email address.', 422);
        }
        if (mb_strlen($message) < 10) {
            Response::error('Please tell us a bit more about your project.', 422);
        }

        $db = Database::instance();
        $db->exec("CREATE TABLE IF NOT EXISTS wwi_leads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(190) NOT NULL,
            company VARCHAR(190) DEFAULT '',
            phone VARCHAR(40) DEFAULT '',
            service VARCHAR(120) DEFAULT '',
            message TEXT,
            source VARCHAR(60) DEFAULT 'contact',
            status VARCHAR(20) DEFAULT 'new',
            ip_hash CHAR(40) DEFAULT '',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_site (site_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $siteId = (int)Config::get('SITE_ID', '1');
        $ip = (string)$request->ip();
        $ipHash = sha1($ip . '|' . (string)Config::get('JWT_SECRET', 'intsolcom'));

        $st = $db->prepare("SELECT COUNT(*) FROM wwi_leads WHERE site_id = ? AND ip_hash = ? AND created_at > (NOW() - INTERVAL 10 MINUTE)");
        $st->execute([$siteId, $ipHash]);
        if ((int)$st->fetchColumn() >= 3) {
            Response::error('Too many submissions. Please try again in a few minutes.', 429);
        }

        $db->prepare("INSERT INTO wwi_leads (site_id, name, email, company, phone, service, message, source, ip_hash) VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$siteId, $name, $email, $company, $phone, $service, $message, 'contact_form', $ipHash]);

        try {
            $notify = $this->setting('contact_notify_email', 'customer@intsolcom.com');
            $notifyCo = $this->setting('contact_notify_email_co', 'cliente@intsolcom.com');
            $mailer = new \App\Services\EmailService();
            if ($notify !== '' && $mailer->configured()) {
                $subject = 'New contact form lead — ' . $name;
                $body = "New contact form submission\n\n"
                    . "Name: $name\n"
                    . "Email: $email\n"
                    . "Company: $company\n"
                    . "Phone: $phone\n"
                    . "Service: $service\n"
                    . "Message:\n$message\n\n"
                    . "Date: " . date('Y-m-d H:i:s') . "\n";
                $mailer->send($notify, $subject, $body);
                if ($notifyCo !== '' && $notifyCo !== $notify) {
                    $mailer->send($notifyCo, $subject, $body);
                }
            }
        } catch (\Throwable $e) {
            error_log('[INTSOLCOM] Contact notification failed: ' . $e->getMessage());
        }

        Response::json(['ok' => true, 'message' => 'Thank you — we received your message.']);
    }

    private function setting(string $key, string $default): string
    {
        try {
            $st = Database::instance()->prepare("SELECT `value` FROM settings WHERE site_id = @site_id AND `key` = ? LIMIT 1");
            $st->execute([$key]);
            $v = $st->fetchColumn();
            if ($v !== false && $v !== null && trim((string)$v) !== '') return trim((string)$v);
        } catch (\Throwable $e) {
        }
        return $default;
    }
}
