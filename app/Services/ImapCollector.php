<?php

namespace App\Services;

use App\Models\EmailAccount;
use App\Models\AiMailAnalysis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ImapCollector
{
    protected TriageService $triage;

    public function __construct(TriageService $triage)
    {
        $this->triage = $triage;
    }

    public function collect(EmailAccount $account, ?callable $progressCallback = null): array
    {
        $collected = 0;
        $analyzed = 0;
        $skipped = 0;
        $errors = [];

        Log::info("[ImapCollector] Début collecte : {$account->email}");

        $connStr = $account->getImapConnectionString();
        $inbox = @imap_open($connStr, $account->imap_username, $account->imap_password_decrypted, 0, 1, [
            'DISABLE_AUTHENTICATOR' => 'GSSAPI',
        ]);

        if (!$inbox) {
            $error = imap_last_error() ?: 'Connexion IMAP impossible';
            Log::error("[ImapCollector] Connexion échouée : {$error}");
            $account->recordCheck(0, 'error', $error);
            return ['collected' => 0, 'analyzed' => 0, 'skipped' => 0, 'errors' => [$error]];
        }

        try {
            // Chercher les emails récents (depuis la dernière vérification ou les 50 derniers)
            $since = $account->last_checked_at
                ? $account->last_checked_at->format('d-M-Y')
                : date('d-M-Y', strtotime('-7 days'));

            $emails = imap_search($inbox, 'SINCE "' . $since . '"');

            if ($emails === false || empty($emails)) {
                Log::info("[ImapCollector] Aucun nouvel email pour {$account->email}");
                imap_close($inbox);
                $account->recordCheck(0, 'success');
                return ['collected' => 0, 'analyzed' => 0, 'skipped' => 0, 'errors' => []];
            }

            rsort($emails); // Plus récents d'abord
            $total = count($emails);
            Log::info("[ImapCollector] {$total} emails trouvés depuis {$since}");

            foreach ($emails as $i => $emailNum) {
                try {
                    $emailData = $this->parseEmail($inbox, $emailNum, $account);
                    if (!$emailData) { $skipped++; continue; }

                    // Doublon ?
                    if ($emailData['message_id'] && AiMailAnalysis::where('message_id', $emailData['message_id'])->exists()) {
                        $skipped++;
                        continue;
                    }

                    $collected++;

                    // Analyser avec l'IA si activé
                    if ($account->auto_analyze) {
                        try {
                            $this->triage->analyze($emailData, $account);
                            $analyzed++;
                        } catch (\Exception $e) {
                            Log::warning("[ImapCollector] Analyse IA échouée", [
                                'subject' => $emailData['subject'],
                                'error' => $e->getMessage(),
                            ]);
                            $errors[] = "Analyse IA : {$e->getMessage()}";
                        }
                    }

                    // ═══════════════════════════════════════════════════
                    // IMPORTANT : On ne marque PAS comme lu par défaut !
                    // L'email reste non lu dans Outlook/Thunderbird/etc.
                    // L'utilisateur décidera dans l'interface AI Mail Triage.
                    // ═══════════════════════════════════════════════════
                    if ($account->mark_as_read_on_collect) {
                        imap_setflag_full($inbox, (string) $emailNum, '\\Seen');
                    }

                    if ($progressCallback) {
                        $progressCallback($i + 1, $total, $collected, $analyzed);
                    }

                } catch (\Exception $e) {
                    $errors[] = "Email #{$emailNum}: {$e->getMessage()}";
                    Log::warning("[ImapCollector] Erreur parsing", ['error' => $e->getMessage()]);
                }
            }

        } finally {
            imap_close($inbox);
        }

        $status = empty($errors) ? 'success' : 'partial';
        $account->recordCheck($collected, $status, empty($errors) ? null : implode("\n", array_slice($errors, 0, 5)));

        return compact('collected', 'analyzed', 'skipped', 'errors');
    }

    public function collectAll(?callable $progressCallback = null): array
    {
        $accounts = EmailAccount::dueForCheck()->get();
        $results = [];
        foreach ($accounts as $account) {
            $results[$account->email] = $this->collect($account, $progressCallback);
        }
        return $results;
    }

    /**
     * Marque un email comme lu sur le serveur IMAP.
     * Appelé UNIQUEMENT quand l'utilisateur le décide.
     */
    public function markAsReadOnServer(EmailAccount $account, string $messageId): bool
    {
        $connStr = $account->getImapConnectionString();
        $inbox = @imap_open($connStr, $account->imap_username, $account->imap_password_decrypted, 0, 1, [
            'DISABLE_AUTHENTICATOR' => 'GSSAPI',
        ]);

        if (!$inbox) return false;

        try {
            $emails = imap_search($inbox, 'HEADER Message-ID "' . $messageId . '"');
            if ($emails && count($emails) > 0) {
                imap_setflag_full($inbox, (string) $emails[0], '\\Seen');
                imap_close($inbox);
                return true;
            }
        } catch (\Exception $e) {
            Log::warning("[ImapCollector] Could not mark as read", ['error' => $e->getMessage()]);
        }

        imap_close($inbox);
        return false;
    }

    // ─── Parsing ────────────────────────────────────────

    protected function parseEmail($inbox, int $emailNum, EmailAccount $account): ?array
    {
        $header = @imap_headerinfo($inbox, $emailNum);
        if (!$header) return null;

        $overview = @imap_fetch_overview($inbox, (string) $emailNum);
        $overview = $overview[0] ?? null;

        $from = $header->from[0] ?? null;
        $fromEmail = $from ? strtolower(trim($from->mailbox . '@' . $from->host)) : '';
        $fromName = isset($from->personal) ? $this->decodeMime($from->personal) : null;

        $to = $header->to[0] ?? null;
        $toEmail = $to ? strtolower(trim($to->mailbox . '@' . $to->host)) : $account->email;

        $subject = isset($overview->subject) ? $this->decodeMime($overview->subject) : '(sans sujet)';

        $messageId = trim($header->message_id ?? '', '<> ');
        if (empty($messageId)) {
            $messageId = Str::uuid()->toString() . '@generated.aimtriage';
        }

        $date = isset($header->date) ? date('Y-m-d H:i:s', strtotime($header->date)) : now()->toDateTimeString();

        // Corps texte ET html
        $bodyText = $this->getBodyByType($inbox, $emailNum, 'PLAIN');
        $bodyHtml = $this->getBodyByType($inbox, $emailNum, 'HTML');

        $attachments = $this->getAttachmentNames($inbox, $emailNum);

        return [
            'from_email' => $fromEmail,
            'from_name' => $fromName,
            'to_email' => $toEmail,
            'subject' => $subject,
            'body_text' => $bodyText,
            'body_html' => $bodyHtml,
            'date' => $date,
            'message_id' => $messageId,
            'has_attachments' => !empty($attachments),
            'attachment_names' => implode(', ', $attachments),
        ];
    }

    protected function getBodyByType($inbox, int $emailNum, string $subtype): string
    {
        $structure = imap_fetchstructure($inbox, $emailNum);

        if (!isset($structure->parts) || empty($structure->parts)) {
            $body = imap_fetchbody($inbox, $emailNum, '1');
            $decoded = $this->decodeBody($body, $structure->encoding ?? 0);
            return ($subtype === 'PLAIN') ? $decoded : '';
        }

        foreach ($structure->parts as $index => $part) {
            if (($part->subtype ?? '') === $subtype) {
                $body = imap_fetchbody($inbox, $emailNum, (string) ($index + 1));
                return $this->decodeBody($body, $part->encoding ?? 0);
            }
        }

        return '';
    }

    protected function decodeBody(string $body, int $encoding): string
    {
        return match ($encoding) {
            3 => base64_decode($body),
            4 => quoted_printable_decode($body),
            default => $body,
        };
    }

    protected function getAttachmentNames($inbox, int $emailNum): array
    {
        $structure = imap_fetchstructure($inbox, $emailNum);
        $names = [];
        if (!isset($structure->parts)) return $names;

        foreach ($structure->parts as $part) {
            if (!isset($part->disposition) || strtoupper($part->disposition) !== 'ATTACHMENT') continue;

            $filename = null;
            foreach (['dparameters', 'parameters'] as $paramSet) {
                if (!empty($part->$paramSet)) {
                    foreach ($part->$paramSet as $param) {
                        if (in_array(strtolower($param->attribute), ['filename', 'name'])) {
                            $filename = $this->decodeMime($param->value);
                            break 2;
                        }
                    }
                }
            }
            if ($filename) $names[] = $filename;
        }
        return $names;
    }

    protected function decodeMime(string $text): string
    {
        $decoded = imap_mime_header_decode($text);
        $result = '';
        foreach ($decoded as $part) {
            $charset = strtolower($part->charset);
            if (in_array($charset, ['default', 'us-ascii', 'utf-8'])) {
                $result .= $part->text;
            } else {
                $result .= @mb_convert_encoding($part->text, 'UTF-8', $charset) ?: $part->text;
            }
        }
        return $result;
    }
}
