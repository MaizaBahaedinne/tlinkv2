<!DOCTYPE html>
<html lang="fr">
<head>
    @include('partials.google-tag')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <title>{{ $title }} | Fondation Tunivisions</title>
    <style>
        *{box-sizing:border-box}
        html,body{margin:0;min-height:100%}
        body{min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:28px;padding:32px 20px;text-align:center;color:#fff;font-family:Manrope,system-ui,-apple-system,"Segoe UI",sans-serif;background:linear-gradient(135deg,#6e0019 0%,#a30027 55%,#d00031 100%);position:relative;overflow-x:hidden}
        body:before,body:after{content:"";position:absolute;border-radius:50%;pointer-events:none}
        body:before{width:420px;height:420px;left:-120px;top:-120px;background:rgba(255,255,255,.08)}
        body:after{width:520px;height:520px;right:-160px;bottom:-180px;background:rgba(245,166,35,.22);filter:blur(30px)}
        main{position:relative;z-index:1;display:grid;gap:28px;justify-items:center;max-width:760px;width:100%}
        .brand img{display:block;height:64px;width:auto;max-width:78vw}
        .mark{display:grid;place-items:center;width:46px;height:46px;border-radius:14px;background:#fff;color:#a30027;font-weight:800;font-size:22px;box-shadow:0 8px 24px rgba(0,0,0,.25)}
        .tag{display:inline-block;padding:5px 14px;border:1px solid rgba(255,255,255,.3);border-radius:999px;background:rgba(255,255,255,.1);font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase}
        h1{margin:0;font-size:clamp(30px,6vw,52px);line-height:1.1;font-weight:800}
        p.msg{margin:0;max-width:560px;font-size:clamp(15px,2.4vw,18px);line-height:1.6;color:rgba(255,255,255,.86);white-space:pre-line}
        .count{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:clamp(8px,2vw,18px);width:100%;max-width:560px}
        .cell{padding:clamp(12px,3vw,22px) 6px;border:1px solid rgba(255,255,255,.22);border-radius:20px;background:rgba(255,255,255,.12);backdrop-filter:blur(6px)}
        .cell strong{display:block;font-size:clamp(28px,7vw,52px);font-weight:800;line-height:1;font-variant-numeric:tabular-nums}
        .cell span{display:block;margin-top:8px;font-size:clamp(10px,2vw,13px);font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:rgba(255,255,255,.75)}
        .date{font-size:14px;color:rgba(255,255,255,.8)}
        footer{position:relative;z-index:1;display:flex;flex-wrap:wrap;justify-content:center;gap:6px 16px;align-items:center;font-size:12px;color:rgba(255,255,255,.65)}
        footer a,footer button{color:rgba(255,255,255,.75);background:none;border:0;padding:0;font:inherit;text-decoration:underline;cursor:pointer}
        footer form{margin:0}
        .news{width:100%;max-width:560px;padding:clamp(16px,4vw,24px);border:1px solid rgba(255,255,255,.22);border-radius:20px;background:rgba(255,255,255,.12);backdrop-filter:blur(6px);text-align:left}
        .news h2{margin:0 0 4px;font-size:18px;font-weight:800}
        .news p{margin:0;font-size:13px;line-height:1.5;color:rgba(255,255,255,.8)}
        .news form{display:flex;flex-wrap:wrap;gap:10px;margin-top:14px}
        .news input[type=email]{flex:1 1 220px;min-width:0;padding:13px 16px;border:0;border-radius:12px;font:inherit;font-size:16px;color:#1e293b}
        .news button{flex:0 0 auto;padding:13px 22px;border:0;border-radius:12px;background:#fff;color:#a30027;font:inherit;font-weight:800;cursor:pointer}
        .news .ok{padding:12px 14px;border-radius:12px;background:rgba(255,255,255,.18);font-weight:700;font-size:14px;color:#fff}
        .news .err{margin-top:10px;font-weight:700;color:#fff}
        .hp,.vh{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}
        @media (max-width:420px){.news button{flex:1 1 100%}}
    </style>
</head>
<body>
    <main>
        <div class="brand">
            <picture>
                <source srcset="{{ asset('logoFondation-white.webp') }}" type="image/webp">
                <img src="{{ asset('logoFondation-white.png') }}" alt="Fondation Tunivisions" height="64">
            </picture>
        </div>
        <span class="tag">Fondation Tunivisions</span>
        <h1>{{ $title }}</h1>
        <p class="msg">{{ $message }}</p>

        @if ($launchAt)
            <div class="count" id="count" role="timer" aria-live="off" data-target="{{ $launchAt->getTimestampMs() }}" data-now="{{ $serverNow }}">
                <div class="cell"><strong data-u="d">--</strong><span>Jours</span></div>
                <div class="cell"><strong data-u="h">--</strong><span>Heures</span></div>
                <div class="cell"><strong data-u="m">--</strong><span>Minutes</span></div>
                <div class="cell"><strong data-u="s">--</strong><span>Secondes</span></div>
            </div>
            <p class="date">Ouverture le {{ $launchAt->locale('fr')->translatedFormat('l j F Y \à H\hi') }}</p>
        @endif

        @php($newsletterError = session('errors')?->first('email'))
        <section class="news" id="newsletter" aria-labelledby="news-title">
            <h2 id="news-title">Restez informé(e)</h2>
            <p>Recevez la newsletter de la Fondation Tunivisions et soyez prévenu(e) dès l’ouverture du site.</p>
            @if (session('newsletter_sent'))
                <p class="ok" role="status" style="margin-top:14px">Merci ! Votre adresse est enregistrée.</p>
            @else
                <form method="post" action="{{ route('site.newsletter.store', ['locale' => config('site.default_locale')]) }}" novalidate>
                    @csrf
                    <div class="hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                    <label for="launch-email" class="vh">Adresse e-mail</label>
                    <input id="launch-email" type="email" name="email" value="{{ old('email') }}" required maxlength="190" autocomplete="email" inputmode="email" placeholder="Votre adresse e-mail">
                    <button type="submit">Je m’abonne</button>
                </form>
                @if ($newsletterError)
                    <p class="err" role="alert">{{ $newsletterError }}</p>
                @endif
                <p style="margin-top:10px;font-size:12px">Pas de spam : vous pouvez vous désinscrire à tout moment.</p>
            @endif
        </section>
    </main>

    <footer>
        @auth
            <span>{{ auth()->user()->name }}</span>
            <form method="post" action="{{ route('logout') }}">@csrf<button type="submit">Se déconnecter</button></form>
        @else
            <a href="{{ route('login') }}">Accès administrateur</a>
        @endauth
        <span>Propulsé par Persomy.com</span>
    </footer>

    @if ($launchAt)
        <script>
            (function () {
                var box = document.getElementById('count');
                // L'heure du serveur sert de référence : l'horloge de l'appareil du visiteur peut être décalée.
                var offset = Number(box.dataset.now) - Date.now();
                var target = Number(box.dataset.target);
                var cells = {};
                box.querySelectorAll('[data-u]').forEach(function (el) { cells[el.dataset.u] = el; });
                var pad = function (n) { return String(n).padStart(2, '0'); };

                function tick() {
                    var left = Math.max(0, target - (Date.now() + offset));
                    var s = Math.floor(left / 1000);
                    cells.d.textContent = pad(Math.floor(s / 86400));
                    cells.h.textContent = pad(Math.floor(s % 86400 / 3600));
                    cells.m.textContent = pad(Math.floor(s % 3600 / 60));
                    cells.s.textContent = pad(s % 60);
                    if (left <= 0) { clearInterval(timer); setTimeout(function () { location.reload(); }, 1500); }
                }

                var timer = setInterval(tick, 1000);
                tick();
            })();
        </script>
    @endif
</body>
</html>
