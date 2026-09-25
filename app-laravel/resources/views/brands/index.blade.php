<x-layouts.app title="Marcas · SocialFlow AI">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-indigo-600">Tenants</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight">Marcas</h1>
            <p class="mt-2 text-slate-600">Administrá la información base de cada marca.</p>
        </div>
        <a href="{{ route('marcas.create') }}" class="inline-flex w-fit rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700">Crear marca</a>
    </div>

    <div class="mt-8 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        @forelse ($brands as $brand)
            <a href="{{ route('marcas.show', $brand) }}" class="block border-b border-slate-100 px-6 py-5 transition last:border-b-0 hover:bg-slate-50">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="font-semibold text-slate-950">{{ $brand->name }}</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ $brand->slug }}</p>
                    </div>
                    <span class="text-sm font-medium text-indigo-600">Ver detalle</span>
                </div>
            </a>
        @empty
            <div class="px-6 py-12 text-center">
                <p class="font-medium text-slate-800">Todavía no hay marcas.</p>
                <p class="mt-2 text-sm text-slate-600">Creá la primera ficha para comenzar.</p>
            </div>
        @endforelse
    </div>
</x-layouts.app>
