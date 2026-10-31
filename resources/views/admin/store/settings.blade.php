@extends('admin.layouts.app')

@section('title', 'Réglages boutique')
@section('section', 'Tunivisions Stores')

@section('content')
    <x-admin.page-header title="Réglages, livraison et paiement" subtitle="Ouvrez la boutique, puis choisissez les modes de livraison et de paiement proposés aux membres." />

    @include('admin.store._tabs')

    <article class="card">
        <div class="card-head">
            <div>
                <h2>Boutique</h2>
                <p class="sub">Informations générales et conditions de retour.</p>
            </div>
            <span class="badge badge-{{ $settings['enabled'] ? 'success' : 'warning' }}">{{ $settings['enabled'] ? 'Ouverte' : 'Fermée' }}</span>
        </div>
        <form method="post" action="{{ route('admin.store.settings.update') }}">
            @csrf
            @method('PUT')
            <div class="card-body form-grid">
                <div class="field span-2">
                    <label class="check check-card">
                        <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $settings['enabled']))>
                        <span>Boutique ouverte aux membres<small>L’onglet « Boutique » apparaît et les membres peuvent commander. Avant d’ouvrir : articles avec du stock, un mode de livraison et un mode de paiement activés.</small></span>
                    </label>
                </div>
                <x-admin.input name="name" label="Nom de la boutique" :value="$settings['name']" required maxlength="80" />
                <x-admin.input name="tagline" label="Accroche" :value="$settings['tagline']" maxlength="200" />
                <x-admin.input name="return_days" label="Délai de retour (jours après la livraison)" type="number" min="0" max="365" :value="$settings['return_days']" required />
                <x-admin.input name="contact_email" label="E-mail de contact" type="email" :value="$settings['contact_email']" maxlength="190" />
                <x-admin.input name="contact_phone" label="Téléphone de contact" type="tel" :value="$settings['contact_phone']" maxlength="40" />
                <x-admin.textarea name="return_policy" label="Conditions de retour" :value="$settings['return_policy']" rows="4" maxlength="5000" hint="Affichées aux membres sur leurs commandes." />
            </div>
            <div class="form-actions"><button type="submit" class="btn btn-primary">Enregistrer</button></div>
        </form>
    </article>

    <article class="card card-flush">
        <div class="card-head">
            <div>
                <h2>Modes de livraison</h2>
                <p class="sub">{{ $deliveries->where('is_active', true)->count() }} actif(s) sur {{ $deliveries->count() }}. Frais de base, gratuité au-delà d’un seuil et tarif par gouvernorat.</p>
            </div>
        </div>

        @foreach($deliveries as $method)
            <details class="method">
                <summary>
                    <div class="grow">
                        <strong>{{ $method->name }}</strong>
                        <span class="text-sm text-muted">
                            {{ \App\Models\StoreDeliveryMethod::KINDS[$method->kind] ?? $method->kind }}
                            · {{ $method->isPickup() ? 'gratuit' : \App\Support\Store\StoreSettings::money((float) $method->fee) }}@if($method->free_above !== null && ! $method->isPickup()) (offert dès {{ \App\Support\Store\StoreSettings::money((float) $method->free_above) }})@endif
                            @if($method->estimated_delay) · {{ $method->estimated_delay }}@endif
                        </span>
                    </div>
                    <span class="badge badge-{{ $method->is_active ? 'success' : 'neutral' }}">{{ $method->is_active ? 'Activé' : 'Désactivé' }}</span>
                </summary>
                <div class="card-body">
                    <form method="post" action="{{ route('admin.store.settings.delivery.update', $method) }}" class="form-grid">
                        @csrf
                        @method('PUT')
                        @include('admin.store._delivery-fields', ['method' => $method, 'governorates' => $governorates])
                        <div class="span-2 method-actions">
                            <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
                            <button type="submit" form="del-delivery-{{ $method->id }}" class="btn btn-danger btn-sm"><x-admin.icon name="trash" :size="15" /> Supprimer</button>
                        </div>
                    </form>
                    <form id="del-delivery-{{ $method->id }}" method="post" action="{{ route('admin.store.settings.delivery.destroy', $method) }}" onsubmit="return confirm('Supprimer ce mode de livraison ? Les commandes passées gardent leurs informations.')">
                        @csrf
                        @method('DELETE')
                    </form>
                </div>
            </details>
        @endforeach

        <details class="method">
            <summary>
                <div class="grow"><strong><x-admin.icon name="plus" :size="15" /> Ajouter un mode de livraison</strong></div>
            </summary>
            <div class="card-body">
                <form method="post" action="{{ route('admin.store.settings.delivery.store') }}" class="form-grid">
                    @csrf
                    @include('admin.store._delivery-fields', ['method' => new \App\Models\StoreDeliveryMethod(['kind' => 'home', 'is_active' => true]), 'governorates' => $governorates])
                    <div class="span-2"><button type="submit" class="btn btn-primary btn-sm">Ajouter</button></div>
                </form>
            </div>
        </details>
    </article>

    <article class="card card-flush">
        <div class="card-head">
            <div>
                <h2>Modes de paiement</h2>
                <p class="sub">{{ $payments->where('is_active', true)->count() }} actif(s) sur {{ $payments->count() }}. Les paiements se valident à la main depuis la commande.</p>
            </div>
        </div>

        @foreach($payments as $method)
            <details class="method">
                <summary>
                    <div class="grow">
                        <strong>{{ $method->name }}</strong>
                        <span class="text-sm text-muted">{{ \App\Models\StorePaymentMethod::APPLIES_TO[$method->applies_to] ?? $method->applies_to }}</span>
                    </div>
                    <span class="badge badge-{{ $method->is_active ? 'success' : 'neutral' }}">{{ $method->is_active ? 'Activé' : 'Désactivé' }}</span>
                </summary>
                <form method="post" action="{{ route('admin.store.settings.payment.update', $method) }}" class="card-body form-grid">
                    @csrf
                    @method('PUT')
                    <div class="field">
                        <label for="pm-{{ $method->id }}-name">Nom <span class="req">*</span></label>
                        <input id="pm-{{ $method->id }}-name" type="text" name="name" value="{{ $method->name }}" maxlength="100" required>
                    </div>
                    <div class="field">
                        <label for="pm-{{ $method->id }}-applies">S’applique à</label>
                        <select id="pm-{{ $method->id }}-applies" name="applies_to">
                            @foreach(\App\Models\StorePaymentMethod::APPLIES_TO as $value => $label)
                                <option value="{{ $value }}" @selected($method->applies_to === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field span-2">
                        <label for="pm-{{ $method->id }}-instructions">Consignes affichées au membre</label>
                        <textarea id="pm-{{ $method->id }}-instructions" name="instructions" rows="2" maxlength="3000" placeholder="RIB, délai de règlement, personne à contacter…">{{ $method->instructions }}</textarea>
                    </div>
                    <div class="field">
                        <label for="pm-{{ $method->id }}-sort">Ordre d’affichage</label>
                        <input id="pm-{{ $method->id }}-sort" type="number" name="sort_order" value="{{ $method->sort_order }}" min="0">
                    </div>
                    <div class="field">
                        <label class="check check-card">
                            <input type="checkbox" name="is_active" value="1" @checked($method->is_active)>
                            <span>Activé<small>Proposé aux membres.</small></span>
                        </label>
                    </div>
                    <div class="span-2"><button type="submit" class="btn btn-primary btn-sm">Enregistrer</button></div>
                </form>
            </details>
        @endforeach

        <p class="card-note">Le paiement en ligne (carte, Konnect, Flouci…) n’est pas encore branché : il demande les identifiants marchands du prestataire.</p>
    </article>
@endsection
