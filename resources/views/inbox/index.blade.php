@extends('layouts.app')
@section('title', 'Boîte de réception')
@section('content')

{{-- Header + Filtres --}}
<div class="flex items-center justify-between mb-4">
    <h1 class="text-xl font-bold text-gray-900">
        📬 Boîte de réception
        @if($unreadCount > 0)
            <span class="text-sm font-normal text-gray-500">({{ $unreadCount }} non lus)</span>
        @endif
    </h1>
</div>

<div class="bg-white rounded-xl shadow-sm border mb-4 p-3" x-data="{ open: false }">
    <div class="flex items-center justify-between">
        <div class="flex items-center space-x-2">
            <button @click="open = !open" class="text-sm text-gray-600 hover:text-indigo-600">
                🔍 Filtres <span x-text="open ? '▲' : '▼'" class="text-xs"></span>
            </button>
            {{-- Filtres rapides --}}
            <a href="{{ route('inbox.index') }}" class="px-2 py-1 rounded text-xs {{ !request()->hasAny(['read','starred','legitimacy','category']) ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-600' }}">Tous</a>
            <a href="{{ route('inbox.index', ['read' => '0']) }}" class="px-2 py-1 rounded text-xs {{ request('read') === '0' ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-600' }}">Non lus</a>
            <a href="{{ route('inbox.index', ['starred' => '1']) }}" class="px-2 py-1 rounded text-xs {{ request('starred') ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-600' }}">⭐ Favoris</a>
            <a href="{{ route('inbox.index', ['legitimacy' => 'spam']) }}" class="px-2 py-1 rounded text-xs {{ request('legitimacy') === 'spam' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600' }}">🚫 Spam</a>
        </div>
        <form action="{{ route('inbox.index') }}" method="GET" class="flex items-center space-x-2">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Rechercher..."
                   class="border rounded-lg px-3 py-1.5 text-sm w-48">
            <button type="submit" class="bg-indigo-600 text-white px-3 py-1.5 rounded-lg text-sm">🔍</button>
        </form>
    </div>

    <form x-show="open" x-cloak method="GET" action="{{ route('inbox.index') }}" class="mt-3 grid md:grid-cols-4 gap-3">
        <select name="account_id" class="border rounded-lg px-3 py-2 text-sm">
            <option value="">Tous les comptes</option>
            @foreach($accounts as $acc)
                <option value="{{ $acc->id }}" {{ request('account_id') == $acc->id ? 'selected' : '' }}>{{ $acc->label }}</option>
            @endforeach
        </select>
        <select name="legitimacy" class="border rounded-lg px-3 py-2 text-sm">
            <option value="">Toute légitimité</option>
            @foreach(['legitimate' => 'Légitime', 'spam' => 'Spam', 'phishing' => 'Phishing', 'uncertain' => 'Incertain', 'auto_reply' => 'Auto-réponse'] as $v => $l)
                <option value="{{ $v }}" {{ request('legitimacy') === $v ? 'selected' : '' }}>{{ $l }}</option>
            @endforeach
        </select>
        <select name="category" class="border rounded-lg px-3 py-2 text-sm">
            <option value="">Toute catégorie</option>
            @foreach($categories as $k => $l)
                <option value="{{ $k }}" {{ request('category') === $k ? 'selected' : '' }}>{{ $k }}</option>
            @endforeach
        </select>
        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm">Filtrer</button>
    </form>
</div>

{{-- Liste des emails --}}
<div class="bg-white rounded-xl shadow-sm border overflow-hidden">
    @forelse($emails as $email)
    <div class="flex items-center border-b last:border-0 {{ !$email->is_read ? 'bg-blue-50/50' : '' }} hover:bg-gray-50 transition">

        {{-- Étoile --}}
        <form action="{{ route('inbox.toggle-star', $email) }}" method="POST" class="pl-3">
            @csrf
            <button type="submit" class="text-lg leading-none {{ $email->is_starred ? 'text-yellow-400' : 'text-gray-300 hover:text-yellow-400' }}">
                {{ $email->is_starred ? '★' : '☆' }}
            </button>
        </form>

        {{-- Contenu principal --}}
        <a href="{{ route('inbox.show', $email) }}" class="flex-1 p-3 block min-w-0">
            <div class="flex items-center space-x-3">
                {{-- Urgence --}}
                @include('components.urgency-dot', ['urgency' => $email->urgency])

                {{-- Expéditeur --}}
                <span class="text-sm {{ !$email->is_read ? 'font-bold text-gray-900' : 'text-gray-600' }} w-48 truncate">
                    {{ $email->from_name ?? $email->from_email }}
                </span>

                {{-- Sujet + résumé IA --}}
                <div class="flex-1 min-w-0 flex items-center space-x-2">
                    <span class="text-sm {{ !$email->is_read ? 'font-semibold text-gray-900' : 'text-gray-700' }} truncate">
                        {{ $email->subject }}
                    </span>
                    @if($email->summary)
                        <span class="text-xs text-gray-400 truncate hidden lg:inline">— {{ $email->summary }}</span>
                    @endif
                </div>

                {{-- Badges --}}
                <div class="flex items-center space-x-2 flex-shrink-0">
                    @if($email->has_attachments)
                        <span class="text-gray-400" title="Pièces jointes">📎</span>
                    @endif
                    @if($email->language && $email->language !== 'fr')
                        <span class="px-1.5 py-0.5 rounded text-xs bg-blue-50 text-blue-600">{{ strtoupper($email->language) }}</span>
                    @endif
                    @include('components.legitimacy-badge', ['legitimacy' => $email->legitimacy])
                    <span class="text-xs {{ $email->confidence >= 75 ? 'text-green-600' : ($email->confidence >= 50 ? 'text-orange-500' : 'text-red-500') }}">{{ $email->confidence }}%</span>
                </div>

                {{-- Date --}}
                <span class="text-xs text-gray-400 flex-shrink-0 w-20 text-right">
                    {{ $email->email_date ? $email->email_date->format('d/m H:i') : $email->created_at->format('d/m H:i') }}
                </span>
            </div>
        </a>
    </div>
    @empty
    <div class="p-12 text-center">
        <div class="text-4xl mb-4">📭</div>
        <p class="text-gray-500 font-medium">Boîte vide</p>
        <p class="text-gray-400 text-sm mt-1">Configurez un <a href="{{ route('accounts.index') }}" class="text-indigo-600 hover:underline">compte email</a> et lancez une collecte.</p>
    </div>
    @endforelse
</div>

@if($emails->hasPages())
    <div class="mt-6">{{ $emails->links() }}</div>
@endif
@endsection
