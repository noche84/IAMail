<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiLearnedExample extends Model
{
    protected $fillable = [
        'account_id', 'example_input', 'expected_output', 'explanation',
        'trigger_type', 'times_used', 'effectiveness_score', 'is_active', 'is_global',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_global' => 'boolean',
        'effectiveness_score' => 'float',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(EmailAccount::class, 'account_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForAccount($query, ?int $accountId)
    {
        return $query->where(function ($q) use ($accountId) {
            $q->where('is_global', true);
            if ($accountId) {
                $q->orWhere('account_id', $accountId);
            }
        });
    }

    public function scopeMostEffective($query)
    {
        return $query->orderByDesc('effectiveness_score')->orderByDesc('times_used');
    }

    public function recordUsage(): void
    {
        $this->increment('times_used');
    }

    public function toPromptBlock(): string
    {
        $block = "--- Exemple appris ---\n";
        $block .= "Email : {$this->example_input}\n";
        $block .= "Classification correcte : {$this->expected_output}\n";
        if ($this->explanation) {
            $block .= "Raison : {$this->explanation}\n";
        }
        return $block;
    }
}
