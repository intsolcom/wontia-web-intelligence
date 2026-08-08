<?php
namespace App\Controllers\Admin;

use App\Core\Response;
use App\Widgets\WidgetRegistry;

class BrickController
{
    public function index(): void
    {
        Response::json(['ok' => true, 'data' => WidgetRegistry::all(), 'total' => WidgetRegistry::count()]);
    }

    public function show(string $type): void
    {
        $class = WidgetRegistry::get($type);
        if (!$class) Response::error('BRICK not found', 404);
        Response::json(['ok' => true, 'data' => [
            'meta' => $class::meta(),
            'configSchema' => $class::configSchema(),
            'defaultConfig' => $class::defaultConfig(),
            'adminPreview' => $class::adminPreview(),
        ]]);
    }
}
