<x-layouts.app title="Conocimiento de {{ $brand->name }} · SocialFlow AI">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-indigo-600">Base de conocimiento</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight">{{ $brand->name }}</h1>
            <p class="mt-2 text-slate-600">Información verificable y aislada por marca.</p>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('marcas.borradores.index', $brand) }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold">Borradores</a>
            <a href="{{ route('marcas.conocimiento.create', $brand) }}" class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white">Agregar conocimiento</a>
        </div>
    </div>

    <form method="GET" class="mt-8 flex gap-3">
        <label for="q" class="sr-only">Buscar conocimiento</label>
        <input id="q" name="q" value="{{ $search }}" placeholder="Buscar por título, contenido o fuente" class="block w-full rounded-lg border-slate-300">
        <button class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold">Buscar</button>
    </form>

    <div class="mt-6 space-y-3">
        @forelse ($entries as $entry)
            <a href="{{ route('marcas.conocimiento.show', [$brand, $entry]) }}" class="block rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:bg-slate-50">
                <div class="flex items-start justify-between gap-4">
                    <div><h2 class="font-semibold">{{ $entry->title }}</h2><p class="mt-1 text-sm text-slate-500">{{ $entry->source ?: 'Fuente no registrada' }}</p></div>
                    <span class="text-sm text-indigo-600">{{ $entry->status }}</span>
                </div>
                <p class="mt-3 line-clamp-2 text-sm text-slate-600">{{ $entry->content }}</p>
            </a>
        @empty
            <div class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center text-slate-600">No hay conocimiento registrado para esta marca.</div>
        @endforelse
    </div>
    <div class="mt-6">{{ $entries->links() }}</div>
</x-layouts.app>