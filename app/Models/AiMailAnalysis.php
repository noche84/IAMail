<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AiMailAnalysis extends Model
{
    protected $table = 'ai_mail_analyses';

    protected $fillable = [
        'email_account_id', 'message_id', 'from_email', 'from_name', 'to_email',
        'subject', 'body_preview', 'body_html', 'has_attachments', 'attachment_names',
        'email_date', 'language', 'legitimacy', 'urgency', 'category',
        'suggested_action', 'summary', 'reasoning', 'confidence',
        'ai_provider', 'ai_model', 'ai_tokens_used', 'ai_duration_ms', 'ai_cost_eur',
        'human_status', 'is_read', 'is_starred', 'reviewed_by', 'reviewed_at',
        'source_type', 'source_id',
    ];

    protected $casts = [
        'confidence' => 'integer',
        'ai_tokens_used' => 'integer',
        'ai_duration_ms' => 'integer',
        'ai_cost_eur' => 'float',
        'has_attachments' => 'boolean',
        'is_read' => 'boolean',
        'is_starred' => 'boolean',
        'email_date' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    // ─── Relations ─────────────────────────────────────────

    public function account(): BelongsTo
    {
        return $this->belongsTo(EmailAccount::class, 'email_account_id');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(AiMailCorrection::class, 'analysis_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // ─── Scopes ────────────────────────────────────────────

    public function scopePending($query)     { return $query->where('human_status', 'pending'); }
    public function scopeUnread($query)      { return $query->where('is_read', false); }
    public function scopeStarred($query)     { return $query->where('is_starred', true); }

    public function scopeNeedsReview($query)
    {
        return $query->where('human_status', 'pending')
            ->where(function ($q) {
                $q->where('confidence', '<', config('aitriage.processing.auto_threshold', 75))
                    ->orWhere('legitimacy', 'uncertain')
                    ->orWhere('suggested_action', 'flag_review');
            });
    }

    public function scopeAutoClassified($query)
    {
        return $query->where('human_status', 'pending')
            ->where('confidence', '>=', config('aitriage.processing.auto_threshold', 75))
            ->where('legitimacy', '!=', 'uncertain');
    }

    public function scopeLegitimate($query)  { return $query->where('legitimacy', 'legitimate'); }
    public function scopeSpam($query)        { return $query->whereIn('legitimacy', ['spam', 'phishing']); }
    public function scopeRecent($query, int $days = 7) { return $query->where('created_at', '>=', now()->subDays($days)); }

    // ─── Helpers ───────────────────────────────────────────

    public function isHighConfidence(): bool
    {
        return $this->confidence >= config('aitriage.processing.auto_threshold', 75);
    }

    public function validate(int $userId): void
    {
        $this->update([
            'human_status' => 'validated', 'reviewed_by' => $userId, 'reviewed_at' => now(),
        ]);
        $this->account?->recordAnalysis(wasCorrected: false);
    }

    public function correct(array $corrections, int $userId, ?string $note = null): AiMailCorrection
    {
        $correction = $this->corrections()->create([
            'corrected_by' => $userId,
            'original_legitimacy' => $this->legitimacy, 'original_urgency' => $this->urgency,
            'original_category' => $this->category, 'original_action' => $this->suggested_action,
            'corrected_legitimacy' => $corrections['legitimacy'] ?? $this->legitimacy,
            'corrected_urgency' => $corrections['urgency'] ?? $this->urgency,
            'corrected_category' => $corrections['category'] ?? $this->category,
            'corrected_action' => $corrections['suggested_action'] ?? $this->suggested_action,
            'correction_note' => $note,
        ]);

        $this->update([
            'legitimacy' => $corrections['legitimacy'] ?? $this->legitimacy,
            'urgency' => $corrections['urgency'] ?? $this->urgency,
            'category' => $corrections['category'] ?? $this->category,
            'suggested_action' => $corrections['suggested_action'] ?? $this->suggested_action,
            'human_status' => 'corrected', 'reviewed_by' => $userId, 'reviewed_at' => now(),
        ]);

        $this->account?->recordAnalysis(wasCorrected: true);
        return $correction;
    }

    /**
     * Retourne le body à afficher : HTML si dispo, sinon texte.
     */
    public function getDisplayBodyAttribute(): string
    {
        if ($this->body_html) {
            return $this->body_html;
        }
        return nl2br(e($this->body_preview ?? ''));
    }

    public function getLegitimacyLabelAttribute(): string
    {
        return match ($this->legitimacy) {
            'legitimate' => 'Légitime', 'spam' => 'Spam', 'phishing' => 'Phishing',
            'auto_reply' => 'Auto-réponse', 'uncertain' => 'Incertain', default => 'En attente',
        };
    }
}
