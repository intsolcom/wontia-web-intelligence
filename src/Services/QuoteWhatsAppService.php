<?php
namespace App\Services;

use App\Core\Config;

class QuoteWhatsAppService
{
    public function send(array $quote, string $pdfFile): array
    {
        $settings = (new QuoteService())->settings();
        $token = trim((string)$settings['quote_whatsapp_api_token']);
        $phoneId = trim((string)$settings['quote_whatsapp_phone_id']);
        $to = preg_replace('/\D+/', '', (string)$settings['quote_whatsapp_to']) ?: '';
        if ($token === '' || $phoneId === '' || $to === '') {
            return ['ok' => false, 'mode' => 'share', 'message' => 'WhatsApp Business API no está configurada.'];
        }
        if (strlen($to) === 10) $to = '57' . $to;
        $base = rtrim((string)$settings['quote_public_base_url'], '/');
        if ($base === '') $base = rtrim((string)Config::get('APP_URL', ''), '/');
        $pdfPath = (new QuoteService())->signedPath($quote, 'pdf', 3600);
        if ($pdfPath === '' || $base === '') return ['ok' => false, 'mode' => 'share', 'message' => 'No hay URL pública para el PDF.'];
        $pdfUrl = $base . $pdfPath;
        $caption = 'Cotización ' . (string)($quote['code'] ?? '') . ' de SONIKA. Documento privado y vigente.';
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'document',
            'document' => [
                'link' => $pdfUrl,
                'filename' => 'Cotizacion-' . preg_replace('/[^A-Z0-9-]/i', '', (string)($quote['code'] ?? 'SONIKA')) . '.pdf',
                'caption' => $caption,
            ],
        ];
        $url = 'https://graph.facebook.com/v20.0/' . rawurlencode($phoneId) . '/messages';
        $ch = curl_init($url);
        if ($ch === false) return ['ok' => false, 'mode' => 'share', 'message' => 'No se pudo iniciar WhatsApp API.'];
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($raw === false || $status < 200 || $status >= 300) {
            return ['ok' => false, 'mode' => 'share', 'message' => $error !== '' ? 'WhatsApp API no respondió.' : 'WhatsApp API rechazó el envío.', 'status' => $status];
        }
        return ['ok' => true, 'mode' => 'api', 'message' => 'PDF enviado por WhatsApp.'];
    }
}
