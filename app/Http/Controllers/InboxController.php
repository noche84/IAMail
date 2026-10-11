<?php

namespace App\Http\Controllers;

use App\Models\AiMailAnalysis;
use App\Models\EmailAccount;
use App\Services\ImapCollector;
use Illuminate\Http\Request;

class InboxController extends Controller
{
    /**
     * Boîte de réception — vue principale façon email client.
     */
    public function index(Request $request)
    {
        $query = AiMailAnalysis::with('account')
            ->orderByDesc('email_date');

        // Filtres
        if ($request->filled('account_id')) {
            $query->where('email_account_id', $request->account_id);
        }
        if ($request->filled('read')) {
            $query->where('is_read', $request->read === '1');
        }
        if ($request->filled('starred')) {
            $query->where('is_starred', true);
        }
        if ($request->filled('legitimacy')) {
            $query->where('legitimacy', $request->legitimacy);
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('urgency')) {
            $query->where('urgency', $request->urgency);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q
                ->where('subject', 'like', "%{$s}%")
                ->orWhere('from_email', 'like', "%{$s}%")
                ->orWhere('from_name', 'like', "%{$s}%")
                ->orWhere('summary', 'like', "%{$s}%")
            );
        }

        $emails = $query->paginate(30)->withQueryString();
        $accounts = EmailAccount::active()->orderBy('label')->get();
        $categories = config('aitriage.categories', []);

        $unreadCount = AiMailAnalysis::where('is_read', false)->count();

        return view('inbox.index', compact('emails', 'accounts', 'categories', 'unreadCount'));
    }

    /**
     * Lecture d'un email — affiche le contenu + classification IA.
     */
    public function show(AiMailAnalysis $analysis)
    {
        // Marquer comme lu dans AI Mail Triage
        if (!$analysis->is_read) {
            $analysis->update(['is_read' => true]);
        }

        $analysis->load(['account', 'corrections.corrector']);

        return view('inbox.show', [
            'email' => $analysis,
            'categories' => config('aitriage.categories', []),
            'urgencyLevels' => config('aitriage.urgency_levels', []),
            'actions' => config('aitriage.suggested_actions', []),
        ]);
    }

    /**
     * Toggle étoile.
     */
    public function toggleStar(AiMailAnalysis $analysis)
    {
        $analysis->update(['is_starred' => !$analysis->is_starred]);
        return back();
    }

    /**
     * Marquer comme lu/non-lu (dans AI Mail Triage uniquement).
     */
    public function toggleRead(AiMailAnalysis $analysis)
    {
        $analysis->update(['is_read' => !$analysis->is_read]);
        return back();
    }

    /**
     * Marquer comme lu sur le serveur IMAP aussi.
     * Action EXPLICITE de l'utilisateur.
     */
    public function markReadOnServer(AiMailAnalysis $analysis, ImapCollector $collector)
    {
        if (!$analysis->account || !$analysis->message_id) {
            return back()->with('error', 'Impossible : pas de compte IMAP ou de Message-ID.');
        }

        $success = $collector->markAsReadOnServer($analysis->account, $analysis->message_id);

        if ($success) {
            $analysis->update(['is_read' => true]);
            return back()->with('success', '✅ Email marqué comme lu ici ET sur le serveur ('.$analysis->account->email.').');
        }

        return back()->with('error', '❌ Impossible de marquer comme lu sur le serveur IMAP.');
    }

    /**
     * Marquer plusieurs emails comme lus.
     */
    public function bulkMarkRead(Request $request)
    {
        $ids = $request->input('ids', []);
        if (!empty($ids)) {
            AiMailAnalysis::whereIn('id', $ids)->update(['is_read' => true]);
        }
        return back()->with('success', count($ids) . ' emails marqués comme lus.');
    }
}
