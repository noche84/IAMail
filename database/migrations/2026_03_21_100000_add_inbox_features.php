<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Option "marquer comme lu" sur les comptes
        Schema::table('email_accounts', function (Blueprint $table) {
            $table->boolean('mark_as_read_on_collect')->default(false)
                ->after('auto_analyze')
                ->comment('NE PAS cocher sauf si vous ne consultez JAMAIS cette boîte dans un autre client');
        });

        // Stocker le HTML complet pour l'affichage
        Schema::table('ai_mail_analyses', function (Blueprint $table) {
            $table->longText('body_html')->nullable()->after('body_preview');
            $table->boolean('has_attachments')->default(false)->after('body_html');
            $table->text('attachment_names')->nullable()->after('has_attachments');
            $table->timestamp('email_date')->nullable()->after('attachment_names');
            $table->boolean('is_read')->default(false)->after('human_status')
                ->comment('Lu par l\'utilisateur dans AI Mail Triage');
            $table->boolean('is_starred')->default(false)->after('is_read');
        });
    }

    public function down(): void
    {
        Schema::table('email_accounts', function (Blueprint $table) {
            $table->dropColumn('mark_as_read_on_collect');
        });
        Schema::table('ai_mail_analyses', function (Blueprint $table) {
            $table->dropColumn(['body_html', 'has_attachments', 'attachment_names', 'email_date', 'is_read', 'is_starred']);
        });
    }
};
