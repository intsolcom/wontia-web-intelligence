<?php
namespace App\Core\AiBrick;

class AiResponse
{
    public string $content = '';
    public string $model = '';
    public string $provider = '';
    public ?int $modelId = null;
    public ?int $providerId = null;
    public int $inputTokens = 0;
    public int $outputTokens = 0;
    public int $totalTokens = 0;
    public int $latencyMs = 0;
    public float $cost = 0;
    public ?string $finishReason = null;
    public array $toolCalls = [];
    public ?string $error = null;
    public bool $ok = true;
    public bool $usedFallback = false;
    public int $attempt = 0;
    public string $systemId = '';
    public string $module = '';
    public string $function = '';
    public string $strategy = 'manual';

    public function toArray(): array
    {
        return [
            'ok' => $this->ok,
            'content' => $this->content,
            'model' => $this->model,
            'provider' => $this->provider,
            'model_id' => $this->modelId,
            'provider_id' => $this->providerId,
            'usage' => [
                'input_tokens' => $this->inputTokens,
                'output_tokens' => $this->outputTokens,
                'total_tokens' => $this->totalTokens,
            ],
            'latency_ms' => $this->latencyMs,
            'cost' => round($this->cost, 6),
            'finish_reason' => $this->finishReason,
            'tool_calls' => $this->toolCalls,
            'error' => $this->error,
            'used_fallback' => $this->usedFallback,
            'attempt' => $this->attempt,
            'system_id' => $this->systemId,
            'module' => $this->module,
            'function' => $this->function,
            'strategy' => $this->strategy,
        ];
    }
}
