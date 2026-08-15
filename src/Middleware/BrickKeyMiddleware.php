<?php
namespace App\Middleware;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;

class BrickKeyMiddleware
{
    public function handle(Request $request): void
    {
        $key = (string)Config::get('BRICK_API_KEY', '');
        if ($key === '') {
            Response::error('BRICK key not configured for this instance', 403);
        }
        $provided = $request->header('X-Brick-Key');
        if (!$provided || !hash_equals($key, $provided)) {
            Response::error('Invalid BRICK key', 401);
        }
    }
}
