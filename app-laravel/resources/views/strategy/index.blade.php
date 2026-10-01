<div>
    <!-- Because you are alive, everything is possible. - Thich Nhat Hanh -->
</div>
<x-layouts.app title="Estrategia de {{ $brand->name }} · SocialFlow AI">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-violet-600">Estrategia</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight">{{ $brand->name }}</h1>
            <p class="mt-3 max-w-2xl text-sm text-slate-600">Generá tres ideas basadas en conocimiento autorizado y en el historial reciente. Son sugerencias estratégicas, no publicaciones ni contenido aprobado.</p>
        </div>
        <form action="{{ route('marcas.estrategia.store', $brand) }}" method="POST">
            @csrf
            <input type="hidden" name="strategy_token" value="{{ $strategyToken }}">
            <button class="rounded-lg bg-violet-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-violet-800">Generar sugerencias</button>
        </form>
    </div>

    @if (session('error'))
        <div class="mt-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    <div class="mt-8 space-y-8">
        @forelse ($runs as $run)
            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-semibold">Ejecución {{ $run->getKey() }}</h2>
                        <p class="mt-1 text-xs text-slate-500">{{ $run->created_at->format('d/m/Y H:i') }} · {{ $run->model }}</p>
                    </div>
                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $run->status === 'succeeded' ? 'bg-emerald-100 text-emerald-800' : ($run->status === 'failed' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800') }}">
                        {{ strtoupper($run->status) }}
                    </span>
                </div>

                @if ($run->status === 'succeeded')
                    <div class="mt-6 grid gap-5 lg:grid-cols-3">
                        @foreach ($run->suggestions as $index => $suggestion)
                            <article class="flex flex-col rounded-xl border border-violet-100 bg-violet-50 p-5">
                                <div class="flex flex-wrap gap-2 text-xs font-semibold uppercase tracking-wide text-violet-700">
                                    <span>{{ str_replace('_', ' ', $suggestion['objective']) }}</span>
                                    <span aria-hidden="true">·</span>
                                    <span>{{ str_replace('_', ' ', $suggestion['format']) }}</span>
                                </div>
                                <h3 class="mt-3 text-lg font-semibold text-slate-950">{{ $suggestion['topic'] }}</h3>
                                <dl class="mt-4 flex-1 space-y-4 text-sm text-slate-700">
                                    <div>
                                        <dt class="font-semibold text-slate-900">Enfoque</dt>
                                        <dd class="mt-1">{{ $suggestion['angle'] }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold text-slate-900">Por qué</dt>
                                        <dd class="mt-1">{{ $suggestion['reason'] }}</dd>
                                    </div>
                                </dl>
                                <form action="{{ route('marcas.estrategia.borradores.store', [$brand, $run, $index]) }}" method="POST" class="mt-5">
                                    @csrf
                                    <button class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white">Crear borrador</button>
                                </form>
                            </article>
                        @endforeach
                    </div>
                    <p class="mt-5 text-sm text-slate-600">Crear un borrador prepara un ContextSnapshot con el flujo existente. No genera, aprueba, publica ni programa contenido automáticamente.</p>
                @elseif ($run->status === 'failed')
                    <p class="mt-5 text-sm text-red-800">La ejecución falló sin crear sugerencias utilizables. Podés iniciar una nueva ejecución.</p>
                @else
                    <p class="mt-5 text-sm text-amber-800">La ejecución todavía no produjo sugerencias utilizables.</p>
                @endif
            </section>
        @empty
            <div class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center text-slate-600">
                Todavía no hay sugerencias estratégicas para esta marca.
            </div>
        @endforelse
    </div>

    <div class="mt-8">{{ $runs->links() }}</div>
</x-layouts.app>
