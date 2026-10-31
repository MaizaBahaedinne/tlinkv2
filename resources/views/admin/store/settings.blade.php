@extends('admin.layouts.app')

@section('title', 'Réglages boutique')
@section('section', 'Tunivisions Stores')

@section('content')
    <x-admin.page-header title="Réglages, livraison et paiement" subtitle="Ouvrez la boutique, choisissez les modes de livraison et de paiement proposés aux membres." />

    <article class="card">
        <h2 class="card-title">Boutique</h2>
        <form method="post" action="{{ route('admin.store.settings.update') }}" class="form-grid">
            @csrf
            @method('PUT')
            <div class="field span-2">
                <label style="font-weight:400"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $settings['enabled']))> <strong>Boutique ouverte</strong> — les membres voient l’onglet « Boutique » et peuvent commander</label>
                <p class="hint">Avant d’ouvrir : ajoutez des articles avec du stock, activez au moins un mode de livraison et un mode de paiement.</p>
            </div>
            <x-admin.input name="name" label="Nom de la boutique" :value="$settings['name']" required maxlength="80" />
            <x-admin.input name="tagline" label="Accroche" :value="$settings['tagline']" maxlength="200" />
            <x-admin.input name="return_days" label="Délai de retour (jours après la livraison)" type="number" min="0" max="365" :value="$settings['return_days']" required />
            <x-admin.input name="contact_email" label="E-mail de contact" type="email" :value="$settings['contact_email']" maxlength="190" />
            <x-admin.input name="contact_phone" label="Téléphone de contact" :value="$settings['contact_phone']" maxlength="40" />
            <x-admin.textarea name="return_policy" label="Conditions de retour (affichées aux membres)" :value="$settings['return_policy']" rows="4" maxlength="5000" />
            <div class="span-2"><button type="submit" class="btn btn-primary">Enregistrer</button></div>
        </form>
    </article>

    <article class="card card-flush" style="margin-top:20px">
        <div class="card-head"><h2 class="card-title">Modes de paiement</h2></div>
        <p class="hint" style="padding:0 16px">Activez ceux que vous proposez. Les paiements sont validés à la main depuis la commande (paiement en ligne : non branché pour l’instant).</p>
        <div class="list">
            @foreach($payments as $method)
                <form method="post" action="{{ route('admin.store.settings.payment.update', $method) }}" class="list-item form-grid" style="align-items:end">
                    @csrf
                    @method('PUT')
                    <div class="field"><label>Nom</label><input type="text" name="name" value="{{ $method->name }}" maxlength="100" required></div>
                    <div class="field">
                        <label>S’applique à</label>
                        <select name="applies_to">
                            @foreach(\App\Models\StorePaymentMethod::APPLIES_TO as $value => $label)
                                <option value="{{ $value }}" @selected($method->applies_to === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field span-2"><label>Consignes affichées au membre</label><textarea name="instructions" rows="2" maxlength="3000">{{ $method->instructions }}</textarea></div>
                    <div class="field"><label style="font-weight:400"><input type="checkbox" name="is_active" value="1" @checked($method->is_active)> Activé</label></div>
                    <div class="field"><label>Ordre</label><input type="number" name="sort_order" value="{{ $method->sort_order }}" min="0"></div>
                    <div class="span-2"><button type="submit" class="btn btn-secondary btn-sm">Enregistrer « {{ $method->name }} »</button></div>
                </form>
            @endforeach
        </div>
    </article>

    <article class="card card-flush" style="margin-top:20px">
        <div class="card-head"><h2 class="card-title">Modes de livraison</h2></div>
        <div class="list">
            @foreach($deliveries as $method)
                <details class="list-item" style="display:block">
                    <summary style="cursor:pointer">
                        <strong style="display:inline">{{ $method->name }}</strong>
                        <x-admin.badge :status="$method->is_active ? 'active' : 'inactive'" :label="$method->is_active ? 'Activé' : 'Désactivé'" />
                        <span class="text-sm text-muted">· {{ \App\Models\StoreDeliveryMethod::KINDS[$method->kind] }} · {{ $method->isPickup() ? 'gratuit' : \App\Support\Store\StoreSettings::money((float) $method->fee) }}</span>
                    </summary>
                    <form method="post" action="{{ route('admin.store.settings.delivery.update', $method) }}" class="form-grid" style="margin-top:12px">
                        @csrf
                        @method('PUT')
                        @include('admin.store._delivery-fields', ['method' => $method, 'governorates' => $governorates])
                        <div class="span-2"><button type="submit" class="btn btn-secondary btn-sm">Enregistrer</button></div>
                    </form>
                    <form method="post" action="{{ route('admin.store.settings.delivery.destroy', $method) }}" onsubmit="return confirm('Supprimer ce mode de livraison ?')" style="margin-top:8px">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm"><x-admin.icon name="trash" :size="15" /> Supprimer</button>
                    </form>
                </details>
            @endforeach
        </div>

        <details style="padding:16px">
            <summary style="cursor:pointer;font-weight:700"><x-admin.icon name="plus" :size="15" /> Ajouter un mode de livraison</summary>
            <form method="post" action="{{ route('admin.store.settings.delivery.store') }}" class="form-grid" style="margin-top:12px">
                @csrf
                @include('admin.store._delivery-fields', ['method' => new \App\Models\StoreDeliveryMethod(['kind' => 'home', 'is_active' => true]), 'governorates' => $governorates])
                <div class="span-2"><button type="submit" class="btn btn-primary btn-sm">Ajouter</button></div>
            </form>
        </details>
    </article>
@endsection
