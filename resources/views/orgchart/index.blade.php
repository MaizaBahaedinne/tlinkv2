<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>Organigramme de la Fondation We Are Carthage · T.Link</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="stylesheet" href="https://fonts.bunny.net/css?family=space-grotesk:500,600,700|manrope:400,500,600,700,800">
    @php($ocv = max(array_map(fn ($f) => @filemtime(public_path('backoffice/'.$f)) ?: 1, ['orgchart.css', 'orgchart.js', 'orgchart-layout.js', 'orgchart-export.js'])))
    <link rel="stylesheet" href="{{ asset('backoffice/orgchart.css') }}?v={{ $ocv }}">
</head>
<body class="oc-body">
<div class="oc" id="oc-app" data-edit-mode="off">

    <header class="oc-top">
        <a class="oc-brand" href="{{ route('home') }}" title="Retour à T.Link">
            <span class="oc-mark">T</span><span class="oc-brand-name">T.Link</span>
        </a>
        <h1 class="oc-title">Organigramme de la Fondation We Are Carthage</h1>
        <div class="oc-top-actions">
            <span class="oc-user" title="Compte connecté">{{ $userName }}</span>
            <span class="oc-role {{ $canEdit ? 'is-admin' : '' }}">{{ $canEdit ? 'Administrateur' : 'Lecture seule' }}</span>
            @if($adminUrl)
                <a class="oc-link" href="{{ $adminUrl }}">Administration</a>
            @endif
        </div>
    </header>

    <div class="oc-toolbar" role="toolbar" aria-label="Actions de l'organigramme">
        <button type="button" class="oc-btn oc-only-mobile" data-act="toggle-side" aria-label="Afficher les branches">Branches</button>

        <div class="oc-group" data-needs="editor">
            <div class="oc-menu">
                <button type="button" class="oc-btn" data-menu="m-add" aria-haspopup="true" aria-expanded="false">Ajouter ▾</button>
                <div class="oc-menu-list" id="m-add" hidden>
                    <button type="button" data-act="add-node">Poste, unité ou département</button>
                    <button type="button" data-act="add-club">Club (régional, université, lycée)</button>
                    <button type="button" data-act="sync">Réaligner les clubs et périmètres</button>
                </div>
            </div>
            <button type="button" class="oc-btn" data-act="toggle-edit" aria-pressed="false" title="Active le déplacement des blocs à la souris">Modifier</button>
            <button type="button" class="oc-btn oc-btn-primary" data-act="save" disabled>Enregistrer<span class="oc-dirty" id="oc-dirty" hidden></span></button>
        </div>

        <div class="oc-group">
            <div class="oc-menu">
                <button type="button" class="oc-btn" data-menu="m-export" aria-haspopup="true" aria-expanded="false">Exporter ▾</button>
                <div class="oc-menu-list" id="m-export" hidden>
                    <button type="button" data-act="export-png">Image PNG</button>
                    <button type="button" data-act="export-svg">Image vectorielle SVG</button>
                    <button type="button" data-act="export-pdf-fit">PDF (une page)</button>
                    <button type="button" data-act="export-pdf-pages">PDF (plusieurs pages A3)</button>
                    <button type="button" data-act="print">Imprimer (multi-pages)</button>
                    <hr>
                    <button type="button" data-act="export-csv">Tableau CSV</button>
                    <button type="button" data-act="export-json" data-needs="editor">Données JSON (sauvegarde)</button>
                </div>
            </div>
            <button type="button" class="oc-btn" data-act="import" data-needs="editor">Importer</button>
        </div>

        <div class="oc-group">
            <button type="button" class="oc-btn" data-act="reset-view" title="Recentre, ajuste le zoom et rétablit le pliage par défaut">Réinitialiser la vue</button>
            <button type="button" class="oc-btn" data-act="expand-all">Tout déplier</button>
            <button type="button" class="oc-btn" data-act="collapse-all">Tout replier</button>
            <button type="button" class="oc-btn" data-act="history" data-needs="editor">Historique</button>
        </div>

        <button type="button" class="oc-btn oc-only-mobile oc-push" data-act="toggle-panel" aria-label="Afficher les détails">Détails</button>
    </div>

    <div class="oc-main">
        <aside class="oc-side" id="oc-side" aria-label="Recherche et branches">
            <div class="oc-side-head">
                <label class="oc-search">
                    <span class="oc-sr">Rechercher</span>
                    <input type="search" id="oc-search" placeholder="Personne, poste, club, gouvernorat…" autocomplete="off" maxlength="80">
                </label>
                <label class="oc-check">
                    <input type="checkbox" id="oc-filter-validate">
                    <span>Mettre en évidence les relations <strong>à valider</strong></span>
                </label>
            </div>
            <div class="oc-side-body">
                <div id="oc-results" class="oc-results" hidden></div>
                <h2 class="oc-side-title">Branches</h2>
                <nav id="oc-branches" class="oc-branches" aria-label="Branches de l'organigramme"></nav>
                <h2 class="oc-side-title">Synthèse</h2>
                <dl id="oc-stats" class="oc-stats"></dl>
            </div>
        </aside>

        <main class="oc-stage" id="oc-stage">
            <svg id="oc-svg" role="img" aria-label="Organigramme de la Fondation We Are Carthage" tabindex="0">
                <defs>
                    <clipPath id="oc-clip-c" clipPathUnits="objectBoundingBox"><circle cx=".5" cy=".5" r=".5"/></clipPath>
                </defs>
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

            <div class="oc-legend" id="oc-legend">
                <button type="button" class="oc-legend-toggle" data-act="toggle-legend" aria-expanded="true">Légende</button>
                <div class="oc-legend-body" id="oc-legend-body"></div>
            </div>

            <div class="oc-hint" id="oc-hint" hidden></div>
            <div class="oc-loading" id="oc-loading">Chargement de l'organigramme…</div>
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

{{-- Bloc / poste --}}
<dialog id="dlg-node" class="oc-dlg">
    <form id="f-node" novalidate>
        <header><h2 id="dn-title">Bloc</h2><button type="button" class="oc-icon-btn" data-close aria-label="Fermer">×</button></header>
        <div class="oc-dlg-body">
            <div class="oc-field"><label for="n-title">Intitulé du poste ou du bloc *</label><input id="n-title" name="title" maxlength="255" required></div>
            <div class="oc-field"><label for="n-subtitle">Sous-titre (bureau, club…)</label><input id="n-subtitle" name="subtitle" maxlength="255"></div>
            <div class="oc-grid2">
                <div class="oc-field"><label for="n-kind">Type</label><select id="n-kind" name="kind"></select></div>
                <div class="oc-field"><label for="n-category">Catégorie (couleur)</label><select id="n-category" name="category"></select></div>
            </div>
            <div class="oc-field" id="n-capacity-wrap"><label for="n-capacity">Titulaires du poste</label><select id="n-capacity" name="capacity"></select><small class="oc-muted">Seuls les postes accueillent des personnes ; un poste à titulaire unique refuse une deuxième personne.</small></div>
            <div class="oc-field" id="n-access-wrap"><label for="n-access">Niveau d'accès (droits de la personne en poste)</label><select id="n-access" name="access_level"></select><small class="oc-muted">Les droits d'administration viennent uniquement des postes. Ils s'appliquent au périmètre du poste (ou hérité du bloc parent), sauf l'administrateur global.</small></div>
            <div class="oc-field"><label for="n-parent">Rattaché à</label><select id="n-parent" name="parent_id"></select></div>
            <div class="oc-grid2">
                <div class="oc-field"><label for="n-relation">Nature du lien</label><select id="n-relation" name="relation_type"></select></div>
                <div class="oc-field"><label for="n-validation">Statut du lien</label><select id="n-validation" name="validation_status"></select></div>
            </div>
            <div class="oc-field"><label for="n-vnote">Note de validation</label><input id="n-vnote" name="validation_note" maxlength="500" placeholder="Pourquoi ce lien est à valider…"></div>
            <div class="oc-grid2">
                <div class="oc-field"><label for="n-layout">Disposition des sous-blocs</label><select id="n-layout" name="children_layout"></select></div>
                <div class="oc-field" id="n-gov-wrap"><label for="n-gov">Gouvernorat</label><select id="n-gov" name="governorate_id"></select></div>
            </div>
            <label class="oc-check"><input type="checkbox" id="n-collapsed" name="is_collapsed"><span>Replié par défaut</span></label>
            <fieldset class="oc-fieldset" id="n-structure">
                <legend>Périmètre</legend>
                <div class="oc-field"><label for="n-scope">Périmètre (fondation, University, High School, Alumni)</label><select id="n-scope" name="scope"></select></div>
                <p class="oc-muted">Les sous-blocs héritent du périmètre. Il détermine les droits des postes, les adhésions, les publications et les événements concernés.</p>
            </fieldset>
            <fieldset class="oc-fieldset" id="n-club-fields" hidden>
                <legend>Informations du club</legend>
                <div class="oc-grid2">
                    <div class="oc-field"><label for="n-status">Statut</label><select id="n-status" name="status"></select></div>
                    <div class="oc-field"><label for="n-founded">Date de fondation</label><input type="date" id="n-founded" name="founded_on"></div>
                </div>
                <div class="oc-grid2">
                    <div class="oc-field"><label for="n-inst-name">Établissement</label><input id="n-inst-name" name="institution_name" maxlength="255"></div>
                    <div class="oc-field"><label for="n-inst-type">Type d’établissement</label><select id="n-inst-type" name="institution_type"></select></div>
                </div>
                <div class="oc-grid2">
                    <div class="oc-field"><label for="n-email">E-mail du club</label><input type="email" id="n-email" name="email" maxlength="255"></div>
                    <div class="oc-field"><label for="n-facebook">Page Facebook</label><input type="url" id="n-facebook" name="facebook_url" maxlength="500" placeholder="https://"></div>
                </div>
                <label class="oc-check"><input type="checkbox" id="n-charter" name="charter_signed"><span>Charte signée</span></label>
            </fieldset>
            <div class="oc-field"><label for="n-desc">Description</label><textarea id="n-desc" name="description" rows="3" maxlength="5000"></textarea></div>
            <p class="oc-form-error" id="n-error" role="alert" hidden></p>
        </div>
        <footer><button type="button" class="oc-btn" data-close>Annuler</button><button type="submit" class="oc-btn oc-btn-primary">Enregistrer</button></footer>
    </form>
</dialog>

{{-- Club --}}
<dialog id="dlg-club" class="oc-dlg">
    <form id="f-club" novalidate>
        <header><h2>Nouveau club</h2><button type="button" class="oc-icon-btn" data-close aria-label="Fermer">×</button></header>
        <div class="oc-dlg-body">
            <div class="oc-field"><label for="c-type">Type de club *</label>
                <select id="c-type" name="type">
                    <option value="regional">Club régional « We Are »</option>
                    <option value="university">University Club</option>
                    <option value="highschool">High School Club</option>
                </select>
            </div>
            <div class="oc-field"><label for="c-name">Nom du club *</label><input id="c-name" name="name" maxlength="120" required placeholder="We Are …"></div>
            <div class="oc-field" id="c-gov-wrap"><label for="c-gov">Gouvernorat *</label><select id="c-gov" name="governorate_id"></select></div>
            <label class="oc-check"><input type="checkbox" id="c-template" name="apply_template" checked><span>Créer l'équipe type (président, VP Marketing / RH / Events / R&amp;D, membres)</span></label>
            <p class="oc-note">Le nombre de clubs University et High School n'est pas limité. L'équipe type est modifiable ensuite poste par poste ; aucun nom n'est pré-rempli.</p>
            <p class="oc-form-error" id="c-error" role="alert" hidden></p>
        </div>
        <footer><button type="button" class="oc-btn" data-close>Annuler</button><button type="submit" class="oc-btn oc-btn-primary">Créer le club</button></footer>
    </form>
</dialog>

{{-- Personne --}}
<dialog id="dlg-person" class="oc-dlg">
    <form id="f-person" novalidate enctype="multipart/form-data">
        <header><h2 id="dp-title">Personne</h2><button type="button" class="oc-icon-btn" data-close aria-label="Fermer">×</button></header>
        <div class="oc-dlg-body">
            <div class="oc-person-photo">
                <span class="oc-avatar-lg" id="p-preview" aria-hidden="true"></span>
                <div class="oc-field"><label for="p-photo">Photo (JPG, PNG, WebP — 2 Mo max)</label><input type="file" id="p-photo" name="photo" accept="image/jpeg,image/png,image/webp"></div>
            </div>
            <label class="oc-check" id="p-remove-wrap" hidden><input type="checkbox" id="p-remove" name="remove_photo"><span>Supprimer la photo actuelle</span></label>
            <p class="oc-note" id="p-account" hidden>Nom, photo, e-mail et téléphone viennent du compte de l’utilisateur. <a id="p-account-link" href="#" hidden>Ouvrir le compte</a></p>
            <div class="oc-field"><label for="p-name">Nom complet *</label><input id="p-name" name="full_name" maxlength="255" required></div>
            <div class="oc-field"><label for="p-job">Fonction</label><input id="p-job" name="job_title" maxlength="255"></div>
            <div class="oc-grid2">
                <div class="oc-field"><label for="p-phone">Téléphone professionnel</label><input id="p-phone" name="phone" maxlength="40" inputmode="tel" autocomplete="off"></div>
                <div class="oc-field"><label for="p-email">E-mail professionnel</label><input id="p-email" name="email" type="email" maxlength="255" autocomplete="off"></div>
            </div>
            <label class="oc-check"><input type="checkbox" id="p-visible" name="contact_visible" checked><span>Coordonnées visibles par tous les membres connectés</span></label>
            <div class="oc-field"><label for="p-notes">Notes internes (visibles des administrateurs uniquement)</label><textarea id="p-notes" name="notes" rows="2" maxlength="2000"></textarea></div>
            <p class="oc-form-error" id="p-error" role="alert" hidden></p>
        </div>
        <footer>
            <button type="button" class="oc-btn oc-btn-danger oc-push-left" id="p-delete" hidden>Supprimer la personne</button>
            <button type="button" class="oc-btn" data-close>Annuler</button>
            <button type="submit" class="oc-btn oc-btn-primary">Enregistrer</button>
        </footer>
    </form>
</dialog>

{{-- Import --}}
<dialog id="dlg-import" class="oc-dlg">
    <form id="f-import" novalidate>
        <header><h2>Importer un organigramme (JSON)</h2><button type="button" class="oc-icon-btn" data-close aria-label="Fermer">×</button></header>
        <div class="oc-dlg-body">
            <p class="oc-note oc-note-warn">L'import <strong>remplace entièrement</strong> l'organigramme actuel (blocs, personnes, affectations). Exportez d'abord une sauvegarde JSON. Les photos ne sont pas incluses dans le fichier.</p>
            <div class="oc-field"><label for="i-file">Fichier JSON exporté depuis cette page</label><input type="file" id="i-file" name="file" accept=".json,application/json"></div>
            <label class="oc-check"><input type="checkbox" id="i-confirm"><span>Je confirme le remplacement complet de l'organigramme</span></label>
            <p class="oc-form-error" id="i-error" role="alert" hidden></p>
        </div>
        <footer><button type="button" class="oc-btn" data-close>Annuler</button><button type="submit" class="oc-btn oc-btn-danger">Importer</button></footer>
    </form>
</dialog>

{{-- Historique --}}
<dialog id="dlg-history" class="oc-dlg oc-dlg-wide">
    <div>
        <header><h2 id="dh-title">Historique des modifications</h2><button type="button" class="oc-icon-btn" data-close aria-label="Fermer">×</button></header>
        <div class="oc-dlg-body"><ol class="oc-history" id="dh-list"></ol></div>
        <footer><button type="button" class="oc-btn" data-close>Fermer</button></footer>
    </div>
</dialog>

{{-- Confirmation --}}
<dialog id="dlg-confirm" class="oc-dlg oc-dlg-small">
    <form method="dialog" id="f-confirm">
        <header><h2 id="cf-title">Confirmer</h2></header>
        <div class="oc-dlg-body">
            <p id="cf-body"></p>
            <label class="oc-check" id="cf-opt-wrap" hidden><input type="checkbox" id="cf-opt"><span id="cf-opt-label"></span></label>
        </div>
        <footer><button type="submit" value="cancel" class="oc-btn" id="cf-cancel">Annuler</button><button type="submit" value="ok" class="oc-btn oc-btn-danger" id="cf-ok">Confirmer</button></footer>
    </form>
</dialog>

<script type="application/json" id="oc-config">{!! json_encode(['base' => rtrim(route('orgchart.index'), '/'), 'canEdit' => $canEdit], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) !!}</script>
<script src="{{ asset('backoffice/orgchart-layout.js') }}?v={{ $ocv }}" defer></script>
<script src="{{ asset('backoffice/orgchart-export.js') }}?v={{ $ocv }}" defer></script>
<script src="{{ asset('backoffice/orgchart.js') }}?v={{ $ocv }}" defer></script>
</body>
</html>
