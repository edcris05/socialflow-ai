<x-layouts.app title="Editar borrador · SocialFlow AI">
    <div class="max-w-4xl">
        <p class="text-sm font-semibold uppercase tracking-[0.2em] text-indigo-600">{{ $brand->name }}</p>
        <h1 class="mt-2 text-3xl font-semibold">Editar borrador</h1>

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
        <form action="{{ route('marcas.borradores.update', [$brand, $draft]) }}" method="POST" class="mt-8">
            @method('PUT')
            @include('drafts.form', ['draft' => $draft])
        </form>
    </div>
</x-layouts.app>