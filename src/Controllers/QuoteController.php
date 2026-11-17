<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\QuoteService;
use App\Services\StoreRateLimitException;

class QuoteController
{
    public function save(Request $req): void
    {
        try {
            $result = (new QuoteService())->save($req->json(), $req->ip());
            Response::json(['ok' => true, 'data' => $result], 201);
        } catch (StoreRateLimitException $e) {
            Response::error($e->getMessage(), 429);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            Response::error($e->getMessage(), 400);
        } catch (\Throwable $e) {
            Response::error('No se pudo preparar la cotización', 500);
        }
    }

    public function validateCustomer(Request $req): void
    {
        try {
            $customer = (new QuoteService())->validateCustomer($req->json());
            Response::json(['ok' => true, 'data' => ['customer' => $customer]]);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            Response::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Response::error('No se pudieron validar los datos', 500);
        }
    }

    public function event(Request $req, string $token): void
    {
        try {
            $svc = new QuoteService();
            $quote = $svc->findByPublicToken($token, false);
            if (!$quote || !$svc->isPublicAvailable($quote)) {
                Response::error('Cotización no disponible', 404);
                return;
            }
            $event = (string)($req->json()['event'] ?? '');
            $allowed = [QuoteService::EVENT_CHECKOUT, QuoteService::EVENT_LINK_COPY, QuoteService::EVENT_WHATSAPP];
            if (!in_array($event, $allowed, true)) {
                Response::error('Evento no válido', 422);
                return;
            }
            $svc->recordEvent((int)$quote['id'], $event, $req->ip(), $req->userAgent());
            Response::json(['ok' => true]);
        } catch (\Throwable $e) {
            Response::error('No se pudo registrar el evento', 500);
        }
    }

    public function whatsapp(Request $req, string $token): void
    {
        try {
            $svc = new QuoteService();
            $quote = $svc->findByPublicToken($token, false);
            $admin = \App\Core\Session::isLoggedIn() && in_array((string)\App\Core\Session::userRole(), QuoteService::ROLES, true);
            if (!$quote || !$svc->isPublicAvailable($quote) || (!$admin && $svc->settings()['quote_require_verification'] && !$svc->hasAccess($quote))) {
                Response::error('Cotización no disponible', 404);
                return;
            }
            $pdf = (new \App\Services\QuotePdfService())->generate($quote);
            $result = (new \App\Services\QuoteWhatsAppService())->send($quote, $pdf);
            if (!empty($result['ok'])) {
                $svc->recordEvent((int)$quote['id'], QuoteService::EVENT_WHATSAPP, $req->ip(), $req->userAgent());
                Response::json(['ok' => true, 'data' => ['mode' => 'api', 'pdf_url' => $svc->signedPath($quote, 'pdf', 900)]]);
                return;
            }
            Response::json(['ok' => false, 'mode' => 'share', 'message' => (string)($result['message'] ?? ''), 'pdf_url' => $svc->signedPath($quote, 'pdf', 900)], 200);
        } catch (\Throwable $e) {
            Response::error('No se pudo enviar el PDF por WhatsApp', 503);
        }
    }

    public function show(Request $req, string $code): void
    {
        $quote = (new QuoteService())->findByCode($code, false);
        if (!$quote) {
            Response::error('Cotización no encontrada', 404);
            return;
        }
        Response::json(['ok' => true, 'data' => (new QuoteService())->publicQuote($quote)]);
    }

    public function sent(Request $req, string $code): void
    {
        $ok = (new QuoteService())->markSent($code);
        Response::json(['ok' => $ok]);
    }

    public function converted(Request $req, string $code): void
    {
        $ok = (new QuoteService())->markConverted($code);
        Response::json(['ok' => $ok]);
    }
}
