<?php

namespace App\Http\Controllers;

use App\Models\AiMailAnalysis;
use App\Services\TriageService;
use App\Services\PropagationService;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $query = AiMailAnalysis::with('account')
            ->orderByRaw("FIELD(urgency, 'critical', 'high', 'medium', 'low')")
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('human_status', $request->status);
        } else {
            $query->pending();
        }

        if ($request->filled('legitimacy')) $query->where('legitimacy', $request->legitimacy);
        if ($request->filled('category')) $query->where('category', $request->category);
        if ($request->filled('urgency')) $query->where('urgency', $request->urgency);
        if ($request->filled('account_id')) $query->where('email_account_id', $request->account_id);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q
                ->where('subject', 'like', "%{$s}%")
                ->orWhere('from_email', 'like', "%{$s}%")
                ->orWhere('summary', 'like', "%{$s}%")
            );
        }

        $analyses = $query->paginate(25)->withQueryString();
        $categories = config('aitriage.categories', []);

        return view('review.index', compact('analyses', 'categories'));
    }

    public function show(AiMailAnalysis $analysis)
    {
        $analysis->load(['account', 'corrections.corrector']);
        return view('review.show', [
            'analysis' => $analysis,
            'categories' => config('aitriage.categories', []),
            'urgencyLevels' => config('aitriage.urgency_levels', []),
            'actions' => config('aitriage.suggested_actions', []),
        ]);
    }

    public function validateAnalysis(AiMailAnalysis $analysis, TriageService $triage)
    {
        $triage->validateAnalysis($analysis, auth()->id());
        return redirect()->route('review.index')->with('success', 'Classification validée.');
    }

    public function correct(Request $request, AiMailAnalysis $analysis, TriageService $triage)
    {
        $validated = $request->validate([
            'legitimacy' => 'required|in:spam,phishing,auto_reply,legitimate,uncertain',
            'urgency' => 'required|in:critical,high,medium,low',
            'category' => 'required|string|max:50',
            'suggested_action' => 'required|in:' . implode(',', array_keys(config('aitriage.suggested_actions', []))),
            'correction_note' => 'nullable|string|max:500',
        ]);

        $propagated = $triage->correct(
            $analysis, $validated, auth()->id(), $validated['correction_note'] ?? null
        );

        $message = 'Classification corrigée.';
        if ($propagated > 0) {
            $message .= " ⚡ {$propagated} email(s) similaire(s) corrigé(s) automatiquement !";
        }
        $message .= ' L\'IA apprendra de cette correction.';

        return redirect()->route('review.index')->with('success', $message);
    }

    public function ignore(AiMailAnalysis $analysis)
    {
        $analysis->update([
            'human_status' => 'ignored', 'reviewed_by' => auth()->id(), 'reviewed_at' => now(),
        ]);
        return redirect()->route('review.index')->with('success', 'Email ignoré.');
    }

    public function bulkValidate(Request $request)
    {
        $ids = $request->input('ids', []);
        $query = empty($ids)
            ? AiMailAnalysis::autoClassified()->pending()
            : AiMailAnalysis::whereIn('id', $ids)->pending();

        $count = $query->update([
            'human_status' => 'validated', 'reviewed_by' => auth()->id(), 'reviewed_at' => now(),
        ]);

        return redirect()->route('review.index')->with('success', "{$count} analyses validées.");
    }

    /**
     * Relancer la vérification de propagation sur les emails en attente.
     */
    public function recheckPending(PropagationService $propagation)
    {
        $result = $propagation->recheckPendingEmails();

        if ($result['reclassified'] > 0) {
            return redirect()->route('review.index')
                ->with('success', "⚡ {$result['reclassified']} email(s) reclassifié(s) automatiquement grâce aux corrections précédentes.");
        }

        return redirect()->route('review.index')
            ->with('success', "Aucun email à reclassifier ({$result['unchanged']} en attente vérifiés).");
    }
}
