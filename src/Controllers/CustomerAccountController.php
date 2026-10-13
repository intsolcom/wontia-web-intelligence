<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\CustomerService;
use App\Services\LoyaltyService;
use App\Services\PaymentMethodService;
use App\Services\ReturnService;
use App\Services\ReviewService;

class CustomerAccountController
{
    private function svc(): CustomerService
    {
        return new CustomerService();
    }

    private function requireLogin(): ?array
    {
        $c = $this->svc()->current();
        if (!$c) {
            Response::error('Inicia sesión para continuar.', 401);
            return null;
        }
        return $c;
    }

    public function register(Request $req): void
    {
        if (!$this->allow($req, 'reg', 8)) return;
        $r = $this->svc()->register(
            (string)$req->input('name', ''),
            (string)$req->input('email', ''),
            (string)$req->input('password', ''),
            (string)$req->input('phone', '')
        );
        if (empty($r['ok'])) Response::error($r['message'] ?? 'No se pudo registrar', 422, $r);
        Response::json($r);
    }

    public function login(Request $req): void
    {
        if (!$this->allow($req, 'login', 12)) return;
        $r = $this->svc()->login((string)$req->input('email', ''), (string)$req->input('password', ''));
        if (empty($r['ok'])) Response::error($r['message'] ?? 'Credenciales inválidas', 401, $r);
        Response::json($r);
    }

    public function logout(): void
    {
        $this->svc()->logout();
        Response::json(['ok' => true]);
    }

    public function me(): void
    {
        $c = $this->svc()->current();
        if (!$c) { Response::json(['ok' => true, 'data' => null]); return; }
        Response::json(['ok' => true, 'data' => [
            'customer' => $c,
            'addresses' => $this->svc()->addresses((int)$c['id']),
            'favorites' => $this->svc()->favorites((int)$c['id']),
        ]]);
    }

    public function profile(Request $req): void
    {
        $c = $this->requireLogin();
        if (!$c) return;
        Response::json($this->svc()->updateProfile((int)$c['id'], [
            'name' => $req->input('name'),
            'phone' => $req->input('phone'),
            'document' => $req->input('document'),
            'password' => $req->input('password', ''),
        ]));
    }

    public function orders(): void
    {
        $c = $this->requireLogin();
        if (!$c) return;
        Response::json(['ok' => true, 'data' => $this->svc()->orders((int)$c['id'], (string)$c['email'])]);
    }

    public function addresses(): void
    {
        $c = $this->requireLogin();
        if (!$c) return;
        Response::json(['ok' => true, 'data' => $this->svc()->addresses((int)$c['id'])]);
    }

    public function addressSave(Request $req): void
    {
        $c = $this->requireLogin();
        if (!$c) return;
        $d = $req->json();
        $r = $this->svc()->addressSave((int)$c['id'], $d);
        if (empty($r['ok'])) Response::error($r['message'] ?? 'Error', 422, $r);
        Response::json($r);
    }

    public function addressDelete(Request $req, string $id): void
    {
        $c = $this->requireLogin();
        if (!$c) return;
        Response::json($this->svc()->addressDelete((int)$c['id'], (int)$id));
    }

    public function favorites(): void
    {
        $c = $this->requireLogin();
        if (!$c) return;
        Response::json(['ok' => true, 'data' => $this->svc()->favorites((int)$c['id'])]);
    }

    public function favoriteAdd(Request $req): void
    {
        $c = $this->requireLogin();
        if (!$c) return;
        $r = $this->svc()->favoriteAdd((int)$c['id'], $req->json());
        if (empty($r['ok'])) Response::error($r['message'] ?? 'Error', 422, $r);
        Response::json($r);
    }

    public function favoriteDelete(Request $req, string $ref): void
    {
        $c = $this->requireLogin();
        if (!$c) return;
        Response::json($this->svc()->favoriteDelete((int)$c['id'], urldecode($ref)));
    }

    public function forgot(Request $req): void
    {
        if (!$this->allow($req, 'forgot', 8)) return;
        $this->svc()->forgotPassword((string)$req->input('email', ''));
        Response::json(['ok' => true, 'message' => 'Si la cuenta existe, enviamos un código de recuperación a tu correo.']);
    }

    public function reset(Request $req): void
    {
        if (!$this->allow($req, 'reset', 12)) return;
        $r = $this->svc()->resetPassword((string)$req->input('email', ''), (string)$req->input('code', ''), (string)$req->input('password', ''));
        if (empty($r['ok'])) Response::error($r['message'] ?? 'Error', 422, $r);
        Response::json($r);
    }

    public function exportData(): void
    {
        $c = $this->requireLogin();
        if (!$c) return;
        Response::json(['ok' => true, 'data' => $this->svc()->exportData((int)$c['id'])]);
    }

    public function deleteAccount(): void
    {
        $c = $this->requireLogin();
        if (!$c) return;
        Response::json($this->svc()->deleteAccount((int)$c['id'], (string)$c['email']));
    }

    public function verifyEmail(Request $req): void
    {
        if (!$this->allow($req, 'verify', 12)) return;
        $r = $this->svc()->verifyEmail((string)$req->input('email', ''), (string)$req->input('code', ''));
        if (empty($r['ok'])) Response::error($r['message'] ?? 'Código inválido', 422, $r);
        Response::json($r);
    }

    public function verifyEmailResend(): void
    {
        $c = $this->requireLogin();
        if (!$c) return;
        Response::json($this->svc()->sendEmailVerification((int)$c['id'], (string)$c['email']));
    }

    public function favoriteAlert(Request $req, string $ref): void
    {
        $c = $this->requireLogin();
        if (!$c) return;
        Response::json($this->svc()->favoriteAlertToggle((int)$c['id'], urldecode($ref), $req->json()));
    }

    public function reviews(): void
    {
        $c = $this->requireLogin();
        if (!$c) return;
        Response::json(['ok' => true, 'data' => (new ReviewService())->mine((int)$c['id'])]);
    }

    public function reviewAdd(Request $req): void
    {
        $c = $this->requireLogin();
        if (!$c) return;
        $d = $req->json();
        $d['email'] = (string)$c['email'];
        $r = (new ReviewService())->add((int)$c['id'], (string)$c['name'], $d);
        if (empty($r['ok'])) Response::error($r['message'] ?? 'Error', 422, $r);
        Response::json($r);
    }

    public function loyalty(): void
    {
        $c = $this->requireLogin();
        if (!$c) return;
        $svc = new LoyaltyService();
        $s = $svc->settings();
        Response::json(['ok' => true, 'data' => [
            'enabled' => $s['enabled'],
            'balance' => $svc->balance((int)$c['id']),
            'redeem_cents' => $s['redeem_cents'],
            'history' => $svc->history((int)$c['id']),
        ]]);
    }

    public function orderReturn(Request $req, string $uuid): void
    {
        $c = $this->requireLogin();
        if (!$c) return;
        $r = (new ReturnService())->request($uuid, (int)$c['id'], (string)$c['email'], (string)$req->input('reason', ''));
        if (empty($r['ok'])) Response::error($r['message'] ?? 'Error', 422, $r);
        Response::json($r);
    }

    public function returns(): void
    {
        $c = $this->requireLogin();
        if (!$c) return;
        Response::json(['ok' => true, 'data' => (new ReturnService())->mine((int)$c['id'])]);
    }

    public function paymentMethods(): void
    {
        $c = $this->requireLogin();
        if (!$c) return;
        Response::json(['ok' => true, 'data' => (new PaymentMethodService())->list((int)$c['id'])]);
    }

    public function paymentMethodSave(Request $req): void
    {
        $c = $this->requireLogin();
        if (!$c) return;
        $r = (new PaymentMethodService())->save((int)$c['id'], $req->json());
        if (empty($r['ok'])) Response::error($r['message'] ?? 'Error', 422, $r);
        Response::json($r);
    }

    public function paymentMethodDefault(Request $req, string $id): void
    {
        $c = $this->requireLogin();
        if (!$c) return;
        Response::json((new PaymentMethodService())->setDefault((int)$c['id'], (int)$id));
    }

    public function paymentMethodDelete(Request $req, string $id): void
    {
        $c = $this->requireLogin();
        if (!$c) return;
        Response::json((new PaymentMethodService())->delete((int)$c['id'], (int)$id));
    }

    private function allow(Request $req, string $scope, int $max): bool
    {
        $dir = ROOT_DIR . '/cache/security';
        if (!is_dir($dir)) @mkdir($dir, 0770, true);
        $f = $dir . '/acct_' . preg_replace('/[^a-z]/', '', $scope) . '_' . md5((string)$req->ip()) . '.json';
        $now = time();
        $d = ['c' => 0, 't' => $now];
        if (file_exists($f)) {
            $p = json_decode((string)file_get_contents($f), true);
            if (is_array($p) && $now - (int)($p['t'] ?? $now) < 300) $d = ['c' => (int)($p['c'] ?? 0), 't' => (int)$p['t']];
        }
        if ($d['c'] >= $max) { Response::error('Demasiados intentos. Espera unos minutos.', 429); return false; }
        $d['c']++;
        @file_put_contents($f, json_encode($d), LOCK_EX);
        return true;
    }
}
