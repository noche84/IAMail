<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Provider Configuration
    |--------------------------------------------------------------------------
    */

    'providers' => [
        'primary' => env('AI_TRIAGE_PRIMARY_PROVIDER', 'gemini'),
        'fallback' => env('AI_TRIAGE_FALLBACK_PROVIDER', 'mistral'),

        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
            'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models/',
            'timeout' => 30,
        ],

        'mistral' => [
            'api_key' => env('MISTRAL_API_KEY'),
            'model' => env('MISTRAL_MODEL', 'mistral-small-latest'),
            'endpoint' => 'https://api.mistral.ai/v1/chat/completions',
            'timeout' => 30,
        ],

        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-20250514'),
            'endpoint' => 'https://api.anthropic.com/v1/messages',
            'timeout' => 30,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Catégories de classification
    |--------------------------------------------------------------------------
    */

    'categories' => [
        'client' => 'Message d\'un client existant',
        'prospect' => 'Demande d\'un prospect / nouveau contact',
        'fournisseur' => 'Communication fournisseur',
        'facture' => 'Facture, rappel de paiement, note de crédit',
        'administratif' => 'Document administratif, légal, réglementaire',
        'subvention' => 'Appel à projets, subvention, financement',
        'interne' => 'Communication interne',
        'newsletter' => 'Newsletter, communication marketing',
        'spam' => 'Spam, publicité non sollicitée',
        'phishing' => 'Tentative de phishing ou arnaque',
        'auto_reply' => 'Réponse automatique (out-of-office, accusé)',
        'autre' => 'Autre / Inclassable',
    ],

    /*
    |--------------------------------------------------------------------------
    | Niveaux d'urgence
    |--------------------------------------------------------------------------
    */

    'urgency_levels' => [
        'critical' => 'Action requise immédiatement (deadline < 48h, incident)',
        'high' => 'À traiter dans la journée',
        'medium' => 'À traiter cette semaine',
        'low' => 'Informatif, pas d\'action urgente',
    ],

    /*
    |--------------------------------------------------------------------------
    | Actions suggérées
    |--------------------------------------------------------------------------
    */

    'suggested_actions' => [
        'respond' => 'Répondre à l\'expéditeur',
        'forward' => 'Transférer à quelqu\'un',
        'archive' => 'Archiver (aucune action nécessaire)',
        'flag_review' => 'Marquer pour revue humaine',
        'delete' => 'Supprimer (spam/phishing)',
        'create_task' => 'Créer une tâche/dossier',
    ],

    /*
    |--------------------------------------------------------------------------
    | Paramètres de traitement
    |--------------------------------------------------------------------------
    */

    'processing' => [
        'auto_threshold' => (int) env('AI_TRIAGE_AUTO_THRESHOLD', 75),
        'max_learned_examples' => (int) env('AI_TRIAGE_MAX_EXAMPLES', 20),
        'learning_min_corrections' => (int) env('AI_TRIAGE_MIN_CORRECTIONS', 2),
        'retention_days' => (int) env('AI_TRIAGE_RETENTION_DAYS', 365),
    ],

    /*
    |--------------------------------------------------------------------------
    | Prompt système de base
    |--------------------------------------------------------------------------
    */

    'system_prompt' => "Tu es un assistant expert en tri et classification d'emails professionnels.
Tu analyses chaque email et retournes un JSON structuré.
Tu dois être particulièrement vigilant à :
- Ne PAS confondre un email légitime en langue étrangère avec du spam
- Détecter les vraies tentatives de phishing (liens suspects, urgence artificielle, fautes)
- Identifier les emails à deadline (subventions, appels d'offres, échéances légales)
- Distinguer les factures légitimes des arnaques à la fausse facture

IMPORTANT : Réponds UNIQUEMENT avec un objet JSON valide, sans markdown, sans backticks, sans texte avant ou après.",

];
