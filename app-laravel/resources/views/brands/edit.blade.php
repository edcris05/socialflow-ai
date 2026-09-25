<x-layouts.app title="Editar {{ $brand->name }} · SocialFlow AI">
    <div class="max-w-3xl">
        <p class="text-sm font-semibold uppercase tracking-[0.2em] text-indigo-600">Marca</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight">Editar {{ $brand->name }}</h1>
        <p class="mt-2 text-slate-600">Actualizá solo información confirmada de la marca.</p>

        <form action="{{ route('marcas.update', $brand) }}" method="POST" class="mt-8">
            @csrf
            @method('PUT')
            @include('brands.partials.form', ['brand' => $brand])
        </form>
    </div>
</x-layouts.app>
