<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="{{ $brand['color'] }}">
    <meta name="format-detection" content="telephone=no">
    <link rel="manifest" href="{{ route('pwa.manifest', [], false) }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="{{ mb_substr($brand['name'], 0, 12) }}">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ route('pwa.icon', ['purpose' => 'apple', 'size' => 180, 'c' => ltrim($brand['color'], '#')], false) }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $brand['name'])@hasSection('title') · {{ $brand['name'] }}@endif</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased" style="--brand: {{ $brand['color'] }}">
    <div x-data="{ off: !navigator.onLine }" @online.window="off = false" @offline.window="off = true" x-cloak x-show="off" role="alert"
         class="sticky top-0 z-50 bg-amber-500 px-4 py-2 text-center text-sm font-medium text-white">Keine Internetverbindung. Änderungen werden erst gespeichert, wenn du wieder online bist.</div>
    @yield('body')
</body>
</html>
