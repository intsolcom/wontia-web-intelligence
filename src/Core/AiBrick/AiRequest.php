<?php
namespace App\Core\AiBrick;

class AiRequest
{
    public string $systemPrompt = '';
    public array $messages = [];
    public string $modelIdentifier = '';
    public float $temperature = 0.7;
    public int $maxTokens = 1500;
    public array $tools = [];
    public ?array $responseFormat = null;
    public bool $streaming = false;
    public array $metadata = [];
    public ?int $userId = null;
    public string $systemId = 'wontia';
    public string $module = 'general';
    public string $function = 'default';
    public array $requiredCapabilities = [];
    public ?int $modelId = null;

    public static function fromArray(array $d): self
    {
        $r = new self();
        $r->systemPrompt = (string)($d['system_prompt'] ?? '');
        $r->messages = is_array($d['messages'] ?? null) ? $d['messages'] : [];
        $r->modelIdentifier = (string)($d['model'] ?? '');
        $r->temperature = (float)($d['temperature'] ?? 0.7);
        $r->maxTokens = (int)($d['max_tokens'] ?? 1500);
        $r->tools = is_array($d['tools'] ?? null) ? $d['tools'] : [];
        $r->responseFormat = is_array($d['response_format'] ?? null) ? $d['response_format'] : null;
        $r->streaming = (bool)($d['streaming'] ?? false);
        $r->metadata = is_array($d['metadata'] ?? null) ? $d['metadata'] : [];
        $r->userId = isset($d['user_id']) ? (int)$d['user_id'] : null;
        $r->systemId = (string)($d['system_id'] ?? 'wontia');
        $r->module = (string)($d['module'] ?? 'general');
        $r->function = (string)($d['function'] ?? 'default');
        $r->requiredCapabilities = is_array($d['capabilities'] ?? null) ? $d['capabilities'] : [];
        $r->modelId = isset($d['model_id']) ? (int)$d['model_id'] : null;
        return $r;
    }
}
