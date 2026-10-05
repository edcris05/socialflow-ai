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
    @if (session('error'))
        <div class="mt-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>
    @endif
    @error('confirm_publish')
        <div class="mt-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-800">Debes confirmar explícitamente la publicación real.</div>
    @enderror

    <div class="mt-8 space-y-4">
        @forelse ($publications as $publication)
            @php
                $media = $publication->draft->currentPublicationMedia;
                $connection = $brand->metaConnection;
                $attempt = $publication->publicationAttempts->first();
                $publishingEnabled = config('services.meta.publishing_enabled', false);
                $mediaHost = filled($media?->public_url) ? parse_url($media->public_url, PHP_URL_HOST) : null;
                $preflightLabel = match (true) {
                    $media === null => 'SIN MEDIA',
                    $media->preflight_status === \App\Models\PublicationMedia::PREFLIGHT_PASSED && ! $media->hasFreshPreflight() => 'REQUIERE NUEVA COMPROBACIÓN (VENCIDA)',
                    default => $media->preflightStatusLabel(),
                };
                $attemptLabel = match ($attempt?->status) {
                    \App\Models\PublicationAttempt::STATUS_PUBLISHING => 'PUBLISHING',
                    \App\Models\PublicationAttempt::STATUS_PUBLISHED => 'PUBLISHED',
                    \App\Models\PublicationAttempt::STATUS_FAILED => 'FAILED',
                    \App\Models\PublicationAttempt::STATUS_OUTCOME_UNKNOWN => 'OUTCOME UNKNOWN',
                    default => 'SIN INTENTOS',
                };
                $reviewScheduleStatus = match (true) {
                    $publication->status === \App\Models\ScheduledPublication::STATUS_CANCELLED => 'CANCELADO',
                    $publication->isReady() => 'READY',
                    default => 'PROGRAMADO',
                };
                $publishBlockers = [];

                if (! $publishingEnabled) {
                    $publishBlockers[] = 'La publicación real está deshabilitada por configuración.';
                }
                if (! $publication->isReady()) {
                    $publishBlockers[] = 'La programación todavía no está READY.';
                }
                if ($publication->draft->status !== \App\Models\Draft::STATUS_APPROVED) {
                    $publishBlockers[] = 'El borrador no está aprobado.';
                }
                if ($media === null) {
                    $publishBlockers[] = 'No existe una imagen vigente.';
                } elseif ($media->status !== \App\Models\PublicationMedia::STATUS_APPROVED) {
                    $publishBlockers[] = 'La imagen vigente no está aprobada.';
                } elseif (! $media->hasValidImage()) {
                    $publishBlockers[] = 'La imagen vigente no tiene un formato admitido.';
                } elseif (! $media->hasValidPublicUrl()) {
                    $publishBlockers[] = 'La imagen vigente no tiene una URL pública válida.';
                } elseif (! $media->hasFreshPreflight()) {
                    $publishBlockers[] = 'El preflight debe comprobarse nuevamente.';
                }
                if ($connection === null) {
                    $publishBlockers[] = 'La marca no tiene una conexión de Meta.';
                } elseif ($connection->status !== \App\Models\MetaConnection::STATUS_VERIFIED) {
                    $publishBlockers[] = 'La conexión de Meta no está verificada.';
                } elseif (! $connection->isConfigured()) {
                    $publishBlockers[] = 'La conexión de Meta no tiene credenciales configuradas.';
                } elseif (blank($connection->instagram_account_id)) {
                    $publishBlockers[] = 'La conexión no tiene Instagram Account ID.';
                }
                if ($attempt !== null) {
                    $publishBlockers[] = 'Ya existe un intento de publicación y no se iniciará otro.';
                }

                $canOfferPublish = $publishBlockers === [];
            @endphp
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

                <div class="mt-6 border-t border-slate-200 pt-5">
                    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-950">Revisión antes de publicar</h3>
                            <p class="mt-1 text-sm text-slate-600">Revisa este resumen completo. El backend volverá a validar todas las condiciones y repetirá el preflight antes de contactar a Meta.</p>
                        </div>
                        <span class="w-fit rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ $attemptLabel }}</span>
                    </div>

                    <div class="mt-5 grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,0.8fr)]">
                        <section class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <h4 class="text-sm font-semibold uppercase tracking-wide text-slate-700">Contenido exacto</h4>
                            <p class="mt-3 whitespace-pre-wrap break-words text-sm leading-6 text-slate-950">{{ $publication->draft->content }}</p>
                        </section>

                        <section class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <h4 class="text-sm font-semibold uppercase tracking-wide text-slate-700">Imagen vigente</h4>
                            @if ($media)
                                <img
                                    src="{{ route('marcas.borradores.media.show', [$brand, $publication->draft, $media]) }}"
                                    alt="Vista previa de la imagen que se publicará"
                                    class="mt-3 max-h-80 w-full rounded-lg border border-slate-200 bg-white object-contain"
                                >
                                <dl class="mt-3 space-y-2 text-sm text-slate-700">
                                    <div><dt class="inline font-semibold">Archivo:</dt> <dd class="inline">{{ $media->original_filename }}</dd></div>
                                    <div><dt class="inline font-semibold">Media:</dt> <dd class="inline">{{ $media->statusLabel() }}</dd></div>
                                    <div><dt class="inline font-semibold">Host público:</dt> <dd class="inline break-all">{{ $mediaHost ?: 'NO CONFIGURADO' }}</dd></div>
                                    <div><dt class="inline font-semibold">Preflight:</dt> <dd class="inline">{{ $preflightLabel }}</dd></div>
                                    @if ($media->preflight_checked_at)
                                        <div><dt class="inline font-semibold">Última comprobación:</dt> <dd class="inline">{{ $media->preflight_checked_at->format('d/m/Y H:i:s') }}</dd></div>
                                    @endif
                                </dl>
                            @else
                                <p class="mt-3 text-sm font-semibold text-red-800">SIN IMAGEN VIGENTE</p>
                            @endif
                        </section>
                    </div>

                    <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <section class="rounded-lg border border-slate-200 p-4">
                            <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cuenta Instagram</h4>
                            <p class="mt-2 break-all text-sm font-semibold text-slate-900">{{ $connection?->instagram_account_id ?: 'NO CONFIGURADA' }}</p>
                            <p class="mt-1 text-sm text-slate-600">Meta: {{ $connection?->statusLabel() ?? 'NO CONFIGURADO' }}</p>
                        </section>
                        <section class="rounded-lg border border-slate-200 p-4">
                            <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Programación</h4>
                            <p class="mt-2 text-sm font-semibold text-slate-900">{{ $publication->scheduled_for->format('d/m/Y H:i') }}</p>
                            <p class="mt-1 text-sm text-slate-600">Estado: {{ $reviewScheduleStatus }}</p>
                        </section>
                        <section class="rounded-lg border border-slate-200 p-4">
                            <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Borrador</h4>
                            <p class="mt-2 text-sm font-semibold text-slate-900">{{ $publication->draft->statusLabel() }}</p>
                            <p class="mt-1 text-sm text-slate-600">El caption mostrado arriba se envía completo.</p>
                        </section>
                        <section class="rounded-lg border border-slate-200 p-4">
                            <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Intento de publicación</h4>
                            <p class="mt-2 text-sm font-semibold text-slate-900">{{ $attemptLabel }}</p>
                            @if ($attempt?->status === \App\Models\PublicationAttempt::STATUS_PUBLISHED)
                                <p class="mt-1 text-sm text-emerald-700">Este contenido ya fue publicado.</p>
                            @elseif ($attempt?->status === \App\Models\PublicationAttempt::STATUS_OUTCOME_UNKNOWN)
                                <p class="mt-1 text-sm font-semibold text-red-800">El resultado es incierto. No reintentar.</p>
                            @elseif ($attempt)
                                <p class="mt-1 text-sm text-slate-600">No se permite iniciar otro intento.</p>
                            @else
                                <p class="mt-1 text-sm text-slate-600">Todavía no se contactó a Meta.</p>
                            @endif
                        </section>
                    </div>

                    @if ($publishBlockers !== [])
                        <div class="mt-5 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                            <p class="font-semibold">La publicación no está habilitada por estos motivos:</p>
                            <ul class="mt-2 list-disc space-y-1 pl-5">
                                @foreach ($publishBlockers as $blocker)
                                    <li>{{ $blocker }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if ($canOfferPublish)
                        <form action="{{ route('marcas.programacion.publicar.store', [$brand, $publication]) }}" method="POST" class="mt-5 rounded-lg border border-fuchsia-200 bg-fuchsia-50 p-4">
                            @csrf
                            <label class="flex items-start gap-3 text-sm font-semibold text-slate-900">
                                <input type="checkbox" name="confirm_publish" value="true" required class="mt-0.5 rounded border-slate-300">
                                <span>Confirmo que revisé el texto, la imagen y la cuenta de destino, y quiero publicar este contenido realmente en Instagram.</span>
                            </label>
                            <button class="mt-4 rounded-lg bg-fuchsia-700 px-4 py-2.5 text-sm font-semibold text-white" onclick="return confirm('¿Confirmas la publicación real en Instagram?')">Publicar ahora en Instagram</button>
                        </form>
                    @elseif ($attempt === null)
                        <div class="mt-5 rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <label class="flex items-start gap-3 text-sm text-slate-500">
                                <input type="checkbox" disabled class="mt-0.5 rounded border-slate-300">
                                <span>La confirmación se habilitará cuando todas las condiciones sean seguras.</span>
                            </label>
                            <button type="button" disabled class="mt-4 cursor-not-allowed rounded-lg bg-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-600">Publicar ahora en Instagram</button>
                        </div>
                    @endif
                </div>
            </article>
        @empty
            <div class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center text-slate-600">Todavía no hay elementos programados.</div>
        @endforelse
    </div>

    <div class="mt-8">{{ $publications->links() }}</div>
</x-layouts.app>
