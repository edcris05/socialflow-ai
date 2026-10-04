<x-layouts.app title="Meta · {{ $brand->name }} · SocialFlow AI">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-sky-600">Conexiones</p>
            <h1 class="mt-2 text-3xl font-semibold">Meta · {{ $brand->name }}</h1>
            <p class="mt-3 max-w-2xl text-sm text-slate-600">Configuración manual segura para verificar la identidad de la cuenta. La verificación no publica contenido.</p>
        </div>
        <a href="{{ route('marcas.show', $brand) }}" class="inline-flex w-fit rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800">Volver a la marca</a>
    </div>

    @if (session('meta_error'))
        <div class="mt-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
            {{ session('meta_error') }}
        </div>
    @endif

    <section class="mt-8 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-semibold">META</h2>
            <span @class([
                'rounded-full px-3 py-1 text-xs font-semibold',
                'bg-emerald-100 text-emerald-800' => $connection?->status === \App\Models\MetaConnection::STATUS_VERIFIED,
                'bg-red-100 text-red-800' => $connection?->status === \App\Models\MetaConnection::STATUS_ERROR,
                'bg-amber-100 text-amber-800' => $connection && ! in_array($connection->status, [\App\Models\MetaConnection::STATUS_VERIFIED, \App\Models\MetaConnection::STATUS_ERROR], true),
                'bg-slate-100 text-slate-700' => ! $connection,
            ])>{{ $connection?->statusLabel() ?? 'NO CONFIGURADO' }}</span>
        </div>

        @if ($connection)
            <dl class="mt-5 grid gap-4 text-sm text-slate-700 sm:grid-cols-2">
                <div><dt class="font-semibold text-slate-900">Facebook Page ID</dt><dd class="mt-1">{{ $connection->facebook_page_id ?: 'No configurado' }}</dd></div>
                <div><dt class="font-semibold text-slate-900">Instagram Account ID</dt><dd class="mt-1">{{ $connection->instagram_account_id ?: 'No configurado' }}</dd></div>
                <div><dt class="font-semibold text-slate-900">Token</dt><dd class="mt-1">{{ $connection->isConfigured() ? 'Token configurado: Sí' : 'Token configurado: No' }}</dd></div>
                <div><dt class="font-semibold text-slate-900">Expiración</dt><dd class="mt-1">{{ $connection->token_expires_at?->format('d/m/Y H:i') ?? 'No indicada' }}</dd></div>
                @if ($connection->last_verified_at)
                    <div><dt class="font-semibold text-slate-900">Última verificación exitosa</dt><dd class="mt-1">{{ $connection->last_verified_at->format('d/m/Y H:i') }}</dd></div>
                @endif
                @if ($connection->status === \App\Models\MetaConnection::STATUS_ERROR && $connection->last_error)
                    <div class="sm:col-span-2"><dt class="font-semibold text-red-800">Error</dt><dd class="mt-1 rounded-lg bg-red-50 px-3 py-2 text-red-800">{{ $connection->last_error }}</dd></div>
                @endif
            </dl>
        @endif

        <div class="mt-6 border-t border-slate-100 pt-5">
            @if ($connection?->isConfigured() && filled($connection->instagram_account_id))
                <form action="{{ route('marcas.meta.verify', $brand) }}" method="POST">
                    @csrf
                    <button type="submit" class="rounded-lg bg-emerald-700 px-5 py-3 text-sm font-semibold text-white shadow-sm">Verificar conexión</button>
                    <p class="mt-2 text-sm text-slate-600">Consulta la identidad de la cuenta autenticada. No crea ni publica contenido.</p>
                </form>
            @elseif ($connection && ! $connection->isConfigured())
                <p class="text-sm text-amber-800">Agregá un access token para habilitar la verificación.</p>
            @elseif ($connection && blank($connection->instagram_account_id))
                <p class="text-sm text-amber-800">Configurá el Instagram Account ID para habilitar la verificación.</p>
            @else
                <p class="text-sm text-slate-600">Guardá la configuración para habilitar la verificación.</p>
            @endif
        </div>

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
