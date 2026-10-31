{{-- Page d'erreur autonome (sans base de données, session ni Vite) : elle doit s'afficher même quand l'application est en panne. --}}
@php
    $page = \App\Support\ErrorReport::build(request(), (int) $code, $exception ?? null);
    $info = $page['info'];
    $subject = 'Erreur '.$page['code'].' — réf. '.$page['reference'];
    $mailto = $page['contact'] ? 'mailto:'.$page['contact'].'?subject='.rawurlencode($subject).'&body='.rawurlencode($page['report']) : null;
    $whatsapp = 'https://wa.me/?text='.rawurlencode($page['report']);
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <title>{{ $page['code'] }} — {{ $info['title'] }} | {{ config('app.name') }}</title>
    <style>
        *{box-sizing:border-box}
        html,body{margin:0}
        body{min-height:100vh;background:#fbf6f7;color:#1f1217;font-family:Manrope,system-ui,-apple-system,"Segoe UI",sans-serif;line-height:1.55;-webkit-font-smoothing:antialiased}
        a{color:#a30027}
        .wrap{max-width:760px;margin:0 auto;padding:28px 18px 56px;display:grid;gap:18px}
        .brand{display:flex;justify-content:center;padding:6px 0 4px}
        .brand img{display:block;height:48px;width:auto}
        .hero{position:relative;overflow:hidden;padding:34px 26px;border-radius:28px;color:#fff;text-align:center;background:linear-gradient(135deg,#6e0019 0%,#a30027 55%,#d00031 100%);box-shadow:0 18px 40px -18px rgba(163,0,39,.55)}
        .hero:before{content:"";position:absolute;width:300px;height:300px;right:-90px;top:-110px;border-radius:50%;background:rgba(255,255,255,.1)}
        .hero:after{content:"";position:absolute;width:260px;height:260px;left:-90px;bottom:-130px;border-radius:50%;background:rgba(245,166,35,.25)}
        .hero>*{position:relative;z-index:1}
        .code{display:inline-flex;align-items:center;gap:10px;padding:5px 14px;border:1px solid rgba(255,255,255,.35);border-radius:999px;background:rgba(255,255,255,.12);font-size:13px;font-weight:800;letter-spacing:.1em}
        .hero h1{margin:14px 0 8px;font-size:clamp(26px,5.5vw,40px);line-height:1.15;font-weight:800}
        .hero p{margin:0 auto;max-width:540px;font-size:clamp(15px,2.4vw,17px);color:rgba(255,255,255,.9)}
        .actions{display:flex;flex-wrap:wrap;justify-content:center;gap:10px;margin-top:22px}
        .btn{display:inline-flex;align-items:center;gap:8px;padding:11px 18px;border:1px solid transparent;border-radius:14px;font:inherit;font-size:14px;font-weight:700;text-decoration:none;cursor:pointer;transition:transform .12s,box-shadow .12s}
        .btn:hover{transform:translateY(-1px)}
        .btn:focus-visible{outline:3px solid #f5a623;outline-offset:2px}
        .btn-light{background:#fff;color:#a30027;box-shadow:0 6px 16px -8px rgba(0,0,0,.4)}
        .btn-ghost{background:rgba(255,255,255,.12);border-color:rgba(255,255,255,.4);color:#fff}
        .btn-brand{background:#d00031;color:#fff}
        .btn-line{background:#fff;border-color:#e3d3d8;color:#1f1217}
        .card{padding:22px 24px;border:1px solid #eadfe2;border-radius:22px;background:#fff;box-shadow:0 8px 24px -18px rgba(31,18,23,.35)}
        .card h2{margin:0 0 10px;font-size:17px;font-weight:800}
        .card ul{margin:0;padding-left:20px;display:grid;gap:6px;color:#45343b;font-size:14.5px}
        .cols{display:grid;gap:18px;grid-template-columns:1fr 1fr}
        .share{border-color:#f0c6d0;background:linear-gradient(180deg,#fff 0%,#fff7f9 100%)}
        .share .lead{margin:0 0 14px;font-size:14.5px;color:#45343b}
        .ref{display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin-bottom:14px}
        .ref code{padding:7px 12px;border-radius:10px;background:#1f1217;color:#fff;font-size:15px;font-weight:700;letter-spacing:.04em}
        .ref small{color:#6b5a61}
        label.f{display:block;margin:0 0 6px;font-size:13px;font-weight:700;color:#45343b}
        textarea,input[type=text]{width:100%;padding:10px 12px;border:1px solid #e3d3d8;border-radius:12px;background:#fff;color:#1f1217;font:inherit;font-size:13.5px}
        textarea:focus,input:focus{outline:3px solid rgba(208,0,49,.25);border-color:#d00031}
        textarea[readonly]{background:#faf5f6;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12.5px;color:#45343b;resize:vertical}
        .note{margin-top:12px}
        .share .actions{justify-content:flex-start;margin-top:14px}
        #copied{display:none;margin-left:2px;font-size:13px;font-weight:700;color:#127a43;align-self:center}
        details{margin-top:14px;font-size:13px;color:#6b5a61}
        details summary{cursor:pointer;font-weight:700}
        details pre{margin:8px 0 0;padding:10px 12px;border-radius:10px;background:#faf5f6;white-space:pre-wrap;word-break:break-word;font-size:12px}
        footer{text-align:center;font-size:12.5px;color:#8a7a80}
        @media (max-width:640px){.cols{grid-template-columns:1fr}.hero{padding:28px 18px}.card{padding:18px}}
        @media (prefers-reduced-motion:reduce){.btn{transition:none}.btn:hover{transform:none}}
    </style>
</head>
<body>
<div class="wrap">
    <div class="brand"><a href="{{ url('/') }}"><img src="{{ asset('tlink-logo.webp') }}" alt="{{ config('app.name') }}" width="127" height="48"></a></div>

    <header class="hero">
        <span class="code">ERREUR {{ $page['code'] }}</span>
        <h1>{{ $info['title'] }}</h1>
        <p>{{ $info['summary'] }}</p>
        <div class="actions">
            @if($info['retry'])
                <button type="button" class="btn btn-light" onclick="location.reload()">Réessayer</button>
            @endif
            @if($info['login'])
                <a class="btn btn-light" href="{{ url('/connexion') }}">Se connecter</a>
            @endif
            <a class="btn {{ $info['retry'] || $info['login'] ? 'btn-ghost' : 'btn-light' }}" href="{{ url('/') }}">Retour à l’accueil</a>
            <button type="button" class="btn btn-ghost" onclick="history.length > 1 ? history.back() : location.assign('{{ url('/') }}')">Page précédente</button>
        </div>
    </header>

    <div class="cols">
        <section class="card">
            <h2>Que s’est-il passé ?</h2>
            <ul>@foreach($info['causes'] as $cause)<li>{{ $cause }}</li>@endforeach</ul>
        </section>
        <section class="card">
            <h2>Que faire ?</h2>
            <ul>@foreach($info['actions'] as $action)<li>{{ $action }}</li>@endforeach</ul>
        </section>
    </div>

    <section class="card share" aria-labelledby="share-title">
        <h2 id="share-title">Transmettre l’incident à l’administrateur</h2>
        <p class="lead">Si le problème persiste, envoyez ce rapport à l’administrateur. Il contient la référence qui lui permet de retrouver précisément l’incident et de le corriger. Aucun mot de passe ni donnée personnelle sensible n’y figure.</p>

        <div class="ref"><code id="ref">{{ $page['reference'] }}</code><small>à conserver, {{ $page['when'] }}</small></div>

        <label class="f" for="report">Rapport</label>
        <textarea id="report" readonly rows="8">{{ $page['report'] }}</textarea>

        <div class="note">
            <label class="f" for="note">Que faisiez-vous quand l’erreur est apparue ? (facultatif)</label>
            <input id="note" type="text" maxlength="300" placeholder="Ex. : j’essayais de valider ma commande">
        </div>

        <div class="actions">
            <button type="button" class="btn btn-brand" id="copy">Copier le rapport</button>
            @if($mailto)<a class="btn btn-line" id="mail" href="{{ $mailto }}">Envoyer par e-mail</a>@endif
            <a class="btn btn-line" id="wa" href="{{ $whatsapp }}" target="_blank" rel="noopener noreferrer">Partager sur WhatsApp</a>
            <span id="copied" role="status">Rapport copié</span>
        </div>

        @if($page['debugMessage'])
            <details open><summary>Détail technique (mode débogage uniquement)</summary><pre>{{ $page['debugMessage'] }}</pre></details>
        @endif
    </section>

    <footer>{{ config('app.name') }} — Fondation Tunivisions</footer>
</div>

<script>
(function () {
    var report = document.getElementById('report');
    var note = document.getElementById('note');
    var base = report.value;
    var contact = @json($page['contact']);
    var subject = @json($subject);

    function full() {
        var extra = note.value.trim();
        return extra ? base + '\n\nCe que je faisais : ' + extra : base;
    }

    function refresh() {
        var text = full();
        report.value = text;
        var wa = document.getElementById('wa');
        if (wa) wa.href = 'https://wa.me/?text=' + encodeURIComponent(text);
        var mail = document.getElementById('mail');
        if (mail && contact) mail.href = 'mailto:' + contact + '?subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(text);
    }

    note.addEventListener('input', refresh);

    document.getElementById('copy').addEventListener('click', function () {
        var done = function () {
            var ok = document.getElementById('copied');
            ok.style.display = 'inline';
            setTimeout(function () { ok.style.display = 'none'; }, 2500);
        };
        var text = full();
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done);
        } else {
            report.focus();
            report.select();
            if (document.execCommand('copy')) done();
        }
    });
})();
</script>
</body>
</html>
