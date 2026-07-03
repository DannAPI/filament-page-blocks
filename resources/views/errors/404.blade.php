<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Page not found — {{ config('app.name', 'Laravel') }}</title>
    <style>
        :root { color-scheme: light dark; font-family: ui-sans-serif, system-ui, sans-serif; }
        body { min-height: 100vh; margin: 0; display: grid; place-items: center; background: #f8fafc; color: #0f172a; }
        main { width: min(34rem, calc(100% - 3rem)); text-align: center; }
        p { color: #64748b; line-height: 1.6; }
        a { color: inherit; font-weight: 600; }
        @media (prefers-color-scheme: dark) { body { background: #0f172a; color: #f8fafc; } p { color: #94a3b8; } }
    </style>
</head>
<body>
    <main>
        <p>404</p>
        <h1>Page not found</h1>
        <p>The requested page does not exist or is not published.</p>
        <a href="{{ url('/') }}">Return to the homepage</a>
    </main>
</body>
</html>
