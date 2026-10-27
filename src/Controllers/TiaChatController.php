<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\TiaSupportService;

class TiaChatController
{
    public function chat(Request $req): void
    {
        $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
        if ($origin !== '') {
            $originHost = strtolower((string)parse_url($origin, PHP_URL_HOST));
            $requestHost = strtolower((string)preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
            if ($originHost !== $requestHost && !in_array($originHost, ['sonika.com.co', 'www.sonika.com.co'], true)) {
                Response::error('Origen no autorizado.', 403);
                return;
            }
        }
        $message = (string)$req->input('message', '');
        $page = (string)$req->input('page', '');
        $locale = (string)$req->input('locale', 'es');
        $result = (new TiaSupportService())->chat(
            $message,
            $page,
            $locale,
            (string)$req->ip(),
            (string)$req->userAgent()
        );
        if (empty($result['ok'])) {
            $status = (int)($result['status'] ?? 422);
            Response::error((string)($result['message'] ?? 'No pudimos procesar tu mensaje.'), $status, [
                'handoff' => $result['handoff'] ?? null,
                'links' => $result['links'] ?? [],
            ]);
            return;
        }
        Response::json($result);
    }
}
