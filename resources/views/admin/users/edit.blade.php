@extends('admin.layouts.app')

@section('title', 'Modifier '.$user->name)
@section('section', 'Utilisateurs')

@section('content')
    <x-admin.page-header :title="'Modifier « '.$user->name.' »'" subtitle="Compte, coordonnées et profil. Les droits viennent uniquement des postes de l’organigramme.">
        <x-slot:actions>
            <a href="{{ route('admin.users.show', $user) }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Retour à la fiche</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form method="post" action="{{ route('admin.users.update', $user) }}" class="card card-flush" style="max-width:860px">
        @csrf
        @method('PUT')

        <div class="card-body">
            <h2 class="text-sm text-muted" style="margin-bottom:12px">Compte</h2>
            <div class="form-grid">
                <x-admin.input name="name" label="Nom" :value="$user->name" required :maxlength="255" />
                <x-admin.input name="email" type="email" label="E-mail" :value="$user->email" required :maxlength="255" />
                <x-admin.input name="password" type="password" label="Nouveau mot de passe" autocomplete="new-password" hint="Laisser vide pour conserver le mot de passe actuel." :span="true" />

                <div class="field span-2">
                    <input type="hidden" name="is_active" value="0">
                    <label class="check">
                        <input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $user->is_active)) @disabled($isSelf)>
                        Compte actif (connexion autorisée)
                    </label>
                    @if($isSelf)<p class="hint">Vous ne pouvez pas désactiver votre propre compte.</p>@endif
                    @error('is_active')<p class="error">{{ $message }}</p>@enderror
                </div>
                <x-admin.textarea name="deactivated_reason" label="Motif de désactivation" :value="$user->deactivated_reason" :maxlength="1000" :rows="2" />
            </div>

            <h2 class="text-sm text-muted" style="margin:24px 0 12px">Profil</h2>
            <div class="form-grid">
                <x-admin.input name="headline" label="Titre" :value="$profile->headline" :maxlength="160" :span="true" />
                <x-admin.input name="phone" label="Téléphone" :value="$profile->phone" :maxlength="20" />
                <x-admin.input name="phone_secondary" label="Téléphone secondaire" :value="$profile->phone_secondary" :maxlength="20" />
                <x-admin.select name="gender" label="Genre" :options="$genders" :value="$profile->gender" placeholder="Non renseigné" />
                <x-admin.input name="birth_date" type="date" label="Date de naissance" :value="$profile->birth_date?->format('Y-m-d')" />
                <x-admin.select name="governorate_id" label="Gouvernorat" :options="$governorates" :value="$profile->governorate_id" placeholder="Non renseigné" />
                <x-admin.input name="delegation" label="Délégation" :value="$profile->delegation" :maxlength="255" />
                <x-admin.textarea name="address" label="Adresse" :value="$profile->address" :maxlength="500" :rows="2" />
                <x-admin.textarea name="bio" label="Biographie" :value="$profile->bio" :maxlength="4000" />
            </div>
        </div>

        <div class="form-actions">
            <a href="{{ route('admin.users.show', $user) }}" class="btn btn-ghost">Annuler</a>
            <button type="submit" class="btn btn-primary"><x-admin.icon name="check" :size="16" /> Enregistrer</button>
        </div>
    </form>
@endsection
