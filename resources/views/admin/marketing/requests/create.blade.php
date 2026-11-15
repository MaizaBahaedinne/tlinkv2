@extends('admin.layouts.app')

@section('title', 'Nouvelle demande d’accès')
@section('section', 'Marketing et partenariats')

@section('content')
    <x-admin.page-header title="Nouvelle demande d’accès aux réseaux" subtitle="La demande réunit le compte T.Link du bénéficiaire, son profil Facebook, son numéro de téléphone et un profil vérifié.">
        <x-slot:actions>
            <a href="{{ route('admin.marketing.requests.index') }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Retour aux demandes</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form method="get" class="card" style="margin-bottom:16px">
        <div class="card-body" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
            <x-admin.entity-select name="entity" label="Entité concernée" :groups="$entityOptions" :value="$entity" :required="true" />
            <button type="submit" class="btn btn-secondary">Choisir</button>
        </div>
    </form>

    @if($entity !== null)
        @if($pages === [])
            <x-admin.empty icon="key" title="Aucune page renseignée" text="{{ $entityLabel }} n’a pas encore de page Facebook ou Instagram : renseignez-la d’abord dans « Pages et comptes »." />
        @elseif($people->isEmpty())
            <x-admin.empty icon="users" title="Aucun membre disponible" text="Aucun membre ou titulaire de poste n’est rattaché à {{ $entityLabel }}." />
        @else
            <form method="post" action="{{ route('admin.marketing.requests.store') }}" class="card" id="request-form" autocomplete="off">
                @csrf
                <input type="hidden" name="entity" value="{{ $entity }}">
                <div class="card-body">
                    <h2 style="margin-top:0">{{ $entityLabel }}</h2>
                    <div class="form-grid">
                        <div class="field span-2 {{ $errors->has('beneficiary_id') ? 'has-error' : '' }}">
                            <label for="f-beneficiary_id">Bénéficiaire (membre de l’entité) <span class="req">*</span></label>
                            <select id="f-beneficiary_id" name="beneficiary_id" required>
                                <option value="">Choisir un membre</option>
                                @foreach($people as $person)
                                    <option value="{{ $person['id'] }}" data-phone="{{ $person['phone'] }}" data-facebook="{{ $person['facebook'] }}" @selected((string) old('beneficiary_id') === (string) $person['id'])>
                                        {{ $person['label'] }}{{ $person['verified'] ? '' : ' — profil non vérifié' }}{{ $person['id'] === $me ? ' (moi)' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="hint">Le bénéficiaire doit avoir un profil vérifié. Vous pouvez demander pour un autre membre de votre entité.</p>
                            @error('beneficiary_id')<p class="error">{{ $message }}</p>@enderror
                        </div>
                        <div class="field span-2 {{ $errors->has('platforms') ? 'has-error' : '' }}">
                            <label>Pages demandées <span class="req">*</span></label>
                            <div style="display:flex;gap:16px;flex-wrap:wrap">
                                @foreach($pages as $platform => $url)
                                    <label style="display:flex;gap:6px;align-items:center"><input type="checkbox" name="platforms[]" value="{{ $platform }}" @checked(in_array($platform, (array) old('platforms', array_keys($pages)), true))> {{ \App\Models\MarketingAccessRequest::PLATFORMS[$platform] }}</label>
                                @endforeach
                            </div>
                            @error('platforms')<p class="error">{{ $message }}</p>@enderror
                        </div>
                        <x-admin.input name="facebook_profile_url" type="url" label="Profil Facebook du bénéficiaire" :required="true" maxlength="255" placeholder="https://www.facebook.com/…" />
                        <x-admin.input name="phone" label="Numéro de téléphone du bénéficiaire" :required="true" maxlength="40" />
                        <x-admin.textarea name="reason" label="Motif de la demande" rows="3" maxlength="1500" :value="null" :span="true" />
                    </div>
                </div>
                <div class="form-actions"><button type="submit" class="btn btn-primary"><x-admin.icon name="check" :size="16" /> Envoyer la demande</button></div>
            </form>
            <script>
                (function () {
                    var select = document.getElementById('f-beneficiary_id');
                    var phone = document.getElementById('f-phone');
                    var facebook = document.getElementById('f-facebook_profile_url');
                    select.addEventListener('change', function () {
                        var option = select.options[select.selectedIndex];
                        phone.value = option.getAttribute('data-phone') || '';
                        facebook.value = option.getAttribute('data-facebook') || '';
                    });
                })();
            </script>
        @endif
    @endif
@endsection
