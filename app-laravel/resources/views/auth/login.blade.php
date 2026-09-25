<x-layouts.app title="Ingresar · SocialFlow AI">
    <div class="mx-auto max-w-md">
        <h1 class="text-3xl font-semibold tracking-tight">Ingresar</h1>
        <p class="mt-2 text-slate-600">Accedé a las marcas de tu equipo.</p>
        <form action="{{ route('login.store') }}" method="POST" class="mt-8 space-y-5 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            @csrf
            <div>
                <label for="email" class="block text-sm font-semibold">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus class="mt-2 block w-full rounded-lg border-slate-300">
                @error('email') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="password" class="block text-sm font-semibold">Contraseña</label>
                <input id="password" name="password" type="password" required class="mt-2 block w-full rounded-lg border-slate-300">
                @error('password') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-600"><input name="remember" type="checkbox" value="1"> Recordarme</label>
            <button type="submit" class="w-full rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white">Ingresar</button>
        </form>
    </div>
</x-layouts.app>