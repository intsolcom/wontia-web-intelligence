<?php
namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\GiftCardService;
use App\Services\StoreService;

class GiftCardController
{
    private function svc(): GiftCardService
    {
        return new GiftCardService();
    }

    private function by(): string
    {
        $user = Session::user();
        return mb_substr((string)($user['username'] ?? $user['email'] ?? 'admin'), 0, 80);
    }

    public function overview(Request $req): void
    {
        $svc = $this->svc();
        $status = (string)$req->get('status', '');
        $search = (string)$req->get('search', '');
        $settings = (new StoreService())->settings();
        Response::json(['ok' => true, 'data' => [
            'stats' => $svc->adminStats(),
            'cards' => $svc->adminList(['status' => $status, 'search' => $search, 'limit' => 100]),
            'occasions' => $svc->adminOccasions(),
            'payment_methods' => $svc->paymentMethods(),
            'settings' => [
                'enabled' => ($settings['gift_card_enabled'] ?? '1') !== '0',
                'min_cents' => (int)($settings['gift_card_min_cents'] ?? 10000),
                'max_cents' => (int)($settings['gift_card_max_cents'] ?? 2000000),
                'expiry_days' => (int)($settings['gift_card_expiry_days'] ?? 365),
                'hold_hours' => (int)($settings['gift_card_hold_hours'] ?? 24),
                'terms_url' => (string)($settings['gift_card_terms_url'] ?? '/politicas'),
            ],
        ]]);
    }

    public function list(Request $req): void
    {
        $svc = $this->svc();
        Response::json(['ok' => true, 'data' => $svc->adminList([
            'status' => (string)$req->get('status', ''),
            'search' => (string)$req->get('search', ''),
            'limit' => (int)$req->get('limit', 200),
        ])]);
    }

    public function issue(Request $req): void
    {
        $result = $this->svc()->adminIssue($req->json(), $this->by());
        Response::json($result, !empty($result['ok']) ? 201 : 422);
    }

    public function void(Request $req, string $id): void
    {
        $body = $req->json();
        $result = $this->svc()->adminVoid((int)$id, (string)($body['reason'] ?? ''), $this->by());
        Response::json($result, !empty($result['ok']) ? 200 : 422);
    }

    public function resend(Request $req, string $id): void
    {
        $result = $this->svc()->adminResend((int)$id);
        Response::json($result, !empty($result['ok']) ? 200 : 422);
    }

    public function occasions(Request $req): void
    {
        Response::json(['ok' => true, 'data' => $this->svc()->adminOccasions()]);
    }

    public function occasionSave(Request $req, ?string $id = null): void
    {
        $result = $this->svc()->saveOccasion($req->json(), $id !== null ? (int)$id : null);
        Response::json($result, !empty($result['ok']) ? 200 : 422);
    }

    public function expire(Request $req): void
    {
        $body = $req->json();
        $result = $this->svc()->expireReservations((int)($body['hours'] ?? 24));
        Response::json($result);
    }
}
