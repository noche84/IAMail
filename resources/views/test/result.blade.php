@extends('layouts.app')
@section('title', 'Résultat IA')
@section('content')

<div class="mb-4">
    <a href="{{ route('test.form') }}" class="text-sm text-indigo-600 hover:underline">← Nouveau test</a>
</div>

<h1 class="text-2xl font-bold text-gray-900 mb-6">🧪 Résultat de l'analyse IA</h1>

<div class="grid md:grid-cols-3 gap-6">

    {{-- Résultat principal --}}
    <div class="md:col-span-2 space-y-6">

        {{-- Résumé IA --}}
        <div class="bg-indigo-50 rounded-xl border border-indigo-200 p-6">
            <div class="flex items-center space-x-3 mb-3">
                <span class="text-2xl">🤖</span>
                <h3 class="font-semibold text-indigo-900">Verdict de l'IA</h3>
            </div>
            @if($analysis->summary)
                <p class="text-lg font-medium text-indigo-800 mb-3">« {{ $analysis->summary }} »</p>
            @endif
            @if($analysis->reasoning)
                <p class="text-sm text-indigo-600">{{ $analysis->reasoning }}</p>
            @endif
        </div>

        {{-- Email analysé --}}
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Email analysé</h3>
            <div class="space-y-1 text-sm text-gray-600 mb-4">
                <div><strong>De :</strong> {{ $analysis->from_name }} &lt;{{ $analysis->from_email }}&gt;</div>
                <div><strong>À :</strong> {{ $analysis->to_email }}</div>
                <div><strong>Sujet :</strong> {{ $analysis->subject }}</div>
            </div>
            <div class="bg-gray-50 rounded-lg p-4 text-sm text-gray-700 whitespace-pre-wrap max-h-60 overflow-y-auto">{{ $analysis->body_preview }}</div>
        </div>

        {{-- Métadonnées IA --}}
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Métadonnées techniques</h3>
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-center">
                <div>
                    <div class="text-xs text-gray-400">Provider</div>
                    <div class="font-semibold text-gray-900">{{ $analysis->ai_provider }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-400">Modèle</div>
                    <div class="font-semibold text-gray-900 text-xs">{{ $analysis->ai_model }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-400">Tokens</div>
                    <div class="font-semibold text-gray-900">{{ number_format($analysis->ai_tokens_used) }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-400">Durée</div>
                    <div class="font-semibold text-gray-900">{{ number_format($analysis->ai_duration_ms) }}ms</div>
                </div>
                <div>
                    <div class="text-xs text-gray-400">Coût</div>
                    <div class="font-semibold text-gray-900">{{ number_format($analysis->ai_cost_eur, 5) }} €</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Classification --}}
    <div class="space-y-6">
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Classification</h3>
            <div class="space-y-4">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-500">Confiance</span>
                    <div class="flex items-center space-x-2">
                        <div class="w-20 bg-gray-100 rounded-full h-3">
                            <div class="rounded-full h-3 transition-all {{ $analysis->confidence >= 75 ? 'bg-green-500' : ($analysis->confidence >= 50 ? 'bg-orange-400' : 'bg-red-500') }}"
                                 style="width: {{ $analysis->confidence }}%"></div>
                        </div>
                        <span class="text-lg font-bold {{ $analysis->confidence >= 75 ? 'text-green-600' : ($analysis->confidence >= 50 ? 'text-orange-500' : 'text-red-500') }}">{{ $analysis->confidence }}%</span>
                    </div>
                </div>

                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-500">Légitimité</span>
                    @include('components.legitimacy-badge', ['legitimacy' => $analysis->legitimacy])
                </div>

                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-500">Urgence</span>
                    <span class="flex items-center space-x-1">
                        @include('components.urgency-dot', ['urgency' => $analysis->urgency])
                        <span class="text-sm font-medium">{{ ucfirst($analysis->urgency) }}</span>
                    </span>
                </div>

                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-500">Catégorie</span>
                    <span class="px-2 py-0.5 rounded text-sm bg-gray-100 text-gray-800 font-medium">{{ $analysis->category }}</span>
                </div>

                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-500">Langue</span>
                    <span class="px-2 py-0.5 rounded text-sm bg-blue-50 text-blue-600 font-medium">{{ strtoupper($analysis->language) }}</span>
                </div>

                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-500">Action</span>
                    <span class="text-sm font-medium text-gray-900">{{ config('aitriage.suggested_actions')[$analysis->suggested_action] ?? $analysis->suggested_action }}</span>
                </div>
            </div>
        </div>

        <div class="flex flex-col space-y-3">
            <a href="{{ route('review.show', $analysis) }}" class="text-center bg-white border px-4 py-2.5 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                📬 Voir dans la revue (corriger)
            </a>
            <a href="{{ route('test.form') }}" class="text-center bg-indigo-600 text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
                🧪 Tester un autre email
            </a>
        </div>

        {{-- L'email analysé a été classé automatiquement ? --}}
        <div class="bg-gray-50 rounded-xl border p-4 text-xs text-gray-500">
            @if($analysis->confidence >= config('aitriage.processing.auto_threshold', 75))
                <p class="text-green-600 font-medium">✅ Cet email serait auto-classé (confiance ≥ {{ config('aitriage.processing.auto_threshold') }}%).</p>
                <p class="mt-1">En production, il n'apparaîtrait pas dans la file de revue humaine.</p>
            @else
                <p class="text-orange-600 font-medium">⚠️ Cet email nécessiterait une revue humaine (confiance < {{ config('aitriage.processing.auto_threshold') }}%).</p>
            @endif
        </div>
    </div>
</div>

@endsection
