@extends('admin.layouts.app')

@section('title', $account->exists ? 'Modifier un accès' : 'Ajouter un accès')
@section('section', 'Marketing et partenariats')

@section('content')
    <x-admin.page-header :title="$account->exists ? 'Modifier l’accès « '.$account->name.' »' : 'Ajouter un accès marketing'" subtitle="Mot de passe obligatoire : il est chiffré et visible uniquement des personnes habilitées, avec trace d’audit.">
        <x-slot:actions>
            <a href="{{ route('admin.marketing.accounts.index') }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Retour au coffre</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form method="post" action="{{ $account->exists ? route('admin.marketing.accounts.update', $account) : route('admin.marketing.accounts.store') }}" class="card" autocomplete="off">
        @csrf
        @if($account->exists) @method('PUT') @endif
        <div class="card-body">
            <div class="form-grid">
                <x-admin.select name="club_id" label="Club" :options="$clubOptions" :value="$account->club_id" :required="! $canBeFoundation" :placeholder="$canBeFoundation ? 'Fondation (sans club)' : 'Choisir un club'" />
                <x-admin.select name="platform" label="Plateforme" :options="$platforms" :value="$account->platform" :required="true" :placeholder="null" />
                <x-admin.input name="name" label="Nom de la page, du profil ou adresse e-mail" :value="$account->name" :required="true" maxlength="160" />
                <x-admin.input name="login" label="Identifiant de connexion" :value="$account->login" :required="true" maxlength="190" autocomplete="off" />
                <x-admin.input name="password" type="password" label="Mot de passe" :required="! $account->exists" maxlength="255" autocomplete="new-password"
                    :hint="$account->exists ? 'Laisser vide pour conserver le mot de passe actuel.' : 'Obligatoire.'" />
                <x-admin.input name="profile_url" type="url" label="Lien de la page ou du profil" :value="$account->profile_url" maxlength="255" placeholder="https://" />
                <x-admin.input name="recovery_email" type="email" label="E-mail de récupération" :value="$account->recovery_email" maxlength="190" />
                <x-admin.input name="recovery_phone" label="Téléphone de récupération" :value="$account->recovery_phone" maxlength="40" />
                <x-admin.select name="two_factor" label="Double authentification" :options="$twoFactor" :value="$account->two_factor" :required="true" :placeholder="null" />
                <x-admin.textarea name="recovery_codes" label="Codes de secours (optionnel)" rows="3" maxlength="4000" :value="null"
                    :hint="$hasCodes ? 'Des codes sont déjà enregistrés : laisser vide pour les conserver.' : 'Un code par ligne.'" />
                <x-admin.textarea name="notes" label="Notes (administrateurs de la page, règles d’usage…)" :value="$account->notes" rows="3" maxlength="3000" />
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><x-admin.icon name="check" :size="16" /> Enregistrer dans le coffre</button>
        </div>
    </form>
@endsection
