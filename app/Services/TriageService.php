<?php

namespace App\Services;

use App\Models\EmailAccount;
use App\Models\AiMailAnalysis;
use App\Services\Providers\ProviderManager;
use Illuminate\Support\Facades\Log;

class TriageService
{
    public function __construct(
        protected PromptBuilder $promptBuilder,
        protected ProviderManager $providerManager,
        protected FeedbackLearner $feedbackLearner,
        protected PropagationService $propagation,
    ) {}

    public function analyze(array $emailData, ?EmailAccount $account = null): AiMailAnalysis
    {
        if (!$account) {
            $account = EmailAccount::findForEmail($emailData['to_email'] ?? '');
        }

        if ($account && $account->isDomainBlocked($emailData['from_email'] ?? '')) {
            return $this->saveAnalysis($emailData, [
                'legitimacy' => 'spam', 'urgency' => 'low', 'category' => 'spam',
                'suggested_action' => 'delete', 'language' => 'unknown',
                'summary' => 'Domaine bloqué (règle compte)',
                'reasoning' => 'Domaine dans la liste noire.', 'confidence' => 100,
            ], $account);
        }

        $systemPrompt = $this->promptBuilder->buildSystemPrompt($account);
        $userPrompt = $this->promptBuilder->buildUserPrompt($emailData);

        try {
            $aiResult = $this->providerManager->analyze($systemPrompt, $userPrompt);
        } catch (\Exception $e) {
            Log::error('[Triage] All providers failed', ['error' => $e->getMessage()]);
            return $this->saveAnalysis($emailData, [
                'legitimacy' => 'uncertain', 'urgency' => 'medium', 'category' => 'autre',
                'suggested_action' => 'flag_review', 'language' => 'unknown',
                'summary' => 'Analyse IA impossible — revue manuelle requise',
                'reasoning' => 'Erreur : ' . $e->getMessage(), 'confidence' => 0,
            ], $account, ['provider' => 'none', 'model' => 'none', 'tokens' => 0, 'duration_ms' => 0, 'cost_eur' => 0]);
        }

        return $this->saveAnalysis($emailData, $this->parseAiResponse($aiResult['content']), $account, $aiResult);
    }

    public function analyzeBatch(array $emails, ?EmailAccount $account = null, ?callable $progressCallback = null): array
    {
        $results = [];
        $total = count($emails);
        foreach ($emails as $i => $emailData) {
            try {
                $results[] = ['status' => 'success', 'analysis' => $this->analyze($emailData, $account)];
            } catch (\Exception $e) {
                $results[] = ['status' => 'error', 'error' => $e->getMessage()];
            }
            if ($progressCallback) { $progressCallback($i + 1, $total); }
            if ($i + 1 < $total) { usleep(200_000); }
        }
        return $results;
    }

    /**
     * Corrige une analyse, apprend, ET propage aux emails similaires.
     *
     * @return int Nombre d'emails similaires corrigés en cascade
     */
    public function correct(AiMailAnalysis $analysis, array $corrections, int $userId, ?string $note = null): int
    {
        // 1. Appliquer la correction
        $correction = $analysis->correct($corrections, $userId, $note);

        // 2. Apprentissage (few-shot)
        $this->feedbackLearner->processCorrection($correction);

        // 3. Propager aux emails similaires en attente
        $propagated = $this->propagation->propagateCorrection($correction);

        return $propagated;
    }

    public function validateAnalysis(AiMailAnalysis $analysis, int $userId): void
    {
        $analysis->validate($userId);
    }

    // ─── Private ──────────────────────────────────────────

    protected function parseAiResponse(string $content): array
    {
        $content = trim(preg_replace('/^```(?:json)?\s*/i', '', trim($content)));
        $content = preg_replace('/\s*```$/', '', $content);

        $parsed = json_decode(trim($content), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'legitimacy' => 'uncertain', 'urgency' => 'medium', 'category' => 'autre',
                'suggested_action' => 'flag_review', 'language' => 'unknown',
                'summary' => 'Réponse IA invalide', 'reasoning' => 'JSON invalide.', 'confidence' => 0,
            ];
        }

        $allowedLegitimacy = ['spam', 'phishing', 'auto_reply', 'legitimate', 'uncertain'];
        $allowedUrgency = ['critical', 'high', 'medium', 'low'];
        $allowedActions = array_keys(config('aitriage.suggested_actions', []));

        return [
            'legitimacy' => in_array($parsed['legitimacy'] ?? '', $allowedLegitimacy) ? $parsed['legitimacy'] : 'uncertain',
            'urgency' => in_array($parsed['urgency'] ?? '', $allowedUrgency) ? $parsed['urgency'] : 'medium',
            'category' => $parsed['category'] ?? 'autre',
            'suggested_action' => in_array($parsed['suggested_action'] ?? '', $allowedActions) ? $parsed['suggested_action'] : 'flag_review',
            'language' => $parsed['language'] ?? 'unknown',
            'summary' => mb_substr($parsed['summary'] ?? '', 0, 500),
            'reasoning' => mb_substr($parsed['reasoning'] ?? '', 0, 1000),
            'confidence' => max(0, min(100, (int) ($parsed['confidence'] ?? 50))),
        ];
    }

    protected function saveAnalysis(
        array $emailData, array $classification, ?EmailAccount $account, ?array $aiMeta = null
    ): AiMailAnalysis {
        $bodyText = $emailData['body_text'] ?? strip_tags($emailData['body_html'] ?? '');

        $analysis = AiMailAnalysis::create([
            'email_account_id' => $account?->id,
            'message_id' => $emailData['message_id'] ?? null,
            'from_email' => $emailData['from_email'],
            'from_name' => $emailData['from_name'] ?? null,
            'to_email' => $emailData['to_email'],
            'subject' => $emailData['subject'],
            'body_preview' => mb_substr(trim($bodyText), 0, 500),
            'body_html' => $emailData['body_html'] ?? null,
            'has_attachments' => $emailData['has_attachments'] ?? false,
            'attachment_names' => $emailData['attachment_names'] ?? null,
            'email_date' => $emailData['date'] ?? now(),
            'language' => $classification['language'],
            'legitimacy' => $classification['legitimacy'],
            'urgency' => $classification['urgency'],
            'category' => $classification['category'],
            'suggested_action' => $classification['suggested_action'],
            'summary' => $classification['summary'],
            'reasoning' => $classification['reasoning'],
            'confidence' => $classification['confidence'],
            'ai_provider' => $aiMeta['provider'] ?? 'deterministic',
            'ai_model' => $aiMeta['model'] ?? null,
            'ai_tokens_used' => $aiMeta['tokens'] ?? 0,
            'ai_duration_ms' => $aiMeta['duration_ms'] ?? 0,
            'ai_cost_eur' => $aiMeta['cost_eur'] ?? 0,
            'human_status' => 'pending',
            'is_read' => false,
        ]);

        $account?->recordAnalysis();
        return $analysis;
    }
}
