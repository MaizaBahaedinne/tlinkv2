<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Administration') · T.Link Admin</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="stylesheet" href="https://fonts.bunny.net/css?family=space-grotesk:500,600,700|manrope:400,500,600,700,800">
    @php($adminCssVersion = @filemtime(public_path('backoffice/admin.css')) ?: 1)
    <link rel="stylesheet" href="{{ asset('backoffice/admin.css') }}?v={{ $adminCssVersion }}">
</head>
<body class="adm-body">
<div class="adm">
    <aside class="adm-sidebar" aria-label="Navigation administration">
        <a href="{{ route('admin.dashboard') }}" class="adm-brand">
            <span class="adm-brand-mark">T</span>
            <span>
                <span class="adm-brand-name">T.Link</span><br>
                <span class="adm-brand-sub">Administration</span>
            </span>
        </a>

        <nav class="adm-nav">
            @foreach($adminNav ?? [] as $group)
                <div>
                    <p class="adm-nav-title">{{ $group['title'] }}</p>
                    @foreach($group['items'] as $item)
                        <a href="{{ route($item['route']) }}"
                           class="adm-nav-link {{ request()->routeIs($item['match']) ? 'is-active' : '' }}"
                           @if(request()->routeIs($item['match'])) aria-current="page" @endif>
                            <x-admin.icon :name="$item['icon']" />
                            <span>{{ $item['label'] }}</span>
                            @if($item['badge'])
                                <span class="count">{{ $item['badge'] }}</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            @endforeach
        </nav>

    </aside>
    <div class="adm-overlay" data-nav-toggle></div>

    <div class="adm-main">
        <header class="adm-topbar">
            <button type="button" class="btn btn-secondary btn-icon adm-burger" data-nav-toggle aria-label="Ouvrir le menu">
                <x-admin.icon name="menu" />
            </button>
            <div class="adm-crumbs">
                <span class="hide-mobile">Administration</span>
                <span class="hide-mobile">/</span>
                <strong>@yield('section', 'Tableau de bord')</strong>
            </div>
            <div class="adm-top-right">
                <a href="{{ route('home') }}" class="btn btn-secondary btn-sm">
                    <x-admin.icon name="external" :size="15" />
                    <span class="hide-mobile">Voir le site</span>
                </a>
                @isset($adminUser)
                    <details class="adm-user" data-user-menu>
                        <summary class="adm-user-trigger" aria-label="Menu utilisateur">
                            <x-admin.avatar :name="$adminUser->name" :size="34" />
                            <span class="adm-user-name hide-mobile">{{ $adminUser->name }}</span>
                            <span class="adm-user-caret" aria-hidden="true">▾</span>
                        </summary>
                        <div class="adm-user-menu">
                            <div class="adm-user-head">
                                <strong>{{ $adminUser->name }}</strong>
                                <span>{{ $adminUser->email }}</span>
                            </div>
                            <a href="{{ route('profile.show') }}" class="adm-user-item">
                                <x-admin.icon name="user" :size="15" /> Mon profil
                            </a>
                            <a href="{{ route('home') }}" class="adm-user-item">
                                <x-admin.icon name="external" :size="15" /> Voir le site
                            </a>
                            <form method="post" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="adm-user-item">
                                    <x-admin.icon name="logout" :size="15" /> Déconnexion
                                </button>
                            </form>
                        </div>
                    </details>
                @endisset
            </div>
        </header>

        <main class="adm-content">
            <div class="adm-container">
                @if(session('status'))
                    <div class="alert alert-success" data-autohide role="status">
                        <x-admin.icon name="check" /> <span>{{ session('status') }}</span>
                    </div>
                @endif
                @if(session('error'))
                    <div class="alert alert-error" role="alert">
                        <x-admin.icon name="x" /> <span>{{ session('error') }}</span>
                    </div>
                @endif
                @if($errors->any())
                    <div class="alert alert-error" role="alert">
                        <x-admin.icon name="x" />
                        <span>Le formulaire contient {{ $errors->count() }} erreur{{ $errors->count() > 1 ? 's' : '' }}. Merci de les corriger.</span>
                    </div>
                @endif

                @yield('content')
            </div>
        </main>
    </div>
</div>

<dialog id="confirm-dialog" class="confirm">
    <div class="confirm-body">
        <h3 data-confirm-title>Confirmer cette action</h3>
        <p data-confirm-text></p>
    </div>
    <div class="confirm-actions">
        <button type="button" class="btn btn-secondary" data-confirm-action="no">Annuler</button>
        <button type="button" class="btn btn-primary" data-confirm-action="yes">Confirmer</button>
    </div>
</dialog>

<script src="{{ asset('backoffice/admin.js') }}?v={{ @filemtime(public_path('backoffice/admin.js')) ?: 1 }}" defer></script>
@stack('scripts')
@include('partials.impersonation-banner')
</body>
</html>
