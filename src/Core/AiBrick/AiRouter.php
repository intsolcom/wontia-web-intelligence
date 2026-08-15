<?php
namespace App\Core\AiBrick;

class AiRouter
{
    private AiBrickService $service;

    public function __construct(?AiBrickService $service = null)
    {
        $this->service = $service ?? new AiBrickService();
    }

    public function route(array $input): array
    {
        $request = AiRequest::fromArray($input);
        $policy = $this->service->findPolicy($request->systemId, $request->module, $request->function);

        if ($policy && (float)$policy['monthly_budget'] > 0) {
            $spent = $this->service->policyMonthCost($request->systemId, $request->module, $request->function);
            $hard = (float)$policy['monthly_budget'] * ((int)$policy['budget_hard_limit_pct'] / 100);
            if ($spent >= $hard) {
                $response = new AiResponse();
                $response->ok = false;
                $response->error = 'Monthly budget limit reached for this function';
                $response->systemId = $request->systemId;
                $response->module = $request->module;
                $response->function = $request->function;
                $response->strategy = $policy['strategy'];
                $this->service->recordUsage($response, 'budget_blocked', $response->error);
                return $response->toArray();
            }
        }

        $chain = $this->buildChain($request, $policy);

        $lastResponse = null;
        $attempt = 0;
        foreach ($chain as $candidate) {
            if ($attempt >= 3) break;
            $provider = $this->service->findProvider((int)$candidate['provider_id']);
            if (!$provider || $provider['status'] !== 'enabled') continue;

            $provider['resolved_key'] = $this->service->resolveApiKey($provider);
            $adapter = $this->service->adapterFor($provider);

            $attemptRequest = clone $request;
            $attemptRequest->modelIdentifier = $candidate['model_identifier'];
            $attemptRequest->maxTokens = min($request->maxTokens, max(1, (int)($candidate['max_output_tokens'] ?? 1500)));
            $response = $adapter->complete($attemptRequest, $provider, $candidate);
            $response->strategy = $policy['strategy'] ?? 'balanced';
            $response->usedFallback = $attempt > 0;
            $response->attempt = $attempt;

            if ($response->ok) {
                $this->service->recordUsage($response, $attempt > 0 ? 'fallback' : 'success');
                $this->service->updateHealth($response->modelId, $response->providerId, true, $response->latencyMs);
                return $response->toArray();
            }

            $this->service->recordUsage($response, 'error', $response->error);
            $this->service->updateHealth($response->modelId, $response->providerId, false, $response->latencyMs, $response->error);
            $lastResponse = $response;
            $attempt++;
        }

        $response = $lastResponse ?? new AiResponse();
        $response->ok = false;
        $response->error = $response->error ?: 'No enabled model matches the required capabilities';
        $response->systemId = $request->systemId;
        $response->module = $request->module;
        $response->function = $request->function;
        $response->strategy = $policy['strategy'] ?? 'balanced';
        $response->attempt = $attempt;
        return $response->toArray();
    }

    private function buildChain(AiRequest $request, ?array $policy): array
    {
        $requiredCaps = array_values(array_unique(array_merge(
            $policy['required_capabilities'] ?? [],
            $request->requiredCapabilities
        )));
        $excluded = $policy['excluded_providers'] ?? [];
        $preferred = $policy['preferred_providers'] ?? [];

        $pool = array_values(array_filter($this->service->models(), function ($m) use ($requiredCaps, $excluded) {
            if ((int)$m['enabled'] !== 1 || $m['status'] !== 'active') return false;
            if ($m['provider_slug'] === '') return false;
            if (in_array($m['provider_slug'], $excluded, true)) return false;
            if ($requiredCaps && array_diff($requiredCaps, $m['capabilities'] ?? [])) return false;
            $provider = $this->service->findProvider((int)$m['provider_id']);
            return $provider && $provider['status'] === 'enabled';
        }));

        usort($pool, function ($a, $b) use ($preferred) {
            $pa = in_array($a['provider_slug'], $preferred, true) ? 1 : 0;
            $pb = in_array($b['provider_slug'], $preferred, true) ? 1 : 0;
            if ($pa !== $pb) return $pb <=> $pa;
            if ((int)$a['priority'] !== (int)$b['priority']) return (int)$b['priority'] <=> (int)$a['priority'];
            return $this->blended($a) <=> $this->blended($b);
        });

        if (!$policy) {
            usort($pool, fn($a, $b) => $this->blended($a) <=> $this->blended($b));
            return array_slice($pool, 0, 3);
        }

        $strategy = $policy['strategy'] ?? 'balanced';
        if ($strategy === 'cost') {
            usort($pool, fn($a, $b) => $this->blended($a) <=> $this->blended($b));
            return array_slice($pool, 0, 3);
        }
        if ($strategy === 'performance') {
            return array_slice($pool, 0, 3);
        }
        if ($strategy === 'auto') {
            return array_slice($pool, 0, 3);
        }
        if ($strategy === 'manual') {
            $chain = [];
            foreach ([$policy['primary_model_id'], $policy['fallback_model_id'], $policy['fallback2_model_id']] as $modelId) {
                if (!$modelId) continue;
                $model = $this->service->findModel((int)$modelId);
                if (!$model) continue;
                $provider = $this->service->findProvider((int)$model['provider_id']);
                if (!$provider || $provider['status'] !== 'enabled') continue;
                if ((int)$model['enabled'] !== 1 || $model['status'] !== 'active') continue;
                if ($requiredCaps && array_diff($requiredCaps, $model['capabilities'] ?? [])) continue;
                if (in_array($provider['slug'], $excluded, true)) continue;
                $chain[] = $model;
            }
            foreach ($pool as $extra) {
                if (count($chain) >= 3) break;
                $already = false;
                foreach ($chain as $c) if ((int)$c['id'] === (int)$extra['id']) $already = true;
                if (!$already) $chain[] = $extra;
            }
            return $chain;
        }

        return array_slice($pool, 0, 3);
    }

    private function blended(array $model): float
    {
        return (float)($model['input_cost'] ?? 0) + (float)($model['output_cost'] ?? 0);
    }

    public function test(array $input): array
    {
        $model = $this->service->findModel((int)($input['model_id'] ?? 0));
        if (!$model) return ['ok' => false, 'message' => 'Model not found'];
        $provider = $this->service->findProvider((int)$model['provider_id']);
        if (!$provider) return ['ok' => false, 'message' => 'Provider not found'];
        $provider['resolved_key'] = $this->service->resolveApiKey($provider);
        if (!$provider['resolved_key']) return ['ok' => false, 'message' => 'No API key configured for ' . $provider['name'] . '. Set ' . ($provider['api_key_env'] ?: 'the key env var')];

        $request = AiRequest::fromArray([
            'system_prompt' => $input['system_prompt'] ?? '',
            'messages' => $input['messages'] ?? [['role' => 'user', 'content' => (string)($input['prompt'] ?? '')]],
            'temperature' => $input['temperature'] ?? 0.7,
            'max_tokens' => $input['max_tokens'] ?? 500,
            'system_id' => $input['system_id'] ?? 'wontia',
            'module' => $input['module'] ?? 'general',
            'function' => $input['function'] ?? 'test',
        ]);
        $adapter = $this->service->adapterFor($provider);
        $response = $adapter->complete($request, $provider, $model);
        $response->strategy = 'manual';
        $this->service->recordUsage($response, $response->ok ? 'success' : 'error', $response->error);
        $this->service->updateHealth($response->modelId, $response->providerId, $response->ok, $response->latencyMs, $response->error);
        return $response->toArray();
    }

    public function healthCheckModel(int $modelId): array
    {
        $model = $this->service->findModel($modelId);
        if (!$model) return ['ok' => false, 'message' => 'Model not found'];
        $provider = $this->service->findProvider((int)$model['provider_id']);
        if (!$provider) return ['ok' => false, 'message' => 'Provider not found'];
        $provider['resolved_key'] = $this->service->resolveApiKey($provider);
        $adapter = $this->service->adapterFor($provider);
        $result = $adapter->healthCheck($provider, $model);
        $this->service->updateHealth($modelId, (int)$provider['id'], $result['healthy'], (int)$result['latency_ms'], $result['error']);
        return ['ok' => true, 'data' => $result + ['model_id' => $modelId, 'model' => $model['display_name'] ?? $model['name'], 'provider' => $provider['name']]];
    }

    public function command(array $input): array
    {
        $verb = strtolower(trim((string)($input['command'] ?? '')));
        $args = is_array($input['args'] ?? null) ? $input['args'] : [];
        $policy = $this->service->findPolicy(
            (string)($args['system_id'] ?? 'wontia'),
            (string)($args['module'] ?? 'general'),
            (string)($args['function'] ?? 'default')
        );

        if (str_contains($verb, 'show cost') || str_contains($verb, 'costs')) {
            return ['ok' => true, 'message' => 'AI costs', 'data' => [
                'monthly_cost' => $this->service->monthCost(),
                'by_provider' => $this->service->usageGroup('provider', date('Y-m-d H:i:s', strtotime('-30 days'))),
                'by_model' => $this->service->usageGroup('model', date('Y-m-d H:i:s', strtotime('-30 days'))),
            ]];
        }

        if (!$policy) {
            return ['ok' => false, 'message' => 'No active policy found for that system/module/function'];
        }

        if (str_contains($verb, 'cheapest')) {
            $this->service->savePolicy(['strategy' => 'cost'], (int)$policy['id']);
            return ['ok' => true, 'message' => 'Strategy set to Cost Optimized', 'data' => $this->service->findPolicy($policy['system_id'], $policy['module'], $policy['function_key'])];
        }

        if (str_contains($verb, 'best') || str_contains($verb, 'reasoning')) {
            $this->service->savePolicy(['strategy' => 'performance'], (int)$policy['id']);
            return ['ok' => true, 'message' => 'Strategy set to Performance Optimized'];
        }

        if (str_contains($verb, 'set primary') || str_contains($verb, 'primary')) {
            $model = $this->findModelByIdentifier((string)($args['model'] ?? ''));
            if (!$model) return ['ok' => false, 'message' => 'Model not found'];
            $this->service->savePolicy(['primary_model_id' => $model['id']], (int)$policy['id']);
            return ['ok' => true, 'message' => 'Primary model set to ' . $model['display_name']];
        }

        if (str_contains($verb, 'fallback')) {
            $model = $this->findModelByIdentifier((string)($args['model'] ?? ''));
            if (!$model) return ['ok' => false, 'message' => 'Model not found'];
            $this->service->savePolicy(['fallback_model_id' => $model['id']], (int)$policy['id']);
            return ['ok' => true, 'message' => 'Fallback model set to ' . $model['display_name']];
        }

        if (str_contains($verb, 'health') || str_contains($verb, 'status')) {
            return ['ok' => true, 'message' => 'Provider and model health', 'data' => $this->service->healthRows()];
        }

        return ['ok' => false, 'message' => 'Unknown BRICK command', 'data' => ['supported' => ['show costs', 'use cheapest', 'best reasoning model', 'set primary', 'set fallback', 'health']]];
    }

    private function findModelByIdentifier(string $identifier): ?array
    {
        foreach ($this->service->models() as $m) {
            if ($m['model_identifier'] === $identifier || strcasecmp($m['display_name'] ?? '', $identifier) === 0) return $m;
        }
        return null;
    }
}
