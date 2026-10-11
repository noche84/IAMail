@extends('layouts.app')
@section('title', $email->subject)
@section('content')

<div class="mb-4 flex items-center justify-between">
    <a href="{{ route('inbox.index') }}" class="text-sm text-indigo-600 hover:underline">← Boîte de réception</a>
    <div class="flex items-center space-x-2">
        <form action="{{ route('inbox.toggle-star', $email) }}" method="POST">@csrf
            <button class="text-xl {{ $email->is_starred ? 'text-yellow-400' : 'text-gray-300' }}">{{ $email->is_starred ? '★' : '☆' }}</button>
        </form>
        <form action="{{ route('inbox.toggle-read', $email) }}" method="POST">@csrf
            <button class="px-3 py-1.5 rounded-lg text-xs bg-gray-100 text-gray-600 hover:bg-gray-200">
                {{ $email->is_read ? '📭 Marquer non lu' : '📬 Marquer lu' }}
            </button>
        </form>
        <form action="{{ route('inbox.mark-read-server', $email) }}" method="POST"
              onsubmit="return confirm('Cela marquera l\'email comme LU sur le serveur ({{ $email->account?->email ?? 'IMAP' }}).\nVotre client email (Outlook, Thunderbird...) le verra aussi comme lu.\n\nContinuer ?')">
            @csrf
            <button class="px-3 py-1.5 rounded-lg text-xs bg-green-100 text-green-700 hover:bg-green-200">
                ✓ Lu aussi sur le serveur
            </button>
        </form>
    </div>
</div>

<div class="grid md:grid-cols-4 gap-6">

    {{-- Colonne principale : email --}}
    <div class="md:col-span-3 space-y-4">

        {{-- En-tête email --}}
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4">{{ $email->subject }}</h2>
            <div class="flex items-start justify-between">
                <div class="space-y-1 text-sm text-gray-600">
                    <div><strong>De :</strong> {{ $email->from_name }} &lt;{{ $email->from_email }}&gt;</div>
                    <div><strong>À :</strong> {{ $email->to_email }}</div>
                    <div><strong>Date :</strong> {{ $email->email_date ? $email->email_date->format('d/m/Y à H:i') : $email->created_at->format('d/m/Y à H:i') }}</div>
                    @if($email->account)
                        <div><strong>Compte :</strong> {{ $email->account->label }}</div>
                    @endif
                    @if($email->has_attachments)
                        <div><strong>Pièces jointes :</strong> 📎 {{ $email->attachment_names }}</div>
                    @endif
                </div>
                <div class="flex items-center space-x-2">
                    @if($email->language && $email->language !== 'fr')
                        <span class="px-2 py-1 rounded text-xs bg-blue-50 text-blue-600 font-medium">{{ strtoupper($email->language) }}</span>
                    @endif
                    @include('components.legitimacy-badge', ['legitimacy' => $email->legitimacy])
                </div>
            </div>
        </div>

        {{-- Corps de l'email --}}
        <div class="bg-white rounded-xl shadow-sm border">
            <div class="p-3 border-b flex items-center justify-between">
                <span class="text-xs font-medium text-gray-500">Contenu de l'email</span>
            </div>
            @if($email->body_html)
                {{-- HTML rendu dans une iframe sandboxée --}}
                <iframe id="emailFrame" srcdoc="{{ e($email->body_html) }}"
                        class="w-full border-0"
                        style="min-height: 400px;"
                        sandbox="allow-same-origin"
                        onload="this.style.height = this.contentDocument.body.scrollHeight + 40 + 'px';">
                </iframe>
            @else
                <div class="p-6 text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">{{ $email->body_preview }}</div>
            @endif
        </div>
    </div>

    {{-- Sidebar : IA --}}
    <div class="space-y-4">

        {{-- Analyse IA --}}
        <div class="bg-indigo-50 rounded-xl border border-indigo-200 p-5">
            <h3 class="text-sm font-semibold text-indigo-800 mb-3">🤖 Analyse IA</h3>
            @if($email->summary)
                <p class="text-sm font-medium text-indigo-700 mb-2">{{ $email->summary }}</p>
            @endif
            @if($email->reasoning)
                <p class="text-xs text-indigo-600">{{ $email->reasoning }}</p>
            @endif
            <div class="mt-3 text-xs text-indigo-400">
                {{ $email->ai_provider }} · {{ $email->ai_duration_ms }}ms · {{ number_format($email->ai_cost_eur, 5) }}€
            </div>
        </div>

        {{-- Classification --}}
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Classification</h3>
            <div class="space-y-2.5">
                <div class="flex justify-between items-center">
                    <span class="text-xs text-gray-500">Confiance</span>
                    <div class="flex items-center space-x-2">
                        <div class="w-16 bg-gray-100 rounded-full h-2">
                            <div class="rounded-full h-2 {{ $email->confidence >= 75 ? 'bg-green-500' : ($email->confidence >= 50 ? 'bg-orange-400' : 'bg-red-500') }}"
                                 style="width: {{ $email->confidence }}%"></div>
                        </div>
                        <span class="text-sm font-bold">{{ $email->confidence }}%</span>
                    </div>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-xs text-gray-500">Légitimité</span>
                    @include('components.legitimacy-badge', ['legitimacy' => $email->legitimacy])
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-xs text-gray-500">Urgence</span>
                    <span class="flex items-center space-x-1">
                        @include('components.urgency-dot', ['urgency' => $email->urgency])
                        <span class="text-xs font-medium">{{ ucfirst($email->urgency) }}</span>
                    </span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-xs text-gray-500">Catégorie</span>
                    <span class="px-2 py-0.5 rounded text-xs bg-gray-100 font-medium">{{ $email->category }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-xs text-gray-500">Action</span>
                    <span class="text-xs font-medium">{{ $actions[$email->suggested_action] ?? $email->suggested_action }}</span>
                </div>
            </div>
        </div>

        {{-- Actions --}}
        <div class="bg-white rounded-xl shadow-sm border p-5 space-y-2">
            <h3 class="text-sm font-semibold text-gray-700 mb-2">Actions</h3>

            @if($email->human_status === 'pending')
                <form action="{{ route('review.validate', $email) }}" method="POST">@csrf
                    <button class="w-full bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700">✓ IA correcte</button>
                </form>

                <a href="{{ route('review.show', $email) }}" class="block text-center bg-orange-100 text-orange-700 px-4 py-2 rounded-lg text-sm hover:bg-orange-200">
                    ✎ Corriger la classification
                </a>
            @else
                <div class="text-center text-sm py-2">
                    @if($email->human_status === 'validated')
                        <span class="text-green-600">✓ Classification validée</span>
                    @elseif($email->human_status === 'corrected')
                        <span class="text-orange-600">✎ Classification corrigée</span>
                    @endif
                </div>
            @endif
        </div>

        {{-- Corrections passées --}}
        @if($email->corrections->isNotEmpty())
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <h3 class="text-xs font-semibold text-gray-600 mb-2">Historique corrections</h3>
            @foreach($email->corrections as $correction)
                <div class="border-l-2 border-orange-300 pl-3 py-1 mb-2 text-xs">
                    @foreach($correction->changes as $field => $change)
                        <div>{{ ucfirst($field) }} : <span class="line-through text-red-500">{{ $change['from'] }}</span> → <span class="text-green-600">{{ $change['to'] }}</span></div>
                    @endforeach
                    @if($correction->correction_note)
                        <div class="italic text-gray-400 mt-1">{{ $correction->correction_note }}</div>
                    @endif
                </div>
            @endforeach
        </div>
        @endif
    </div>
</div>

@endsection
