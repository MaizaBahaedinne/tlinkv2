@extends('layouts.base', ['title' => 'T.Link | Accueil'])

@section('content')
    <div id="tlink-app"></div>
    <script>
        window.__TLINK__ = {
            auth: {
                isAuthenticated: @json((bool) $user),
                user: @json($user ? ['id' => $user->id, 'name' => $user->name, 'email' => $user->email] : null),
            },
            feed: @json($posts->getCollection()->map(fn ($post) => [
                'id' => $post->id,
                'content' => $post->content,
                'type' => $post->type,
                'visibility' => $post->visibility,
                'published_at' => optional($post->published_at)?->toIso8601String(),
                'author' => $post->author ? ['id' => $post->author->id, 'name' => $post->author->name] : null,
            ])->values()),
            entities: @json($entities),
            governorates: @json($governorates),
            csrf: @json(csrf_token()),
        };
    </script>
@endsection
