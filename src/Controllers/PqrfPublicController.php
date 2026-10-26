<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\AuthSecurityService;
use App\Services\CustomerService;
use App\Services\PqrfService;

class PqrfPublicController
{
    private function svc(): PqrfService
    {
        return new PqrfService();
    }

    private function customer(): ?array
    {
        return (new CustomerService())->current();
    }

    private function noSession(): void
    {
        Response::error('Inicia sesión en tu cuenta para radicar una solicitud.', 401, [
            'channels' => $this->svc()->channels(),
        ]);
    }

    public function create(Request $req): void
    {
        $customer = $this->customer();
        if (!$customer) { $this->noSession(); return; }

        $ip = (string)$req->ip();
        $sec = new AuthSecurityService();
        $ipLimit = $sec->throttle('pqrf_create_ip', $ip, 5, 600);
        if (empty($ipLimit['allowed'])) {
            Response::error('Demasiados intentos. Espera unos minutos e intenta de nuevo.', 429, ['retry_after' => (int)($ipLimit['retry_after'] ?? 60)]);
            return;
        }
        $customerLimit = $sec->throttle('pqrf_create_customer', (string)$customer['id'], 10, 3600);
        if (empty($customerLimit['allowed'])) {
            Response::error('Ya radicaste varias solicitudes en la última hora. Espera un poco antes de continuar.', 429, ['retry_after' => (int)($customerLimit['retry_after'] ?? 600)]);
            return;
        }

        $data = [
            'type' => (string)$req->input('type', ''),
            'category' => (string)$req->input('category', ''),
            'subject' => (string)$req->input('subject', ''),
            'description' => (string)$req->input('description', ''),
            'order_ref' => (string)$req->input('order_ref', ''),
            'contact_name' => (string)$req->input('contact_name', ''),
            'contact_email' => (string)$req->input('contact_email', ''),
            'contact_phone' => (string)$req->input('contact_phone', ''),
            'auth_accepted' => $req->input('auth_accepted', ''),
        ];
        $idempotency = (string)($req->header('Idempotency-Key') ?? '');
        if ($idempotency === '') $idempotency = (string)$req->input('idempotency_key', '');

        $result = $this->svc()->create((int)$customer['id'], $data, $_FILES['attachments'] ?? [], $ip, (string)$req->userAgent(), $idempotency);
        if (empty($result['ok'])) {
            Response::error((string)($result['message'] ?? 'No pudimos radicar la solicitud.'), 422, ['errors' => $result['errors'] ?? []]);
            return;
        }
        Response::json($result);
    }

    public function track(Request $req): void
    {
        $token = (string)$req->get('token', '');
        $sec = new AuthSecurityService();
        $limit = $sec->throttle('pqrf_track_ip', (string)$req->ip(), 60, 600);
        if (empty($limit['allowed'])) {
            Response::error('Demasiadas consultas. Espera un momento.', 429);
            return;
        }
        $result = $this->svc()->track($token);
        if (empty($result['ok'])) {
            if (($result['reason'] ?? '') === 'expired') {
                Response::error('El enlace de seguimiento expiró. Escríbenos para obtener ayuda.', 410, ['channels' => $this->svc()->channels()]);
                return;
            }
            Response::error('No encontramos ese radicado.', 404, ['channels' => $this->svc()->channels()]);
            return;
        }
        Response::json($result);
    }

    public function trackMessage(Request $req): void
    {
        $token = trim((string)$req->input('token', ''));
        $message = (string)$req->input('message', '');
        $sec = new AuthSecurityService();
        $limit = $sec->throttle('pqrf_track_msg_ip', (string)$req->ip(), 20, 600);
        if (empty($limit['allowed'])) {
            Response::error('Demasiados mensajes. Espera un momento.', 429);
            return;
        }
        $result = $this->svc()->addCustomerMessage($token, $message, (string)$req->ip(), (string)$req->userAgent());
        if (empty($result['ok'])) {
            if (($result['reason'] ?? '') === 'expired') {
                Response::error('El enlace de seguimiento expiró. Escríbenos para obtener ayuda.', 410, ['channels' => $this->svc()->channels()]);
                return;
            }
            if (($result['reason'] ?? '') === 'validation') {
                Response::error((string)($result['message'] ?? 'Revisa el mensaje.'), 422);
                return;
            }
            Response::error('No encontramos ese radicado.', 404, ['channels' => $this->svc()->channels()]);
            return;
        }
        Response::json($result);
    }

    public function download(Request $req): void
    {
        $token = (string)$req->get('token', '');
        $id = (int)$req->get('id', 0);
        $sec = new AuthSecurityService();
        $limit = $sec->throttle('pqrf_download_ip', (string)$req->ip(), 40, 600);
        if (empty($limit['allowed'])) {
            Response::error('Demasiadas descargas. Espera un momento.', 429);
            return;
        }
        $file = $this->svc()->attachmentForCustomer($token, $id);
        if (!$file) {
            Response::error('Archivo no encontrado.', 404);
            return;
        }
        $this->streamFile($file);
    }

    private function streamFile(array $file): void
    {
        $path = (string)($file['full_path'] ?? '');
        if ($path === '' || !is_file($path)) {
            Response::error('Archivo no encontrado.', 404);
            return;
        }
        $name = (string)($file['original_name'] ?? 'archivo');
        $name = str_replace(["\r", "\n"], '', $name);
        $fallback = preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?: 'archivo';
        header('Content-Type: ' . (string)($file['mime'] ?? 'application/octet-stream'));
        header('Content-Length: ' . (string)filesize($path));
        header('Content-Disposition: attachment; filename="' . $fallback . '"; filename*=UTF-8\'\'' . rawurlencode($name));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, max-age=0');
        header('Pragma: no-cache');
        readfile($path);
        exit;
    }
}
