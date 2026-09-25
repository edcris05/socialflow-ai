<x-layouts.app title="Registrarse · SocialFlow AI">
    <div class="mx-auto max-w-md">
        <h1 class="text-3xl font-semibold tracking-tight">Crear cuenta</h1>
        <p class="mt-2 text-slate-600">Tu cuenta será el primer límite de acceso de tus marcas.</p>
        <form action="{{ route('register.store') }}" method="POST" class="mt-8 space-y-5 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            @csrf
            <div>
                <label for="name" class="block text-sm font-semibold">Nombre</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required class="mt-2 block w-full rounded-lg border-slate-300">
                @error('name') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="email" class="block text-sm font-semibold">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required class="mt-2 block w-full rounded-lg border-slate-300">
                @error('email') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="password" class="block text-sm font-semibold">Contraseña</label>
                <input id="password" name="password" type="password" required class="mt-2 block w-full rounded-lg border-slate-300">
                @error('password') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="password_confirmation" class="block text-sm font-semibold">Repetir contraseña</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required class="mt-2 block w-full rounded-lg border-slate-300">
            </div>
            <button type="submit" class="w-full rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white">Crear cuenta</button>
        </form>
    </div>
</x-layouts.app>