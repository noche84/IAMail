<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class EmailAccount extends Model
{
    protected $fillable = [
        'label', 'email',
        'imap_host', 'imap_port', 'imap_encryption', 'imap_username', 'imap_password', 'imap_folder',
        'ai_prompt', 'custom_categories', 'trusted_domains', 'blocked_domains',
        'check_interval_minutes', 'is_active', 'auto_analyze', 'mark_as_read_on_collect',
        'last_checked_at', 'last_check_count', 'last_check_status', 'last_check_error',
        'total_collected', 'corrections_count', 'accuracy_rate',
    ];

    protected $casts = [
        'custom_categories' => 'array',
        'trusted_domains' => 'array',
        'blocked_domains' => 'array',
        'is_active' => 'boolean',
        'auto_analyze' => 'boolean',
        'mark_as_read_on_collect' => 'boolean',
        'last_checked_at' => 'datetime',
        'accuracy_rate' => 'float',
    ];

    protected $hidden = ['imap_password'];

    public function setImapPasswordAttribute(string $value): void
    {
        $this->attributes['imap_password'] = Crypt::encryptString($value);
    }

    public function getImapPasswordDecryptedAttribute(): string
    {
        try { return Crypt::decryptString($this->attributes['imap_password']); }
        catch (\Exception $e) { return $this->attributes['imap_password']; }
    }

    public function analyses(): HasMany { return $this->hasMany(AiMailAnalysis::class, 'email_account_id'); }
    public function learnedExamples(): HasMany { return $this->hasMany(AiLearnedExample::class, 'account_id'); }

    public function scopeActive($query) { return $query->where('is_active', true); }
    public function scopeDueForCheck($query)
    {
        return $query->active()->where(function ($q) {
            $q->whereNull('last_checked_at')
                ->orWhereRaw('last_checked_at < NOW() - INTERVAL check_interval_minutes MINUTE');
        });
    }

    public static function findForEmail(string $emailAddress): ?self
    {
        return static::active()->where('email', strtolower($emailAddress))->first();
    }

    public function isDomainTrusted(string $email): bool
    {
        $domain = strtolower(substr(strrchr($email, '@'), 1));
        return in_array($domain, $this->trusted_domains ?? []);
    }

    public function isDomainBlocked(string $email): bool
    {
        $domain = strtolower(substr(strrchr($email, '@'), 1));
        return in_array($domain, $this->blocked_domains ?? []);
    }

    public function getCategories(): array
    {
        return !empty($this->custom_categories) ? $this->custom_categories : config('aitriage.categories', []);
    }

    public function recordAnalysis(bool $wasCorrected = false): void
    {
        $this->increment('total_collected');
        if ($wasCorrected) { $this->increment('corrections_count'); }
        if ($this->total_collected > 0) {
            $this->update(['accuracy_rate' => round((($this->total_collected - $this->corrections_count) / $this->total_collected) * 100, 2)]);
        }
    }

    public function getImapConnectionString(): string
    {
        $enc = match ($this->imap_encryption) { 'ssl' => '/ssl', 'tls' => '/tls', default => '/novalidate-cert' };
        return '{' . $this->imap_host . ':' . $this->imap_port . '/imap' . $enc . '}' . $this->imap_folder;
    }

    public function testConnection(): array
    {
        try {
            $inbox = @imap_open($this->getImapConnectionString(), $this->imap_username, $this->imap_password_decrypted, 0, 1, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']);
            if (!$inbox) return ['success' => false, 'error' => imap_last_error() ?: 'Connexion impossible'];
            $count = imap_check($inbox)->Nmsgs ?? 0;
            imap_close($inbox);
            return ['success' => true, 'message_count' => $count];
        } catch (\Exception $e) { return ['success' => false, 'error' => $e->getMessage()]; }
    }

    public function recordCheck(int $collected, string $status, ?string $error = null): void
    {
        $this->update(['last_checked_at' => now(), 'last_check_count' => $collected, 'last_check_status' => $status, 'last_check_error' => $error]);
    }
}
