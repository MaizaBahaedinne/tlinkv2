@extends('admin.layouts.app')

@section('title', 'Charte des réseaux sociaux')
@section('section', 'Marketing et partenariats')

@section('content')
    <x-admin.page-header title="Charte d’usage des réseaux sociaux" subtitle="Texte signé par chaque membre avant de recevoir les accès. La charte commune s’applique à toutes les entités ; un club, un chapitre ou un projet peut avoir la sienne.">
        <x-slot:actions>
            @if($canUpdate)<a href="{{ route('admin.marketing.charters.edit', ['entity' => 'default']) }}" class="btn btn-primary"><x-admin.icon name="edit" :size="16" /> Modifier la charte commune</a>@endif
        </x-slot:actions>
    </x-admin.page-header>

    <article class="card" style="margin-bottom:16px">
        <div class="card-head"><div><h2>Charte commune</h2><p class="sub">{{ $default ? 'Version '.$default->version.' · modifiée le '.$default->updated_at->format('d/m/Y') : 'Texte intégré par défaut (non modifié).' }}</p></div></div>
        <div class="card-body" style="white-space:pre-line">{{ $default?->body ?? \App\Support\MarketingCharters::DEFAULT_BODY }}</div>
    </article>

    <article class="card card-flush" style="margin-bottom:16px">
        <div class="card-head"><div><h2>Chartes propres à une entité</h2><p class="sub">Elles remplacent la charte commune pour l’entité concernée.</p></div></div>
        @if($custom->isEmpty())
            <x-admin.empty icon="file-text" title="Aucune charte propre" text="Toutes les entités utilisent la charte commune." />
        @else
            <div class="table-wrap">
                <table class="tbl">
                    <thead><tr><th>Entité</th><th>Version</th><th></th></tr></thead>
                    <tbody>
                        @foreach($custom as $charter)
                            <tr>
                                <td>{{ \App\Support\MarketingEntities::find($charter->entity)['label'] ?? $charter->entity }}</td>
                                <td>{{ $charter->version }}</td>
                                <td class="cell-actions">
                                    @if($canUpdate)
                                        <a href="{{ route('admin.marketing.charters.edit', ['entity' => $charter->entity]) }}" class="btn btn-secondary btn-sm">Modifier</a>
                                        <form method="post" action="{{ route('admin.marketing.charters.destroy') }}" style="display:inline" onsubmit="return confirm('Cette entité reviendra à la charte commune. Continuer ?')">
                                            @csrf<input type="hidden" name="entity" value="{{ $charter->entity }}">
                                            <button type="submit" class="btn btn-secondary btn-sm">Revenir à la commune</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </article>

    @if($canUpdate)
        <form method="get" action="{{ route('admin.marketing.charters.edit') }}" class="card">
            <div class="card-body" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
                <x-admin.entity-select name="entity" label="Créer une charte propre à" :groups="$entityOptions" :required="true" />
                <button type="submit" class="btn btn-secondary">Rédiger</button>
            </div>
        </form>
    @endif
@endsection
