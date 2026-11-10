@extends('admin.layouts.app')

@section('title', 'Modèles d’e-mails')
@section('section', 'Messagerie')

@section('content')
    <x-admin.page-header title="Modèles d’e-mails" subtitle="Personnalisez l’objet, le message, le bouton et la signature des e-mails automatiques envoyés aux membres." />

    @include('admin.mail._tabs')

    <div class="grid" style="display:grid;gap:16px">
        @foreach($catalog as $key => $definition)
            <article class="card">
                <div class="card-body" style="display:flex;gap:16px;align-items:center;justify-content:space-between;flex-wrap:wrap">
                    <div>
                        <strong>{{ $definition['label'] }}</strong>
                        <p class="text-sm text-muted" style="margin:4px 0 0">{{ $definition['description'] }}</p>
                    </div>
                    <div style="display:flex;gap:10px;align-items:center">
                        @if($customized[$key])
                            <span class="badge badge-info">Personnalisé</span>
                        @else
                            <span class="badge badge-neutral">Modèle d’origine</span>
                        @endif
                        <a href="{{ route('admin.mail.templates.edit', $key) }}" class="btn btn-primary btn-sm">Modifier</a>
                    </div>
                </div>
            </article>
        @endforeach
    </div>
@endsection
