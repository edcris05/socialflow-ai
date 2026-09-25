<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'SocialFlow AI' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
            <a href="{{ route('home') }}" class="text-lg font-semibold tracking-tight text-slate-950">SocialFlow AI</a>
            <nav aria-label="Navegación principal">
                <a href="{{ route('marcas.index') }}" class="text-sm font-medium text-slate-600 transition hover:text-slate-950">Marcas</a>
            </nav>
        </div>
    </header>

    <main class="mx-auto w-full max-w-6xl px-6 py-10">
        @if (session('status'))
            <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">
                {{ session('status') }}
            </div>
        @endif

        {{ $slot }}
    </main>
</body>
</html>
