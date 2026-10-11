@php
$c = match($legitimacy ?? 'pending') {
    'legitimate' => ['bg-green-100', 'text-green-700', 'Légitime'],
    'spam' => ['bg-red-100', 'text-red-700', 'Spam'],
    'phishing' => ['bg-red-200', 'text-red-800', '⚠ Phishing'],
    'auto_reply' => ['bg-gray-100', 'text-gray-600', 'Auto-réponse'],
    'uncertain' => ['bg-yellow-100', 'text-yellow-700', 'Incertain'],
    default => ['bg-gray-100', 'text-gray-500', 'En attente'],
};
@endphp
<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $c[0] }} {{ $c[1] }}">{{ $c[2] }}</span>
