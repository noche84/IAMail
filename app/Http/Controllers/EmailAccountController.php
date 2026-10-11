<?php

namespace App\Http\Controllers;

use App\Models\EmailAccount;
use App\Models\AiLearnedExample;
use App\Services\FeedbackLearner;
use App\Services\ImapCollector;
use App\Services\PromptBuilder;
use Illuminate\Http\Request;

class EmailAccountController extends Controller
{
    public function index()
    {
        $accounts = EmailAccount::withCount(['analyses', 'learnedExamples'])->orderBy('label')->get();
        return view('accounts.index', compact('accounts'));
    }

    public function create()
    {
        return view('accounts.form', ['account' => null]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateAccount($request);
        EmailAccount::create($validated);
        return redirect()->route('accounts.index')
            ->with('success', 'Compte email créé. Pensez à tester la connexion IMAP.');
    }

    public function edit(EmailAccount $account)
    {
        return view('accounts.form', ['account' => $account]);
    }

    public function update(Request $request, EmailAccount $account)
    {
        $validated = $this->validateAccount($request, $account);
        if (empty($validated['imap_password'])) {
            unset($validated['imap_password']);
        }
        $account->update($validated);
        return redirect()->route('accounts.index')->with('success', 'Compte mis à jour.');
    }

    public function destroy(EmailAccount $account)
    {
        $account->delete();
        return redirect()->route('accounts.index')->with('success', 'Compte supprimé.');
    }

    public function test(EmailAccount $account)
    {
        $result = $account->testConnection();
        if ($result['success']) {
            return back()->with('success', "✅ Connexion réussie ! {$result['message_count']} messages dans la boîte.");
        }
        return back()->with('error', "❌ Connexion échouée : {$result['error']}");
    }

    public function collectNow(EmailAccount $account, ImapCollector $collector)
    {
        $result = $collector->collect($account);
        if ($result['collected'] > 0 || $result['analyzed'] > 0) {
            return back()->with('success', "✅ {$result['collected']} emails collectés, {$result['analyzed']} analysés.");
        }
        if (!empty($result['errors'])) {
            return back()->with('error', "⚠️ " . implode(' | ', array_slice($result['errors'], 0, 3)));
        }
        return back()->with('success', 'Aucun nouvel email non lu.');
    }

    // ─── Exemples appris ────────────────────────────────

    public function learnedExamples(EmailAccount $account)
    {
        $examples = AiLearnedExample::forAccount($account->id)->orderByDesc('created_at')->paginate(20);
        return view('accounts.learned', compact('account', 'examples'));
    }

    public function addExample(Request $request, EmailAccount $account, FeedbackLearner $learner)
    {
        $validated = $request->validate([
            'example_input' => 'required|string|max:1000',
            'legitimacy' => 'required|string',
            'urgency' => 'required|string',
            'category' => 'required|string',
            'suggested_action' => 'required|string',
            'explanation' => 'required|string|max:500',
            'is_global' => 'boolean',
        ]);

        $learner->createManualExample($account->id, $validated['example_input'], [
            'legitimacy' => $validated['legitimacy'],
            'urgency' => $validated['urgency'],
            'category' => $validated['category'],
            'suggested_action' => $validated['suggested_action'],
        ], $validated['explanation'], $request->boolean('is_global', false));

        return redirect()->route('accounts.learned', $account)->with('success', 'Exemple ajouté.');
    }

    public function deleteExample(EmailAccount $account, AiLearnedExample $example)
    {
        $example->delete();
        return redirect()->route('accounts.learned', $account)->with('success', 'Exemple supprimé.');
    }

    public function previewPrompt(EmailAccount $account, PromptBuilder $promptBuilder)
    {
        $fullPrompt = $promptBuilder->buildSystemPrompt($account);
        return view('accounts.preview-prompt', compact('account', 'fullPrompt'));
    }

    // ─── Validation ─────────────────────────────────────

    protected function validateAccount(Request $request, ?EmailAccount $account = null): array
    {
        $uniqueRule = $account ? 'unique:email_accounts,email,' . $account->id : 'unique:email_accounts';

        $validated = $request->validate([
            'label' => 'required|string|max:255',
            'email' => "required|email|{$uniqueRule}",
            'imap_host' => 'required|string|max:255',
            'imap_port' => 'required|integer|min:1|max:65535',
            'imap_encryption' => 'required|in:ssl,tls,none',
            'imap_username' => 'required|string|max:255',
            'imap_password' => ($account ? 'nullable' : 'required') . '|string|max:500',
            'imap_folder' => 'nullable|string|max:100',
            'check_interval_minutes' => 'required|integer|min:1|max:1440',
            'is_active' => 'boolean',
            'auto_analyze' => 'boolean',
            'mark_as_read_on_collect' => 'boolean',
            'ai_prompt' => 'nullable|string|max:5000',
            'custom_categories' => 'nullable|string',
            'trusted_domains' => 'nullable|string',
            'blocked_domains' => 'nullable|string',
        ]);

        $validated['imap_folder'] = $validated['imap_folder'] ?: 'INBOX';
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['auto_analyze'] = $request->boolean('auto_analyze', true);
        $validated['mark_as_read_on_collect'] = $request->boolean('mark_as_read_on_collect', false);
        $validated['email'] = strtolower($validated['email']);

        // Parse comma-separated → array
        $validated['trusted_domains'] = $this->parseCsv($validated['trusted_domains'] ?? '');
        $validated['blocked_domains'] = $this->parseCsv($validated['blocked_domains'] ?? '');

        // Parse JSON categories
        $validated['custom_categories'] = $this->parseJson($validated['custom_categories'] ?? '');

        return $validated;
    }

    protected function parseCsv(?string $value): array
    {
        if (empty($value)) return [];
        return array_filter(array_map('trim', explode(',', $value)));
    }

    protected function parseJson(?string $value): ?array
    {
        if (empty($value)) return null;
        $decoded = json_decode($value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }
}
