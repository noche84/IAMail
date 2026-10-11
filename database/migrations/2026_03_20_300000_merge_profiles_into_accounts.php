<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Ajouter les colonnes profil IA dans email_accounts
        Schema::table('email_accounts', function (Blueprint $table) {
            $table->text('ai_prompt')->nullable()->after('profile_id')
                ->comment('Instructions IA pour cette boîte');
            $table->json('custom_categories')->nullable()->after('ai_prompt');
            $table->json('trusted_domains')->nullable()->after('custom_categories');
            $table->json('blocked_domains')->nullable()->after('trusted_domains');
            $table->unsignedInteger('corrections_count')->default(0)->after('total_collected');
            $table->float('accuracy_rate', 5, 2)->default(0)->after('corrections_count');
        });

        // 2. Migrer les données des profils existants vers les comptes liés
        $profiles = DB::table('ai_mail_profiles')->get();
        foreach ($profiles as $profile) {
            DB::table('email_accounts')
                ->where('profile_id', $profile->id)
                ->update([
                    'ai_prompt' => $profile->ai_prompt,
                    'custom_categories' => $profile->custom_categories,
                    'trusted_domains' => $profile->trusted_domains,
                    'blocked_domains' => $profile->blocked_domains,
                    'corrections_count' => $profile->corrections_count,
                    'accuracy_rate' => $profile->accuracy_rate,
                ]);
        }

        // 3. Mettre à jour ai_mail_analyses : remapper profile_id → email_account_id
        DB::statement('
            UPDATE ai_mail_analyses a
            INNER JOIN email_accounts ea ON ea.profile_id = a.profile_id
            SET a.email_account_id = ea.id
            WHERE a.profile_id IS NOT NULL AND a.email_account_id IS NULL
        ');

        // 4. Mettre à jour ai_learned_examples : profile_id → account_id
        Schema::table('ai_learned_examples', function (Blueprint $table) {
            $table->unsignedBigInteger('account_id')->nullable()->after('profile_id');
        });

        DB::statement('
            UPDATE ai_learned_examples le
            INNER JOIN email_accounts ea ON ea.profile_id = le.profile_id
            SET le.account_id = ea.id
            WHERE le.profile_id IS NOT NULL
        ');

        // 5. Supprimer les anciennes colonnes et la table profils
        Schema::table('ai_mail_analyses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('profile_id');
        });

        Schema::table('ai_learned_examples', function (Blueprint $table) {
            $table->dropConstrainedForeignId('profile_id');
            $table->foreign('account_id')->references('id')->on('email_accounts')->nullOnDelete();
        });

        Schema::table('email_accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('profile_id');
        });

        Schema::dropIfExists('ai_mail_profiles');
    }

    public function down(): void
    {
        // Recréer ai_mail_profiles si besoin de rollback
        Schema::create('ai_mail_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('email_address')->unique();
            $table->string('label');
            $table->text('ai_prompt');
            $table->json('custom_categories')->nullable();
            $table->json('trusted_domains')->nullable();
            $table->json('blocked_domains')->nullable();
            $table->json('forward_rules')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('emails_processed')->default(0);
            $table->unsignedInteger('corrections_count')->default(0);
            $table->float('accuracy_rate', 5, 2)->default(0);
            $table->timestamps();
        });

        Schema::table('email_accounts', function (Blueprint $table) {
            $table->foreignId('profile_id')->nullable()->constrained('ai_mail_profiles')->nullOnDelete();
            $table->dropColumn(['ai_prompt', 'custom_categories', 'trusted_domains', 'blocked_domains', 'corrections_count', 'accuracy_rate']);
        });

        Schema::table('ai_learned_examples', function (Blueprint $table) {
            $table->foreignId('profile_id')->nullable()->constrained('ai_mail_profiles')->nullOnDelete();
            $table->dropConstrainedForeignId('account_id');
        });

        Schema::table('ai_mail_analyses', function (Blueprint $table) {
            $table->foreignId('profile_id')->nullable()->constrained('ai_mail_profiles')->nullOnDelete();
        });
    }
};
