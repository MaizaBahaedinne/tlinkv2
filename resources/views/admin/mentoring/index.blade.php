@extends('admin.layouts.app')

@section('title', 'Tickets de mentorat')
@section('section', 'Tickets de mentorat')

@section('content')
    @php
        $openCount = collect(\App\Models\MentoringTicket::OPEN)->sum(fn ($key) => $counts[$key] ?? 0);
        $tones = ['violet' => 'info'];
        $priorityTones = ['low' => 'neutral', 'normal' => 'info', 'high' => 'warning', 'urgent' => 'danger'];
    @endphp

    <x-admin.page-header title="Tickets de mentorat" subtitle="Recevez les demandes, incidents et problèmes remontés par les membres, qualifiez-les puis affectez-les à un mentor." />

    <div class="tabs" style="margin-bottom:14px">
        <a href="{{ route('admin.mentoring.tickets.index') }}" class="tab {{ $status === 'open' ? 'is-active' : '' }}">À traiter <span class="n">{{ $openCount }}</span></a>
        @foreach(\App\Models\MentoringTicket::STATUSES as $key => [$label])
            <a href="{{ route('admin.mentoring.tickets.index', ['statut' => $key]) }}" class="tab {{ $status === $key ? 'is-active' : '' }}">{{ $label }} <span class="n">{{ $counts[$key] ?? 0 }}</span></a>
        @endforeach
        <a href="{{ route('admin.mentoring.tickets.index', ['statut' => 'all']) }}" class="tab {{ $status === 'all' ? 'is-active' : '' }}">Tous <span class="n">{{ array_sum($counts) }}</span></a>
    </div>

    <article class="card card-flush">
        <div class="toolbar">
            <form method="get" action="{{ route('admin.mentoring.tickets.index') }}">
                <input type="hidden" name="statut" value="{{ $status }}">
                <div class="search">
                    <x-admin.icon name="search" :size="16" />
                    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="80" placeholder="Référence, titre ou demandeur" aria-label="Recherche">
                </div>
                <select name="priorite" aria-label="Priorité">
                    <option value="">Toutes les priorités</option>
                    @foreach(\App\Models\MentoringTicket::PRIORITIES as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['priorite'] ?? null) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="public" aria-label="Concerne">
                    <option value="">Personnel et collectif</option>
                    @foreach(\App\Models\MentoringTicket::AUDIENCES as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['public'] ?? null) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <button class="btn btn-primary btn-sm" type="submit">Filtrer</button>
                @if(filled($filters['q'] ?? null) || filled($filters['priorite'] ?? null) || filled($filters['public'] ?? null))
                    <a href="{{ route('admin.mentoring.tickets.index', ['statut' => $status]) }}" class="btn btn-ghost btn-sm">Réinitialiser</a>
                @endif
            </form>
        </div>

        @if($tickets->count())
            <div class="list">
                @foreach($tickets as $item)
                    <a href="{{ route('admin.mentoring.tickets.show', $item) }}" class="list-item" style="align-items:flex-start;flex-wrap:wrap;color:inherit;text-decoration:none">
                        <div class="grow" style="min-width:260px">
                            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:4px">
                                <span class="text-sm text-muted">{{ $item->reference }}</span>
                                <span class="badge badge-{{ $tones[$item->statusTone()] ?? $item->statusTone() }}">{{ $item->statusLabel() }}</span>
                                <span class="badge badge-neutral">{{ \App\Models\MentoringTicket::TYPES[$item->type] ?? $item->type }}</span>
                                @if($item->category !== null)
                                    <span class="badge badge-{{ $priorityTones[$item->priority] ?? 'neutral' }}">Priorité {{ mb_strtolower(\App\Models\MentoringTicket::PRIORITIES[$item->priority] ?? $item->priority) }}</span>
                                @endif
                            </div>
                            <strong style="display:block">{{ $item->title }}</strong>
                            <div class="text-sm text-muted">
                                {{ $item->created_at->format('d/m/Y') }}
                                · {{ $item->collectiveLabel() ?? 'Personnel' }}
                                @if($item->requester) · par {{ $item->requester->name }} @endif
                                · {{ $item->mentor ? 'Mentor : '.$item->mentor->name : 'Sans mentor' }}
                                @if($item->category !== null) · {{ \App\Models\MentoringTicket::CATEGORIES[$item->category] ?? $item->category }} @endif
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
            {{ $tickets->links('admin.partials.pagination') }}
        @else
            <x-admin.empty icon="file-text" title="Aucun ticket" text="Aucun ticket ne correspond à ces critères." />
        @endif
    </article>
@endsection
