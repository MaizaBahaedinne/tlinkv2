@php($verification = \App\Support\IdentityVerifications::isVerified($accessRequest->beneficiary))
<dl style="display:grid;grid-template-columns:max-content 1fr;gap:8px 18px;margin:0">
    <dt class="text-muted">Entité</dt><dd>{{ $accessRequest->entityLabel() }}</dd>
    <dt class="text-muted">Pages demandées</dt><dd>{{ $accessRequest->platformLabels() }}</dd>
    <dt class="text-muted">Bénéficiaire</dt><dd>{{ $accessRequest->beneficiary?->name }} <span class="text-sm text-muted">(compte T.Link {{ $accessRequest->beneficiary?->email }})</span></dd>
    <dt class="text-muted">Demandeur</dt><dd>{{ $accessRequest->requester?->name }}</dd>
    <dt class="text-muted">Profil Facebook</dt><dd><a href="{{ $accessRequest->facebook_profile_url }}" target="_blank" rel="noopener noreferrer">{{ $accessRequest->facebook_profile_url }}</a></dd>
    <dt class="text-muted">Téléphone</dt><dd>{{ $accessRequest->phone }}</dd>
    <dt class="text-muted">Profil vérifié</dt><dd>@if($verification)<span class="badge badge-success">Identité vérifiée</span>@else<span class="badge badge-danger">Vérification retirée ou expirée</span>@endif</dd>
    @if($accessRequest->reason)<dt class="text-muted">Motif</dt><dd>{{ $accessRequest->reason }}</dd>@endif
    <dt class="text-muted">Statut</dt><dd><span class="badge badge-{{ $accessRequest->tone() }}">{{ $accessRequest->statusLabel() }}</span></dd>
    @if($accessRequest->reviewed_at)<dt class="text-muted">Décision</dt><dd>{{ $accessRequest->reviewer?->name }}, le {{ $accessRequest->reviewed_at->format('d/m/Y H:i') }}@if($accessRequest->review_note) — {{ $accessRequest->review_note }}@endif</dd>@endif
    @if($accessRequest->charter_signed_at)<dt class="text-muted">Charte</dt><dd>Signée par {{ $accessRequest->charter_signed_name }} le {{ $accessRequest->charter_signed_at->format('d/m/Y H:i') }} (version {{ $accessRequest->charter_version }})@if($accessRequest->charter_signature)<br><img src="{{ $accessRequest->charter_signature }}" alt="Signature" style="height:90px;max-width:100%;border:1px solid var(--line);border-radius:8px;background:#fff;margin-top:6px">@endif</dd>@endif
    @if($accessRequest->meta_portfolio)<dt class="text-muted">Portfolio Meta</dt><dd>{{ $accessRequest->meta_portfolio }} <span class="text-sm text-muted">(attribué le {{ $accessRequest->meta_assigned_at?->format('d/m/Y') }})</span></dd>@endif
    @if($accessRequest->revoked_at)<dt class="text-muted">Retrait</dt><dd>Le {{ $accessRequest->revoked_at->format('d/m/Y H:i') }}</dd>@endif
</dl>
