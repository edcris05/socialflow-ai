<x-layouts.app title="Meta · {{ $brand->name }} · SocialFlow AI">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-sky-600">Conexiones</p>
            <h1 class="mt-2 text-3xl font-semibold">Meta · {{ $brand->name }}</h1>
            <p class="mt-3 max-w-2xl text-sm text-slate-600">Configuración manual segura para preparar una futura conexión. No verifica ni publica contenido.</p>
        </div>
        <a href="{{ route('marcas.show', $brand) }}" class="inline-flex w-fit rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800">Volver a la marca</a>
    </div>

    <section class="mt-8 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-semibold">META</h2>
            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $connection ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700' }}">{{ $connection?->statusLabel() ?? 'NO CONFIGURADO' }}</span>
        </div>

        @if ($connection)
            <dl class="mt-5 grid gap-4 text-sm text-slate-700 sm:grid-cols-2">
                <div><dt class="font-semibold text-slate-900">Facebook Page ID</dt><dd class="mt-1">{{ $connection->facebook_page_id ?: 'No configurado' }}</dd></div>
                <div><dt class="font-semibold text-slate-900">Instagram Account ID</dt><dd class="mt-1">{{ $connection->instagram_account_id ?: 'No configurado' }}</dd></div>
                <div><dt class="font-semibold text-slate-900">Token</dt><dd class="mt-1">{{ $connection->isConfigured() ? 'Token configurado: Sí' : 'Token configurado: No' }}</dd></div>
                <div><dt class="font-semibold text-slate-900">Expiración</dt><dd class="mt-1">{{ $connection->token_expires_at?->format('d/m/Y H:i') ?? 'No indicada' }}</dd></div>
            </dl>
        @endif

        <form action="{{ route('marcas.meta.store', $brand) }}" method="POST" class="mt-6 space-y-5">
            @csrf
            <div>
                <label for="facebook_page_id" class="block text-sm font-semibold text-slate-900">Facebook Page ID</label>
                <input id="facebook_page_id" name="facebook_page_id" value="{{ old('facebook_page_id', $connection?->facebook_page_id) }}" maxlength="255" class="mt-2 block w-full rounded-lg border-slate-300">
                @error('facebook_page_id')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="instagram_account_id" class="block text-sm font-semibold text-slate-900">Instagram Account ID</label>
                <input id="instagram_account_id" name="instagram_account_id" value="{{ old('instagram_account_id', $connection?->instagram_account_id) }}" maxlength="255" class="mt-2 block w-full rounded-lg border-slate-300">
                @error('instagram_account_id')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="access_token" class="block text-sm font-semibold text-slate-900">Access Token</label>
                <p class="mt-1 text-sm text-slate-600">{{ $connection?->isConfigured() ? 'Token configurado: Sí. Dejá el campo vacío para conservarlo o ingresá uno nuevo para reemplazarlo.' : 'Se almacenará cifrado y no volverá a mostrarse.' }}</p>
                <input id="access_token" name="access_token" type="password" autocomplete="new-password" maxlength="5000" class="mt-2 block w-full rounded-lg border-slate-300">
                @error('access_token')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="token_expires_at" class="block text-sm font-semibold text-slate-900">Expiración opcional</label>
                <input id="token_expires_at" name="token_expires_at" type="datetime-local" value="{{ old('token_expires_at', $connection?->token_expires_at?->format('Y-m-d\\TH:i')) }}" class="mt-2 block rounded-lg border-slate-300">
                @error('token_expires_at')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-5">
                <button class="rounded-lg bg-sky-700 px-5 py-3 text-sm font-semibold text-white shadow-sm">Guardar configuración</button>
                @if ($connection?->isConfigured())
                    <p class="text-sm text-slate-600">Dejá el token vacío para conservarlo.</p>
                @endif
            </div>
        </form>

        @if ($connection?->isConfigured())
            <form action="{{ route('marcas.meta.token.destroy', $brand) }}" method="POST" class="mt-4 border-t border-slate-100 pt-4">
                @csrf
                @method('DELETE')
                <button type="submit" onclick="return confirm('¿Eliminar el token de Meta? Los identificadores de Page e Instagram se conservarán.');" class="rounded-lg border border-red-300 bg-red-50 px-4 py-2.5 text-sm font-semibold text-red-800">Eliminar token</button>
            </form>
        @endif
    </section>
</x-layouts.app>
