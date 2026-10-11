@extends('layouts.app')

@section('title', 'Revue — ' . Str::limit($analysis->subject, 40))

@section('content')
<div class="mb-4">
    <a href="{{ route('review.index') }}" class="text-sm text-indigo-600 hover:underline">← Retour à la liste</a>
</div>

<div class="grid md:grid-cols-3 gap-6">

    {{-- Colonne gauche : Contenu email --}}
    <div class="md:col-span-2 space-y-6">

        {{-- En-tête email --}}
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <div class="flex items-start justify-between mb-4">
                <h2 class="text-lg font-bold text-gray-900">{{ $analysis->subject }}</h2>
                @include('components.legitimacy-badge', ['legitimacy' => $analysis->legitimacy])
            </div>
            <div class="space-y-1 text-sm text-gray-600">
                <div><strong>De :</strong> {{ $analysis->from_name }} &lt;{{ $analysis->from_email }}&gt;</div>
                <div><strong>À :</strong> {{ $analysis->to_email }}</div>
                <div><strong>Profil :</strong> {{ $analysis->account?->label ?? 'Aucun (défaut)' }}</div>
                <div><strong>Date :</strong> {{ $analysis->created_at->format('d/m/Y H:i') }}</div>
                @if($analysis->language)
                    <div><strong>Langue :</strong> {{ strtoupper($analysis->language) }}</div>
                @endif
            </div>
        </div>

        {{-- Corps email --}}
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Corps du message (aperçu)</h3>
            <div class="bg-gray-50 rounded-lg p-4 text-sm text-gray-700 whitespace-pre-wrap max-h-96 overflow-y-auto">{{ $analysis->body_preview ?: 'Aucun contenu' }}</div>
        </div>

        {{-- Raisonnement IA --}}
        <div class="bg-indigo-50 rounded-xl border border-indigo-200 p-6">
            <h3 class="text-sm font-semibold text-indigo-800 mb-2">🤖 Analyse IA</h3>
            @if($analysis->summary)
                <p class="text-sm text-indigo-700 mb-3 font-medium">{{ $analysis->summary }}</p>
            @endif
            @if($analysis->reasoning)
                <p class="text-sm text-indigo-600">{{ $analysis->reasoning }}</p>
            @endif
            <div class="mt-3 flex items-center space-x-4 text-xs text-indigo-400">
                <span>Provider : {{ $analysis->ai_provider }}</span>
                <span>Modèle : {{ $analysis->ai_model }}</span>
                <span>{{ $analysis->ai_tokens_used }} tokens</span>
                <span>{{ $analysis->ai_duration_ms }}ms</span>
                <span>{{ number_format($analysis->ai_cost_eur, 5) }} €</span>
            </div>
        </div>

        {{-- Historique des corrections --}}
        @if($analysis->corrections->isNotEmpty())
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">📝 Historique des corrections</h3>
            @foreach($analysis->corrections as $correction)
                <div class="border-l-4 border-orange-300 pl-4 py-2 mb-3">
                    <div class="text-xs text-gray-400 mb-1">
                        {{ $correction->corrector?->name ?? 'Utilisateur' }} — {{ $correction->created_at->format('d/m/Y H:i') }}
                        @if($correction->is_learned)
                            <span class="ml-2 px-1.5 py-0.5 rounded text-xs bg-green-100 text-green-700">✓ Appris</span>
                        @endif
                    </div>
                    @foreach($correction->changes as $field => $change)
                        <div class="text-sm">
                            <span class="text-gray-500">{{ ucfirst($field) }} :</span>
                            <span class="line-through text-red-500">{{ $change['from'] }}</span>
                            → <span class="text-green-600 font-medium">{{ $change['to'] }}</span>
                        </div>
                    @endforeach
                    @if($correction->correction_note)
                        <div class="mt-1 text-xs italic text-gray-500">{{ $correction->correction_note }}</div>
                    @endif
                </div>
            @endforeach
        </div>
        @endif
    </div>

    {{-- Colonne droite : Classification + Actions --}}
    <div class="space-y-6">

        {{-- Classification actuelle --}}
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Classification</h3>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-sm text-gray-500">Confiance</span>
                    <div class="flex items-center space-x-2">
                        <div class="w-20 bg-gray-100 rounded-full h-2.5">
                            <div class="rounded-full h-2.5 {{ $analysis->confidence >= 75 ? 'bg-green-500' : ($analysis->confidence >= 50 ? 'bg-orange-400' : 'bg-red-500') }}"
                                 style="width: {{ $analysis->confidence }}%"></div>
                        </div>
                        <span class="text-sm font-bold">{{ $analysis->confidence }}%</span>
                    </div>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-500">Légitimité</span>
                    @include('components.legitimacy-badge', ['legitimacy' => $analysis->legitimacy])
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-500">Urgence</span>
                    <span class="text-sm font-medium">
                        @include('components.urgency-dot', ['urgency' => $analysis->urgency])
                        {{ ucfirst($analysis->urgency) }}
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-500">Catégorie</span>
                    <span class="text-sm font-medium text-gray-900">{{ $analysis->category ?? '—' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-500">Action suggérée</span>
                    <span class="text-sm font-medium text-gray-900">{{ $actions[$analysis->suggested_action] ?? $analysis->suggested_action }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-500">Statut</span>
                    <span class="text-sm font-medium">
                        @if($analysis->human_status === 'validated')
                            <span class="text-green-600">✓ Validé</span>
                        @elseif($analysis->human_status === 'corrected')
                            <span class="text-orange-600">✎ Corrigé</span>
                        @elseif($analysis->human_status === 'ignored')
                            <span class="text-gray-400">Ignoré</span>
                        @else
                            <span class="text-yellow-600">⏳ En attente</span>
                        @endif
                    </span>
                </div>
            </div>
        </div>

        {{-- Actions rapides --}}
        @if($analysis->human_status === 'pending')
        <div class="bg-white rounded-xl shadow-sm border p-6 space-y-3">
            <h3 class="text-sm font-semibold text-gray-700 mb-2">Actions rapides</h3>

            {{-- Valider --}}
            <form action="{{ route('review.validate', $analysis) }}" method="POST">
                @csrf
                <button class="w-full bg-green-600 text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-green-700 transition">
                    ✓ Valider (IA correcte)
                </button>
            </form>

            {{-- Ignorer --}}
            <form action="{{ route('review.ignore', $analysis) }}" method="POST">
                @csrf
                <button class="w-full bg-gray-100 text-gray-600 px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-200 transition">
                    Ignorer
                </button>
            </form>
        </div>

        {{-- Formulaire de correction --}}
        <div class="bg-white rounded-xl shadow-sm border p-6" x-data="{ open: false }">
            <button @click="open = !open" class="w-full text-left flex justify-between items-center">
                <h3 class="text-sm font-semibold text-orange-700">✎ Corriger la classification</h3>
                <svg class="w-4 h-4 text-gray-400 transition" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <form x-show="open" x-cloak action="{{ route('review.correct', $analysis) }}" method="POST" class="mt-4 space-y-4">
                @csrf

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Légitimité</label>
                    <select name="legitimacy" class="w-full border rounded-lg px-3 py-2 text-sm">
                        @foreach(['legitimate' => 'Légitime', 'spam' => 'Spam', 'phishing' => 'Phishing', 'auto_reply' => 'Auto-réponse', 'uncertain' => 'Incertain'] as $val => $label)
                            <option value="{{ $val }}" {{ $analysis->legitimacy === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Urgence</label>
                    <select name="urgency" class="w-full border rounded-lg px-3 py-2 text-sm">
                        @foreach($urgencyLevels as $val => $label)
                            <option value="{{ $val }}" {{ $analysis->urgency === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Catégorie</label>
                    <select name="category" class="w-full border rounded-lg px-3 py-2 text-sm">
                        @foreach($categories as $val => $label)
                            <option value="{{ $val }}" {{ $analysis->category === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Action suggérée</label>
                    <select name="suggested_action" class="w-full border rounded-lg px-3 py-2 text-sm">
                        @foreach($actions as $val => $label)
                            <option value="{{ $val }}" {{ $analysis->suggested_action === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        Note (pourquoi cette correction ?)
                        <span class="text-gray-400 font-normal">— aide l'IA à apprendre</span>
                    </label>
                    <textarea name="correction_note" rows="3" class="w-full border rounded-lg px-3 py-2 text-sm"
                              placeholder="Ex : C'est un email de la Région Wallonne en anglais, pas du spam. Les emails @spw.wallonie.be sont toujours légitimes."></textarea>
                </div>

                <button type="submit" class="w-full bg-orange-600 text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-orange-700 transition">
                    Enregistrer la correction
                </button>

                <p class="text-xs text-gray-400 text-center">
                    💡 L'IA apprendra de cette correction pour les futurs emails similaires.
                </p>
            </form>
        </div>
        @endif
    </div>
</div>
@endsection
