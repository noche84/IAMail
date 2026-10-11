<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Boîte de réception') — AI Mail Triage</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="bg-gray-50 min-h-screen">

    <nav class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center space-x-8">
                    <a href="{{ route('inbox.index') }}" class="flex items-center space-x-2">
                        <span class="text-2xl">🤖</span>
                        <span class="font-bold text-gray-900">AI Mail Triage</span>
                    </a>
                    <div class="hidden md:flex space-x-1">
                        @php
                            $cur = request()->route()?->getName() ?? '';
                            $unread = \App\Models\AiMailAnalysis::where('is_read', false)->count();
                            $needsReview = \App\Models\AiMailAnalysis::needsReview()->count();
                        @endphp
                        <a href="{{ route('inbox.index') }}" class="px-3 py-2 rounded-md text-sm font-medium {{ str_starts_with($cur, 'inbox') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-100' }}">
                            📬 Boîte de réception
                            @if($unread > 0)
                                <span class="ml-1 inline-flex items-center justify-center px-2 py-0.5 rounded-full text-xs font-bold bg-indigo-100 text-indigo-700">{{ $unread }}</span>
                            @endif
                        </a>
                        <a href="{{ route('review.index') }}" class="px-3 py-2 rounded-md text-sm font-medium {{ str_starts_with($cur, 'review') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-100' }}">
                            🤖 Classification IA
                            @if($needsReview > 0)
                                <span class="ml-1 inline-flex items-center justify-center px-2 py-0.5 rounded-full text-xs font-bold bg-orange-100 text-orange-700">{{ $needsReview }}</span>
                            @endif
                        </a>
                        <a href="{{ route('dashboard') }}" class="px-3 py-2 rounded-md text-sm font-medium {{ str_starts_with($cur, 'dashboard') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-100' }}">
                            📊 Stats
                        </a>
                        <a href="{{ route('accounts.index') }}" class="px-3 py-2 rounded-md text-sm font-medium {{ str_starts_with($cur, 'accounts') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-100' }}">
                            📧 Comptes
                        </a>
                        <a href="{{ route('test.form') }}" class="px-3 py-2 rounded-md text-sm font-medium {{ str_starts_with($cur, 'test') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-100' }}">
                            🧪 Test
                        </a>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-500">{{ auth()->user()?->name }}</span>
                    <form action="{{ route('logout') }}" method="POST">@csrf
                        <button class="text-sm text-gray-400 hover:text-red-600">Déconnexion</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    @if(session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" x-transition class="max-w-7xl mx-auto mt-4 px-4">
            <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg flex justify-between">
                <span>✅ {{ session('success') }}</span>
                <button @click="show = false">&times;</button>
            </div>
        </div>
    @endif
    @if(session('error'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 10000)" x-transition class="max-w-7xl mx-auto mt-4 px-4">
            <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg flex justify-between">
                <span>❌ {{ session('error') }}</span>
                <button @click="show = false">&times;</button>
            </div>
        </div>
    @endif

    <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">@yield('content')</main>

    @stack('scripts')
</body>
</html>
