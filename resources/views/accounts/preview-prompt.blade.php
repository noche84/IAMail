@extends('layouts.app')

@section('title', 'Aperçu prompt — ' . $account->label)

@section('content')
<div class="mb-4">
    <a href="{{ route('accounts.index') }}" class="text-sm text-indigo-600 hover:underline">← Retour aux comptes</a>
</div>

<h1 class="text-2xl font-bold text-gray-900 mb-2">👁 Aperçu du prompt complet</h1>
<p class="text-sm text-gray-500 mb-6">
    Compte : <strong>{{ $account->label }}</strong> ({{ $account->email_address }})
    — Voici exactement ce qui est envoyé à l'IA comme instructions système pour chaque email.
</p>

<div class="bg-white rounded-xl shadow-sm border">
    <div class="p-4 border-b flex justify-between items-center">
        <span class="text-sm font-medium text-gray-700">System Prompt</span>
        <span class="text-xs text-gray-400">{{ mb_strlen($fullPrompt) }} caractères — ~{{ (int)(mb_strlen($fullPrompt) / 4) }} tokens estimés</span>
    </div>
    <pre class="p-6 text-sm text-gray-700 whitespace-pre-wrap font-mono leading-relaxed max-h-[80vh] overflow-y-auto">{{ $fullPrompt }}</pre>
</div>
@endsection
