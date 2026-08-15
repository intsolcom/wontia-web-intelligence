<?php
namespace App\Core\AiBrick;

interface AiProviderAdapter
{
    public function complete(AiRequest $request, array $provider, array $model): AiResponse;

    public function healthCheck(array $provider, array $model): array;
}
