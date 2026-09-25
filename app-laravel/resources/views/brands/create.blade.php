<x-layouts.app title="Crear marca · SocialFlow AI">
    <div class="max-w-3xl">
        <p class="text-sm font-semibold uppercase tracking-[0.2em] text-indigo-600">Nueva marca</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight">Crear marca</h1>
        <p class="mt-2 text-slate-600">Completá la información confiable disponible. Podrás ampliarla más adelante.</p>

        <form action="{{ route('marcas.store') }}" method="POST" class="mt-8">
            @csrf
            @include('brands.partials.form')
        </form>
    </div>
</x-layouts.app>
