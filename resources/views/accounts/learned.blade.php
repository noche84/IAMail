@extends('layouts.app')

@section('title', 'Exemples appris — ' . $account->label)

@section('content')
<div class="mb-4">
    <a href="{{ route('accounts.index') }}" class="text-sm text-indigo-600 hover:underline">← Retour aux comptes</a>
</div>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">🧠 Exemples appris</h1>
        <p class="text-sm text-gray-500 mt-1">Compte : <strong>{{ $account->label }}</strong> ({{ $account->email_address }})</p>
    </div>
</div>

<p class="text-sm text-gray-600 mb-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
    💡 Ces exemples sont injectés dans le prompt IA à chaque analyse. Ils proviennent des corrections humaines
    ou ont été ajoutés manuellement. Plus les exemples sont pertinents, plus l'IA sera précise.
</p>

{{-- Formulaire ajout manuel --}}
<div class="bg-white rounded-xl shadow-sm border p-6 mb-6" x-data="{ open: false }">
    <button @click="open = !open" class="w-full text-left flex justify-between items-center">
        <h3 class="font-semibold text-gray-800">➕ Ajouter un exemple manuellement</h3>
        <svg class="w-4 h-4 text-gray-400 transition" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    <form x-show="open" x-cloak action="{{ route('accounts.add-example', $account) }}" method="POST" class="mt-4 space-y-4">
        @csrf

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description de l'email type</label>
            <textarea name="example_input" rows="3" class="w-full border rounded-lg px-3 py-2 text-sm"
                      placeholder="Expéditeur : @europa.eu&#10;Sujet : Call for proposals — Horizon Europe&#10;Langue : anglais&#10;Contenu : appel à projets avec deadline" required></textarea>
        </div>

        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Légitimité correcte</label>
                <select name="legitimacy" class="w-full border rounded-lg px-3 py-2 text-sm" required>
                    <option value="legitimate">Légitime</option>
                    <option value="spam">Spam</option>
                    <option value="phishing">Phishing</option>
                    <option value="auto_reply">Auto-réponse</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Urgence correcte</label>
                <select name="urgency" class="w-full border rounded-lg px-3 py-2 text-sm" required>
                    <option value="critical">Critique</option>
                    <option value="high">Haute</option>
                    <option value="medium" selected>Moyenne</option>
                    <option value="low">Basse</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Catégorie correcte</label>
                <select name="category" class="w-full border rounded-lg px-3 py-2 text-sm" required>
                    @foreach($account->getCategories() as $key => $label)
                        <option value="{{ $key }}">{{ $key }} — {{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Action correcte</label>
                <select name="suggested_action" class="w-full border rounded-lg px-3 py-2 text-sm" required>
                    @foreach(config('aitriage.suggested_actions', []) as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Explication (pour l'IA)</label>
            <textarea name="explanation" rows="2" class="w-full border rounded-lg px-3 py-2 text-sm"
                      placeholder="Les emails @europa.eu sont des appels à projets européens, toujours légitimes même en anglais." required></textarea>
        </div>

        <div>
            <label class="flex items-center space-x-2">
                <input type="checkbox" name="is_global" value="1" class="rounded border-gray-300">
                <span class="text-sm text-gray-700">Appliquer à tous les comptes (exemple global)</span>
            </label>
        </div>

        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-indigo-700">
            Ajouter l'exemple
        </button>
    </form>
</div>

{{-- Liste des exemples --}}
<div class="space-y-4">
    @forelse($examples as $example)
    <div class="bg-white rounded-xl shadow-sm border p-5 {{ !$example->is_active ? 'opacity-50' : '' }}">
        <div class="flex items-start justify-between">
            <div class="flex-1">
                <div class="flex items-center space-x-2 mb-2">
                    <span class="px-2 py-0.5 rounded-full text-xs {{ $example->trigger_type === 'correction' ? 'bg-orange-100 text-orange-700' : 'bg-blue-100 text-blue-700' }}">
                        {{ $example->trigger_type === 'correction' ? '🔄 Auto (correction)' : '✍️ Manuel' }}
                    </span>
                    @if($example->is_global)
                        <span class="px-2 py-0.5 rounded-full text-xs bg-purple-100 text-purple-700">🌍 Global</span>
                    @endif
                    @if(!$example->is_active)
                        <span class="px-2 py-0.5 rounded-full text-xs bg-red-100 text-red-700">Désactivé</span>
                    @endif
                </div>

                <div class="text-sm text-gray-700 mb-2">
                    <strong>Input :</strong>
                    <pre class="mt-1 text-xs bg-gray-50 p-3 rounded-lg whitespace-pre-wrap">{{ $example->example_input }}</pre>
                </div>

                <div class="text-sm text-gray-700 mb-2">
                    <strong>Output attendu :</strong>
                    <pre class="mt-1 text-xs bg-green-50 p-3 rounded-lg whitespace-pre-wrap">{{ $example->expected_output }}</pre>
                </div>

                @if($example->explanation)
                <div class="text-sm text-gray-500 italic">
                    💬 {{ $example->explanation }}
                </div>
                @endif

                <div class="mt-2 flex items-center space-x-4 text-xs text-gray-400">
                    <span>Utilisé {{ $example->times_used }}× dans des prompts</span>
                    <span>Efficacité : {{ number_format($example->effectiveness_score, 0) }}%</span>
                    <span>Créé le {{ $example->created_at->format('d/m/Y') }}</span>
                </div>
            </div>
            <form action="{{ route('accounts.delete-example', [$account, $example]) }}" method="POST"
                  onsubmit="return confirm('Supprimer cet exemple appris ?')">
                @csrf @method('DELETE')
                <button type="submit" class="text-red-400 hover:text-red-600 p-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </button>
            </form>
        </div>
    </div>
    @empty
    <div class="bg-white rounded-xl shadow-sm border p-12 text-center">
        <div class="text-4xl mb-4">🧠</div>
        <p class="text-gray-500 font-medium">Aucun exemple appris pour ce compte</p>
        <p class="text-gray-400 text-sm mt-1">Les exemples apparaîtront ici quand vous corrigerez des classifications.</p>
    </div>
    @endforelse
</div>

@if($examples->hasPages())
    <div class="mt-6">{{ $examples->links() }}</div>
@endif
@endsection
