<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'EveNantes')</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; color: #1f2937; }
        header, main, footer { max-width: 980px; margin: 0 auto; padding: 1rem; }
        header { display: flex; justify-content: space-between; align-items: center; }
        a { color: #2563eb; text-decoration: none; }
        .card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 1rem; margin-bottom: 1rem; }
        .muted { color: #6b7280; }
    </style>
</head>
<body>
<header>
    <h1><a href="{{ route('home') }}">EveNantes</a></h1>
    <nav><a href="{{ route('events.index') }}">Evenements</a></nav>
</header>
<main>
    @yield('content')
</main>
<footer class="muted">Agenda culturel et loisirs de Nantes</footer>
</body>
</html>

