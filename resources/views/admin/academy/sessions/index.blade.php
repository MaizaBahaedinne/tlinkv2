@extends('admin.layouts.app')

@section('title', 'Calendrier Academy')
@section('section', 'Tunivisions Academy')

@section('content')
    <x-admin.page-header title="Calendrier des sessions" subtitle="Sessions en direct de Tunivisions Academy. Elles s’affichent dans le calendrier des membres, à côté des événements de la Fondation.">
        <x-slot:actions>
            <button type="button" class="btn btn-primary" data-modal-open="session-create"><x-admin.icon name="plus" :size="16" /> Ajouter une session</button>
        </x-slot:actions>
    </x-admin.page-header>

    <dialog id="session-create" class="modal" aria-labelledby="session-create-title" @if(old('_form') === 'ns' && $errors->any()) data-modal-autoopen @endif>
        <div class="modal-head">
            <h2 id="session-create-title">Ajouter une session</h2>
            <button type="button" class="btn btn-ghost btn-sm" data-modal-close aria-label="Fermer">✕</button>
        </div>
        <form method="post" action="{{ route('admin.academy.sessions.store') }}" class="modal-form">
            @csrf
            <div class="modal-body grid" style="gap:12px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
                @include('admin.academy.sessions._form', ['session' => null, 'prefix' => 'ns'])
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
                <button type="submit" class="btn btn-primary"><x-admin.icon name="plus" :size="16" /> Ajouter</button>
            </div>
        </form>
    </dialog>

    <article class="card card-flush">
        <div class="toolbar">
            <form method="get" action="{{ route('admin.academy.sessions.index') }}">
                <label class="check"><input type="checkbox" name="passees" value="1" @checked($past) onchange="this.form.submit()"> Sessions passées</label>
            </form>
        </div>

        @if($sessions->count())
            <div class="list">
                @foreach($sessions as $session)
                    <div class="list-item" style="align-items:flex-start;flex-wrap:wrap">
                        <div class="grow" style="min-width:220px">
                            <strong style="display:inline">{{ $session->title }}</strong>
                            @unless($session->is_published)<x-admin.badge status="draft" />@endunless
                            <div class="text-sm text-muted">
                                {{ $session->starts_at->translatedFormat('l d F Y à H:i') }}
                                @if($session->location) · {{ $session->location }} @endif
                                · {{ $session->course?->title ?? 'Ouverte à tous' }}
                            </div>
                        </div>
                        <form method="post" action="{{ route('admin.academy.sessions.destroy', $session) }}" onsubmit="return confirm('Supprimer cette session ?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm"><x-admin.icon name="trash" :size="15" /> Supprimer</button>
                        </form>
                        <details style="flex-basis:100%" @if(old('_form') === 's'.$session->id && $errors->any()) open @endif>
                            <summary class="btn btn-secondary btn-sm" style="cursor:pointer">Modifier</summary>
                            <form method="post" action="{{ route('admin.academy.sessions.update', $session) }}" class="grid" style="gap:12px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));margin-top:10px">
                                @csrf
                                @method('PUT')
                                @include('admin.academy.sessions._form', ['session' => $session, 'prefix' => 's'.$session->id])
                                <div style="grid-column:1/-1;display:flex;justify-content:flex-end"><button type="submit" class="btn btn-primary btn-sm">Enregistrer</button></div>
                            </form>
                        </details>
                    </div>
                @endforeach
            </div>
            {{ $sessions->links('admin.partials.pagination') }}
        @else
            <x-admin.empty icon="calendar" :title="$past ? 'Aucune session passée' : 'Aucune session à venir'" text="Planifiez une session en direct : les membres la verront dans leur calendrier Academy." />
        @endif
    </article>
@endsection
