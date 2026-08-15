<?php
namespace App\Core\AiBrick;

class AnthropicAdapter implements AiProviderAdapter
{
    public function complete(AiRequest $request, array $provider, array $model): AiResponse
    {
        $response = new AiResponse();
        $response->systemId = $request->systemId;
        $response->module = $request->module;
        $response->function = $request->function;
        $response->model = $model['display_name'] ?? $model['name'] ?? $request->modelIdentifier;
        $response->provider = $provider['name'] ?? '';
        $response->modelId = (int)($model['id'] ?? 0);
        $response->providerId = (int)($provider['id'] ?? 0);

        $key = $provider['resolved_key'] ?? '';
        $base = rtrim($provider['api_base_url'] ?? '', '/');

        if (!$key) {
            $response->ok = false;
            $response->error = 'No API key configured for provider ' . ($provider['name'] ?? '');
            return $response;
        }
        if (!$base) {
            $response->ok = false;
            $response->error = 'No API base URL configured';
            return $response;
        }

        $messages = [];
        foreach ($request->messages as $m) {
            if (!is_array($m) || empty($m['content'])) continue;
            $role = ($m['role'] ?? 'user') === 'assistant' ? 'assistant' : 'user';
            $messages[] = ['role' => $role, 'content' => $m['content']];
        }
        if (!$messages) $messages[] = ['role' => 'user', 'content' => 'Hello'];

        $payload = [
            'model' => $request->modelIdentifier ?: ($model['model_identifier'] ?? ''),
            'max_tokens' => $request->maxTokens,
            'messages' => $messages,
            'temperature' => $request->temperature,
            'stream' => false,
        ];
        if ($request->systemPrompt !== '') $payload['system'] = $request->systemPrompt;
        if ($request->tools) $payload['tools'] = $request->tools;

        $start = microtime(true);
        $ch = curl_init($base . '/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $key,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 120,
        ]);
        $raw = curl_exec($ch);
        $curlError = curl_error($ch);
        $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $response->latencyMs = (int)round((microtime(true) - $start) * 1000);

        if ($curlError) {
            $response->ok = false;
            $response->error = $curlError;
            return $response;
        }

        $data = json_decode($raw, true);
        if ($statusCode < 200 || $statusCode >= 300 || !is_array($data)) {
            $response->ok = false;
            $response->error = is_array($data) ? ($data['error']['message'] ?? 'HTTP ' . $statusCode) : 'Invalid response';
            return $response;
        }

        $content = '';
        foreach (($data['content'] ?? []) as $block) {
            if (($block['type'] ?? '') === 'text') $content .= $block['text'] ?? '';
        }
        $response->content = $content;
        $response->finishReason = $data['stop_reason'] ?? null;
        $response->inputTokens = (int)($data['usage']['input_tokens'] ?? 0);
        $response->outputTokens = (int)($data['usage']['output_tokens'] ?? 0);
        $response->totalTokens = $response->inputTokens + $response->outputTokens;
        $response->cost = $this->estimateCost($response->inputTokens, $response->outputTokens, $model);
        return $response;
    }

    public function healthCheck(array $provider, array $model): array
    {
        $probe = new AiRequest();
        $probe->modelIdentifier = $model['model_identifier'] ?? '';
        $probe->maxTokens = 8;
        $probe->messages = [['role' => 'user', 'content' => 'ping']];
        $result = $this->complete($probe, $provider, $model);
        return [
            'healthy' => $result->ok,
            'latency_ms' => $result->latencyMs,
            'error' => $result->error,
        ];
    }

    private function estimateCost(int $inputTokens, int $outputTokens, array $model): float
    {
        $in = (float)($model['input_cost'] ?? 0);
        $out = (float)($model['output_cost'] ?? 0);
        return ($inputTokens / 1000000) * $in + ($outputTokens / 1000000) * $out;
    }
}
