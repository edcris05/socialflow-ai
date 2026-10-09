<x-layouts.app title="{{ $brand->name }} · SocialFlow AI">
    @php
        $autopublishingSetting = $brand->autopublishingSetting;
        $autopublishingEnabled = $brand->autopublishingEnabled();
    @endphp

    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-indigo-600">Marca</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight">{{ $brand->name }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ $brand->slug }}</p>
        </div>
        <a href="{{ route('marcas.edit', $brand) }}" class="inline-flex w-fit rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 transition hover:bg-slate-100">Editar marca</a>
        <a href="{{ route('marcas.conocimiento.index', $brand) }}" class="inline-flex w-fit rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white">Base de conocimiento</a>
        <a href="{{ route('marcas.estrategia.index', $brand) }}" class="inline-flex w-fit rounded-lg border border-violet-300 bg-violet-50 px-4 py-2.5 text-sm font-semibold text-violet-800">Estrategia</a>
        <a href="{{ route('marcas.contexto.create', $brand) }}" class="inline-flex w-fit rounded-lg border border-indigo-300 bg-indigo-50 px-4 py-2.5 text-sm font-semibold text-indigo-800">Preparar contenido</a>
        <a href="{{ route('marcas.programacion.index', $brand) }}" class="inline-flex w-fit rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-800">Programación</a>
        <a href="{{ route('marcas.meta.show', $brand) }}" class="inline-flex w-fit rounded-lg border border-sky-300 bg-sky-50 px-4 py-2.5 text-sm font-semibold text-sky-800">Meta</a>
    </div>

    @if (session('status'))
        <div class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <section class="mt-8 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
            <div>
                <p class="text-sm font-semibold text-slate-950">Autopublicación</p>
                <p class="mt-2 max-w-2xl text-sm text-slate-600">Cuando está activada, las publicaciones programadas elegibles pueden ser enviadas automáticamente por el scheduler.</p>
                <p class="mt-2 text-xs text-slate-500">Esta política por marca no activa los controles globales ni configura un scheduler en el host.</p>
            </div>
            <span class="w-fit rounded-full px-3 py-1 text-xs font-semibold {{ $autopublishingEnabled ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700' }}">
                {{ $autopublishingEnabled ? 'ACTIVADA' : 'DESACTIVADA' }}
            </span>
        </div>

        @if ($autopublishingEnabled)
            <div class="mt-4 text-sm text-slate-600">
                @if ($autopublishingSetting?->enabled_at)
                    Activada por {{ $autopublishingSetting->enabledBy?->name ?? 'Usuario eliminado' }} el {{ $autopublishingSetting->enabled_at->format('d/m/Y H:i') }}.
                @endif
            </div>
            <form action="{{ route('marcas.autopublicacion.destroy', $brand) }}" method="POST" class="mt-4">
                @csrf
                @method('DELETE')
                <button class="rounded-lg border border-red-300 bg-red-50 px-4 py-2.5 text-sm font-semibold text-red-800">Desactivar autopublicación</button>
            </form>
        @else
            @if ($autopublishingSetting?->disabled_at)
                <p class="mt-4 text-sm text-slate-600">Desactivada por {{ $autopublishingSetting->disabledBy?->name ?? 'Usuario eliminado' }} el {{ $autopublishingSetting->disabled_at->format('d/m/Y H:i') }}.</p>
            @endif
            <form action="{{ route('marcas.autopublicacion.store', $brand) }}" method="POST" class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4">
                @csrf
                <label class="flex items-start gap-3 text-sm font-semibold text-slate-900">
                    <input type="checkbox" name="confirm_enable" value="1" required class="mt-0.5 rounded border-slate-300">
                    <span>Confirmo que quiero habilitar la autopublicación para esta marca.</span>
                </label>
                @error('confirm_enable')
                    <p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p>
                @enderror
                <button class="mt-4 rounded-lg bg-amber-700 px-4 py-2.5 text-sm font-semibold text-white" onclick="return confirm('¿Confirmas la activación de la autopublicación para esta marca?')">Activar autopublicación</button>
            </form>
        @endif
    </section>

    <dl class="mt-8 grid gap-5 sm:grid-cols-2">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:col-span-2">
            <dt class="text-sm font-medium text-slate-500">Descripción</dt>
            <dd class="mt-2 whitespace-pre-line text-slate-800">{{ $brand->description ?: 'Sin descripción registrada.' }}</dd>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <dt class="text-sm font-medium text-slate-500">Tono de voz</dt>
            <dd class="mt-2 text-slate-800">{{ $brand->tone_of_voice ?: 'Sin definir.' }}</dd>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <dt class="text-sm font-medium text-slate-500">Público objetivo</dt>
            <dd class="mt-2 text-slate-800">{{ $brand->target_audience ?: 'Sin definir.' }}</dd>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <dt class="text-sm font-medium text-slate-500">Canales sociales</dt>
            <dd class="mt-2 text-slate-800">{{ filled($brand->social_channels) ? implode(', ', $brand->social_channels) : 'Sin definir.' }}</dd>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <dt class="text-sm font-medium text-slate-500">Restricciones</dt>
            <dd class="mt-2 text-slate-800">{{ filled($brand->restrictions) ? implode(', ', $brand->restrictions) : 'Sin definir.' }}</dd>
        </div>
    </dl>
</x-layouts.app>
