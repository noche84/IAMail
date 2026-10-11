<?php

namespace App\Services\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiProvider implements AiProviderInterface
{
    protected string $apiKey;
    protected string $model;
    protected string $endpoint;
    protected int $timeout;

    public function __construct()
    {
        $config = config('aitriage.providers.gemini');
        $this->apiKey = $config['api_key'] ?? '';
        $this->model = $config['model'] ?? 'gemini-2.0-flash';
        $this->endpoint = $config['endpoint'] ?? 'https://generativelanguage.googleapis.com/v1beta/models/';
        $this->timeout = $config['timeout'] ?? 30;
    }

    public function analyze(string $systemPrompt, string $userPrompt): array
    {
        if (empty($this->apiKey)) {
            throw new \Exception('Gemini API key not configured');
        }

        $url = rtrim($this->endpoint, '/') . '/' . $this->model . ':generateContent?key=' . $this->apiKey;
        $startTime = microtime(true);

        $response = Http::timeout($this->timeout)->post($url, [
            'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
            'contents' => [['parts' => [['text' => $userPrompt]]]],
            'generationConfig' => [
                'temperature' => 0.1,
                'maxOutputTokens' => 1024,
                'responseMimeType' => 'application/json',
            ],
        ]);

        $durationMs = (int) ((microtime(true) - $startTime) * 1000);

        if (!$response->successful()) {
            Log::error('[Triage:Gemini] API error', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \Exception('Gemini API error: ' . $response->status());
        }

        $data = $response->json();
        return [
            'content' => $data['candidates'][0]['content']['parts'][0]['text'] ?? '',
            'tokens' => $data['usageMetadata']['totalTokenCount'] ?? 0,
            'model' => $this->model,
            'duration_ms' => $durationMs,
        ];
    }

    public function getName(): string { return 'gemini'; }

    public function estimateCost(int $tokens): float
    {
        return ($tokens / 1_000_000) * 0.15;
    }
}
