<?php

namespace App\Services\Providers;

use Illuminate\Support\Facades\Log;

class ProviderManager
{
    protected array $providers = [];

    public function __construct()
    {
        $providerMap = [
            'gemini' => GeminiProvider::class,
            'mistral' => MistralProvider::class,
            'anthropic' => AnthropicProvider::class,
        ];

        $primary = config('aitriage.providers.primary', 'gemini');
        $fallback = config('aitriage.providers.fallback', 'mistral');
        $order = array_unique([$primary, $fallback, ...array_keys($providerMap)]);

        foreach ($order as $name) {
            if (isset($providerMap[$name])) {
                $this->providers[$name] = new $providerMap[$name]();
            }
        }
    }

    /**
     * Analyse en essayant chaque provider dans l'ordre de fallback.
     */
    public function analyze(string $systemPrompt, string $userPrompt): array
    {
        $lastException = null;

        foreach ($this->providers as $name => $provider) {
            try {
                Log::debug("[Triage] Trying provider: {$name}");
                $result = $provider->analyze($systemPrompt, $userPrompt);
                $result['provider'] = $name;
                $result['cost_eur'] = $provider->estimateCost($result['tokens']);

                Log::info("[Triage] Success with {$name}", [
                    'tokens' => $result['tokens'],
                    'duration_ms' => $result['duration_ms'],
                ]);
                return $result;
            } catch (\Exception $e) {
                $lastException = $e;
                Log::warning("[Triage] Provider {$name} failed: {$e->getMessage()}");
                continue;
            }
        }

        throw new \Exception(
            'All AI providers failed. Last error: ' . ($lastException?->getMessage() ?? 'Unknown'),
            0, $lastException
        );
    }

    public function getAvailableProviders(): array
    {
        return array_keys($this->providers);
    }
}
