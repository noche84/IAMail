<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMailCorrection extends Model
{
    protected $fillable = [
        'analysis_id', 'corrected_by',
        'original_legitimacy', 'original_urgency', 'original_category', 'original_action',
        'corrected_legitimacy', 'corrected_urgency', 'corrected_category', 'corrected_action',
        'correction_note', 'is_learned',
    ];

    protected $casts = ['is_learned' => 'boolean'];

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(AiMailAnalysis::class, 'analysis_id');
    }

    public function corrector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }

    public function getChangesAttribute(): array
    {
        $changes = [];
        foreach (['legitimacy', 'urgency', 'category', 'action'] as $field) {
            $orig = $this->{"original_{$field}"};
            $corr = $this->{"corrected_{$field}"};
            if ($orig !== $corr) {
                $changes[$field] = ['from' => $orig, 'to' => $corr];
            }
        }
        return $changes;
    }
}
