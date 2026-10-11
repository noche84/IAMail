<?php

namespace App\Services\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnthropicProvider implements AiProviderInterface
{
    protected string $apiKey;
    protected string $model;
    protected string $endpoint;
    protected int $timeout;

    public function __construct()
    {
        $config = config('aitriage.providers.anthropic');
        $this->apiKey = $config['api_key'] ?? '';
        $this->model = $config['model'] ?? 'claude-sonnet-4-20250514';
        $this->endpoint = $config['endpoint'] ?? 'https://api.anthropic.com/v1/messages';
        $this->timeout = $config['timeout'] ?? 30;
    }

    public function analyze(string $systemPrompt, string $userPrompt): array
    {
        if (empty($this->apiKey)) {
            throw new \Exception('Anthropic API key not configured');
        }

        $startTime = microtime(true);

        $response = Http::timeout($this->timeout)
            ->withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
            ])
            ->post($this->endpoint, [
                'model' => $this->model,
                'max_tokens' => 1024,
                'system' => $systemPrompt,
                'messages' => [['role' => 'user', 'content' => $userPrompt]],
                'temperature' => 0.1,
            ]);

        $durationMs = (int) ((microtime(true) - $startTime) * 1000);

        if (!$response->successful()) {
            Log::error('[Triage:Anthropic] API error', ['status' => $response->status()]);
            throw new \Exception('Anthropic API error: ' . $response->status());
        }

        $data = $response->json();
        return [
            'content' => $data['content'][0]['text'] ?? '',
            'tokens' => ($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0),
            'model' => $this->model,
            'duration_ms' => $durationMs,
        ];
    }

    public function getName(): string { return 'anthropic'; }

    public function estimateCost(int $tokens): float
    {
        return ($tokens / 1_000_000) * 6.0;
    }
}
