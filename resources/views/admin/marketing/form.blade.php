@extends('admin.layouts.app')

@section('title', $account->exists ? 'Modifier une page' : 'Ajouter une page')
@section('section', 'Marketing et partenariats')

@section('content')
    <x-admin.page-header :title="$account->exists ? 'Modifier « '.$account->name.' »' : 'Ajouter une page ou un accès'" subtitle="Renseignez d’abord la page : son lien s’affiche sur la page de l’entité. L’identifiant et le mot de passe sont facultatifs ; chiffrés, ils ne sont remis qu’après validation d’une demande d’accès, avec trace d’audit.">
        <x-slot:actions>
            <a href="{{ route('admin.marketing.accounts.index') }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Retour aux pages</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form method="post" action="{{ $account->exists ? route('admin.marketing.accounts.update', $account) : route('admin.marketing.accounts.store') }}" class="card" autocomplete="off">
        @csrf
        @if($account->exists) @method('PUT') @endif
        <div class="card-body">
            <div class="form-grid">
                <x-admin.entity-select name="entity" label="Entité (Fondation, chapitre, projet ou club)" :groups="$entityOptions" :value="$entityValue" :required="true" />
                <x-admin.select name="platform" label="Plateforme" :options="$platforms" :value="$account->platform" :required="true" :placeholder="null" />
                <x-admin.input name="name" label="Nom de la page, du profil ou adresse e-mail" :value="$account->name" :required="true" maxlength="160" />
                <x-admin.input name="profile_url" type="url" label="Lien de la page (affiché sur la page de l’entité)" :value="$account->profile_url" maxlength="255" placeholder="https://www.facebook.com/…" hint="Obligatoire pour Facebook et Instagram." />
                <x-admin.input name="login" label="Identifiant de connexion (facultatif)" :value="$account->login" maxlength="190" autocomplete="off" hint="Obligatoire pour une adresse e-mail." />
                <x-admin.input name="password" type="password" label="Mot de passe (facultatif)" maxlength="255" autocomplete="new-password"
                    :hint="$hasPassword ? 'Un mot de passe est enregistré : laisser vide pour le conserver.' : 'Les membres l’obtiennent après validation de leur demande et signature de la charte.'" />
                <x-admin.input name="recovery_email" type="email" label="E-mail de récupération" :value="$account->recovery_email" maxlength="190" />
                <x-admin.input name="recovery_phone" label="Téléphone de récupération" :value="$account->recovery_phone" maxlength="40" />
                <x-admin.select name="two_factor" label="Double authentification" :options="$twoFactor" :value="$account->two_factor" :required="true" :placeholder="null" />
                <x-admin.textarea name="recovery_codes" label="Codes de secours (optionnel)" rows="3" maxlength="4000" :value="null"
                    :hint="$hasCodes ? 'Des codes sont déjà enregistrés : laisser vide pour les conserver.' : 'Un code par ligne.'" />
                <x-admin.textarea name="notes" label="Notes (administrateurs de la page, règles d’usage…)" :value="$account->notes" rows="3" maxlength="3000" />
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><x-admin.icon name="check" :size="16" /> Enregistrer</button>
        </div>
    </form>
@endsection
