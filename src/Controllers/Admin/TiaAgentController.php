<?php
namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Services\TiaAgentService;

class TiaAgentController
{
    private TiaAgentService $service;

    public function __construct()
    {
        $this->service = new TiaAgentService();
    }

    public function command(Request $request): void
    {
        $command = (string)($request->json()['command'] ?? '');
        $result = $this->service->command($command);
        Response::json(['ok' => $result['ok'] ?? false, 'message' => $result['message'] ?? '', 'data' => $result]);
    }

    public function confirm(Request $request): void
    {
        $token = (string)($request->json()['token'] ?? '');
        $result = $this->service->confirm($token);
        Response::json(['ok' => $result['ok'] ?? false, 'message' => $result['message'] ?? '', 'data' => $result]);
    }

    public function sections(): void
    {
        Response::json(['ok' => true, 'data' => $this->service->sections()]);
    }

    public function history(): void
    {
        Response::json(['ok' => true, 'data' => $this->service->history(20)]);
    }
}
