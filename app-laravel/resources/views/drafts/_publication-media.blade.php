<section class="mt-8 rounded-xl border border-sky-200 bg-sky-50 p-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-sky-950">Imagen para publicación</h2>
            <p class="mt-1 text-sm text-sky-900">La aprobación de la imagen es independiente de la aprobación del borrador.</p>
        </div>
        <span class="rounded-full bg-white px-3 py-1 text-sm font-semibold text-sky-950">
            {{ $publicationMedia?->statusLabel() ?? 'SIN IMAGEN' }}
        </span>
    </div>

    @if ($publicationMedia)
        <div class="mt-5 grid gap-5 md:grid-cols-[minmax(0,16rem)_1fr]">
            <img
                src="{{ route('marcas.borradores.media.show', [$brand, $draft, $publicationMedia]) }}"
                alt="Vista previa de la imagen para publicación"
                class="max-h-72 w-full rounded-lg border border-sky-200 bg-white object-contain"
            >
            <div class="space-y-2 text-sm text-sky-950">
                <p><span class="font-semibold">Archivo:</span> {{ $publicationMedia->original_filename }}</p>
                <p><span class="font-semibold">Formato:</span> {{ $publicationMedia->mime_type }}</p>
                <p><span class="font-semibold">Dimensiones:</span> {{ $publicationMedia->width ?? '?' }} × {{ $publicationMedia->height ?? '?' }} px</p>
                <p><span class="font-semibold">Tamaño:</span> {{ number_format($publicationMedia->size_bytes / 1024, 0, ',', '.') }} KB</p>
                <p class="break-all"><span class="font-semibold">URL pública:</span> {{ $publicationMedia->public_url ?: 'NO CONFIGURADA' }}</p>
                @if ($publicationMedia->status === \App\Models\PublicationMedia::STATUS_APPROVED)
                    <p class="font-semibold text-emerald-800">Aprobada por {{ $publicationMedia->approvedBy?->name ?? 'Usuario eliminado' }} el {{ $publicationMedia->approved_at?->format('d/m/Y H:i') }}.</p>
                @elseif ($publicationMedia->status === \App\Models\PublicationMedia::STATUS_REJECTED)
                    <p class="font-semibold text-red-800">Rechazada por {{ $publicationMedia->rejectedBy?->name ?? 'Usuario eliminado' }} el {{ $publicationMedia->rejected_at?->format('d/m/Y H:i') }}.</p>
                    @if ($publicationMedia->rejection_reason)
                        <p class="text-red-800">Motivo: {{ $publicationMedia->rejection_reason }}</p>
                    @endif
                @endif
            </div>
        </div>

        <form action="{{ route('marcas.borradores.media.public-url.update', [$brand, $draft, $publicationMedia]) }}" method="POST" class="mt-5">
            @csrf
            @method('PATCH')
            <label for="public_url" class="block text-sm font-semibold text-sky-950">URL pública para Meta</label>
            <p class="mt-1 text-sm text-sky-900">Debe ser una URL HTTP(S) externa. La ruta privada local sólo sirve para esta vista previa.</p>
            @error('public_url')
                <p class="mt-1 text-sm font-semibold text-red-700">{{ $message }}</p>
            @enderror
            <div class="mt-2 flex flex-wrap gap-3">
                <input id="public_url" name="public_url" type="url" maxlength="2048" value="{{ old('public_url', $publicationMedia->public_url) }}" class="min-w-0 flex-1 rounded-lg border-sky-300 bg-white" placeholder="https://cdn.example.com/imagen.jpg">
                <button class="rounded-lg bg-sky-800 px-4 py-2.5 text-sm font-semibold text-white">Guardar URL</button>
            </div>
        </form>

        <div class="mt-5 flex flex-wrap gap-3">
            <form action="{{ route('marcas.borradores.media.approve', [$brand, $draft, $publicationMedia]) }}" method="POST">
                @csrf
                @method('PATCH')
                <button class="rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white">Aprobar imagen</button>
            </form>
            <form action="{{ route('marcas.borradores.media.reject', [$brand, $draft, $publicationMedia]) }}" method="POST" class="flex flex-wrap gap-3">
                @csrf
                @method('PATCH')
                <input name="rejection_reason" maxlength="1000" class="rounded-lg border-red-300 bg-white" placeholder="Motivo opcional">
                <button class="rounded-lg bg-red-700 px-4 py-2.5 text-sm font-semibold text-white">Rechazar imagen</button>
            </form>
        </div>
    @endif

    <form action="{{ route('marcas.borradores.media.store', [$brand, $draft]) }}" method="POST" enctype="multipart/form-data" class="mt-6 border-t border-sky-200 pt-5">
        @csrf
        <label for="image" class="block text-sm font-semibold text-sky-950">{{ $publicationMedia ? 'Reemplazar imagen' : 'Cargar imagen' }}</label>
        <p class="mt-1 text-sm text-sky-900">Sólo JPEG, máximo 8 MB. Un reemplazo siempre queda sin aprobar y conserva el archivo anterior para auditoría.</p>
        @error('image')
            <p class="mt-1 text-sm font-semibold text-red-700">{{ $message }}</p>
        @enderror
        <div class="mt-3 grid gap-3 md:grid-cols-2">
            <input id="image" name="image" type="file" accept="image/jpeg,.jpg,.jpeg" required class="block w-full rounded-lg border border-sky-300 bg-white p-2 text-sm">
            <input name="public_url" type="url" maxlength="2048" value="{{ old('public_url') }}" class="block w-full rounded-lg border-sky-300 bg-white" placeholder="URL pública externa (opcional)">
        </div>
        <button class="mt-3 rounded-lg bg-sky-800 px-4 py-2.5 text-sm font-semibold text-white">{{ $publicationMedia ? 'Cargar reemplazo' : 'Cargar imagen' }}</button>
    </form>
</section>
