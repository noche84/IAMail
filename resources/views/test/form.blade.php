@extends('layouts.app')
@section('title', 'Tester l\'IA')
@section('content')

<div class="max-w-3xl">
    <h1 class="text-2xl font-bold text-gray-900 mb-2">🧪 Tester l'analyse IA</h1>
    <p class="text-sm text-gray-500 mb-6">
        Soumettez un email fictif pour voir comment l'IA le classe.
        Idéal pour vérifier que votre profil et votre prompt fonctionnent correctement.
    </p>

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 text-sm">
            <ul class="list-disc pl-5 space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('test.analyze') }}" class="space-y-6">
        @csrf

        <div class="bg-white rounded-xl shadow-sm border p-6 space-y-4">
            <h3 class="font-semibold text-gray-800">En-tête de l'email</h3>

            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">De (email)</label>
                    <input type="email" name="from_email" value="{{ old('from_email', 'jean.dupont@europa.eu') }}"
                           class="w-full border rounded-lg px-3 py-2 text-sm" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">De (nom)</label>
                    <input type="text" name="from_name" value="{{ old('from_name', 'Jean Dupont') }}"
                           class="w-full border rounded-lg px-3 py-2 text-sm">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">À (email)</label>
                <input type="email" name="to_email" value="{{ old('to_email', '*') }}"
                       class="w-full border rounded-lg px-3 py-2 text-sm" required>
                <p class="text-xs text-gray-400 mt-1">Utilisez une adresse correspondant à un profil configuré, ou <code>*</code> pour le profil par défaut.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Sujet</label>
                <input type="text" name="subject" value="{{ old('subject', 'Horizon Europe — Call for proposals 2026') }}"
                       class="w-full border rounded-lg px-3 py-2 text-sm" required>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border p-6">
            <h3 class="font-semibold text-gray-800 mb-3">Corps du message</h3>
            <textarea name="body_text" rows="10" class="w-full border rounded-lg px-3 py-2 text-sm font-mono" required>{{ old('body_text', 'Dear partner,

We are pleased to inform you that the Horizon Europe programme has opened a new call for proposals.

Deadline: April 15, 2026
Budget: €2.5 million per project
Eligibility: SMEs in EU member states

Please find attached the detailed call document. Do not hesitate to reach out if you need assistance with your application.

Best regards,
Jean Dupont
European Commission — DG Research') }}</textarea>
        </div>

        <div class="bg-white rounded-xl shadow-sm border p-6">
            <div class="flex items-center space-x-4">
                <label class="flex items-center space-x-2">
                    <input type="checkbox" name="has_attachments" value="1" {{ old('has_attachments') ? 'checked' : '' }}
                           class="rounded border-gray-300">
                    <span class="text-sm text-gray-700">Pièces jointes</span>
                </label>
                <input type="text" name="attachment_names" value="{{ old('attachment_names', 'call-2026.pdf') }}"
                       class="border rounded-lg px-3 py-2 text-sm flex-1" placeholder="Noms des fichiers joints">
            </div>
        </div>

        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-sm text-yellow-800">
            ⚡ L'analyse appellera votre provider IA ({{ config('aitriage.providers.primary', 'gemini') }}).
            Assurez-vous que la clé API est configurée dans votre fichier <code>.env</code>.
        </div>

        <button type="submit" class="bg-indigo-600 text-white px-6 py-3 rounded-lg font-medium hover:bg-indigo-700 transition text-sm">
            🚀 Analyser avec l'IA
        </button>
    </form>

    {{-- Exemples rapides --}}
    <div class="mt-8 bg-white rounded-xl shadow-sm border p-6">
        <h3 class="font-semibold text-gray-800 mb-3">💡 Exemples à tester</h3>
        <div class="space-y-3 text-sm text-gray-600">
            <p><strong>Subvention en anglais :</strong> De europa.eu, sujet en anglais avec deadline → Devrait être classé <em>legitimate / subvention / critical</em></p>
            <p><strong>Fausse facture :</strong> De facture-12345@gmail.com, "FACTURE IMPAYÉE — Action immédiate requise" → Devrait être <em>phishing / delete</em></p>
            <p><strong>Newsletter :</strong> De newsletter@marketing.com, "Les 10 tendances 2026" → Devrait être <em>legitimate / newsletter / low</em></p>
            <p><strong>Client urgent :</strong> De un vrai domaine, "Panne serveur — besoin aide immédiate" → Devrait être <em>legitimate / client / critical</em></p>
        </div>
    </div>
</div>

@endsection
