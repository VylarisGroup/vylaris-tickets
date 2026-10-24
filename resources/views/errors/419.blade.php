<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Sitzung abgelaufen</title>
    <style>
        body { margin: 0; min-height: 100dvh; display: flex; align-items: center; justify-content: center; font-family: system-ui, sans-serif; background: #f8fafc; color: #0f172a; padding: 16px; }
        main { max-width: 24rem; text-align: center; }
        a { display: inline-block; margin-top: 1rem; border-radius: .5rem; background: #1867f8; color: #fff; font-weight: 600; padding: .75rem 1.5rem; text-decoration: none; }
        p { color: #475569; line-height: 1.5; }
    </style>
</head>
<body>
<main>
    <h1>Die Sitzung ist abgelaufen</h1>
    <p>Die Seite war zu lange geöffnet, zum Beispiel weil die App im Hintergrund lag. Deine Eingaben wurden nicht gespeichert. Bitte lade die Seite neu und versuche es erneut.</p>
    <a href="{{ url()->previous() ?: url('/') }}">Seite neu laden</a>
</main>
</body>
</html>
