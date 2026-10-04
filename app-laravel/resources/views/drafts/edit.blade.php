<x-layouts.app title="Editar borrador · SocialFlow AI">
    <div class="max-w-4xl">
        <p class="text-sm font-semibold uppercase tracking-[0.2em] text-indigo-600">{{ $brand->name }}</p>
        <h1 class="mt-2 text-3xl font-semibold">Editar borrador</h1>

        <section class="mt-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-semibold">Estado</h2>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-semibold text-slate-800">{{ $draft->statusLabel() }}</span>
            </div>
            @if ($draft->status === 'approved')
                <p class="mt-3 font-semibold text-emerald-800">Aprobado para publicación.</p>
                <p class="mt-1 text-sm text-slate-700">Aprobado por {{ $draft->approvedBy?->name ?? 'Usuario eliminado' }} el {{ $draft->approved_at?->format('d/m/Y H:i') }}.</p>
                <p class="mt-2 text-sm text-slate-600">La aprobación no publicó ni programó contenido en ninguna plataforma.</p>
                @if ($scheduledPublication)
                    <p class="mt-3 text-sm font-semibold text-emerald-800">Programado para {{ $scheduledPublication->scheduled_for->format('d/m/Y H:i') }} · {{ $scheduledPublication->statusLabel() }}</p>
                @endif
            @elseif ($draft->status === 'rejected')
                <p class="mt-3 font-semibold text-red-800">Este borrador fue rechazado.</p>
                <p class="mt-1 text-sm text-slate-700">Rechazado por {{ $draft->rejectedBy?->name ?? 'Usuario eliminado' }} el {{ $draft->rejected_at?->format('d/m/Y H:i') }}.</p>
                @if ($draft->rejection_reason)
                    <p class="mt-2 text-sm text-slate-700">Motivo: {{ $draft->rejection_reason }}</p>
                @endif
            @else
                <p class="mt-3 text-sm text-slate-700">Podés editarlo, conservarlo como borrador, aprobarlo o rechazarlo.</p>
            @endif
        </section>

        @if (session('status'))
            <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif

        @if ($draft->status === 'approved')
            @include('drafts._publication-media', ['publicationMedia' => $draft->currentPublicationMedia])

            <section class="mt-8 rounded-xl border border-emerald-200 bg-emerald-50 p-5">
                <h2 class="text-lg font-semibold text-emerald-950">Programación</h2>
                @if ($scheduledPublication)
                    <p class="mt-2 text-sm text-emerald-900">{{ $scheduledPublication->statusLabel() }} para {{ $scheduledPublication->scheduled_for->format('d/m/Y H:i') }}.</p>
                    <a href="{{ route('marcas.programacion.index', $brand) }}" class="mt-4 inline-block rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white">Ver programación</a>
                @else
                    <form action="{{ route('marcas.borradores.programar', [$brand, $draft]) }}" method="POST" class="mt-4 flex flex-wrap items-end gap-3">
                        @csrf
                        <div>
                            <label for="scheduled_for" class="block text-sm font-semibold text-emerald-950">Fecha y hora</label>
                            <p class="mt-1 text-sm text-emerald-900">La fecha y hora deben ser posteriores al momento actual.</p>
                            @error('scheduled_for')
                                <p class="mt-1 text-sm font-semibold text-red-700">{{ $message }}</p>
                            @enderror
                            <input id="scheduled_for" name="scheduled_for" type="datetime-local" min="{{ now()->format('Y-m-d\\TH:i') }}" required class="mt-2 rounded-lg border-emerald-300 bg-white">
                        </div>
                        <button class="rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white">Programar</button>
                    </form>
                @endif
            </section>
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
                                @if (($result['claim']['text_matches_content'] ?? true) === false)
                                    <div class="mt-1 text-amber-800">El claim fue declarado por el generador pero su fragmento textual no pudo vincularse exactamente al contenido.</div>
                                @endif
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

        @if ($draft->manually_edited_at && $latestGenerationRun)
            <section class="mt-6 rounded-xl border border-violet-200 bg-violet-50 p-5 text-sm text-violet-950">
                <h2 class="font-semibold">Contenido editado manualmente después de la generación.</h2>
                <p class="mt-2 text-violet-800">Los resultados automáticos de evaluación y grounding corresponden a la versión generada original, no necesariamente al contenido actual del borrador.</p>
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
        @if ($draft->status === 'draft' && $draft->contextSnapshot)
            <form action="{{ route('marcas.borradores.generar', [$brand, $draft]) }}" method="POST" class="mt-6">
                @csrf
                <input type="hidden" name="generation_token" value="{{ $generationToken ?? '' }}">
                @if ($latestGenerationRun) <input type="hidden" name="regenerate" value="1"> @endif
                <button class="rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white">{{ $latestGenerationRun ? 'Generar nuevamente' : 'Generar contenido' }}</button>
            </form>
        @endif

        @if ($draft->status === 'draft')
            <form action="{{ route('marcas.borradores.update', [$brand, $draft]) }}" method="POST" class="mt-8">
                @method('PUT')
                @include('drafts.form', ['draft' => $draft])
            </form>

            <section class="mt-8 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold">Decisión humana</h2>
                @if ($latestGenerationRun?->evaluation_status === 'requires_review' || $latestGenerationRun?->grounding_status === 'requires_review')
                    <div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm font-medium text-amber-950">
                        Hay alertas automáticas que requieren revisión. Podés aprobar bajo tu responsabilidad después de revisar y editar el contenido.
                    </div>
                @endif
                <p class="mt-4 text-sm text-slate-600">Aprobar marca el borrador como listo para una futura publicación, pero no ejecuta ninguna acción externa.</p>
                <div class="mt-5 grid gap-5 md:grid-cols-2">
                    <form action="{{ route('marcas.borradores.approve', [$brand, $draft]) }}" method="POST">
                        @csrf
                        @method('PATCH')
                        <button class="rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white">Aprobar</button>
                    </form>
                    <form action="{{ route('marcas.borradores.reject', [$brand, $draft]) }}" method="POST" class="space-y-3">
                        @csrf
                        @method('PATCH')
                        <div>
                            <label for="rejection_reason" class="block text-sm font-semibold">Motivo del rechazo (opcional)</label>
                            <textarea id="rejection_reason" name="rejection_reason" rows="3" maxlength="1000" class="mt-2 block w-full rounded-lg border-slate-300">{{ old('rejection_reason') }}</textarea>
                        </div>
                        <button class="rounded-lg bg-red-700 px-4 py-2.5 text-sm font-semibold text-white">Rechazar</button>
                    </form>
                </div>
            </section>
        @else
            <section class="mt-8 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold">Contenido actual</h2>
                <div class="mt-4 whitespace-pre-line text-sm text-slate-800">{{ $draft->content }}</div>
            </section>
        @endif

        @if ($draft->status === 'rejected')
            <form action="{{ route('marcas.borradores.reopen', [$brand, $draft]) }}" method="POST" class="mt-6">
                @csrf
                @method('PATCH')
                <button class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white">Volver a borrador</button>
            </form>
        @endif
    </div>
</x-layouts.app>
