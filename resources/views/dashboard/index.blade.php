@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <div class="text-sm font-medium text-gray-500">Aujourd'hui</div>
        <div class="mt-1 text-3xl font-bold text-gray-900">{{ number_format($stats['total_today']) }}</div>
        <div class="text-xs text-gray-400 mt-1">emails analysés</div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <div class="text-sm font-medium text-gray-500">À reviewer</div>
        <div class="mt-1 text-3xl font-bold {{ $stats['pending_review'] > 0 ? 'text-orange-600' : 'text-green-600' }}">{{ number_format($stats['pending_review']) }}</div>
        <div class="text-xs"><a href="{{ route('review.index') }}" class="text-indigo-600 hover:underline">Voir →</a></div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <div class="text-sm font-medium text-gray-500">Auto-classés</div>
        <div class="mt-1 text-3xl font-bold text-green-600">{{ number_format($stats['auto_classified']) }}</div>
        <div class="text-xs text-gray-400 mt-1">haute confiance</div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <div class="text-sm font-medium text-gray-500">Spam bloqué</div>
        <div class="mt-1 text-3xl font-bold text-red-500">{{ number_format($stats['spam_blocked']) }}</div>
        <div class="text-xs text-gray-400 mt-1">cette semaine</div>
    </div>
</div>

<div class="grid md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="font-semibold text-gray-900 mb-3">🧠 Apprentissage</h3>
        <div class="space-y-3">
            <div class="flex justify-between"><span class="text-sm text-gray-600">Exemples appris</span><span class="font-semibold text-indigo-600">{{ $stats['learned_examples'] }}</span></div>
            <div class="flex justify-between"><span class="text-sm text-gray-600">Corrections cette semaine</span><span class="font-semibold {{ $stats['corrections_week'] > 10 ? 'text-orange-600' : 'text-green-600' }}">{{ $stats['corrections_week'] }}</span></div>
            <div class="flex justify-between"><span class="text-sm text-gray-600">Coût IA ce mois</span><span class="font-semibold text-gray-900">{{ number_format($stats['total_cost_month'], 4) }} €</span></div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="font-semibold text-gray-900 mb-3">📂 Top catégories (7j)</h3>
        @forelse($topCategories as $category => $count)
            <div class="flex justify-between items-center py-1.5">
                <span class="text-sm text-gray-600">{{ $category }}</span>
                <div class="flex items-center space-x-2">
                    <div class="w-24 bg-gray-100 rounded-full h-2">
                        @php $mx = $topCategories->max(); $pct = $mx > 0 ? ($count / $mx) * 100 : 0; @endphp
                        <div class="bg-indigo-500 rounded-full h-2" style="width: {{ $pct }}%"></div>
                    </div>
                    <span class="text-xs font-medium text-gray-500 w-8 text-right">{{ $count }}</span>
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-400 italic">Aucune donnée — lancez un test !</p>
        @endforelse
    </div>

    <div class="bg-white rounded-xl shadow-sm border p-5">
        <div class="flex justify-between items-center mb-3">
            <h3 class="font-semibold text-gray-900">📧 Comptes</h3>
            <a href="{{ route('accounts.create') }}" class="text-xs text-indigo-600 hover:underline">+ Nouveau</a>
        </div>
        @forelse($accounts as $account)
            <div class="py-2 border-b last:border-0">
                <div class="flex justify-between items-center">
                    <div>
                        <a href="{{ route('accounts.edit', $account) }}" class="text-sm font-medium text-gray-900 hover:text-indigo-600">{{ $account->label }}</a>
                        <div class="text-xs text-gray-400">{{ $account->email }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm font-semibold {{ $account->accuracy_rate >= 90 ? 'text-green-600' : ($account->accuracy_rate >= 70 ? 'text-orange-600' : 'text-red-600') }}">{{ number_format($account->accuracy_rate, 1) }}%</div>
                        <div class="text-xs text-gray-400">{{ number_format($account->total_collected) }} emails</div>
                    </div>
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-400 italic"><a href="{{ route('accounts.create') }}" class="text-indigo-600 hover:underline">Configurer un compte →</a></p>
        @endforelse
    </div>
</div>

@if($needsReview->isNotEmpty())
<div class="bg-white rounded-xl shadow-sm border">
    <div class="p-5 border-b flex justify-between items-center">
        <h3 class="font-semibold text-gray-900">🔔 À reviewer en priorité</h3>
        <a href="{{ route('review.index') }}" class="text-sm text-indigo-600 hover:underline">Voir tout →</a>
    </div>
    <div class="divide-y">
        @foreach($needsReview as $analysis)
        <a href="{{ route('review.show', $analysis) }}" class="block p-4 hover:bg-gray-50 transition">
            <div class="flex items-start justify-between">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center space-x-2">
                        @include('components.urgency-dot', ['urgency' => $analysis->urgency])
                        <span class="text-sm font-medium text-gray-900 truncate">{{ $analysis->subject }}</span>
                    </div>
                    <div class="mt-1 text-xs text-gray-500">{{ $analysis->from_name ?? $analysis->from_email }} — <span class="italic">{{ $analysis->summary }}</span></div>
                </div>
                <div class="ml-4 flex items-center space-x-2">
                    @include('components.legitimacy-badge', ['legitimacy' => $analysis->legitimacy])
                    <span class="text-xs text-gray-400">{{ $analysis->confidence }}%</span>
                </div>
            </div>
        </a>
        @endforeach
    </div>
</div>
@else
<div class="bg-white rounded-xl shadow-sm border p-12 text-center">
    <div class="text-4xl mb-4">🧪</div>
    <p class="text-gray-500 font-medium">Aucun email analysé pour le moment</p>
    <p class="text-gray-400 text-sm mt-1 mb-4">Configurez un compte email ou testez l'IA manuellement.</p>
    <a href="{{ route('test.form') }}" class="inline-flex bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-indigo-700">🧪 Tester l'IA</a>
</div>
@endif
@endsection
