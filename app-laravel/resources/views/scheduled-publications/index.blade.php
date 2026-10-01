<x-layouts.app title="Programación de {{ $brand->name }} · SocialFlow AI">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-emerald-600">Programación</p>
            <h1 class="mt-2 text-3xl font-semibold">{{ $brand->name }}</h1>
            <p class="mt-3 max-w-2xl text-sm text-slate-600">Cola interna de publicaciones futuras. Los elementos listos todavía no se publican automáticamente.</p>
        </div>
        <a href="{{ route('marcas.show', $brand) }}" class="inline-flex w-fit rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800">Volver a la marca</a>
    </div>

    @if (session('status'))
        <div class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="mt-8 space-y-4">
        @forelse ($publications as $publication)
            <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col justify-between gap-4 md:flex-row md:items-start">
                    <div>
                        <h2 class="font-semibold text-slate-950">{{ $publication->draft->title }}</h2>
                        <p class="mt-2 text-sm text-slate-600">{{ $publication->scheduled_for->format('d/m/Y H:i') }} · Programado por {{ $publication->scheduledBy->name }}</p>
                        @if ($publication->status === 'cancelled')
                            <p class="mt-1 text-xs text-slate-500">Cancelado por {{ $publication->cancelledBy?->name ?? 'Usuario eliminado' }} el {{ $publication->cancelled_at?->format('d/m/Y H:i') }}</p>
                        @endif
                    </div>
                    <span class="w-fit rounded-full px-3 py-1 text-xs font-semibold {{ $publication->statusLabel() === 'LISTO' ? 'bg-amber-100 text-amber-800' : ($publication->statusLabel() === 'CANCELADO' ? 'bg-slate-200 text-slate-700' : 'bg-emerald-100 text-emerald-800') }}">{{ $publication->statusLabel() }}</span>
                </div>

                @if ($publication->status === 'scheduled')
                    <div class="mt-5 flex flex-wrap items-end gap-4 border-t border-slate-100 pt-4">
                        <form action="{{ route('marcas.programacion.update', [$brand, $publication]) }}" method="POST" class="flex flex-wrap items-end gap-3">
                            @csrf
                            @method('PATCH')
                            <div>
                                <label for="scheduled_for_{{ $publication->getKey() }}" class="block text-sm font-semibold text-slate-700">Reprogramar</label>
                                <p class="mt-1 text-sm text-slate-600">La fecha y hora deben ser posteriores al momento actual.</p>
                                @error('scheduled_for')
                                    <p class="mt-1 text-sm font-semibold text-red-700">{{ $message }}</p>
                                @enderror
                                <input id="scheduled_for_{{ $publication->getKey() }}" name="scheduled_for" type="datetime-local" min="{{ now()->format('Y-m-d\\TH:i') }}" required class="mt-2 rounded-lg border-slate-300">
                            </div>
                            <button class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white">Guardar fecha</button>
                        </form>
                        <form action="{{ route('marcas.programacion.cancel', [$brand, $publication]) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <button class="rounded-lg bg-red-700 px-4 py-2.5 text-sm font-semibold text-white">Cancelar</button>
                        </form>
                    </div>
                @endif
            </article>
        @empty
            <div class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center text-slate-600">Todavía no hay elementos programados.</div>
        @endforelse
    </div>

    <div class="mt-8">{{ $publications->links() }}</div>
</x-layouts.app>
