<?php

namespace App\Console\Commands;

use App\Models\AiMailAnalysis;
use App\Services\FeedbackLearner;
use Illuminate\Console\Command;

class PruneAnalysesCommand extends Command
{
    protected $signature = 'triage:prune {--days= : Jours à conserver}';
    protected $description = 'Recalcule l\'efficacité des exemples et supprime les anciennes analyses';

    public function handle(FeedbackLearner $learner): int
    {
        $days = $this->option('days') ?? config('aitriage.processing.retention_days', 365);

        $this->info('Recalcul de l\'efficacité des exemples appris...');
        $updated = $learner->recalculateEffectiveness();
        $this->info("→ {$updated} exemples mis à jour.");

        $this->info("Suppression des analyses de plus de {$days} jours...");
        $deleted = AiMailAnalysis::where('created_at', '<', now()->subDays($days))
            ->whereIn('human_status', ['validated', 'corrected', 'ignored'])
            ->delete();
        $this->info("→ {$deleted} analyses supprimées.");

        return self::SUCCESS;
    }
}
