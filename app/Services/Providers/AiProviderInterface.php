<?php

namespace App\Services\Providers;

interface AiProviderInterface
{
    public function analyze(string $systemPrompt, string $userPrompt): array;
    public function getName(): string;
    public function estimateCost(int $tokens): float;
}
