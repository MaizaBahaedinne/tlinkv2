@extends('admin.layouts.app')

@section('title', 'Newsletter')
@section('section', 'Newsletter')

@section('content')
    <x-admin.page-header title="Newsletter" subtitle="Personnes inscrites depuis la page « À propos » du site public. L'envoi des courriers se fait avec votre outil d'e-mailing : exportez la liste en CSV.">
        <x-slot:actions>
            <a href="{{ route('admin.newsletter.export') }}" class="btn btn-secondary"><x-admin.icon name="file-text" :size="16" /> Exporter les abonnés actifs (CSV)</a>
        </x-slot:actions>
    </x-admin.page-header>

    <article class="card card-flush">
        <div class="tabs">
            <a href="{{ route('admin.newsletter.index', ['status' => 'active']) }}" class="tab {{ $status === 'active' ? 'is-active' : '' }}">Actifs <span class="n">{{ $counts['active'] }}</span></a>
            <a href="{{ route('admin.newsletter.index', ['status' => 'unsubscribed']) }}" class="tab {{ $status === 'unsubscribed' ? 'is-active' : '' }}">Désinscrits <span class="n">{{ $counts['unsubscribed'] }}</span></a>
            <a href="{{ route('admin.newsletter.index', ['status' => 'all']) }}" class="tab {{ $status === 'all' ? 'is-active' : '' }}">Tous <span class="n">{{ array_sum($counts) }}</span></a>
        </div>

        <form method="get" action="{{ route('admin.newsletter.index') }}" class="card-body" style="display:flex;gap:10px;flex-wrap:wrap">
            <input type="hidden" name="status" value="{{ $status }}">
            <input type="search" name="q" value="{{ $term }}" maxlength="100" placeholder="Rechercher une adresse e-mail" aria-label="Rechercher une adresse e-mail" style="min-width:260px">
            <button type="submit" class="btn btn-secondary">Rechercher</button>
        </form>

        @if($subscribers->count())
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                        <tr><th>E-mail</th><th>Langue</th><th>Inscrit le</th><th>Statut</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach($subscribers as $subscriber)
                            <tr>
                                <td><a href="mailto:{{ $subscriber->email }}">{{ $subscriber->email }}</a></td>
                                <td>{{ strtoupper($subscriber->locale) }}</td>
                                <td>{{ $subscriber->subscribed_at?->format('d/m/Y H:i') }}</td>
                                <td>
                                    @if($subscriber->unsubscribed_at)
                                        <span class="badge badge-neutral">Désinscrit le {{ $subscriber->unsubscribed_at->format('d/m/Y') }}</span>
                                    @else
                                        <span class="badge badge-success">Actif</span>
                                    @endif
                                </td>
                                <td style="text-align:end">
                                    <x-admin.delete-button :action="route('admin.newsletter.destroy', $subscriber)" message="L'adresse sera supprimée définitivement." title="Supprimer l'abonné ?" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $subscribers->links('admin.partials.pagination') }}
        @else
            <x-admin.empty icon="user" title="Aucun abonné" text="Aucune adresse dans cette catégorie." />
        @endif
    </article>
@endsection
