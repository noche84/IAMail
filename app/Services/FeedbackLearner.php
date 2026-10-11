<?php

namespace App\Services;

use App\Models\AiMailAnalysis;
use App\Models\AiMailCorrection;
use App\Models\AiLearnedExample;
use Illuminate\Support\Facades\Log;

class FeedbackLearner
{
    public function processCorrection(AiMailCorrection $correction): ?AiLearnedExample
    {
        if (!empty($correction->correction_note)) {
            return $this->createLearnedExample($correction);
        }

        $minCorrections = config('aitriage.processing.learning_min_corrections', 2);
        $similarCount = $this->countSimilarCorrections($correction);

        if ($similarCount >= $minCorrections) {
            return $this->createLearnedExample($correction);
        }

        return null;
    }

    protected function countSimilarCorrections(AiMailCorrection $correction): int
    {
        $query = AiMailCorrection::where('is_learned', false);
        foreach ($correction->changes as $field => $change) {
            $query->where("original_{$field}", $change['from'])
                ->where("corrected_{$field}", $change['to']);
        }
        return $query->count();
    }

    protected function createLearnedExample(AiMailCorrection $correction): AiLearnedExample
    {
        $analysis = $correction->analysis;

        $domain = substr(strrchr($analysis->from_email, '@'), 1);
        $exampleInput = "Expéditeur : domaine @{$domain}\n";
        $exampleInput .= "Sujet : {$analysis->subject}\n";
        $exampleInput .= "Langue : {$analysis->language}\n";
        if ($analysis->body_preview) {
            $exampleInput .= "Extrait : " . mb_substr($analysis->body_preview, 0, 200);
        }

        $expectedOutput = json_encode([
            'legitimacy' => $correction->corrected_legitimacy,
            'urgency' => $correction->corrected_urgency,
            'category' => $correction->corrected_category,
            'suggested_action' => $correction->corrected_action,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $explanationParts = [];
        if ($correction->correction_note) {
            $explanationParts[] = "Note : {$correction->correction_note}";
        }
        foreach ($correction->changes as $field => $change) {
            $label = match ($field) {
                'legitimacy' => 'Légitimité', 'urgency' => 'Urgence',
                'category' => 'Catégorie', 'action' => 'Action', default => $field,
            };
            $explanationParts[] = "{$label} : \"{$change['from']}\" → \"{$change['to']}\"";
        }

        $example = AiLearnedExample::create([
            'account_id' => $analysis->email_account_id,
            'example_input' => $exampleInput,
            'expected_output' => $expectedOutput,
            'explanation' => implode('. ', $explanationParts),
            'trigger_type' => 'correction',
            'is_active' => true,
            'is_global' => $analysis->email_account_id === null,
        ]);

        $correction->update(['is_learned' => true]);
        $this->markSimilarAsLearned($correction);

        Log::info('[Triage:Learner] New learned example', ['id' => $example->id]);
        return $example;
    }

    protected function markSimilarAsLearned(AiMailCorrection $correction): void
    {
        $query = AiMailCorrection::where('is_learned', false)->where('id', '!=', $correction->id);
        foreach ($correction->changes as $field => $change) {
            $query->where("original_{$field}", $change['from'])->where("corrected_{$field}", $change['to']);
        }
        $query->update(['is_learned' => true]);
    }

    public function createManualExample(
        ?int $accountId, string $input, array $expectedClassification,
        string $explanation, bool $isGlobal = false
    ): AiLearnedExample {
        return AiLearnedExample::create([
            'account_id' => $accountId,
            'example_input' => $input,
            'expected_output' => json_encode($expectedClassification, JSON_UNESCAPED_UNICODE),
            'explanation' => $explanation,
            'trigger_type' => 'manual',
            'is_active' => true,
            'is_global' => $isGlobal,
        ]);
    }

    public function recalculateEffectiveness(): int
    {
        $updated = 0;
        foreach (AiLearnedExample::active()->get() as $example) {
            $output = json_decode($example->expected_output, true);
            if (!$output) continue;

            $postErrors = AiMailCorrection::where('created_at', '>', $example->created_at)
                ->when($example->account_id, fn($q) => $q->whereHas('analysis', fn($q2) => $q2->where('email_account_id', $example->account_id)))
                ->where('corrected_category', $output['category'] ?? null)
                ->count();

            $score = max(0, 100 - ($postErrors * 10));
            if ($example->effectiveness_score !== (float) $score) {
                $example->update(['effectiveness_score' => $score]);
                $updated++;
            }
            if ($score <= 0 && $example->times_used > 10) {
                $example->update(['is_active' => false]);
            }
        }
        return $updated;
    }
}
