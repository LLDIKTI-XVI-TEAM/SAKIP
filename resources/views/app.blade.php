<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-page">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia>{{ app(\App\Services\PengaturanService::class)->get('aplikasi.nama', config('app.name', 'SAKIP LLDIKTI XVI')) }}</title>
    <meta name="app-name" content="{{ app(\App\Services\PengaturanService::class)->get('aplikasi.nama', config('app.name', 'SAKIP LLDIKTI XVI')) }}">
    <link rel="icon" type="image/png" sizes="150x150" href="/img/dikti16-favicon-blue-150x150.png">

    <!-- Google Fonts Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Scripts & Styles -->
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
<body class="h-full bg-page font-sans text-ink antialiased">
    @inertia
</body>
</html>
