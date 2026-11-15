{{-- Un accès (page ou compte) dans une cellule du tableau : nom, lien, identifiant et actions. --}}
<div style="margin-top:6px">
    <span class="cell-main">{{ $account->name }}</span>
    <div class="cell-sub">
        {{ $account->login }}@if($account->login && $account->profile_url) · @endif
        @if($account->profile_url)<a href="{{ $account->profile_url }}" target="_blank" rel="noopener noreferrer">Ouvrir la page</a>@endif
    </div>
    <div class="cell-sub">
        {{ $account->hasPassword() ? ($account->password_changed_at ? 'Mot de passe changé '.$account->password_changed_at->diffForHumans() : 'Mot de passe enregistré') : 'Sans mot de passe' }}
        @if(($account->two_factor ?? 'none') !== 'none') · 2FA : {{ \App\Models\MarketingAccount::TWO_FACTOR[$account->two_factor] ?? '' }}@endif
    </div>
    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:4px">
        @if($account->hasPassword() || $account->login)
            <button type="button" class="btn btn-secondary btn-sm" data-reveal="{{ route('admin.marketing.accounts.secret', $account) }}" data-name="{{ $account->name }}"><x-admin.icon name="eye" :size="15" /> Révéler</button>
        @endif
        @if($canUpdate)<a href="{{ route('admin.marketing.accounts.edit', $account) }}" class="btn btn-secondary btn-sm"><x-admin.icon name="edit" :size="15" /> Modifier</a>@endif
        @if($canDelete)<x-admin.delete-button :action="route('admin.marketing.accounts.destroy', $account)" :message="'L’accès « '.$account->name.' » sera retiré du coffre.'" title="Supprimer cet accès ?" />@endif
    </div>
</div>
