<?php
namespace App\Core\AiBrick;

class OpenAiCompatibleAdapter implements AiProviderAdapter
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
        $authMethod = $provider['auth_method'] ?? 'bearer';

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
        if ($request->systemPrompt !== '') {
            $messages[] = ['role' => 'system', 'content' => $request->systemPrompt];
        }
        foreach ($request->messages as $m) {
            if (!is_array($m) || empty($m['content'])) continue;
            $role = in_array($m['role'] ?? '', ['user', 'assistant', 'system'], true) ? $m['role'] : 'user';
            if ($role === 'system') continue;
            $messages[] = ['role' => $role, 'content' => $m['content']];
        }

        $payload = [
            'model' => $request->modelIdentifier ?: ($model['model_identifier'] ?? ''),
            'messages' => $messages,
            'temperature' => $request->temperature,
            'max_tokens' => $request->maxTokens,
            'stream' => false,
        ];
        if ($request->tools) $payload['tools'] = $request->tools;
        if ($request->responseFormat) $payload['response_format'] = $request->responseFormat;

        $headers = ['Content-Type: application/json'];
        if ($authMethod === 'api-key' || $authMethod === 'azure') {
            $headers[] = 'api-key: ' . $key;
        } else {
            $headers[] = 'Authorization: Bearer ' . $key;
        }

        $start = microtime(true);
        $ch = curl_init($base . '/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
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

        $choice = $data['choices'][0] ?? [];
        $message = $choice['message'] ?? [];
        $response->content = (string)($message['content'] ?? '');
        $response->finishReason = $choice['finish_reason'] ?? null;
        $response->toolCalls = is_array($message['tool_calls'] ?? null) ? $message['tool_calls'] : [];
        $usage = $data['usage'] ?? [];
        $response->inputTokens = (int)($usage['prompt_tokens'] ?? 0);
        $response->outputTokens = (int)($usage['completion_tokens'] ?? 0);
        $response->totalTokens = (int)($usage['total_tokens'] ?? ($response->inputTokens + $response->outputTokens));
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
