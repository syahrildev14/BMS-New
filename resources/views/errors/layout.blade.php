{{-- Fallback statis: dipakai bila halaman error Inertia gagal dirender (mis. database
     mati) atau saat maintenance (php artisan down). Jangan pakai DB, session, atau Vite di sini. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') - {{ config('app.name', 'BMS') }}</title>
    <link rel="icon" type="image/png" href="/logo-icon.png">
    <style>
        :root { --bg:#f9fafb; --fg:#111827; --muted:#6b7280; --brand:#2563eb; --border:#d1d5db; }
        @media (prefers-color-scheme: dark) { :root { --bg:#0a0a0a; --fg:#f5f5f5; --muted:#a3a3a3; --brand:#60a5fa; --border:#404040; } }
        * { box-sizing: border-box; }
        body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px;
               background:var(--bg); color:var(--fg); font-family:Poppins, ui-sans-serif, system-ui, sans-serif; }
        main { max-width:28rem; text-align:center; }
        img { width:40px; height:40px; }
        .code { font-size:3.75rem; font-weight:700; color:var(--brand); margin:16px 0 0; line-height:1; }
        h1 { font-size:1.25rem; font-weight:600; margin:12px 0 8px; }
        p { color:var(--muted); font-size:.875rem; line-height:1.5; margin:0; }
        .actions { margin-top:24px; display:flex; gap:8px; justify-content:center; flex-wrap:wrap; }
        a, button { font:inherit; font-size:.875rem; padding:8px 16px; border-radius:6px; cursor:pointer; text-decoration:none; }
        .primary { background:var(--brand); color:#fff; border:1px solid var(--brand); }
        .outline { background:transparent; color:var(--fg); border:1px solid var(--border); }
    </style>
</head>
<body>
    <main>
        <img src="/logo-icon.png" alt="">
        <p class="code">@yield('code')</p>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        <div class="actions">
            @yield('action')
            <button type="button" class="outline" onclick="history.back()">Kembali</button>
        </div>
    </main>
</body>
</html>
