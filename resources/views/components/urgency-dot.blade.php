@php
$colors = match($urgency ?? 'medium') {
    'critical' => 'bg-red-500 animate-pulse', 'high' => 'bg-orange-500',
    'medium' => 'bg-blue-400', 'low' => 'bg-gray-300', default => 'bg-gray-300',
};
@endphp
<span class="inline-flex items-center" title="Urgence : {{ ucfirst($urgency ?? 'medium') }}">
    <span class="w-2.5 h-2.5 rounded-full {{ $colors }}"></span>
</span>
