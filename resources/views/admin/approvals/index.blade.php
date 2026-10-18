@extends('admin.layouts.app')

@section('title', 'Approbations')
@section('section', 'Approbations')

@section('content')
    <x-admin.page-header title="Approbations" subtitle="Demandes en attente de validation : adhésions et autres workflows soumis à approbation." />

    <article class="card card-flush">
        <div class="tabs">
            @foreach($statuses as $code => $label)
                <a href="{{ route('admin.approvals.index', ['status' => $code]) }}" class="tab {{ $status === $code ? 'is-active' : '' }}">{{ $label }} <span class="n">{{ $counts[$code] ?? 0 }}</span></a>
            @endforeach
            <a href="{{ route('admin.approvals.index', ['status' => 'all']) }}" class="tab {{ $status === 'all' ? 'is-active' : '' }}">Toutes <span class="n">{{ array_sum($counts) }}</span></a>
        </div>

        @if($approvals->count())
            <div class="list">
                @foreach($approvals as $approval)
                    @php($membership = $approval->approvable_type === \App\Models\Membership::class ? $memberships->get($approval->approvable_id) : null)
                    <div class="list-item" style="align-items:flex-start;flex-wrap:wrap">
                        <x-admin.avatar :name="$approval->requester?->name" :size="40" />
                        <div class="grow" style="min-width:260px">
                            <div style="display:flex;gap:10px;align-items:center;margin-bottom:4px;flex-wrap:wrap">
                                <strong style="display:inline">{{ $approval->requester?->name ?? 'Demandeur inconnu' }}</strong>
                                <x-admin.badge :status="$approval->status" />
                                <span class="text-sm text-muted">{{ $approval->submitted_at?->format('d/m/Y H:i') ?? $approval->created_at?->format('d/m/Y H:i') }}</span>
                            </div>
                            <div class="text-sm">
                                @if($membership)
                                    Demande d’adhésion à <strong>{{ $membership->entity?->name ?? '—' }}</strong>@if($membership->club) · {{ $membership->club->name }}@endif
                                @else
                                    {{ $approval->request_type }}
                                @endif
                            </div>
                            @if($approval->decided_at)
                                <div class="text-sm text-muted">Décidée le {{ $approval->decided_at->format('d/m/Y H:i') }}</div>
                            @endif
                        </div>

                        @if($approval->status === 'pending')
                            @if($decidable[$approval->id] ?? false)
                                <form method="post" action="{{ route('admin.approvals.decide', $approval) }}" class="inline-form" style="flex-basis:100%;margin-top:8px">
                                    @csrf
                                    <input type="text" name="explanation" maxlength="2000" placeholder="Commentaire (optionnel, transmis au demandeur en cas de rejet)" style="flex:1;min-width:220px">
                                    <button type="submit" name="decision" value="approved" class="btn btn-success btn-sm"><x-admin.icon name="check" :size="15" /> Approuver</button>
                                    <button type="submit" name="decision" value="rejected" class="btn btn-danger btn-sm"><x-admin.icon name="x" :size="15" /> Rejeter</button>
                                </form>
                            @else
                                <span class="text-sm text-muted">Vous ne pouvez pas statuer sur cette demande.</span>
                            @endif
                        @endif
                    </div>
                @endforeach
            </div>
            {{ $approvals->links('admin.partials.pagination') }}
        @else
            <x-admin.empty icon="shield" title="Aucune demande" text="Aucune demande dans cette catégorie." />
        @endif
    </article>
@endsection
