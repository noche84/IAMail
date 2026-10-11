<?php

namespace App\Services\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MistralProvider implements AiProviderInterface
{
    protected string $apiKey;
    protected string $model;
    protected string $endpoint;
    protected int $timeout;

    public function __construct()
    {
        $config = config('aitriage.providers.mistral');
        $this->apiKey = $config['api_key'] ?? '';
        $this->model = $config['model'] ?? 'mistral-small-latest';
        $this->endpoint = $config['endpoint'] ?? 'https://api.mistral.ai/v1/chat/completions';
        $this->timeout = $config['timeout'] ?? 30;
    }

    public function analyze(string $systemPrompt, string $userPrompt): array
    {
        if (empty($this->apiKey)) {
            throw new \Exception('Mistral API key not configured');
        }

        $startTime = microtime(true);

        $response = Http::timeout($this->timeout)
            ->withHeaders(['Authorization' => 'Bearer ' . $this->apiKey])
            ->post($this->endpoint, [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.1,
                'max_tokens' => 1024,
                'response_format' => ['type' => 'json_object'],
            ]);

        $durationMs = (int) ((microtime(true) - $startTime) * 1000);

        if (!$response->successful()) {
            Log::error('[Triage:Mistral] API error', ['status' => $response->status()]);
            throw new \Exception('Mistral API error: ' . $response->status());
        }

        $data = $response->json();
        return [
            'content' => $data['choices'][0]['message']['content'] ?? '',
            'tokens' => $data['usage']['total_tokens'] ?? 0,
            'model' => $this->model,
            'duration_ms' => $durationMs,
        ];
    }

    public function getName(): string { return 'mistral'; }

    public function estimateCost(int $tokens): float
    {
        return ($tokens / 1_000_000) * 0.20;
    }
}
