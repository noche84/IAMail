<?php

namespace App\Console\Commands;

use App\Services\PropagationService;
use Illuminate\Console\Command;

class RecheckPendingCommand extends Command
{
    protected $signature = 'triage:recheck';
    protected $description = 'Re-vérifie les emails en attente et propage les corrections récentes';

    public function handle(PropagationService $propagation): int
    {
        $this->info('🔄 Re-vérification des emails en attente...');

        $result = $propagation->recheckPendingEmails();

        if ($result['reclassified'] > 0) {
            $this->info("⚡ {$result['reclassified']} email(s) reclassifié(s) automatiquement.");
        }
        $this->info("→ {$result['unchanged']} email(s) inchangé(s).");

        return self::SUCCESS;
    }
}
