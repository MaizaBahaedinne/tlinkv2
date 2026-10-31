@extends('admin.layouts.app')

@section('title', 'Vérifications d’identité')
@section('section', 'Vérifications d’identité')

@section('content')
    <x-admin.page-header title="Vérifications d’identité" subtitle="Demandes du badge « Profil vérifié » : CIN tunisienne (recto et verso) et selfie, examinés à la main. Les documents sont supprimés dès que vous décidez." />

    <article class="card card-flush">
        <div class="tabs">
            @foreach($statuses as $code => $label)
                <a href="{{ route('admin.verifications.index', ['status' => $code]) }}" class="tab {{ $status === $code ? 'is-active' : '' }}">{{ $label }} <span class="n">{{ $counts[$code] ?? 0 }}</span></a>
            @endforeach
        </div>

        @if($verifications->count())
            <div class="list">
                @foreach($verifications as $item)
                    <div class="list-item" style="align-items:center;flex-wrap:wrap">
                        <div class="grow" style="min-width:220px">
                            <strong>{{ $item->user?->name ?? 'Compte supprimé' }}</strong>
                            <span class="text-sm text-muted">{{ $item->user?->email }} · envoyée le {{ $item->created_at->format('d/m/Y H:i') }}
                                @if($item->reviewed_at) · traitée le {{ $item->reviewed_at->format('d/m/Y') }} @endif</span>
                            @if($item->status === 'rejected' && $item->rejection_reason)
                                <span class="text-sm text-muted">Motif : {{ $item->rejection_reason }}</span>
                            @endif
                        </div>
                        <x-admin.badge :status="$item->status" />
                        @if($item->isPending())
                            <a href="{{ route('admin.verifications.show', $item) }}" class="btn btn-primary btn-sm"><x-admin.icon name="user-check" :size="15" /> Examiner</a>
                        @endif
                    </div>
                @endforeach
            </div>
            {{ $verifications->links('admin.partials.pagination') }}
        @else
            <x-admin.empty icon="shield" title="Aucune demande" text="Aucune demande dans cette catégorie." />
        @endif
    </article>
@endsection
