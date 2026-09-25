@php
    $brand ??= null;
    $socialChannels = old('social_channels', $brand?->social_channels ?? []);
    $restrictions = old('restrictions', $brand?->restrictions ?? []);
@endphp

<div class="space-y-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
    <div>
        <label for="name" class="block text-sm font-semibold text-slate-800">Nombre</label>
        <input id="name" name="name" type="text" value="{{ old('name', $brand?->name) }}" required maxlength="255" class="mt-2 block w-full rounded-lg border-slate-300 text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        @error('name') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="slug" class="block text-sm font-semibold text-slate-800">Slug</label>
        <input id="slug" name="slug" type="text" value="{{ old('slug', $brand?->slug) }}" required maxlength="255" pattern="[a-z0-9]+(-[a-z0-9]+)*" class="mt-2 block w-full rounded-lg border-slate-300 text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" aria-describedby="slug-help">
        <p id="slug-help" class="mt-2 text-sm text-slate-500">Usá minúsculas, números y guiones; por ejemplo, <span class="font-mono">mi-marca</span>.</p>
        @error('slug') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="description" class="block text-sm font-semibold text-slate-800">Descripción</label>
        <textarea id="description" name="description" rows="4" maxlength="5000" class="mt-2 block w-full rounded-lg border-slate-300 text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $brand?->description) }}</textarea>
        @error('description') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="grid gap-6 sm:grid-cols-2">
        <div>
            <label for="tone_of_voice" class="block text-sm font-semibold text-slate-800">Tono de voz</label>
            <input id="tone_of_voice" name="tone_of_voice" type="text" value="{{ old('tone_of_voice', $brand?->tone_of_voice) }}" maxlength="255" class="mt-2 block w-full rounded-lg border-slate-300 text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            @error('tone_of_voice') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="target_audience" class="block text-sm font-semibold text-slate-800">Público objetivo</label>
            <input id="target_audience" name="target_audience" type="text" value="{{ old('target_audience', $brand?->target_audience) }}" maxlength="1000" class="mt-2 block w-full rounded-lg border-slate-300 text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            @error('target_audience') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
    </div>

    <div>
        <label for="social_channels" class="block text-sm font-semibold text-slate-800">Canales sociales</label>
        <textarea id="social_channels" name="social_channels_text" rows="3" class="mt-2 block w-full rounded-lg border-slate-300 text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" aria-describedby="social-channels-help">{{ implode("\n", $socialChannels) }}</textarea>
        <p id="social-channels-help" class="mt-2 text-sm text-slate-500">Ingresá un canal por línea.</p>
        @error('social_channels') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="restrictions" class="block text-sm font-semibold text-slate-800">Restricciones</label>
        <textarea id="restrictions" name="restrictions_text" rows="3" class="mt-2 block w-full rounded-lg border-slate-300 text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" aria-describedby="restrictions-help">{{ implode("\n", $restrictions) }}</textarea>
        <p id="restrictions-help" class="mt-2 text-sm text-slate-500">Ingresá una restricción por línea. No agregues información no confirmada.</p>
        @error('restrictions') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="flex flex-wrap items-center gap-3 pt-2">
        <button type="submit" class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700">{{ $brand ? 'Guardar cambios' : 'Crear marca' }}</button>
        <a href="{{ $brand ? route('marcas.show', $brand) : route('marcas.index') }}" class="rounded-lg px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 hover:text-slate-950">Cancelar</a>
    </div>
</div>
