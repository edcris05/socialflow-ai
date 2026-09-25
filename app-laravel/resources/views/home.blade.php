<x-layouts.app title="SocialFlow AI">
    <section class="max-w-3xl py-12">
        <p class="text-sm font-semibold uppercase tracking-[0.2em] text-indigo-600">MVP local</p>
        <h1 class="mt-4 text-4xl font-semibold tracking-tight text-slate-950 sm:text-5xl">Información confiable para cada marca.</h1>
        <p class="mt-6 max-w-2xl text-lg leading-8 text-slate-600">SocialFlow AI organiza la información de cada marca como un tenant independiente. Cada cuenta solo puede acceder a las marcas que tiene asignadas.</p>
        <div class="mt-8">
            <a href="{{ auth()->check() ? route('marcas.index') : route('login') }}" class="inline-flex rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700">{{ auth()->check() ? 'Administrar marcas' : 'Ingresar' }}</a>
        </div>
    </section>

    <section class="grid gap-4 border-t border-slate-200 pt-8 sm:grid-cols-3">
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h2 class="font-semibold">Marcas aisladas</h2>
            <p class="mt-2 text-sm leading-6 text-slate-600">Cada ficha representa el tenant inicial de la plataforma.</p>
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h2 class="font-semibold">Información editable</h2>
            <p class="mt-2 text-sm leading-6 text-slate-600">Tono, público, canales y restricciones quedan organizados por marca.</p>
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h2 class="font-semibold">Preparado para crecer</h2>
            <p class="mt-2 text-sm leading-6 text-slate-600">La autenticación y los servicios de IA se incorporarán en fases posteriores.</p>
        </div>
    </section>
</x-layouts.app>
