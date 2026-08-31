<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title inertia>{{ config('app.name', 'A3 ERP') }}</title>

    {{--
        [A3-UI-V2] Root Inertia — ISOLÉ de layouts/erp.blade.php. Charge
        UNIQUEMENT react.jsx (CSS inclus par l'entrypoint) — jamais app.js,
        donc jamais Turbo ni Alpine. Voir REACT-01A pour la preuve d'isolation.
    --}}
    @viteReactRefresh
    @vite('resources/js/react.jsx')
    @inertiaHead
</head>
<body class="antialiased">
    @inertia
</body>
</html>
