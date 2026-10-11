<?php

namespace App\Services;

use App\Models\EmailAccount;
use App\Models\AiLearnedExample;

class PromptBuilder
{
    /**
     * Construit le prompt système complet.
     */
    public function buildSystemPrompt(?EmailAccount $account = null): string
    {
        $parts = [];

        // 1. Instructions globales
        $parts[] = config('aitriage.system_prompt');

        // 2. Instructions spécifiques au compte
        if ($account && $account->ai_prompt) {
            $parts[] = "\n--- CONTEXTE SPÉCIFIQUE POUR CETTE BOÎTE EMAIL ---";
            $parts[] = "Boîte : {$account->label} ({$account->email})";
            $parts[] = $account->ai_prompt;
        }

        // 3. Catégories
        $categories = $account ? $account->getCategories() : config('aitriage.categories', []);
        if (!empty($categories)) {
            $parts[] = "\n--- CATÉGORIES DISPONIBLES ---";
            $parts[] = "Tu DOIS classer l'email dans l'une de ces catégories :";
            foreach ($categories as $key => $description) {
                $parts[] = "- \"{$key}\" : {$description}";
            }
        }

        // Urgences
        $urgencies = [];
        foreach (config('aitriage.urgency_levels', []) as $key => $desc) {
            $urgencies[] = "- \"{$key}\" : {$desc}";
        }
        if (!empty($urgencies)) {
            $parts[] = "\n--- NIVEAUX D'URGENCE ---";
            $parts = array_merge($parts, $urgencies);
        }

        // Actions
        $actions = [];
        foreach (config('aitriage.suggested_actions', []) as $key => $desc) {
            $actions[] = "- \"{$key}\" : {$desc}";
        }
        if (!empty($actions)) {
            $parts[] = "\n--- ACTIONS POSSIBLES ---";
            $parts = array_merge($parts, $actions);
        }

        // 4. Domaines
        if ($account) {
            if (!empty($account->trusted_domains)) {
                $parts[] = "\n--- DOMAINES DE CONFIANCE ---";
                $parts[] = "Ces domaines sont TOUJOURS légitimes : " . implode(', ', $account->trusted_domains);
            }
            if (!empty($account->blocked_domains)) {
                $parts[] = "\n--- DOMAINES BLOQUÉS ---";
                $parts[] = "Ces domaines sont TOUJOURS du spam : " . implode(', ', $account->blocked_domains);
            }
        }

        // 5. Exemples appris
        $examples = $this->getLearnedExamples($account);
        if ($examples->isNotEmpty()) {
            $parts[] = "\n--- EXEMPLES D'APPRENTISSAGE (corrections humaines précédentes) ---";
            $parts[] = "Voici des classifications corrigées par des humains. Utilise-les :";
            foreach ($examples as $example) {
                $parts[] = $example->toPromptBlock();
                $example->recordUsage();
            }
        }

        // 6. Format de sortie
        $parts[] = $this->getOutputFormat();

        return implode("\n", $parts);
    }

    /**
     * Construit le prompt utilisateur (contenu de l'email).
     */
    public function buildUserPrompt(array $emailData): string
    {
        $parts = ["Analyse cet email :", ""];
        $parts[] = "De : " . ($emailData['from_name'] ?? '') . " <{$emailData['from_email']}>";
        $parts[] = "À : {$emailData['to_email']}";
        $parts[] = "Sujet : {$emailData['subject']}";
        $parts[] = "Date : " . ($emailData['date'] ?? 'inconnue');

        if (!empty($emailData['has_attachments'])) {
            $parts[] = "Pièces jointes : " . ($emailData['attachment_names'] ?? 'oui');
        }

        $parts[] = "\n--- CORPS DU MESSAGE ---";
        $body = $emailData['body_text'] ?? strip_tags($emailData['body_html'] ?? '');
        $body = trim($body);
        if (mb_strlen($body) > 2000) {
            $body = mb_substr($body, 0, 2000) . "\n[... tronqué]";
        }
        $parts[] = $body ?: '[Corps vide]';

        return implode("\n", $parts);
    }

    protected function getLearnedExamples(?EmailAccount $account): \Illuminate\Support\Collection
    {
        return AiLearnedExample::active()
            ->forAccount($account?->id)
            ->mostEffective()
            ->limit(config('aitriage.processing.max_learned_examples', 20))
            ->get();
    }

    protected function getOutputFormat(): string
    {
        return <<<'PROMPT'

--- FORMAT DE RÉPONSE OBLIGATOIRE ---
Retourne UNIQUEMENT un objet JSON valide :
{
    "legitimacy": "spam|phishing|auto_reply|legitimate|uncertain",
    "urgency": "critical|high|medium|low",
    "category": "une des catégories ci-dessus",
    "suggested_action": "respond|forward|archive|flag_review|delete|create_task",
    "language": "code ISO 2 lettres",
    "summary": "Résumé en 1 ligne en français",
    "reasoning": "Explication courte",
    "confidence": 0-100,
    "detected_entities": {
        "company_name": "ou null",
        "person_name": "ou null",
        "deadline": "ou null",
        "amount": "ou null",
        "reference": "ou null"
    }
}
AUCUN texte avant ou après. Pas de backticks. Pas de markdown.
PROMPT;
    }
}
