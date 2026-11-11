@extends('admin.layouts.app')

@section('title', 'Cartes de membre')
@section('section', 'Cartes et pointage')

@section('content')
    <x-admin.page-header title="Cartes de membre" :subtitle="'Une carte par adhésion validée et par mandat'.($mandate ? ' (mandat en cours : '.$mandate->label.')' : '').'. Elles sont émises, renouvelées et expirées automatiquement.'" />

    <section class="stats" style="margin-bottom:22px">
        @foreach($statuses as $code => $label)
            <x-admin.stat :label="$label" :value="$counts[$code] ?? 0" icon="user-check" :tone="$code === 'active' ? 'green' : ($code === 'suspended' ? 'amber' : 'blue')" />
        @endforeach
    </section>

    <article class="card card-flush">
        <div class="toolbar">
            <form method="get" action="{{ route('admin.cards.index') }}">
                <div class="search">
                    <x-admin.icon name="search" :size="16" />
                    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nom, e-mail ou numéro de carte…">
                </div>
                <select name="status" aria-label="Statut">
                    <option value="">Tous les statuts</option>
                    @foreach($statuses as $code => $label)
                        <option value="{{ $code }}" @selected(($filters['status'] ?? '') === $code)>{{ $label }}</option>
                    @endforeach
                </select>
                <button class="btn btn-primary btn-sm" type="submit">Filtrer</button>
                @if(array_filter($filters))
                    <a href="{{ route('admin.cards.index') }}" class="btn btn-ghost btn-sm">Réinitialiser</a>
                @endif
            </form>
        </div>

        @if($cards->count())
            <div class="table-wrap">
                <table class="tbl">
                    <thead><tr><th>Membre</th><th>Carte</th><th class="hide-mobile">Entité</th><th class="hide-mobile">Mandat</th><th>Échéance</th><th>Pointages</th><th>Statut</th><th></th></tr></thead>
                    <tbody>
                        @foreach($cards as $card)
                            <tr>
                                <td><span class="cell-main">{{ $card->user?->name }}</span><div class="cell-sub">{{ $card->user?->email }}</div></td>
                                <td class="nowrap"><code>{{ $card->number }}</code></td>
                                <td class="hide-mobile">{{ $card->membership?->node?->title ?? (\App\Support\OrgScopes::label($card->membership?->scope) ?: 'Fondation') }}</td>
                                <td class="hide-mobile">{{ $card->mandate?->label }}</td>
                                <td class="nowrap text-sm">{{ $card->expires_at?->format('d/m/Y') ?? '—' }}</td>
                                <td>{{ $card->scans_count }}</td>
                                <td>
                                    @if($card->status === 'active')<span class="badge badge-success">Active</span>
                                    @elseif($card->status === 'suspended')<span class="badge badge-warning">Suspendue</span>
                                    @else<span class="badge badge-neutral">Expirée</span>@endif
                                </td>
                                <td class="cell-actions">
                                    @if($card->status === 'suspended')
                                        <form method="post" action="{{ route('admin.cards.reactivate', $card) }}">@csrf @method('PATCH')<button class="btn btn-secondary btn-sm" type="submit">Réactiver</button></form>
                                    @elseif($card->status === 'active')
                                        <form method="post" action="{{ route('admin.cards.suspend', $card) }}" data-confirm="Le membre ne pourra plus pointer avec cette carte tant qu’elle est suspendue." data-confirm-title="Suspendre cette carte ?">@csrf @method('PATCH')<button class="btn btn-danger btn-sm" type="submit">Suspendre</button></form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $cards->links('admin.partials.pagination') }}
        @else
            <x-admin.empty icon="user-check" title="Aucune carte" text="Aucune carte ne correspond à ces critères." />
        @endif
    </article>
@endsection
