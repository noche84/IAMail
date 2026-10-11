<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('label')->comment('Nom lisible : "Boîte info", "Comptabilité"');
            $table->string('email')->unique()->comment('Adresse email');

            // IMAP (réception)
            $table->string('imap_host')->comment('Ex: mail.infomaniak.com, imap.gmail.com');
            $table->unsignedSmallInteger('imap_port')->default(993);
            $table->string('imap_encryption')->default('ssl')->comment('ssl, tls, ou none');
            $table->string('imap_username')->comment('Souvent identique à l\'email');
            $table->text('imap_password')->comment('Mot de passe ou app password');
            $table->string('imap_folder')->default('INBOX');

            // Lien vers le profil IA
            $table->foreignId('profile_id')->nullable()->constrained('ai_mail_profiles')->nullOnDelete()
                ->comment('Profil IA à utiliser pour cette boîte');

            // Paramètres
            $table->unsignedSmallInteger('check_interval_minutes')->default(5);
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_analyze')->default(true)->comment('Analyser automatiquement les nouveaux emails');

            // Stats
            $table->timestamp('last_checked_at')->nullable();
            $table->unsignedInteger('last_check_count')->default(0);
            $table->string('last_check_status')->nullable()->comment('success, error');
            $table->text('last_check_error')->nullable();
            $table->unsignedInteger('total_collected')->default(0);

            $table->timestamps();
        });

        // Ajouter une colonne email_account_id aux analyses
        Schema::table('ai_mail_analyses', function (Blueprint $table) {
            $table->foreignId('email_account_id')->nullable()->after('profile_id')
                ->constrained('email_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ai_mail_analyses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('email_account_id');
        });
        Schema::dropIfExists('email_accounts');
    }
};
