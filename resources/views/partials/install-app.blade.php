{{-- Bouton « Installer l'application » de l'en-tête : proposé uniquement sur smartphone quand l'application n'est pas déjà installée
     (voir resources/js/install-app.js). Masqué par défaut, affiché par le script après vérification. --}}
@php
    $installApp = collect(config('mobile.apps'))->firstWhere('section', $section ?? 'app') ?? collect(config('mobile.apps'))->firstWhere('section', 'app');
    $installName = $installApp['name'] ?? 'T.Link';
    $installIcon = asset('app-icons/'.($installApp['icons'] ?? 'link').'/icon-192.png');
@endphp
<button type="button" hidden data-install-app title="Installer {{ $installName }}" aria-label="Installer {{ $installName }}" aria-haspopup="dialog"
        class="inline-flex shrink-0 items-center gap-1.5 rounded-xl bg-tlink-blue px-2.5 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-tlink-blue-700">
    <i class="fa-solid fa-download" aria-hidden="true"></i><span>Installer</span>
</button>

<dialog data-install-dialog aria-labelledby="install-app-title" class="m-auto w-[min(92vw,26rem)] rounded-3xl border-0 p-0 text-slate-800 shadow-soft backdrop:bg-slate-900/50">
    <div class="space-y-4 p-5">
        <div class="flex items-center gap-3">
            <img src="{{ $installIcon }}" alt="" width="48" height="48" class="h-12 w-12 rounded-2xl">
            <div class="min-w-0">
                <h2 id="install-app-title" class="font-display text-lg font-semibold text-slate-900">Installer {{ $installName }}</h2>
                <p class="text-sm text-slate-600">Retrouvez l’application sur l’écran d’accueil de votre téléphone, en plein écran.</p>
            </div>
        </div>

        <ol data-install-steps="ios" hidden class="list-decimal space-y-2 pl-5 text-sm text-slate-700">
            <li>Dans <strong>Safari</strong>, appuyez sur le bouton <strong>Partager</strong> <i class="fa-solid fa-arrow-up-from-bracket mx-0.5" aria-hidden="true"></i>.</li>
            <li>Choisissez <strong>Sur l’écran d’accueil</strong>.</li>
            <li>Validez avec <strong>Ajouter</strong>.</li>
        </ol>
        <ol data-install-steps="android" hidden class="list-decimal space-y-2 pl-5 text-sm text-slate-700">
            <li>Ouvrez le menu <strong>⋮</strong> de votre navigateur.</li>
            <li>Choisissez <strong>Installer l’application</strong> (ou <strong>Ajouter à l’écran d’accueil</strong>).</li>
            <li>Confirmez l’installation.</li>
        </ol>

        <div class="flex flex-wrap items-center justify-between gap-2">
            <button type="button" data-install-later class="text-xs font-medium text-slate-500 hover:text-slate-700 hover:underline">Ne plus afficher</button>
            <button type="button" data-install-close class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-200">Fermer</button>
        </div>
    </div>
</dialog>
@if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
    @vite(['resources/js/install-app.js'])
@endif
