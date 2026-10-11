@extends('layouts.app')
@section('title', 'Classification IA')
@section('content')

<div class="flex items-center justify-between mb-6">
    <h1 class="text-xl font-bold text-gray-900">🤖 Classification IA</h1>
    <div class="flex items-center space-x-3">
        {{-- Bouton recheck : propage les corrections aux emails similaires --}}
        <form action="{{ route('review.recheck') }}" method="POST">
            @csrf
            <button type="submit" class="bg-indigo-100 text-indigo-700 px-4 py-2 rounded-lg text-sm hover:bg-indigo-200 transition"
                    title="Re-vérifie les emails en attente et propage les corrections récentes">
                ⚡ Propager les corrections
            </button>
        </form>

        <form action="{{ route('review.bulk-validate') }}" method="POST"
              onsubmit="return confirm('Valider toutes les classifications haute confiance ?')">
            @csrf
            <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700 transition">
                ✓ Valider tout (haute confiance)
            </button>
        </form>
    </div>
</div>

{{-- Info propagation --}}
<div class="bg-indigo-50 border border-indigo-200 rounded-lg p-3 mb-4 text-sm text-indigo-700">
    💡 Quand vous corrigez un email, les emails similaires en attente sont <strong>automatiquement corrigés</strong>.
    Utilisez le bouton « ⚡ Propager » pour relancer la vérification manuellement.
</div>

{{-- Filtres --}}
<div class="bg-white rounded-xl shadow-sm border p-3 mb-4" x-data="{ open: false }">
    <div class="flex items-center space-x-2">
        <button @click="open = !open" class="text-sm text-gray-600 hover:text-indigo-600">🔍 Filtres</button>
        <a href="{{ route('review.index') }}" class="px-2 py-1 rounded text-xs {{ !request()->hasAny(['status','legitimacy','category']) ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-600' }}">En attente</a>
        <a href="{{ route('review.index', ['status' => 'corrected']) }}" class="px-2 py-1 rounded text-xs {{ request('status') === 'corrected' ? 'bg-orange-100 text-orange-700' : 'bg-gray-100 text-gray-600' }}">Corrigés</a>
        <a href="{{ route('review.index', ['status' => 'validated']) }}" class="px-2 py-1 rounded text-xs {{ request('status') === 'validated' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">Validés</a>
    </div>
    <form x-show="open" x-cloak method="GET" class="mt-3 grid md:grid-cols-5 gap-3">
        <select name="status" class="border rounded-lg px-3 py-2 text-sm">
            <option value="">Tous statuts</option>
            <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>En attente</option>
            <option value="validated" {{ request('status') === 'validated' ? 'selected' : '' }}>Validés</option>
            <option value="corrected" {{ request('status') === 'corrected' ? 'selected' : '' }}>Corrigés</option>
        </select>
        <select name="legitimacy" class="border rounded-lg px-3 py-2 text-sm">
            <option value="">Toute légitimité</option>
            @foreach(['legitimate'=>'Légitime','spam'=>'Spam','phishing'=>'Phishing','uncertain'=>'Incertain'] as $v=>$l)
                <option value="{{ $v }}" {{ request('legitimacy')===$v?'selected':'' }}>{{ $l }}</option>
            @endforeach
        </select>
        <select name="urgency" class="border rounded-lg px-3 py-2 text-sm">
            <option value="">Toute urgence</option>
            @foreach(['critical'=>'Critique','high'=>'Haute','medium'=>'Moyenne','low'=>'Basse'] as $v=>$l)
                <option value="{{ $v }}" {{ request('urgency')===$v?'selected':'' }}>{{ $l }}</option>
            @endforeach
        </select>
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Recherche..." class="border rounded-lg px-3 py-2 text-sm">
        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm">Filtrer</button>
    </form>
</div>

{{-- Liste --}}
<div class="bg-white rounded-xl shadow-sm border overflow-hidden">
    @forelse($analyses as $analysis)
    <a href="{{ route('review.show', $analysis) }}"
       class="block border-b last:border-0 p-4 hover:bg-gray-50 transition {{ $analysis->human_status !== 'pending' ? 'opacity-60' : '' }}">
        <div class="flex items-start justify-between">
            <div class="flex-1 min-w-0">
                <div class="flex items-center space-x-2">
                    @include('components.urgency-dot', ['urgency' => $analysis->urgency])
                    <span class="text-sm font-medium text-gray-900 truncate">{{ $analysis->subject }}</span>
                    @if($analysis->language && $analysis->language !== 'fr')
                        <span class="px-1.5 py-0.5 rounded text-xs bg-blue-50 text-blue-600">{{ strtoupper($analysis->language) }}</span>
                    @endif
                </div>
                <div class="mt-1 flex items-center space-x-3 text-xs text-gray-500">
                    <span>{{ $analysis->from_name ?? $analysis->from_email }}</span>
                    <span>→</span>
                    <span>{{ $analysis->account?->label ?? $analysis->to_email }}</span>
                    <span class="text-gray-300">|</span>
                    <span>{{ $analysis->created_at->diffForHumans() }}</span>
                </div>
                @if($analysis->summary)
                    <div class="mt-1 text-xs text-gray-400 italic truncate">{{ $analysis->summary }}</div>
                @endif
            </div>
            <div class="ml-4 flex items-center space-x-3">
                @if($analysis->category)
                    <span class="px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-600">{{ $analysis->category }}</span>
                @endif
                @include('components.legitimacy-badge', ['legitimacy' => $analysis->legitimacy])
                <span class="text-xs font-semibold {{ $analysis->confidence >= 75 ? 'text-green-600' : ($analysis->confidence >= 50 ? 'text-orange-500' : 'text-red-500') }}">{{ $analysis->confidence }}%</span>
                @if($analysis->human_status === 'validated')
                    <span class="text-green-500" title="Validé">✓</span>
                @elseif($analysis->human_status === 'corrected')
                    <span class="text-orange-500" title="Corrigé">✎</span>
                @endif
            </div>
        </div>
    </a>
    @empty
    <div class="p-12 text-center">
        <div class="text-4xl mb-4">🎉</div>
        <p class="text-gray-500 font-medium">Aucun email à reviewer !</p>
    </div>
    @endforelse
</div>

@if($analyses->hasPages())
    <div class="mt-6">{{ $analyses->links() }}</div>
@endif
@endsection
