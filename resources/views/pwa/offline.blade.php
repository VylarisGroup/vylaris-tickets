<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="{{ $brand['color'] }}">
    <title>Offline · {{ $brand['name'] }}</title>
    <style>
        body { margin: 0; min-height: 100dvh; display: flex; align-items: center; justify-content: center; font-family: system-ui, sans-serif; background: #f8fafc; color: #0f172a; padding: env(safe-area-inset-top) 16px env(safe-area-inset-bottom); }
        main { max-width: 22rem; text-align: center; }
        .dot { width: 3.5rem; height: 3.5rem; border-radius: 1rem; background: {{ $brand['color'] }}; margin: 0 auto 1.25rem; }
        button { margin-top: 1rem; border: 0; border-radius: .5rem; background: {{ $brand['color'] }}; color: #fff; font: 600 1rem system-ui; padding: .75rem 1.5rem; }
        p { color: #475569; line-height: 1.5; }
    </style>
</head>
<body>
<main>
    <div class="dot"></div>
    <h1>Keine Verbindung</h1>
    <p>Diese Seite braucht eine Internetverbindung. Kasse und Einlass prüfen Bestände und Tickets immer live, damit nichts doppelt vergeben oder eingelöst wird.</p>
    <p>Bereits geöffnete Bestellungen mit Tickets bleiben auch ohne Empfang abrufbar.</p>
    <button type="button" onclick="location.reload()">Erneut versuchen</button>
</main>
</body>
</html>
