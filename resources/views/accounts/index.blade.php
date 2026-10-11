@extends('layouts.app')
@section('title', 'Comptes email')
@section('content')

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">📧 Comptes email</h1>
    <a href="{{ route('accounts.create') }}" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-indigo-700">+ Ajouter</a>
</div>

<div class="space-y-4">
    @forelse($accounts as $account)
    <div class="bg-white rounded-xl shadow-sm border p-5 {{ !$account->is_active ? 'opacity-50' : '' }}">
        <div class="flex items-start justify-between">
            <div class="flex-1">
                <div class="flex items-center space-x-3">
                    <h3 class="font-semibold text-gray-900">{{ $account->label }}</h3>
                    <code class="text-sm bg-gray-100 px-2 py-0.5 rounded">{{ $account->email }}</code>
                    @if(!$account->is_active)
                        <span class="px-2 py-0.5 rounded-full text-xs bg-red-100 text-red-700">Inactif</span>
                    @elseif($account->last_check_status === 'error')
                        <span class="px-2 py-0.5 rounded-full text-xs bg-red-100 text-red-700">Erreur IMAP</span>
                    @elseif($account->last_check_status === 'success')
                        <span class="px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-700">Connecté</span>
                    @else
                        <span class="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-500">Non testé</span>
                    @endif
                </div>

                <div class="mt-1 text-sm text-gray-500">
                    {{ $account->imap_host }}:{{ $account->imap_port }}
                    — toutes les {{ $account->check_interval_minutes }} min
                    @if($account->ai_prompt)
                        — <span class="text-indigo-600">IA configurée</span>
                    @else
                        — <span class="text-orange-500">Prompt IA non défini</span>
                    @endif
                </div>

                <div class="mt-2 flex items-center space-x-6 text-xs text-gray-400">
                    <span>{{ number_format($account->total_collected) }} emails collectés</span>
                    <span>{{ $account->corrections_count }} corrections</span>
                    <span class="font-semibold {{ $account->accuracy_rate >= 90 ? 'text-green-600' : ($account->accuracy_rate >= 70 ? 'text-orange-600' : 'text-red-600') }}">
                        Précision : {{ number_format($account->accuracy_rate, 1) }}%
                    </span>
                    <span>{{ $account->learned_examples_count ?? 0 }} exemples appris</span>
                    @if($account->last_checked_at)
                        <span>Dernière vérif : {{ $account->last_checked_at->diffForHumans() }}</span>
                    @endif
                </div>

                @if($account->last_check_error)
                    <div class="mt-2 text-xs text-red-600 bg-red-50 px-3 py-1.5 rounded">⚠️ {{ Str::limit($account->last_check_error, 120) }}</div>
                @endif
            </div>

            <div class="ml-4 flex items-center space-x-2">
                <form action="{{ route('accounts.test', $account) }}" method="POST">@csrf
                    <button class="px-3 py-1.5 rounded-lg text-xs bg-gray-50 text-gray-600 hover:bg-gray-100">🔌 Tester</button>
                </form>
                <form action="{{ route('accounts.collect', $account) }}" method="POST">@csrf
                    <button class="px-3 py-1.5 rounded-lg text-xs bg-green-50 text-green-700 hover:bg-green-100">▶ Collecter</button>
                </form>
                <a href="{{ route('accounts.learned', $account) }}" class="px-3 py-1.5 rounded-lg text-xs bg-indigo-50 text-indigo-600 hover:bg-indigo-100">🧠 Exemples</a>
                <a href="{{ route('accounts.preview-prompt', $account) }}" class="px-3 py-1.5 rounded-lg text-xs bg-gray-50 text-gray-600 hover:bg-gray-100">👁 Prompt</a>
                <a href="{{ route('accounts.edit', $account) }}" class="px-3 py-1.5 rounded-lg text-xs bg-gray-50 text-gray-600 hover:bg-gray-100">✏️</a>
            </div>
        </div>
    </div>
    @empty
    <div class="bg-white rounded-xl shadow-sm border p-12 text-center">
        <div class="text-4xl mb-4">📭</div>
        <p class="text-gray-500 font-medium">Aucun compte configuré</p>
        <p class="text-gray-400 text-sm mt-2 mb-4">Ajoutez un compte email avec sa connexion IMAP et son prompt IA.</p>
        <a href="{{ route('accounts.create') }}" class="inline-flex bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-indigo-700">Ajouter le premier compte</a>
    </div>
    @endforelse
</div>

@if($accounts->isNotEmpty())
<div class="mt-8 bg-gray-50 rounded-xl border p-6">
    <h3 class="font-semibold text-gray-800 mb-3">⏰ Collecte automatique</h3>
    <p class="text-sm text-gray-600 mb-2"><strong>En local (MAMP) :</strong></p>
    <code class="block bg-white border rounded px-3 py-2 text-xs font-mono mb-3">php artisan triage:collect --all</code>
    <p class="text-sm text-gray-600 mb-2"><strong>En production :</strong></p>
    <code class="block bg-white border rounded px-3 py-2 text-xs font-mono">* * * * * cd /var/www/IAmail && php artisan schedule:run >> /dev/null 2>&1</code>
</div>
@endif
@endsection
