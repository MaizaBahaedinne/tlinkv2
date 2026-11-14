<!DOCTYPE html>
<html lang="fr">
<head>
    @include('partials.google-tag')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>Modèles de clubs · {{ $categories[$category] }} · T.Link</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="stylesheet" href="https://fonts.bunny.net/css?family=space-grotesk:500,600,700|manrope:400,500,600,700,800">
    @php($ocv = max(array_map(fn ($f) => @filemtime(public_path('backoffice/'.$f)) ?: 1, ['orgchart.css', 'orgchart-layout.js', 'clubtemplate.js'])))
    <link rel="stylesheet" href="{{ asset('backoffice/orgchart.css') }}?v={{ $ocv }}">
</head>
<body class="oc-body">
    @include('partials.google-tag-noscript')
<div class="oc" id="oc-app" data-edit-mode="off">

    <header class="oc-top">
        <a class="oc-brand" href="{{ route('home') }}" title="Retour à T.Link">
            <span class="oc-mark">T</span><span class="oc-brand-name">T.Link</span>
        </a>
        <h1 class="oc-title">Modèles de clubs</h1>
        <nav class="oc-tabs" aria-label="Type de club">
            @foreach($categories as $key => $label)
                <a href="{{ route('admin.templates.index', ['category' => $key]) }}" class="oc-tab {{ $key === $category ? 'is-active' : '' }}" @if($key === $category) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
        <div class="oc-top-actions">
            <span class="oc-user" title="Compte connecté">{{ $userName }}</span>
            <span class="oc-role {{ $canEdit ? 'is-admin' : '' }}">{{ $canEdit ? 'Administrateur' : 'Lecture seule' }}</span>
            <a class="oc-link" href="{{ $adminUrl }}">Administration</a>
        </div>
    </header>

    <div class="oc-toolbar" role="toolbar" aria-label="Actions du modèle">
        <button type="button" class="oc-btn oc-only-mobile" data-act="toggle-side" aria-label="Afficher la synthèse">Synthèse</button>

        @if($canEdit)
            <div class="oc-group">
                <button type="button" class="oc-btn oc-btn-primary" data-act="add" title="Ajoute un poste ou un département sous le bloc sélectionné">Ajouter un bloc</button>
                <button type="button" class="oc-btn" data-act="apply" title="Réapplique le modèle à tous les clubs de ce type">Réappliquer aux clubs</button>
            </div>
        @endif

        <div class="oc-group">
            <button type="button" class="oc-btn" data-act="reset-view" title="Recentre et ajuste le zoom">Réinitialiser la vue</button>
        </div>

        <span class="oc-toolbar-note">Un modèle par type de club : tous les clubs {{ $categories[$category] }} le suivent dès l’enregistrement.</span>

        <button type="button" class="oc-btn oc-only-mobile oc-push" data-act="toggle-panel" aria-label="Afficher les détails">Détails</button>
    </div>

    <div class="oc-main">
        <aside class="oc-side" id="oc-side" aria-label="Synthèse du modèle">
            <div class="oc-side-body">
                <h2 class="oc-side-title">Synthèse</h2>
                <dl id="oc-stats" class="oc-stats"></dl>
                <h2 class="oc-side-title">Niveaux d’accès</h2>
                <div id="ct-levels" class="ct-levels"></div>
                <p class="oc-note">Les droits s’exercent sur le périmètre du club. Les droits globaux et d’entité sont réservés aux postes de direction. Un poste ajouté à la main dans un club, hors modèle, n’est jamais modifié.</p>
            </div>
        </aside>

        <main class="oc-stage" id="oc-stage">
            <svg id="oc-svg" role="img" aria-label="Modèle d’équipe des clubs {{ $categories[$category] }}" tabindex="0">
                <g id="oc-viewport">
                    <g id="oc-links"></g>
                    <g id="oc-nodes"></g>
                </g>
            </svg>

            <div class="oc-zoom" aria-label="Zoom">
                <button type="button" data-act="zoom-out" aria-label="Zoom arrière">−</button>
                <button type="button" data-act="zoom-reset" id="oc-zoom-label" aria-label="Zoom 100 %">100 %</button>
                <button type="button" data-act="zoom-in" aria-label="Zoom avant">+</button>
                <button type="button" data-act="fit" aria-label="Ajuster à l'écran" title="Ajuster à l'écran">⤢</button>
            </div>

            <div class="oc-hint" id="oc-hint" @if(! $canEdit) hidden @endif>Maj + glisser un bloc sur un autre pour le rattacher</div>
            <div class="oc-loading" id="oc-loading">Chargement du modèle…</div>
        </main>

        <aside class="oc-panel" id="oc-panel" aria-label="Propriétés du bloc sélectionné">
            <div class="oc-panel-head">
                <h2>Propriétés</h2>
                <button type="button" class="oc-icon-btn oc-only-mobile" data-act="close-panel" aria-label="Fermer">×</button>
            </div>
            <div class="oc-panel-body" id="oc-panel-body"></div>
        </aside>
    </div>

    <div class="oc-toasts" id="oc-toasts" aria-live="polite"></div>
</div>

{{-- Ajout d'un bloc --}}
<dialog id="dlg-add" class="oc-dlg oc-dlg-small">
    <form id="f-add" novalidate>
        <header><h2>Ajouter au modèle</h2><button type="button" class="oc-icon-btn" data-close aria-label="Fermer">×</button></header>
        <div class="oc-dlg-body">
            <p class="oc-note" id="a-where"></p>
            <div class="oc-field"><label for="a-title">Intitulé *</label><input id="a-title" name="title" maxlength="120" required></div>
            <div class="oc-field"><label for="a-kind">Type</label><select id="a-kind" name="kind"></select></div>
            <div class="oc-field" id="a-capacity-wrap"><label for="a-capacity">Titulaires</label><select id="a-capacity" name="capacity"><option value="single">Un seul titulaire</option><option value="multiple">Plusieurs personnes</option></select></div>
            <div class="oc-field" id="a-access-wrap"><label for="a-access">Niveau d’accès</label><select id="a-access" name="access_level"></select></div>
            <p class="oc-form-error" id="a-error" role="alert" hidden></p>
        </div>
        <footer><button type="button" class="oc-btn" data-close>Annuler</button><button type="submit" class="oc-btn oc-btn-primary">Ajouter à tous les clubs</button></footer>
    </form>
</dialog>

{{-- Confirmation --}}
<dialog id="dlg-confirm" class="oc-dlg oc-dlg-small">
    <form method="dialog" id="f-confirm">
        <header><h2 id="cf-title">Confirmer</h2></header>
        <div class="oc-dlg-body"><p id="cf-body"></p></div>
        <footer><button type="submit" value="cancel" class="oc-btn" id="cf-cancel">Annuler</button><button type="submit" value="ok" class="oc-btn oc-btn-danger" id="cf-ok">Confirmer</button></footer>
    </form>
</dialog>

<script type="application/json" id="ct-config">{!! json_encode(['base' => route('admin.templates.index', ['category' => $category]), 'chart' => $payload], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
<script src="{{ asset('backoffice/orgchart-layout.js') }}?v={{ $ocv }}" defer></script>
<script src="{{ asset('backoffice/clubtemplate.js') }}?v={{ $ocv }}" defer></script>
@include('partials.impersonation-banner')
@include('partials.role-simulation-banner')
</body>
</html>
