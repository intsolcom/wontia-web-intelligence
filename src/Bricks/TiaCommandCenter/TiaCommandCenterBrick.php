<?php
namespace App\Bricks\TiaCommandCenter;

use App\Services\CiuService;

class TiaCommandCenterBrick
{
    public static function meta(): array
    {
        $path = __DIR__ . '/brick.json';
        $data = is_file($path) ? json_decode((string)file_get_contents($path), true) : null;
        return is_array($data) ? $data : ['name' => 'TIA Command Center', 'slug' => 'tia-command-center', 'version' => '0.1.0'];
    }

    public static function installTables(): array
    {
        (new CiuService())->ensureTables();
        return ['ok' => true, 'message' => 'Tablas CIU listas.'];
    }

    public static function overview(): array
    {
        return (new CiuService())->overview();
    }
}
