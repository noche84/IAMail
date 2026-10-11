<?php

namespace App\Console\Commands;

use App\Models\EmailAccount;
use App\Services\ImapCollector;
use Illuminate\Console\Command;

class CollectEmailsCommand extends Command
{
    protected $signature = 'triage:collect
        {--account= : ID ou email d\'un compte spécifique}
        {--all : Forcer la collecte de tous les comptes actifs (ignorer l\'intervalle)}';

    protected $description = 'Collecte les nouveaux emails via IMAP et lance l\'analyse IA';

    public function handle(ImapCollector $collector): int
    {
        $this->info('🤖 AI Mail Triage — Collecte des emails');
        $this->newLine();

        // Compte spécifique ?
        if ($accountFilter = $this->option('account')) {
            $account = EmailAccount::where('id', $accountFilter)
                ->orWhere('email', $accountFilter)
                ->first();

            if (!$account) {
                $this->error("Compte \"{$accountFilter}\" introuvable.");
                return self::FAILURE;
            }

            $this->info("→ Collecte de {$account->email} ({$account->label})...");
            $result = $collector->collect($account, function ($current, $total, $collected, $analyzed) {
                $this->output->write("\r  [{$current}/{$total}] collectés: {$collected}, analysés: {$analyzed}");
            });
            $this->newLine();
            $this->displayResult($account->email, $result);
            return self::SUCCESS;
        }

        // Tous les comptes
        if ($this->option('all')) {
            $accounts = EmailAccount::active()->get();
        } else {
            $accounts = EmailAccount::dueForCheck()->get();
        }

        if ($accounts->isEmpty()) {
            $this->info('Aucun compte à vérifier pour le moment.');
            $this->info('(Utilisez --all pour forcer, ou configurez des comptes dans /accounts)');
            return self::SUCCESS;
        }

        $this->info("{$accounts->count()} compte(s) à vérifier :");
        $this->newLine();

        foreach ($accounts as $account) {
            $this->info("→ {$account->email} ({$account->label})...");
            $result = $collector->collect($account, function ($current, $total, $collected, $analyzed) {
                $this->output->write("\r  [{$current}/{$total}] collectés: {$collected}, analysés: {$analyzed}");
            });
            $this->newLine();
            $this->displayResult($account->email, $result);
            $this->newLine();
        }

        return self::SUCCESS;
    }

    protected function displayResult(string $email, array $result): void
    {
        $this->info("  ✓ {$result['collected']} collectés, {$result['analyzed']} analysés, {$result['skipped']} ignorés");

        if (!empty($result['errors'])) {
            foreach (array_slice($result['errors'], 0, 3) as $error) {
                $this->warn("  ⚠ {$error}");
            }
            if (count($result['errors']) > 3) {
                $this->warn("  ... et " . (count($result['errors']) - 3) . " autres erreurs");
            }
        }
    }
}
