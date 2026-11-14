@extends('admin.layouts.app')

@section('title', 'Pistes de sponsoring')
@section('section', 'Marketing et partenariats')

@section('content')
    <x-admin.page-header title="Pistes de sponsoring" subtitle="Les démarches de sponsoring et de partenariat de votre club auprès des organisations du carnet : de l’idée à l’accord, avec la prochaine action à mener.">
        <x-slot:actions>
            <a href="{{ route('admin.sponsors.index') }}" class="btn btn-secondary"><x-admin.icon name="building" :size="16" /> Carnet d’adresses</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="stats" style="margin-bottom:16px">
        <x-admin.stat label="Pistes en cours" :value="$counts->except(\App\Models\SponsorLead::CLOSED)->sum()" icon="flag" />
        <x-admin.stat label="Montant espéré en cours" :value="number_format($openAmount, 0, ',', ' ').' TND'" icon="activity" />
        <x-admin.stat label="Obtenues" :value="($counts['won'] ?? 0).' · '.number_format($wonAmount, 0, ',', ' ').' TND'" icon="award" />
    </div>

    <article class="card card-flush" style="margin-bottom:16px">
        <form method="get" class="card-body" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
            <div class="field">
                <label for="status">Étape</label>
                <select id="status" name="status">
                    <option value="">Toutes</option>
                    @foreach(\App\Models\SponsorLead::STATUSES as $key => $label)<option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }} ({{ $counts[$key] ?? 0 }})</option>@endforeach
                </select>
            </div>
            <div class="field">
                <label for="club">Club</label>
                <select id="club" name="club">
                    <option value="">Tous</option>
                    @foreach($clubOptions as $id => $title)<option value="{{ $id }}" @selected($filters['club'] === $id)>{{ $title }}</option>@endforeach
                </select>
            </div>
            <label class="check"><input type="checkbox" name="open" value="1" @checked($filters['open'])> En cours seulement</label>
            <button class="btn btn-secondary" type="submit"><x-admin.icon name="search" :size="16" /> Filtrer</button>
        </form>

        @if($leads->count())
            <div class="table-wrap">
                <table class="tbl">
                    <thead><tr><th>Piste</th><th class="hide-mobile">Club</th><th>Étape</th><th class="hide-mobile">Montant</th><th>Prochaine action</th></tr></thead>
                    <tbody>
                        @foreach($leads as $lead)
                            <tr>
                                <td>
                                    <span class="cell-main">{{ $lead->title }}</span>
                                    <div class="cell-sub"><a href="{{ route('admin.sponsors.show', $lead->sponsor_id) }}">{{ $lead->sponsor?->name }}</a> · {{ \App\Models\SponsorLead::KINDS[$lead->kind] ?? $lead->kind }}</div>
                                </td>
                                <td class="hide-mobile">{{ $lead->club?->title ?? 'Fondation' }}</td>
                                <td><span class="badge badge-{{ $lead->status === 'won' ? 'success' : ($lead->status === 'lost' ? 'danger' : 'info') }}">{{ \App\Models\SponsorLead::STATUSES[$lead->status] }}</span></td>
                                <td class="hide-mobile">{{ $lead->amount ? number_format((float) $lead->amount, 0, ',', ' ').' TND' : '—' }}</td>
                                <td>
                                    @if($lead->isClosed())<span class="text-muted">—</span>
                                    @elseif($lead->next_action || $lead->next_action_at)
                                        <span @class(['text-danger' => $lead->isLate()])>{{ $lead->next_action_at?->format('d/m/Y') }}</span>
                                        <div class="cell-sub">{{ $lead->next_action }}</div>
                                    @else<span class="text-muted">À définir</span>@endif
                                </td>
                            </tr>
                            @if($canUpdate)
                                <tr>
                                    <td colspan="5" style="padding:0 16px 10px">
                                        <details>
                                            <summary class="text-sm" style="cursor:pointer">Modifier</summary>
                                            <form method="post" action="{{ route('admin.sponsor-leads.update', $lead) }}" style="margin-top:10px;display:grid;gap:10px">
                                                @csrf @method('PUT')
                                                @include('admin.sponsors._lead-fields', ['lead' => $lead, 'sponsorOptions' => $sponsorOptions, 'preset' => null])
                                                <div style="display:flex;justify-content:flex-end"><button class="btn btn-primary btn-sm" type="submit">Enregistrer</button></div>
                                            </form>
                                            <x-admin.delete-button :action="route('admin.sponsor-leads.destroy', $lead)" message="Cette piste sera supprimée." title="Supprimer cette piste ?" />
                                        </details>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-body">{{ $leads->links() }}</div>
        @else
            <x-admin.empty icon="flag" title="Aucune piste" text="Lancez une première piste auprès d’un sponsor du carnet." />
        @endif
    </article>

    @if($canCreate)
        <article class="card">
            <div class="card-head"><div><h2>Lancer une piste</h2></div></div>
            <div class="card-body">
                <form method="post" action="{{ route('admin.sponsor-leads.store') }}" style="display:grid;gap:12px">
                    @csrf
                    @include('admin.sponsors._lead-fields', ['lead' => new \App\Models\SponsorLead(), 'sponsorOptions' => $sponsorOptions, 'preset' => $preset])
                    <div style="display:flex;justify-content:flex-end"><button class="btn btn-primary" type="submit"><x-admin.icon name="plus" :size="16" /> Ajouter la piste</button></div>
                </form>
            </div>
        </article>
    @endif
@endsection
