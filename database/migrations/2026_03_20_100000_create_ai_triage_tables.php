<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Profils IA par adresse email ───────────────────
        Schema::create('ai_mail_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('email_address')->unique()->comment('Adresse email ou * pour défaut');
            $table->string('label')->comment('Nom lisible');
            $table->text('ai_prompt')->comment('Instructions IA pour cette boîte');
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

        // ─── Analyses IA ────────────────────────────────────
        Schema::create('ai_mail_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->nullable()->constrained('ai_mail_profiles')->nullOnDelete();

            // Données email
            $table->string('message_id')->nullable()->index();
            $table->string('from_email')->index();
            $table->string('from_name')->nullable();
            $table->string('to_email')->index();
            $table->string('subject');
            $table->text('body_preview')->nullable();
            $table->string('language', 10)->nullable();

            // Résultats IA
            $table->string('legitimacy')->default('pending');
            $table->string('urgency')->default('medium');
            $table->string('category')->nullable();
            $table->string('suggested_action')->nullable();
            $table->text('summary')->nullable();
            $table->text('reasoning')->nullable();
            $table->unsignedTinyInteger('confidence')->default(0);

            // Métadonnées IA
            $table->string('ai_provider')->nullable();
            $table->string('ai_model')->nullable();
            $table->unsignedInteger('ai_tokens_used')->default(0);
            $table->unsignedInteger('ai_duration_ms')->default(0);
            $table->decimal('ai_cost_eur', 8, 6)->default(0);

            // Statut humain
            $table->string('human_status')->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            // Lien polymorphique vers email source
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->index(['source_type', 'source_id']);

            $table->timestamps();
            $table->index(['legitimacy', 'human_status']);
            $table->index(['category', 'created_at']);
        });

        // ─── Corrections humaines ───────────────────────────
        Schema::create('ai_mail_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_id')->constrained('ai_mail_analyses')->cascadeOnDelete();
            $table->foreignId('corrected_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('original_legitimacy');
            $table->string('original_urgency');
            $table->string('original_category')->nullable();
            $table->string('original_action')->nullable();

            $table->string('corrected_legitimacy');
            $table->string('corrected_urgency');
            $table->string('corrected_category')->nullable();
            $table->string('corrected_action')->nullable();
            $table->text('correction_note')->nullable();

            $table->boolean('is_learned')->default(false);
            $table->timestamps();
        });

        // ─── Exemples appris (few-shot learning) ────────────
        Schema::create('ai_learned_examples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->nullable()->constrained('ai_mail_profiles')->nullOnDelete();
            $table->text('example_input');
            $table->text('expected_output');
            $table->text('explanation')->nullable();
            $table->string('trigger_type')->default('correction');
            $table->unsignedInteger('times_used')->default(0);
            $table->float('effectiveness_score', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_global')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_learned_examples');
        Schema::dropIfExists('ai_mail_corrections');
        Schema::dropIfExists('ai_mail_analyses');
        Schema::dropIfExists('ai_mail_profiles');
    }
};
