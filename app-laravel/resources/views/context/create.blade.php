<x-layouts.app title="Preparar contenido · {{ $brand->name }}">
    <div class="max-w-4xl">
        <p class="text-sm font-semibold uppercase tracking-[0.2em] text-indigo-600">Context Retrieval v1</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight">Preparar contenido</h1>
        <p class="mt-2 text-slate-600">Buscá contexto verificado de {{ $brand->name }} sin generar contenido todavía.</p>

        <form action="{{ route('marcas.contexto.preview', $brand) }}" method="POST" class="mt-8 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf
            <label for="query" class="block text-sm font-semibold text-slate-800">¿Qué querés comunicar?</label>
            <textarea id="query" name="query" rows="4" required minlength="3" maxlength="2000" class="mt-2 block w-full rounded-lg border-slate-300">{{ old('query') }}</textarea>
            @error('query') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
            <button type="submit" class="mt-4 rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white">Buscar contexto</button>
        </form>

        @if ($package)
            <section class="mt-8">
                <h2 class="text-xl font-semibold">Conocimiento relevante</h2>
                <div class="mt-4 space-y-3">
                    @forelse ($package->relevantKnowledge as $entry)
                        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h3 class="font-semibold">{{ $entry->title }}</h3>
                                <span class="text-sm text-indigo-700">{{ $entry->category }} · {{ $entry->status }}</span>
                            </div>
                            <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-700">{{ str_replace('\\n', "\n", $entry->content) }}</p>
                        </article>
                    @empty
                        <p class="rounded-xl border border-dashed border-slate-300 bg-white p-6 text-slate-600">No se encontró conocimiento relacionado con esta consulta.</p>
                    @endforelse
                </div>
            </section>

            <section class="mt-8">
                <h2 class="text-xl font-semibold">Contexto de marca</h2>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    @foreach ($package->brandContext as $entry)
                        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><h3 class="font-semibold">{{ $entry->title }}</h3><p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $entry->content }}</p></article>
                    @endforeach
                </div>
            </section>

            @if ($package->pendingKnowledge->isNotEmpty() || $package->warnings !== [] || $package->missingInformation !== [])
                <section class="mt-8 rounded-xl border border-amber-200 bg-amber-50 p-6">
                    <h2 class="text-xl font-semibold text-amber-950">Requiere confirmación</h2>
                    <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-amber-900">
                        @foreach ($package->warnings as $warning)<li>{{ $warning }}</li>@endforeach
                        @foreach ($package->missingInformation as $missing)<li>{{ $missing }}</li>@endforeach
                        @foreach ($package->pendingKnowledge as $entry)<li>Pendiente de verificar: {{ $entry->title }}.</li>@endforeach
                    </ul>
                </section>
            @endif

            <section class="mt-8 grid gap-6 sm:grid-cols-2">
                <div>
                    <h2 class="text-lg font-semibold">Políticas</h2>
                    <ul class="mt-3 space-y-2 text-sm text-slate-700">@forelse ($package->policies as $entry)<li>{{ $entry->title }}</li>@empty<li>No se recuperaron políticas específicas.</li>@endforelse</ul>
                </div>
                <div>
                    <h2 class="text-lg font-semibold">Restricciones</h2>
                    <ul class="mt-3 space-y-2 text-sm text-slate-700">@forelse ($package->restrictions as $entry)<li>{{ $entry->title }}</li>@empty<li>No se recuperaron restricciones específicas.</li>@endforelse</ul>
                </div>
            </section>

            <section class="mt-8">
                <h2 class="text-lg font-semibold">Fuentes utilizadas</h2>
                <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-slate-700">@foreach ($package->sources as $source)<li>{{ $source }}</li>@endforeach</ul>
            </section>

            @if (app()->environment('local') && $package->matches->isNotEmpty())
                <details class="mt-8 rounded-xl border border-slate-200 bg-slate-50 p-5 text-sm">
                    <summary class="cursor-pointer font-semibold">Depuración del ranking</summary>
                    <div class="mt-3 space-y-2">@foreach ($package->matches as $match)<div><strong>{{ $match->entry->title }}</strong> · score {{ number_format($match->score, 2) }} · términos: {{ implode(', ', $match->matchedTerms) }}</div>@endforeach</div>
                </details>
            @endif
        @endif
    </div>
</x-layouts.app>