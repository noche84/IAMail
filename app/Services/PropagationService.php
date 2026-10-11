<?php

namespace App\Services;

use App\Models\AiMailAnalysis;
use App\Models\AiMailCorrection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class PropagationService
{
    /**
     * Après une correction, cherche les emails similaires en attente
     * et applique la même correction automatiquement.
     *
     * Critères de similarité (au moins 2 doivent matcher) :
     * 1. Même domaine expéditeur
     * 2. Même classification originale (celle que l'IA avait donné)
     * 3. Sujet similaire (mots-clés communs)
     *
     * @return int Nombre d'emails corrigés par propagation
     */
    public function propagateCorrection(AiMailCorrection $correction): int
    {
        $analysis = $correction->analysis;
        $propagated = 0;

        // Récupérer les emails en attente (pending) qui n'ont pas encore été revus
        $candidates = AiMailAnalysis::where('human_status', 'pending')
            ->where('id', '!=', $analysis->id)
            ->where('created_at', '>=', now()->subDays(30)) // 30 derniers jours
            ->get();

        if ($candidates->isEmpty()) {
            return 0;
        }

        $senderDomain = $this->extractDomain($analysis->from_email);
        $subjectKeywords = $this->extractKeywords($analysis->subject);

        foreach ($candidates as $candidate) {
            $score = $this->calculateSimilarity(
                $candidate,
                $analysis,
                $senderDomain,
                $subjectKeywords
            );

            // Score >= 70 = assez similaire pour propager
            if ($score >= 70) {
                $this->applyPropagation($candidate, $correction, $score);
                $propagated++;
            }
        }

        if ($propagated > 0) {
            Log::info("[Propagation] {$propagated} emails similaires corrigés automatiquement", [
                'source_id' => $analysis->id,
                'source_subject' => $analysis->subject,
                'correction_id' => $correction->id,
            ]);
        }

        return $propagated;
    }

    /**
     * Re-vérifie TOUS les emails en attente par rapport aux exemples appris récents.
     * À lancer en routine (cron) ou manuellement.
     *
     * @return array{reclassified: int, unchanged: int}
     */
    public function recheckPendingEmails(): array
    {
        $reclassified = 0;
        $unchanged = 0;

        $pending = AiMailAnalysis::where('human_status', 'pending')
            ->where('created_at', '>=', now()->subDays(7))
            ->get();

        // Regrouper les corrections récentes par pattern
        $recentCorrections = AiMailCorrection::with('analysis')
            ->where('created_at', '>=', now()->subDays(30))
            ->orderByDesc('created_at')
            ->get();

        if ($recentCorrections->isEmpty() || $pending->isEmpty()) {
            return ['reclassified' => 0, 'unchanged' => $pending->count()];
        }

        foreach ($pending as $email) {
            $bestMatch = $this->findBestMatchingCorrection($email, $recentCorrections);

            if ($bestMatch && $bestMatch['score'] >= 70) {
                $this->applyPropagation($email, $bestMatch['correction'], $bestMatch['score']);
                $reclassified++;
            } else {
                $unchanged++;
            }
        }

        Log::info("[Propagation:Recheck] {$reclassified} reclassifiés, {$unchanged} inchangés");

        return compact('reclassified', 'unchanged');
    }

    // ─── Scoring ────────────────────────────────────────

    /**
     * Calcule un score de similarité entre un email candidat et l'email corrigé.
     * Score 0-100.
     */
    protected function calculateSimilarity(
        AiMailAnalysis $candidate,
        AiMailAnalysis $source,
        string $sourceDomain,
        array $sourceKeywords
    ): int {
        $score = 0;

        // 1. Même domaine expéditeur (très fort)
        $candidateDomain = $this->extractDomain($candidate->from_email);
        if ($candidateDomain === $sourceDomain && !empty($sourceDomain)) {
            $score += 40;
        }

        // 2. Même email expéditeur exact (encore plus fort)
        if (strtolower($candidate->from_email) === strtolower($source->from_email)) {
            $score += 15; // bonus
        }

        // 3. Même classification originale (l'IA a fait la même "erreur")
        if ($candidate->legitimacy === $source->legitimacy
            && $candidate->category === $source->category) {
            $score += 25;
        } elseif ($candidate->legitimacy === $source->legitimacy) {
            $score += 15;
        }

        // 4. Sujet similaire (mots-clés communs)
        $candidateKeywords = $this->extractKeywords($candidate->subject);
        if (!empty($sourceKeywords) && !empty($candidateKeywords)) {
            $common = array_intersect($sourceKeywords, $candidateKeywords);
            $overlap = count($common) / max(count($sourceKeywords), 1);

            if ($overlap >= 0.6) {
                $score += 20;
            } elseif ($overlap >= 0.3) {
                $score += 10;
            }
        }

        // 5. Même compte email (bonus mineur)
        if ($candidate->email_account_id === $source->email_account_id) {
            $score += 5;
        }

        return min(100, $score);
    }

    /**
     * Trouve la correction existante qui correspond le mieux à un email.
     */
    protected function findBestMatchingCorrection(
        AiMailAnalysis $email,
        Collection $corrections
    ): ?array {
        $bestScore = 0;
        $bestCorrection = null;

        foreach ($corrections as $correction) {
            $source = $correction->analysis;
            if (!$source) continue;

            $score = $this->calculateSimilarity(
                $email,
                $source,
                $this->extractDomain($source->from_email),
                $this->extractKeywords($source->subject)
            );

            // Vérifier aussi que la correction est pertinente
            // (l'email candidat a la même classification "erronée")
            if ($email->legitimacy === $correction->original_legitimacy
                || $email->category === $correction->original_category) {
                $score += 10; // bonus : même type d'erreur
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestCorrection = $correction;
            }
        }

        if ($bestCorrection && $bestScore >= 70) {
            return ['correction' => $bestCorrection, 'score' => $bestScore];
        }

        return null;
    }

    // ─── Application ────────────────────────────────────

    /**
     * Applique une correction propagée à un email.
     */
    protected function applyPropagation(
        AiMailAnalysis $email,
        AiMailCorrection $sourceCorrection,
        int $similarityScore
    ): void {
        // Créer la correction avec note de propagation
        $email->corrections()->create([
            'corrected_by' => $sourceCorrection->corrected_by,
            'original_legitimacy' => $email->legitimacy,
            'original_urgency' => $email->urgency,
            'original_category' => $email->category,
            'original_action' => $email->suggested_action,
            'corrected_legitimacy' => $sourceCorrection->corrected_legitimacy,
            'corrected_urgency' => $sourceCorrection->corrected_urgency,
            'corrected_category' => $sourceCorrection->corrected_category,
            'corrected_action' => $sourceCorrection->corrected_action,
            'correction_note' => "⚡ Propagation automatique (similarité {$similarityScore}%) — "
                . "basé sur la correction de l'email \"{$sourceCorrection->analysis->subject}\"",
            'is_learned' => true, // pas besoin de ré-apprendre ce qu'on sait déjà
        ]);

        // Mettre à jour l'email
        $email->update([
            'legitimacy' => $sourceCorrection->corrected_legitimacy,
            'urgency' => $sourceCorrection->corrected_urgency,
            'category' => $sourceCorrection->corrected_category,
            'suggested_action' => $sourceCorrection->corrected_action,
            'human_status' => 'corrected',
            'reviewed_by' => $sourceCorrection->corrected_by,
            'reviewed_at' => now(),
        ]);

        Log::debug("[Propagation] Email #{$email->id} corrigé", [
            'subject' => $email->subject,
            'from' => $email->from_email,
            'similarity' => $similarityScore,
        ]);
    }

    // ─── Helpers ────────────────────────────────────────

    protected function extractDomain(string $email): string
    {
        $at = strrchr($email, '@');
        return $at ? strtolower(substr($at, 1)) : '';
    }

    /**
     * Extrait les mots-clés significatifs d'un sujet.
     * Ignore les mots courts et les mots courants.
     */
    protected function extractKeywords(string $subject): array
    {
        $stopWords = [
            'le', 'la', 'les', 'un', 'une', 'des', 'du', 'de', 'et', 'ou', 'en',
            'à', 'au', 'aux', 'ce', 'ces', 'son', 'sa', 'ses', 'mon', 'ma', 'mes',
            'qui', 'que', 'pour', 'par', 'sur', 'dans', 'avec', 'est', 'sont',
            'the', 'a', 'an', 'and', 'or', 'in', 'on', 'at', 'to', 'for', 'of',
            'is', 'are', 'was', 'were', 'be', 'been', 'has', 'have', 'had',
            're', 'fw', 'fwd', 'tr',
        ];

        $words = preg_split('/[\s\-_:\/\[\]\(\)]+/', strtolower($subject));
        $words = array_filter($words, function ($w) use ($stopWords) {
            return mb_strlen($w) >= 3 && !in_array($w, $stopWords) && !is_numeric($w);
        });

        return array_values(array_unique($words));
    }
}
