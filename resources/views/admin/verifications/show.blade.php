@extends('admin.layouts.app')

@section('title', 'Examiner une vérification')
@section('section', 'Vérifications d’identité')

@section('content')
    <x-admin.page-header :title="$verification->user?->name ?? 'Compte supprimé'" :subtitle="($verification->user?->email ?? '').' · demande du '.$verification->created_at->format('d/m/Y H:i')">
        <x-slot:actions>
            <a href="{{ route('admin.verifications.index') }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Retour aux demandes</a>
        </x-slot:actions>
    </x-admin.page-header>

    <article class="card">
        <div class="card-body">
            @if(! $verification->isPending())
                <p><x-admin.badge :status="$verification->status" /> Décision du {{ $verification->reviewed_at?->format('d/m/Y H:i') }}@if($verification->reviewer) par {{ $verification->reviewer->name }}@endif. Les documents ont été supprimés.</p>
            @else
                <p class="text-sm text-muted">Vérifiez : carte <strong>tunisienne</strong> lisible et entière (recto et verso), selfie de face bien cadré, que le visage correspond à la photo de la carte et au nom du compte, et que les informations saisies ci-dessous sont identiques à celles de la carte. Ces images ne sont jamais mises en cache.</p>

                <dl style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin:14px 0 0">
                    @foreach(['Numéro de la carte' => $verification->cin_number, 'Date de délivrance' => $verification->card_issued_on ? \Illuminate\Support\Carbon::parse($verification->card_issued_on)->format('d/m/Y') : null, 'Ville de délivrance' => $verification->card_issued_place] as $label => $value)
                        <div style="border:1px solid var(--line);border-radius:12px;padding:10px 14px">
                            <dt class="text-sm text-muted">{{ $label }} (saisi par le membre)</dt>
                            <dd style="margin:2px 0 0;font-size:18px;font-weight:700">{{ $value ?: '—' }}</dd>
                        </div>
                    @endforeach
                </dl>

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;margin-top:14px">
                    @foreach(['front' => 'CIN — recto', 'back' => 'CIN — verso', 'selfie' => 'Selfie'] as $key => $label)
                        <figure style="margin:0">
                            <figcaption class="text-sm" style="font-weight:600;margin-bottom:6px">{{ $label }}</figcaption>
                            <a href="{{ route('admin.verifications.document', [$verification, $key]) }}" target="_blank" rel="noopener noreferrer">
                                <img src="{{ route('admin.verifications.document', [$verification, $key]) }}" alt="{{ $label }}" style="width:100%;max-height:340px;object-fit:contain;background:#f4f4f5;border:1px solid var(--line);border-radius:12px">
                            </a>
                        </figure>
                    @endforeach
                </div>
            @endif
        </div>

        @if($verification->isPending())
            <div class="form-actions" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-start">
                <form method="post" action="{{ route('admin.verifications.approve', $verification) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary"><x-admin.icon name="check" :size="16" /> Valider le profil</button>
                </form>

                <form method="post" action="{{ route('admin.verifications.reject', $verification) }}" style="display:flex;gap:8px;flex-wrap:wrap;flex:1;min-width:260px">
                    @csrf
                    <div class="field {{ $errors->has('reason') ? 'has-error' : '' }}" style="flex:1;min-width:220px;margin:0">
                        <input type="text" name="reason" maxlength="500" required value="{{ old('reason') }}" placeholder="Motif du refus (communiqué au membre)" aria-label="Motif du refus">
                        @error('reason')<p class="error">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="btn btn-danger">Refuser</button>
                </form>
            </div>
        @endif
    </article>
@endsection
