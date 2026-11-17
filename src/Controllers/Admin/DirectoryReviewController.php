<?php
namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\DirectoryService;

/**
 * Moderación de reseñas del Directorio Sonika.
 * Roles superadmin|admin|editor.
 */
class DirectoryReviewController
{
    private function service(): DirectoryService
    {
        if (!in_array((string)Session::userRole(), ['superadmin', 'admin', 'editor'], true)) {
            Response::error('Sin permiso para moderar el directorio.', 403);
        }
        return new DirectoryService();
    }

    public function index(Request $req): void
    {
        $service = $this->service();
        $status = (string)($req->get('status', '') ?? '');
        Response::json([
            'ok' => true,
            'data' => [
                'pending' => $service->pendingReviews(),
                'reviews' => $service->allReviews($status),
                'counts' => [
                    'pending' => count($service->pendingReviews()),
                ],
            ],
        ]);
    }

    public function moderate(Request $req, string $id): void
    {
        $service = $this->service();
        $status = (string)($req->input('status', 'pending'));
        $user = Session::user();
        Response::json($service->moderate((int)$id, $status, (string)($user['username'] ?? 'admin')));
    }

    public function profiles(Request $req): void
    {
        $service = $this->service();
        $status = (string)($req->get('status', '') ?? '');
        Response::json([
            'ok' => true,
            'data' => [
                'pending' => $service->pendingProfiles(),
                'profiles' => $service->allProfiles($status),
                'counts' => [
                    'pending' => count($service->pendingProfiles()),
                ],
            ],
        ]);
    }

    public function moderateProfile(Request $req, string $id): void
    {
        $service = $this->service();
        $status = (string)($req->input('status', 'pending'));
        Response::json($service->moderateProfile((int)$id, $status));
    }

    public function toques(Request $req): void
    {
        $service = $this->service();
        Response::json(['ok' => true, 'data' => ['toques' => $service->toques((string)($req->get('status', '') ?? '')), 'profiles' => $service->profiles()]]);
    }

    public function createToque(Request $req): void
    {
        $data = (array)$req->json();
        $data['status'] = 'open';
        Response::json($this->service()->createToque($data, (string)(Session::user()['username'] ?? 'admin')));
    }

    public function moderateToque(Request $req, string $id): void
    {
        Response::json($this->service()->moderateToque((int)$id, (string)$req->input('status', 'open')));
    }

    public function inviteToque(Request $req, string $id): void
    {
        Response::json($this->service()->inviteToToque((int)$id, (int)$req->input('profile_id', 0), (string)(Session::user()['username'] ?? 'admin')));
    }

    public function monyas(Request $req): void
    {
        $service = $this->service();
        Response::json(['ok' => true, 'data' => ['monyas' => $service->monyas((string)($req->get('status', '') ?? '')), 'profiles' => $service->profiles()]]);
    }

    public function createMonya(Request $req): void
    {
        $data = (array)$req->json();
        $data['status'] = 'active';
        Response::json($this->service()->createMonya($data, (string)(Session::user()['username'] ?? 'admin')));
    }

    public function moderateMonya(Request $req, string $id): void
    {
        Response::json($this->service()->moderateMonya((int)$id, (string)$req->input('status', 'active')));
    }

    public function inviteMonya(Request $req, string $id): void
    {
        Response::json($this->service()->addMonyaMember((int)$id, (int)$req->input('profile_id', 0), (string)$req->input('role', ''), (string)(Session::user()['username'] ?? 'admin')));
    }
}
