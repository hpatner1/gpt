<x-layouts.app>
    <div class="max-w-md mx-auto bg-slate-900 border border-slate-800 rounded-xl p-6">
        <h2 class="text-xl font-semibold mb-5">Create account</h2>
        <form method="POST" action="{{ route('register.perform') }}" class="space-y-4">
            @csrf
            <div>
                <label class="text-sm text-slate-400">Name</label>
                <input type="text" name="name" class="w-full mt-1 px-3 py-2 rounded bg-slate-800 border border-slate-700" required>
            </div>
            <div>
                <label class="text-sm text-slate-400">Email</label>
                <input type="email" name="email" class="w-full mt-1 px-3 py-2 rounded bg-slate-800 border border-slate-700" required>
            </div>
            <div>
                <label class="text-sm text-slate-400">Password</label>
                <input type="password" name="password" class="w-full mt-1 px-3 py-2 rounded bg-slate-800 border border-slate-700" required>
            </div>
            <div>
                <label class="text-sm text-slate-400">Confirm password</label>
                <input type="password" name="password_confirmation" class="w-full mt-1 px-3 py-2 rounded bg-slate-800 border border-slate-700" required>
            </div>
            <button class="w-full py-2 rounded bg-indigo-600 hover:bg-indigo-500">Register</button>
        </form>
        <p class="text-sm text-slate-400 mt-4">Already have an account? <a href="{{ route('login') }}" class="text-indigo-400">Login</a></p>
    </div>
</x-layouts.app>
