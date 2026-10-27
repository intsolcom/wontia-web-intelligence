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

        $source = trim((string)($data['source'] ?? 'contact_form'));
        if (!in_array($source, ['contact_form', 'sonika_support'], true)) $source = 'contact_form';
        $isSupport = $source === 'sonika_support';

        $name = trim(mb_substr((string)($data['name'] ?? ''), 0, 120));
        $email = trim(mb_substr((string)($data['email'] ?? ''), 0, 190));
        $company = trim(mb_substr((string)($data['company'] ?? ''), 0, 190));
        $phone = trim(mb_substr((string)($data['phone'] ?? ''), 0, 40));
        $service = trim(mb_substr((string)($data['service'] ?? ''), 0, 120));
        $message = trim(mb_substr((string)($data['message'] ?? ''), 0, 4000));
        $orderRef = trim(mb_substr((string)($data['order_ref'] ?? ''), 0, 60));
        $replyPreference = strtolower(trim((string)($data['reply_preference'] ?? '')));
        $authAccepted = !empty($data['auth_accepted']) ? 1 : 0;

        if ($isSupport) {
            if ($replyPreference === '') $replyPreference = 'email';
            if (!in_array($replyPreference, ['email', 'whatsapp', 'phone'], true)) {
                Response::error('Selecciona un medio de respuesta válido.', 422);
            }
            if (!$authAccepted) {
                Response::error('Debes autorizar el tratamiento de tus datos para enviar la solicitud.', 422);
            }
            if ($service === '') {
                Response::error('Selecciona el motivo de contacto.', 422);
            }
            if ($orderRef !== '' && !preg_match('/^[A-Za-z0-9#._\-\/ ]{3,60}$/', $orderRef)) {
                Response::error('El número de pedido no tiene un formato válido.', 422);
            }
            if (in_array($replyPreference, ['whatsapp', 'phone'], true) && $phone === '') {
                Response::error('Escribe un teléfono para el medio de respuesta elegido.', 422);
            }
        } else {
            $replyPreference = '';
        }

        if ($name === '' || $email === '' || $message === '') {
            Response::error($isSupport ? 'Completa nombre, correo y mensaje.' : 'Please complete the required fields.', 422);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error($isSupport ? 'Ingresa un correo electrónico válido.' : 'Please enter a valid email address.', 422);
        }
        if (mb_strlen($message) < 10) {
            Response::error($isSupport ? 'Cuéntanos un poco más (mínimo 10 caracteres).' : 'Please tell us a bit more about your project.', 422);
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
            order_ref VARCHAR(60) NULL,
            reply_preference VARCHAR(20) NULL,
            auth_accepted TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_site (site_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $this->ensureLeadColumns($db);

        $siteId = (int)Config::get('SITE_ID', '1');
        $ip = (string)$request->ip();
        $ipHash = sha1($ip . '|' . (string)Config::get('JWT_SECRET', 'intsolcom'));

        $st = $db->prepare("SELECT COUNT(*) FROM wwi_leads WHERE site_id = ? AND ip_hash = ? AND created_at > (NOW() - INTERVAL 10 MINUTE)");
        $st->execute([$siteId, $ipHash]);
        if ((int)$st->fetchColumn() >= 3) {
            Response::error($isSupport ? 'Demasiados envíos. Espera unos minutos e intenta de nuevo.' : 'Too many submissions. Please try again in a few minutes.', 429);
        }

        $st = $db->prepare("INSERT INTO wwi_leads (site_id, name, email, company, phone, service, message, source, ip_hash, order_ref, reply_preference, auth_accepted) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        $st->execute([
            $siteId,
            $name,
            $email,
            $company,
            $phone,
            $service,
            $message,
            $source,
            $ipHash,
            $orderRef !== '' ? $orderRef : null,
            $replyPreference !== '' ? $replyPreference : null,
            $authAccepted,
        ]);
        $leadId = (int)$db->lastInsertId();

        try {
            $notify = $this->setting($isSupport ? 'support_notify_email' : 'contact_notify_email', $isSupport ? 'clientes@sonika.com.co' : 'customer@intsolcom.com');
            $notifyCo = $isSupport ? '' : $this->setting('contact_notify_email_co', 'cliente@intsolcom.com');
            $mailer = new \App\Services\EmailService();
            if ($notify !== '' && $mailer->configured()) {
                if (!$isSupport) {
                    $subject = 'New contact form lead — ' . $name;
                    $text = "New contact form submission\n\n"
                        . "Name: $name\n"
                        . "Email: $email\n"
                        . "Company: $company\n"
                        . "Phone: $phone\n"
                        . "Service: $service\n"
                        . "Message:\n$message\n\n"
                        . "Date: " . date('Y-m-d H:i:s') . "\n";
                    $sent = $mailer->send($notify, $subject, $text);
                    if (!$sent) error_log('[SONIKA] Contact notification failed for lead ' . $leadId);
                    if ($notifyCo !== '' && $notifyCo !== $notify) {
                        $mailer->send($notifyCo, $subject, $text);
                    }
                } else {
                    $subject = 'Nuevo mensaje de soporte — ' . $name;
                    $text = "Nuevo mensaje de soporte SONIKA\n\n"
                        . "Nombre: $name\n"
                        . "Email: $email\n"
                        . "Teléfono: $phone\n"
                        . "Motivo: $service\n"
                        . "Pedido: " . ($orderRef !== '' ? $orderRef : '—') . "\n"
                        . "Medio preferido: " . ($replyPreference !== '' ? $replyPreference : '—') . "\n"
                        . "Autorización de datos: " . ($authAccepted ? 'sí' : 'no') . "\n"
                        . "Origen: $source\n\n"
                        . "Mensaje:\n$message\n\n"
                        . "Fecha: " . date('Y-m-d H:i:s') . "\n"
                        . "Lead: #$leadId\n";
                    $safe = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
                    $html = '<div style="font-family:Inter,Arial,sans-serif;max-width:620px;margin:auto;background:#0b0b0b;color:#fff;border-radius:14px;padding:26px">'
                        . '<div style="color:#F20D16;font-size:11px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;margin-bottom:10px">SONIKA · Soporte</div>'
                        . '<h2 style="margin:0 0 14px;font-size:20px;color:#fff">Nuevo mensaje de soporte</h2>'
                        . '<table role="presentation" style="width:100%;border-collapse:collapse;font-size:13.5px;line-height:1.6;color:#d4d4d4">'
                        . '<tr><td style="padding:4px 0;color:#9a9a9a;width:120px">Nombre</td><td style="padding:4px 0;color:#fff">' . $safe($name) . '</td></tr>'
                        . '<tr><td style="padding:4px 0;color:#9a9a9a">Email</td><td style="padding:4px 0"><a href="mailto:' . $safe($email) . '" style="color:#FF6B71">' . $safe($email) . '</a></td></tr>'
                        . '<tr><td style="padding:4px 0;color:#9a9a9a">Teléfono</td><td style="padding:4px 0">' . $safe($phone ?: '—') . '</td></tr>'
                        . '<tr><td style="padding:4px 0;color:#9a9a9a">Motivo</td><td style="padding:4px 0">' . $safe($service ?: '—') . '</td></tr>'
                        . '<tr><td style="padding:4px 0;color:#9a9a9a">Pedido</td><td style="padding:4px 0">' . $safe($orderRef ?: '—') . '</td></tr>'
                        . '<tr><td style="padding:4px 0;color:#9a9a9a">Respuesta</td><td style="padding:4px 0">' . $safe($replyPreference ?: '—') . '</td></tr>'
                        . '<tr><td style="padding:4px 0;color:#9a9a9a">Autorización</td><td style="padding:4px 0">' . ($authAccepted ? 'Sí' : 'No') . '</td></tr>'
                        . '</table>'
                        . '<div style="margin-top:16px;padding-top:14px;border-top:1px solid #2a2a2a;color:#d4d4d4;font-size:14px;line-height:1.65;white-space:pre-wrap">' . $safe($message) . '</div>'
                        . '<p style="margin:18px 0 0;color:#8a8a8a;font-size:11px">Lead #' . $leadId . ' · ' . $safe($source) . '</p>'
                        . '</div>';
                    $sent = $mailer->sendMultipart($notify, $subject, $text, $html, ['reply_to' => $email]);
                    if (!$sent) error_log('[SONIKA] Contact notification failed for lead ' . $leadId);
                }
            }
        } catch (\Throwable $e) {
            error_log('[SONIKA] Contact notification failed: ' . $e->getMessage());
        }

        Response::json([
            'ok' => true,
            'message' => $isSupport ? 'Recibimos tu mensaje. Te contactaremos por el medio que elegiste.' : 'Thank you — we received your message.',
            'id' => $leadId,
        ]);
    }

    private function ensureLeadColumns(\PDO $db): void
    {
        $columns = [
            'order_ref' => "ALTER TABLE wwi_leads ADD COLUMN order_ref VARCHAR(60) NULL",
            'reply_preference' => "ALTER TABLE wwi_leads ADD COLUMN reply_preference VARCHAR(20) NULL",
            'auth_accepted' => "ALTER TABLE wwi_leads ADD COLUMN auth_accepted TINYINT(1) NOT NULL DEFAULT 0",
        ];
        foreach ($columns as $column => $sql) {
            try {
                $st = $db->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
                $st->execute(['wwi_leads', $column]);
                if ((int)$st->fetchColumn() === 0) $db->exec($sql);
            } catch (\Throwable $e) {
            }
        }
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
