<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Hassan Spot Trading Risk Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    @livewireStyles
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen">
    <nav class="border-b border-slate-800 bg-slate-900/90 backdrop-blur sticky top-0 z-30">
        <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
            <div>
                <h1 class="text-lg font-semibold">Hassan Spot Trading Risk Manager</h1>
                <p class="text-xs text-slate-400">Crypto Spot Trading Risk Management Tool</p>
            </div>
            @auth
                <div class="flex items-center gap-3">
                    <span class="text-sm text-slate-300">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="px-3 py-2 rounded-lg bg-red-600 hover:bg-red-500 text-sm">Logout</button>
                    </form>
                </div>
            @endauth
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 py-8">
        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
