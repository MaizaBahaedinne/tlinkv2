@extends('layouts.base', ['title' => 'T.Link | Accueil'])

@section('content')
    <div id="tlink-app"></div>
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
