<x-layouts.app title="{{ $brand->name }} · SocialFlow AI">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-indigo-600">Marca</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight">{{ $brand->name }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ $brand->slug }}</p>
        </div>
        <a href="{{ route('marcas.edit', $brand) }}" class="inline-flex w-fit rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 transition hover:bg-slate-100">Editar marca</a>
        <a href="{{ route('marcas.conocimiento.index', $brand) }}" class="inline-flex w-fit rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white">Base de conocimiento</a>
        <a href="{{ route('marcas.contexto.create', $brand) }}" class="inline-flex w-fit rounded-lg border border-indigo-300 bg-indigo-50 px-4 py-2.5 text-sm font-semibold text-indigo-800">Preparar contenido</a>
    </div>

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
