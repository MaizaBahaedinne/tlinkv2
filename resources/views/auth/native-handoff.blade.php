<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    @include('partials.favicons', ['defaultIconSet' => 'link'])
    <title>Retour à l'application | T.Link</title>
    <style>
        body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#fbf6f7;color:#1f1217;font-family:system-ui,-apple-system,"Segoe UI",sans-serif;text-align:center;padding:24px}
        main{max-width:360px}
        a.button{display:inline-block;margin-top:20px;padding:14px 28px;border-radius:12px;background:#d00031;color:#fff;font-weight:600;text-decoration:none}
        p{color:#5b4a50;line-height:1.5}
    </style>
</head>
<body>
    <main>
        <h1>Connexion réussie</h1>
        <p>Retour vers l'application…</p>
        <a class="button" href="{{ $link }}">Ouvrir l'application</a>
    </main>
    <script>
        window.setTimeout(function () { window.location.href = @json($link); }, 300);
    </script>
</body>
</html>
