<x-layouts.app title="Editar borrador · SocialFlow AI">
    <div class="max-w-4xl">
        <p class="text-sm font-semibold uppercase tracking-[0.2em] text-indigo-600">{{ $brand->name }}</p>
        <h1 class="mt-2 text-3xl font-semibold">Editar borrador</h1>

        @if (session('status'))
            <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        @if ($latestGenerationRun?->evaluation_status === 'requires_review')
            <section class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-950">
                <h2 class="text-lg font-semibold">Evaluacion del contenido</h2>
                <p class="mt-1 text-xs text-amber-700">Generacion {{ $latestGenerationRun->getKey() }} &middot; {{ $latestGenerationRun->created_at->format('d/m/Y H:i') }}</p>
                <p class="mt-2 font-medium">Requiere revision</p>
                <ul class="mt-3 list-inside list-disc space-y-1">
                    @foreach ($latestGenerationRun->evaluation_violations ?? [] as $violation)
                        <li>{{ $violation['message'] }}</li>
                    @endforeach
                </ul>
                <p class="mt-3 text-amber-800">El contenido se conserva para que puedas revisarlo y editarlo.</p>
            </section>
        @elseif ($latestGenerationRun?->evaluation_status === 'passed')
            <section class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-5 text-sm text-emerald-950">
                <h2 class="text-lg font-semibold">Evaluacion del contenido</h2>
                <p class="mt-1 text-xs text-emerald-700">Generacion {{ $latestGenerationRun->getKey() }} &middot; {{ $latestGenerationRun->created_at->format('d/m/Y H:i') }}</p>
                <p class="mt-2">Sin alertas automaticas detectadas.</p>
                <p class="mt-2 text-emerald-800">Esto no verifica todas las afirmaciones factuales del contenido.</p>
            </section>
        @endif

        @if ($latestGenerationRun)
            <section class="mt-6 rounded-xl border border-sky-200 bg-sky-50 p-5 text-sm text-sky-950">
                <h2 class="text-lg font-semibold">Grounding factual</h2>
                @if ($latestGenerationRun->grounding_status === 'passed')
                    <p class="mt-2 font-medium">Claims declarados: PASSED</p>
                    <p class="mt-2 text-sky-800">Los claims factuales declarados por el generador están respaldados.</p>
                @elseif ($latestGenerationRun->grounding_status === 'requires_review')
                    <p class="mt-2 font-medium">Claims declarados: REQUIRES REVIEW</p>
                @else
                    <p class="mt-2 font-medium">Claims declarados: NO EVALUADO</p>
                @endif

                @if (($latestGenerationRun->grounding_results ?? []) === [] && $latestGenerationRun->grounding_status === 'passed')
                    <p class="mt-3">No se declararon claims factuales.</p>
                @else
                    <ul class="mt-3 space-y-3">
                        @foreach ($latestGenerationRun->grounding_results ?? [] as $result)
                            <li>
                                <span class="font-medium">“{{ $result['claim']['text'] }}”</span>
                                <span class="ml-1 font-semibold">{{ $result['status'] }}</span>
                                @if ($result['evidence_excerpt'])
                                    <div class="mt-1 text-sky-800">Evidencia: {{ $result['evidence_excerpt'] }}</div>
                                @else
                                    <div class="mt-1 text-sky-800">No se encontró evidencia histórica.</div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                <p class="mt-3 text-sky-800">Esta versión sólo evalúa los claims factuales declarados por el generador. No verifica exhaustivamente todas las afirmaciones del contenido.</p>
                <p class="mt-2 font-semibold text-sky-950">Requiere aprobación humana antes de publicar.</p>
            </section>
        @endif

        @if ($draft->contextSnapshot)
            <section class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-5">
                <h2 class="text-lg font-semibold">Contexto capturado</h2>
                <dl class="mt-4 space-y-4 text-sm text-slate-700">
                    <div>
                        <dt class="font-semibold text-slate-900">Consulta</dt>
                        <dd class="mt-1 whitespace-pre-line">{{ $draft->contextSnapshot->query }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-900">Fuentes</dt>
                        <dd class="mt-1 list-inside list-disc">
                            @forelse ($draft->contextSnapshot->sources as $source)
                                <div>{{ $source }}</div>
                            @empty
                                <span>No se registraron fuentes.</span>
                            @endforelse
                        </dd>
                    </div>
                    @if ($draft->contextSnapshot->warnings || $draft->contextSnapshot->missing_information)
                        <div>
                            <dt class="font-semibold text-slate-900">Warnings y missing information</dt>
                            <dd class="mt-1 space-y-1">
                                @foreach ($draft->contextSnapshot->warnings as $warning)
                                    <div>⚠️ {{ $warning }}</div>
                                @endforeach
                                @foreach ($draft->contextSnapshot->missing_information as $missing)
                                    <div>ℹ️ {{ $missing }}</div>
                                @endforeach
                            </dd>
                        </div>
                    @endif
                </dl>
            </section>
        @endif

        @if ($draft->contextSnapshot)
            <a href="{{ route('marcas.borradores.prompt.preview', [$brand, $draft]) }}" class="mt-6 inline-block rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white">Ver prompt</a>
        @endif
        @if ($draft->contextSnapshot)
            <form action="{{ route('marcas.borradores.generar', [$brand, $draft]) }}" method="POST" class="mt-6">
                @csrf
                <input type="hidden" name="generation_token" value="{{ $generationToken ?? '' }}">
                @if ($draft->generationRuns()->where('status', 'succeeded')->exists()) <input type="hidden" name="regenerate" value="1"> @endif
                <button class="rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white">{{ $draft->generationRuns()->where('status', 'succeeded')->exists() ? 'Generar nuevamente' : 'Generar contenido' }}</button>
            </form>
        @endif
        <form action="{{ route('marcas.borradores.update', [$brand, $draft]) }}" method="POST" class="mt-8">
            @method('PUT')
            @include('drafts.form', ['draft' => $draft])
        </form>
    </div>
</x-layouts.app>
