@extends('admin.layouts.app')

@section('title', 'Réglages Academy')
@section('section', 'Tunivisions Academy')

@section('content')
    <x-admin.page-header title="Abonnements et réglages" subtitle="Vente des cours, prestataire de paiement et formules d’abonnement de Tunivisions Academy." />

    <article class="card">
        <h2 style="margin-top:0">Vente et paiement</h2>
        <form method="post" action="{{ route('admin.academy.settings.update') }}" class="grid" style="gap:14px;grid-template-columns:repeat(auto-fit,minmax(240px,1fr))">
            @csrf
            @method('PUT')
            <div style="grid-column:1/-1">
                <label class="check"><input type="checkbox" name="sales_enabled" value="1" @checked(old('sales_enabled', $settings['sales_enabled']))> Ouvrir les ventes (achat de cours et abonnements)</label>
                <p class="hint">Désactivées, les cours payants ne sont plus achetables ; les cours gratuits restent accessibles.</p>
            </div>
            <div>
                <label class="text-sm" for="s-gateway">Mode de paiement</label>
                <select id="s-gateway" name="gateway">
                    @foreach($gateways as $key => $gateway)
                        <option value="{{ $key }}" @selected(old('gateway', $settings['gateway']) === $key)>{{ $gateway->label() }}</option>
                    @endforeach
                </select>
                @error('gateway')<p class="error">{{ $message }}</p>@enderror
                <p class="hint">Un prestataire en ligne pourra être branché ici ; en attendant, les paiements sont validés à la main depuis « Commandes et paiements ».</p>
            </div>
            <div>
                <label class="text-sm" for="s-email">E-mail de contact Academy</label>
                <input id="s-email" type="email" name="contact_email" value="{{ old('contact_email', $settings['contact_email']) }}" maxlength="255">
                @error('contact_email')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div style="grid-column:1/-1">
                <label class="text-sm" for="s-instructions">Consignes de paiement affichées au membre</label>
                <textarea id="s-instructions" name="manual_instructions" rows="4" maxlength="2000" placeholder="Ex. : virement sur le compte … au nom de Tunivisions Academy, en indiquant la référence de la commande.">{{ old('manual_instructions', $settings['manual_instructions']) }}</textarea>
                @error('manual_instructions')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div style="grid-column:1/-1;display:flex;justify-content:flex-end">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </article>

    <article class="card" style="margin-top:16px">
        <h2 style="margin-top:0">Certificat de réussite</h2>
        <p class="hint" style="margin-top:-6px">Le certificat affiche le logo d’Academy, le logo de la Fondation Tunivisions et la signature du chef de projet et du président de la Fondation. Sans image de signature, une ligne de signature avec le nom et la fonction est affichée.</p>
        <form method="post" action="{{ route('admin.academy.settings.certificate') }}" enctype="multipart/form-data" class="grid" style="gap:14px;grid-template-columns:repeat(auto-fit,minmax(240px,1fr))">
            @csrf
            @method('PUT')
            @php
                $files = [
                    'logo' => ['Logo Tunivisions Academy', 'logo_path', 'Affiché en haut du certificat, à côté du logo de la Fondation.'],
                    'manager_signature' => ['Signature du chef de projet', 'manager_signature_path', 'Image de la signature sur fond blanc ou transparent.'],
                    'president_signature' => ['Signature du président de la Fondation', 'president_signature_path', 'Image de la signature sur fond blanc ou transparent.'],
                ];
            @endphp
            @foreach($files as $input => [$label, $key, $hint])
                <div>
                    <label class="text-sm" for="c-{{ $input }}">{{ $label }}</label>
                    @if($certificateFiles[$key])
                        <img src="{{ $certificateFiles[$key] }}" alt="{{ $label }}" style="display:block;margin:6px 0;max-width:100%;height:64px;object-fit:contain;background:#fff;border:1px solid var(--line);border-radius:10px;padding:6px">
                        <label class="check"><input type="checkbox" name="remove_{{ $input }}" value="1"> <span class="text-sm">Retirer l’image</span></label>
                    @endif
                    <input id="c-{{ $input }}" type="file" name="{{ $input }}" accept="image/png,image/jpeg,image/webp">
                    @error($input)<p class="error">{{ $message }}</p>@enderror
                    <p class="hint">{{ $hint }} jpg, png ou webp — 2 Mo max.</p>
                </div>
            @endforeach
            @foreach(['manager' => 'Chef de projet', 'president' => 'Président de la Fondation'] as $prefix => $role)
                <div>
                    <label class="text-sm" for="c-{{ $prefix }}-name">{{ $role }} : nom</label>
                    <input id="c-{{ $prefix }}-name" type="text" name="{{ $prefix }}_name" value="{{ old($prefix.'_name', $settings[$prefix.'_name']) }}" maxlength="120">
                    @error($prefix.'_name')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="text-sm" for="c-{{ $prefix }}-title">{{ $role }} : fonction affichée</label>
                    <input id="c-{{ $prefix }}-title" type="text" name="{{ $prefix }}_title" value="{{ old($prefix.'_title', $settings[$prefix.'_title']) }}" maxlength="160">
                    @error($prefix.'_title')<p class="error">{{ $message }}</p>@enderror
                </div>
            @endforeach
            <div style="grid-column:1/-1;display:flex;justify-content:flex-end">
                <button type="submit" class="btn btn-primary">Enregistrer le certificat</button>
            </div>
        </form>
    </article>

    <article class="card card-flush" style="margin-top:16px">
        <div class="card-head"><h2>Abonnements</h2></div>
        @if($plans->count())
            <div class="list">
                @foreach($plans as $plan)
                    <div class="list-item" style="align-items:flex-start;flex-wrap:wrap">
                        <div class="grow">
                            <strong style="display:inline">{{ $plan->name }}</strong>
                            @unless($plan->is_active)<x-admin.badge status="inactive" />@endunless
                            <div class="text-sm text-muted">{{ $plan->priceLabel() }} · {{ $plan->durationLabel() }}</div>
                        </div>
                        <form method="post" action="{{ route('admin.academy.settings.plans.destroy', $plan) }}" onsubmit="return confirm('Supprimer cet abonnement ?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm"><x-admin.icon name="trash" :size="15" /> Supprimer</button>
                        </form>
                        <details style="flex-basis:100%" @if(old('_form') === 'p'.$plan->id && $errors->any()) open @endif>
                            <summary class="btn btn-secondary btn-sm" style="cursor:pointer">Modifier</summary>
                            <form method="post" action="{{ route('admin.academy.settings.plans.update', $plan) }}" class="grid" style="gap:12px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));margin-top:10px">
                                @csrf
                                @method('PUT')
                                @include('admin.academy._plan-fields', ['plan' => $plan, 'prefix' => 'p'.$plan->id])
                                <div style="grid-column:1/-1;display:flex;justify-content:flex-end"><button type="submit" class="btn btn-primary btn-sm">Enregistrer</button></div>
                            </form>
                        </details>
                    </div>
                @endforeach
            </div>
        @else
            <x-admin.empty icon="layers" title="Aucun abonnement" text="Ajoutez une formule (mensuelle, annuelle…) donnant accès à tous les cours inclus dans les abonnements." />
        @endif

        <div style="padding:16px;border-top:1px solid var(--line)">
            <details @if(old('_form') === 'np' && $errors->any()) open @endif>
                <summary class="btn btn-primary btn-sm" style="cursor:pointer"><x-admin.icon name="plus" :size="14" /> Ajouter un abonnement</summary>
                <form method="post" action="{{ route('admin.academy.settings.plans.store') }}" class="grid" style="gap:12px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));margin-top:10px">
                    @csrf
                    @include('admin.academy._plan-fields', ['plan' => null, 'prefix' => 'np'])
                    <div style="grid-column:1/-1;display:flex;justify-content:flex-end"><button type="submit" class="btn btn-primary btn-sm">Ajouter</button></div>
                </form>
            </details>
        </div>
    </article>
@endsection
