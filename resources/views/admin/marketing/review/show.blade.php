@extends('admin.layouts.app')

@section('title', 'Examiner une demande')
@section('section', 'Marketing et partenariats')

@section('content')
    <x-admin.page-header :title="'Demande de '.$accessRequest->beneficiary?->name" :subtitle="'Accès aux pages de '.$accessRequest->entityLabel()">
        <x-slot:actions>
            <a href="{{ route('admin.marketing.review.index') }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Retour</a>
        </x-slot:actions>
    </x-admin.page-header>

    <article class="card" style="margin-bottom:16px">
        <div class="card-body">
            @include('admin.marketing.partials.request-details')
            <h3 style="margin:18px 0 6px">Pages concernées</h3>
            @forelse($accounts as $account)
                <p style="margin:2px 0">{{ \App\Models\MarketingAccount::PLATFORMS[$account->platform] ?? $account->platform }} — {{ $account->name }} @if($account->profile_url)<a href="{{ $account->profile_url }}" target="_blank" rel="noopener noreferrer">Ouvrir</a>@endif</p>
            @empty
                <p class="text-muted">Aucune page enregistrée pour les plateformes demandées.</p>
            @endforelse
        </div>
    </article>

    @if($canUpdate)
        @if($accessRequest->isPending())
            <div style="display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(320px,1fr))">
                <form method="post" action="{{ route('admin.marketing.review.approve', $accessRequest) }}" class="card">
                    @csrf
                    <div class="card-body">
                        <h2 style="margin-top:0">Valider la demande</h2>
                        <p class="text-sm text-muted">Le bénéficiaire devra signer la charte d’usage des réseaux sociaux de {{ $accessRequest->entityLabel() }} avant de voir les accès.</p>
                        <div class="field {{ $errors->has('platforms') ? 'has-error' : '' }}">
                            <label>Pages accordées</label>
                            @foreach((array) $accessRequest->platforms as $platform)
                                <label style="display:flex;gap:6px;align-items:center"><input type="checkbox" name="platforms[]" value="{{ $platform }}" checked> {{ \App\Models\MarketingAccessRequest::PLATFORMS[$platform] ?? $platform }}</label>
                            @endforeach
                            @error('platforms')<p class="error">{{ $message }}</p>@enderror
                        </div>
                        <x-admin.textarea name="review_note" label="Message au demandeur (facultatif)" rows="2" maxlength="1500" :value="null" />
                    </div>
                    <div class="form-actions"><button type="submit" class="btn btn-primary"><x-admin.icon name="check" :size="16" /> Valider</button></div>
                </form>

                <form method="post" action="{{ route('admin.marketing.review.refuse', $accessRequest) }}" class="card">
                    @csrf
                    <div class="card-body">
                        <h2 style="margin-top:0">Refuser la demande</h2>
                        <x-admin.textarea name="review_note" label="Motif du refus" rows="3" maxlength="1500" :required="true" :value="null" />
                    </div>
                    <div class="form-actions"><button type="submit" class="btn btn-secondary">Refuser</button></div>
                </form>
            </div>
        @endif

        @if(in_array($accessRequest->status, ['approved', 'active'], true))
            <div style="display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(320px,1fr))">
                <form method="post" action="{{ route('admin.marketing.review.meta', $accessRequest) }}" class="card">
                    @csrf
                    <div class="card-body">
                        <h2 style="margin-top:0">Portfolio Meta</h2>
                        <p class="text-sm text-muted">Indiquez le portfolio (Meta Business) attribué au bénéficiaire pour {{ $accessRequest->entityLabel() }}.</p>
                        <x-admin.input name="meta_portfolio" label="Portfolio Meta attribué" :value="$accessRequest->meta_portfolio" :required="true" maxlength="190" />
                    </div>
                    <div class="form-actions"><button type="submit" class="btn btn-primary"><x-admin.icon name="check" :size="16" /> Enregistrer</button></div>
                </form>

                <form method="post" action="{{ route('admin.marketing.review.revoke', $accessRequest) }}" class="card">
                    @csrf
                    <div class="card-body">
                        <h2 style="margin-top:0">Retirer l’accès</h2>
                        <x-admin.textarea name="review_note" label="Motif (facultatif)" rows="2" maxlength="1500" :value="null" />
                    </div>
                    <div class="form-actions"><button type="submit" class="btn btn-secondary">Retirer l’accès</button></div>
                </form>
            </div>
        @endif
    @endif

    <article class="card" style="margin-top:16px">
        <div class="card-head"><div><h2>Charte que le bénéficiaire signera</h2><p class="sub">{{ $charter['title'] }} — version {{ $charter['version'] }} · <a href="{{ route('admin.marketing.charters.edit', ['entity' => $accessRequest->entityKey()]) }}">Modifier</a></p></div></div>
        <div class="card-body" style="white-space:pre-line">{{ $accessRequest->charter_text ?: $charter['body'] }}</div>
    </article>
@endsection
