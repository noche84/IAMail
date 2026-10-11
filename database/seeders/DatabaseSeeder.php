<?php

namespace Database\Seeders;

use App\Models\AiMailProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Admin user ─────────────────────────────────
        User::firstOrCreate(
            ['email' => 'admin@aimtriage.be'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
            ]
        );

        // ─── Profil par défaut (attrape-tout) ───────────
        AiMailProfile::firstOrCreate(
            ['email_address' => '*'],
            [
                'label' => 'Défaut (toutes boîtes)',
                'ai_prompt' => "Tu gères les emails d'une entreprise belge.
Les emails en anglais, néerlandais ou allemand sont souvent légitimes (partenaires, clients, institutions EU).
Sois particulièrement vigilant aux :
- Fausses factures (vérifier le domaine de l'expéditeur)
- Arnaques au président / CEO fraud
- Emails urgents de sources inconnues demandant un virement
Les emails institutionnels (.gouv.fr, .europa.eu, .belgium.be, .wallonie.be, .spw.be) sont toujours légitimes.",
                'trusted_domains' => ['europa.eu', 'ec.europa.eu', 'spw.wallonie.be', 'belgium.be', 'forem.be'],
                'blocked_domains' => [],
                'is_active' => true,
            ]
        );

        // ─── Profil exemple : Comptabilité ──────────────
        AiMailProfile::firstOrCreate(
            ['email_address' => 'compta@example.be'],
            [
                'label' => 'Comptabilité (exemple)',
                'ai_prompt' => "Boîte comptabilité. Les emails importants sont :
- Factures fournisseurs (flag si >30 jours)
- Rappels de paiement (urgence haute)
- Extraits bancaires et communications banque
- Notes de crédit
- Déclarations TVA et fiscales
Les newsletters sont basse priorité. Les fausses factures sont fréquentes, vérifie bien le domaine.",
                'trusted_domains' => ['belfius.be', 'bnpparibasfortis.be', 'kbc.be', 'ing.be'],
                'blocked_domains' => [],
                'is_active' => true,
            ]
        );

        $this->command->info('✓ Admin créé : admin@aimtriage.be / password');
        $this->command->info('✓ 2 profils email créés (défaut + comptabilité exemple)');
    }
}
