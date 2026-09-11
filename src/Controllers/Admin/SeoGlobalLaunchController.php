<?php
namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Bricks\SeoGlobalLaunch\SeoGlobalLaunchBrick;

/**
 * SEO Global Launch — endpoints del brick en el admin WWI.
 * Protegidos por AuthMiddleware (grupo /api/v1/admin).
 */
class SeoGlobalLaunchController
{
    public function overview(): void
    {
        Response::json(SeoGlobalLaunchBrick::overview());
    }

    public function activate(): void
    {
        $result = SeoGlobalLaunchBrick::activate();
        Response::json(['ok' => $result['ok'], 'message' => $result['message'] ?? '', 'data' => $result]);
    }

    public function deactivate(): void
    {
        $result = SeoGlobalLaunchBrick::deactivate();
        Response::json(['ok' => $result['ok'], 'message' => $result['message'] ?? '', 'data' => $result]);
    }

    public function installTables(): void
    {
        Response::json(SeoGlobalLaunchBrick::installTables());
    }

    public function scan(): void
    {
        Response::json(SeoGlobalLaunchBrick::scan());
    }

    public function generate(): void
    {
        Response::json(SeoGlobalLaunchBrick::generate());
    }

    public function autofix(): void
    {
        Response::json(SeoGlobalLaunchBrick::autofix());
    }

    public function deepfix(): void
    {
        Response::json(SeoGlobalLaunchBrick::deepfix());
    }

    public function pages(): void
    {
        Response::json(SeoGlobalLaunchBrick::pages());
    }

    public function issues(): void
    {
        Response::json(SeoGlobalLaunchBrick::issues());
    }

    public function scores(): void
    {
        Response::json(SeoGlobalLaunchBrick::scores());
    }

    public function bots(): void
    {
        Response::json(SeoGlobalLaunchBrick::bots());
    }
}
