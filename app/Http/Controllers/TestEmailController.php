<?php

namespace App\Http\Controllers;

use App\Services\TriageService;
use Illuminate\Http\Request;

class TestEmailController extends Controller
{
    /**
     * Formulaire pour tester l'analyse IA sur un email fictif.
     */
    public function form()
    {
        return view('test.form');
    }

    /**
     * Lance l'analyse IA et affiche le résultat.
     */
    public function analyze(Request $request, TriageService $triage)
    {
        $validated = $request->validate([
            'from_email' => 'required|email',
            'from_name' => 'nullable|string|max:255',
            'to_email' => 'required|email',
            'subject' => 'required|string|max:500',
            'body_text' => 'required|string|max:10000',
            'has_attachments' => 'boolean',
            'attachment_names' => 'nullable|string|max:500',
        ]);

        $validated['has_attachments'] = $request->boolean('has_attachments');
        $validated['date'] = now()->toDateTimeString();

        $analysis = $triage->analyze($validated);

        return view('test.result', compact('analysis'));
    }
}
