@extends('layouts.app')
@section('title', $account ? 'Éditer ' . $account->label : 'Nouveau compte')
@section('content')

<div class="mb-4">
    <a href="{{ route('accounts.index') }}" class="text-sm text-indigo-600 hover:underline">← Retour aux comptes</a>
</div>

<div class="max-w-3xl">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">
        {{ $account ? '✏️ ' . $account->label : '📧 Nouveau compte email' }}
    </h1>

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 text-sm">
            @foreach($errors->all() as $error)<div>• {{ $error }}</div>@endforeach
        </div>
    @endif

    <form action="{{ $account ? route('accounts.update', $account) : route('accounts.store') }}"
          method="POST" class="space-y-6" x-data="accountForm()">
        @csrf
        @if($account) @method('PUT') @endif

        {{-- 1. Identité --}}
        <div class="bg-white rounded-xl shadow-sm border p-6 space-y-4">
            <h3 class="font-semibold text-gray-800">📋 Identité</h3>
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Label</label>
                    <input type="text" name="label" value="{{ old('label', $account?->label) }}"
                           class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="Boîte générale" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Adresse email</label>
                    <input type="email" name="email" value="{{ old('email', $account?->email) }}"
                           class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="info@societe.be" required>
                </div>
            </div>

            <div class="flex items-center space-x-6">
                @if($account)
                <label class="flex items-center space-x-2">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $account->is_active) ? 'checked' : '' }} class="rounded border-gray-300">
                    <span class="text-sm text-gray-700">Compte actif</span>
                </label>
                @endif
                <label class="flex items-center space-x-2">
                    <input type="hidden" name="auto_analyze" value="0">
                    <input type="checkbox" name="auto_analyze" value="1"
                           {{ old('auto_analyze', $account?->auto_analyze ?? true) ? 'checked' : '' }} class="rounded border-gray-300">
                    <span class="text-sm text-gray-700">Analyser automatiquement avec l'IA</span>
                </label>
            </div>

            {{-- ⚠️ Option sensible : marquer comme lu --}}
            <div class="p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                <label class="flex items-start space-x-2">
                    <input type="hidden" name="mark_as_read_on_collect" value="0">
                    <input type="checkbox" name="mark_as_read_on_collect" value="1"
                           {{ old('mark_as_read_on_collect', $account?->mark_as_read_on_collect ?? false) ? 'checked' : '' }}
                           class="rounded border-gray-300 mt-0.5">
                    <div>
                        <span class="text-sm font-medium text-yellow-800">Marquer comme lu sur le serveur à la collecte</span>
                        <p class="text-xs text-yellow-700 mt-1">
                            ⚠️ <strong>Ne cochez pas</strong> sauf si vous utilisez AI Mail Triage comme client principal
                            pour cette boîte. Si coché, votre client habituel (Outlook, Thunderbird, Mail...)
                            verra les emails collectés comme déjà lus.
                        </p>
                    </div>
                </label>
            </div>
        </div>

        {{-- 2. Connexion IMAP --}}
        <div class="bg-white rounded-xl shadow-sm border p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="font-semibold text-gray-800">🔌 Connexion IMAP</h3>
                <div class="flex space-x-2">
                    <button type="button" @click="applyPreset('infomaniak')" class="px-2 py-1 rounded text-xs bg-blue-50 text-blue-700 hover:bg-blue-100">Infomaniak</button>
                    <button type="button" @click="applyPreset('gmail')" class="px-2 py-1 rounded text-xs bg-red-50 text-red-700 hover:bg-red-100">Gmail</button>
                    <button type="button" @click="applyPreset('outlook')" class="px-2 py-1 rounded text-xs bg-indigo-50 text-indigo-700 hover:bg-indigo-100">Outlook</button>
                    <button type="button" @click="applyPreset('ovh')" class="px-2 py-1 rounded text-xs bg-gray-50 text-gray-700 hover:bg-gray-100">OVH</button>
                </div>
            </div>
            <div class="grid md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Serveur</label>
                    <input type="text" name="imap_host" x-model="host" class="w-full border rounded-lg px-3 py-2 text-sm" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Port</label>
                    <input type="number" name="imap_port" x-model="port" class="w-full border rounded-lg px-3 py-2 text-sm" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Chiffrement</label>
                    <select name="imap_encryption" x-model="encryption" class="w-full border rounded-lg px-3 py-2 text-sm">
                        <option value="ssl">SSL</option><option value="tls">TLS</option><option value="none">Aucun</option>
                    </select>
                </div>
            </div>
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Utilisateur</label>
                    <input type="text" name="imap_username" value="{{ old('imap_username', $account?->imap_username) }}"
                           class="w-full border rounded-lg px-3 py-2 text-sm" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Mot de passe @if($account)<span class="text-gray-400 font-normal">(vide = inchangé)</span>@endif</label>
                    <input type="password" name="imap_password" class="w-full border rounded-lg px-3 py-2 text-sm"
                           placeholder="{{ $account ? '••••••••' : '' }}" {{ $account ? '' : 'required' }}>
                </div>
            </div>
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Dossier IMAP</label>
                    <input type="text" name="imap_folder" value="{{ old('imap_folder', $account?->imap_folder ?? 'INBOX') }}"
                           class="w-full border rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Intervalle</label>
                    <div class="flex items-center space-x-2">
                        <input type="number" name="check_interval_minutes" min="1" max="1440"
                               value="{{ old('check_interval_minutes', $account?->check_interval_minutes ?? 5) }}"
                               class="w-24 border rounded-lg px-3 py-2 text-sm" required>
                        <span class="text-sm text-gray-500">minutes</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- 3. Intelligence Artificielle --}}
        <div class="bg-white rounded-xl shadow-sm border p-6 space-y-4">
            <h3 class="font-semibold text-gray-800">🤖 Intelligence Artificielle</h3>
            <p class="text-sm text-gray-500">
                Décrivez le contexte de cette boîte. Plus c'est précis, meilleure sera la classification.
            </p>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Prompt IA</label>
                <textarea name="ai_prompt" rows="8" class="w-full border rounded-lg px-3 py-2 text-sm font-mono"
                          placeholder="Tu gères la boîte info@ de la société XYZ...">{{ old('ai_prompt', $account?->ai_prompt) }}</textarea>
            </div>
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Domaines de confiance</label>
                    <input type="text" name="trusted_domains"
                           value="{{ old('trusted_domains', implode(', ', $account?->trusted_domains ?? [])) }}"
                           class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="europa.eu, spw.wallonie.be">
                    <p class="text-xs text-gray-400 mt-1">Toujours légitimes. Séparés par des virgules.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Domaines bloqués</label>
                    <input type="text" name="blocked_domains"
                           value="{{ old('blocked_domains', implode(', ', $account?->blocked_domains ?? [])) }}"
                           class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="spam-company.com">
                    <p class="text-xs text-gray-400 mt-1">Classés spam sans appel IA.</p>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Catégories personnalisées (JSON, optionnel)</label>
                <textarea name="custom_categories" rows="3" class="w-full border rounded-lg px-3 py-2 text-sm font-mono"
                          placeholder='{"client": "Message client", "devis": "Demande de devis"}'>{{ old('custom_categories', $account?->custom_categories ? json_encode($account->custom_categories, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '') }}</textarea>
            </div>
        </div>

        {{-- Submit --}}
        <div class="flex items-center space-x-4">
            <button type="submit" class="bg-indigo-600 text-white px-6 py-2.5 rounded-lg font-medium hover:bg-indigo-700 transition">
                {{ $account ? 'Mettre à jour' : 'Créer le compte' }}
            </button>
        </div>
    </form>

    {{-- ⚠️ Formulaire de suppression HORS du formulaire principal (fix du bug nested forms) --}}
    @if($account)
    <div class="mt-6 pt-6 border-t">
        <form action="{{ route('accounts.destroy', $account) }}" method="POST"
              onsubmit="return confirm('⚠️ Supprimer le compte « {{ $account->label }} » ?\n\nLes emails déjà analysés seront conservés mais le compte IMAP sera supprimé.')">
            @csrf @method('DELETE')
            <button type="submit" class="text-sm text-red-600 hover:underline">🗑 Supprimer ce compte</button>
        </form>
    </div>
    @endif
</div>

@push('scripts')
<script>
function accountForm() {
    return {
        host: '{{ old('imap_host', $account?->imap_host ?? '') }}',
        port: '{{ old('imap_port', $account?->imap_port ?? '993') }}',
        encryption: '{{ old('imap_encryption', $account?->imap_encryption ?? 'ssl') }}',
        applyPreset(p) {
            const presets = {
                infomaniak: { host: 'mail.infomaniak.com', port: '993', encryption: 'ssl' },
                gmail:      { host: 'imap.gmail.com',      port: '993', encryption: 'ssl' },
                outlook:    { host: 'outlook.office365.com', port: '993', encryption: 'ssl' },
                ovh:        { host: 'ssl0.ovh.net',         port: '993', encryption: 'ssl' },
            };
            if (presets[p]) { this.host = presets[p].host; this.port = presets[p].port; this.encryption = presets[p].encryption; }
        }
    };
}
</script>
@endpush
@endsection
