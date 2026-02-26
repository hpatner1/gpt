<x-layouts.app>
    <div class="max-w-md mx-auto bg-slate-900 border border-slate-800 rounded-xl p-6">
        <h2 class="text-xl font-semibold mb-5">Login</h2>
        <form method="POST" action="{{ route('login.perform') }}" class="space-y-4">
            @csrf
            <div>
                <label class="text-sm text-slate-400">Email</label>
                <input type="email" name="email" class="w-full mt-1 px-3 py-2 rounded bg-slate-800 border border-slate-700" required>
                @error('email')<p class="text-red-400 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="text-sm text-slate-400">Password</label>
                <input type="password" name="password" class="w-full mt-1 px-3 py-2 rounded bg-slate-800 border border-slate-700" required>
            </div>
            <button class="w-full py-2 rounded bg-indigo-600 hover:bg-indigo-500">Login</button>
        </form>
        <p class="text-sm text-slate-400 mt-4">No account? <a href="{{ route('register') }}" class="text-indigo-400">Register</a></p>
    </div>
</x-layouts.app>
