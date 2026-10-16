@extends('layouts.base', ['title' => 'T.Link | Accueil'])

@section('content')
    <div id="tlink-app">
        <main style="max-width:960px;margin:32px auto;padding:24px;font-family:system-ui,sans-serif;line-height:1.5;">
            <h1 style="margin:0 0 8px;color:#0f2e5f;">T.Link</h1>
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
                user: @json($user ? ['id' => $user->id, 'name' => $user->name, 'email' => $user->email] : null),
            },
            feed: @json($feedPayload),
            entities: @json($entities),
            governorates: @json($governorates),
            csrf: @json(csrf_token()),
        };
    </script>
@endsection
