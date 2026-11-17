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
        $experimentKey = (string)$req->input('experiment_key', '');
        $experimentVariant = strtoupper((string)$req->input('experiment_variant', ''));
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
        try {
            if (preg_match('/^[a-z0-9_]{1,80}$/', $experimentKey) && in_array($experimentVariant, ['A', 'B'], true)) {
                $result['experiment'] = ['key' => $experimentKey, 'variant' => $experimentVariant];
            }
            $interactionId = (new \App\Services\TiaInteractionService())->record($message, $page, $result);
            if ($interactionId) $result['interaction_id'] = $interactionId;
            $signals = \App\Core\Session::get('tia_last_learning_signals', []);
            (new \App\Services\TiaPlaybookService())->remember((array)($result['cards'] ?? []), (string)($result['reply'] ?? ''));
            if (is_array($signals)) {
                $caseId = (new \App\Services\TiaInteractionService())->captureFailure($message, (string)($result['reply'] ?? ''), $signals);
                if ($caseId) $result['learning_case_id'] = $caseId;
            }
        } catch (\Throwable $e) {
        }
        Response::json($result);
    }

    public function event(Request $req): void
    {
        $uid = (string)$req->input('interaction_id', '');
        $event = (string)$req->input('event', '');
        $target = (string)$req->input('target', '');
        $value = (int)$req->input('value_cents', 0);
        $order = (string)$req->input('order_uuid', '');
        $ok = (new \App\Services\TiaInteractionService())->event($uid, $event, $target, $value, $order);
        Response::json(['ok' => $ok]);
    }

    public function nudge(Request $req): void
    {
        Response::json((new \App\Services\TiaNudgeService())->forPage((string)$req->get('page', '/'), (string)$req->get('q', '')));
    }

    public function feedback(Request $req): void
    {
        $limit = (new \App\Services\AuthSecurityService())->throttle('tia_feedback_ip', (string)$req->ip(), 20, 3600);
        if (empty($limit['allowed'])) {
            Response::error('Demasiados comentarios. Intenta más tarde.', 429);
            return;
        }
        $ok = (new \App\Services\TiaInteractionService())->feedback(
            (string)$req->input('interaction_id', ''),
            (string)$req->input('rating', ''),
            (string)$req->input('reason', ''),
            (string)$req->input('comment', '')
        );
        Response::json(['ok' => $ok]);
    }

    public function experimentEvent(Request $req): void
    {
        $key = (string)$req->input('experiment_key', '');
        $variant = (string)$req->input('experiment_variant', '');
        $event = (string)$req->input('event', '');
        $ok = false;
        if (preg_match('/^[a-z0-9_]{1,80}$/', $key)) {
            $ok = (new \App\Services\TiaExperimentService())->recordEvent($key, $variant, $event);
        }
        Response::json(['ok' => $ok]);
    }
}
