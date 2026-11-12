@extends('layouts.base', ['title' => 'T.Link | Accueil'])

@section('content')
    @php($composer = $composerCapabilities ?? ['can_publish_public' => false, 'allowed_visibilities' => ['authenticated', 'entity', 'department', 'club', 'restricted']])
    @auth
        @include('partials.app-header')
    @endauth
    @if (session('error'))
        <div class="fixed inset-x-0 top-3 z-50 mx-auto w-full max-w-xl rounded-xl bg-rose-600 px-4 py-3 text-sm font-medium text-white shadow-soft" role="alert">
            {{ session('error') }}
        </div>
    @endif
    <div id="tlink-app">
        <main style="max-width:960px;margin:32px auto;padding:24px;font-family:system-ui,sans-serif;line-height:1.5;">
            <h1 style="margin:0 0 8px;color:#d00031;">T.Link</h1>
            <p style="margin:0 0 12px;color:#334155;">Fondation Tunivisions</p>
            <p style="margin:0 0 18px;color:#475569;">Le contenu interactif est en cours de chargement.</p>
            <p style="margin:0;">
                <a href="{{ route('login') }}">Connexion</a>
                @if (Route::has('register'))
                    | <a href="{{ route('register') }}">Inscription</a>
                @endif
            </p>
        </main>
    </div>
    <script>
        window.__TLINK__ = {
            auth: {
                isAuthenticated: @json((bool) $user),
                canAdmin: @json((bool) ($canAdmin ?? false)),
                hasOffice: @json((bool) ($hasOffice ?? false)),
                user: @json($authUser ?? null),
            },
            feed: @json($feedPayload),
            composer: @json($composer),
            partners: @json($partners),
            advert: @json($advert ?? null),
            links: @json($links),
            myBadges: @json($myBadges ?? []),
            menu: @json($menu ?? []),
            storiesEnabled: @json((bool) ($storiesEnabled ?? false)),
            stories: @json($stories ?? []),
            upcoming: @json($upcoming ?? []),
            upcomingEnabled: @json((bool) ($upcomingEnabled ?? false)),
            pendingMemberships: @json($pendingMemberships ?? null),
            governorates: @json($governorates),
            csrf: @json(csrf_token()),
        };
    </script>
@endsection
