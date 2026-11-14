@extends('admin.layouts.app')

@section('title', $sponsor->name)
@section('section', 'Marketing et partenariats')

@section('content')
    <x-admin.page-header :title="$sponsor->name" :subtitle="(\App\Models\Sponsor::RELATIONS[$sponsor->relation] ?? '').' · '.(\App\Models\Sponsor::STATUSES[$sponsor->status] ?? '')">
        <x-slot:actions>
            <a href="{{ route('admin.sponsors.index') }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Carnet</a>
            @if($canDelete)
                <x-admin.delete-button :action="route('admin.sponsors.destroy', $sponsor)" :message="'« '.$sponsor->name.' » sera retiré du carnet avec ses contacts et ses pistes.'" title="Supprimer cette fiche ?" />
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    @if($otherClubs->isNotEmpty())
        <div class="alert alert-info" role="status" style="margin-bottom:16px">
            <x-admin.icon name="flag" />
            <span>Une démarche est déjà en cours auprès de cette organisation : {{ $otherClubs->implode(', ') }}. Rapprochez-vous de cette équipe avant de la contacter.</span>
        </div>
    @endif

    <div class="grid-2" style="align-items:start">
        <div style="display:grid;gap:16px">
            <article class="card">
                <div class="card-head"><div><h2>Fiche</h2><p class="sub">Ajoutée par {{ $sponsor->creator?->name ?? '—' }} le {{ $sponsor->created_at->format('d/m/Y') }}.</p></div></div>
                <div class="card-body">
                    <form method="post" action="{{ route('admin.sponsors.update', $sponsor) }}" style="display:grid;gap:14px">
                        @csrf @method('PUT')
                        <div class="form-grid">
                            <x-admin.input name="name" label="Nom de l’organisation" :value="$sponsor->name" :required="true" maxlength="190" />
                            <x-admin.select name="relation" label="Type" :options="\App\Models\Sponsor::RELATIONS" :value="$sponsor->relation" :required="true" :placeholder="null" />
                            <x-admin.select name="status" label="Statut" :options="\App\Models\Sponsor::STATUSES" :value="$sponsor->status" :required="true" :placeholder="null" />
                            <x-admin.input name="sector" label="Secteur" :value="$sponsor->sector" maxlength="120" />
                            <x-admin.input name="website" type="url" label="Site web" :value="$sponsor->website" maxlength="255" placeholder="https://" />
                            <x-admin.input name="email" type="email" label="E-mail général" :value="$sponsor->email" maxlength="190" />
                            <x-admin.input name="phone" label="Téléphone" :value="$sponsor->phone" maxlength="40" />
                            <x-admin.input name="city" label="Ville" :value="$sponsor->city" maxlength="120" />
                            <x-admin.input name="address" label="Adresse" :value="$sponsor->address" maxlength="255" :span="true" />
                            <x-admin.select name="owner_club_id" label="Club qui a l’entrée" :options="$clubOptions" :value="$sponsor->owner_club_id" placeholder="Aucun (Fondation)" />
                            <x-admin.textarea name="notes" label="Notes (historique, conditions, ce qui a marché…)" :value="$sponsor->notes" rows="4" maxlength="5000" />
                        </div>
                        @if($canUpdate)<div style="display:flex;justify-content:flex-end"><button class="btn btn-primary" type="submit"><x-admin.icon name="check" :size="16" /> Enregistrer</button></div>@endif
                    </form>
                </div>
            </article>

            <article class="card card-flush">
                <div class="card-head"><div><h2>Interlocuteurs</h2><p class="sub">{{ $sponsor->contacts->count() }} contact(s).</p></div></div>
                @foreach($sponsor->contacts as $contact)
                    <details style="border-top:1px solid var(--line);padding:10px 16px">
                        <summary style="cursor:pointer"><strong>{{ $contact->name }}</strong>@if($contact->is_primary) <span class="badge badge-info">Principal</span>@endif <span class="text-sm text-muted">{{ collect([$contact->job_title, $contact->email, $contact->phone])->filter()->implode(' · ') }}</span></summary>
                        <form method="post" action="{{ route('admin.sponsors.contacts.update', [$sponsor, $contact]) }}" class="form-grid" style="margin-top:10px">
                            @csrf @method('PUT')
                            <div class="field"><label>Nom</label><input type="text" name="name" value="{{ $contact->name }}" maxlength="160" required></div>
                            <div class="field"><label>Fonction</label><input type="text" name="job_title" value="{{ $contact->job_title }}" maxlength="160"></div>
                            <div class="field"><label>E-mail</label><input type="email" name="email" value="{{ $contact->email }}" maxlength="190"></div>
                            <div class="field"><label>Téléphone</label><input type="text" name="phone" value="{{ $contact->phone }}" maxlength="40"></div>
                            <div class="field span-2"><label>Notes</label><textarea name="notes" rows="2" maxlength="2000">{{ $contact->notes }}</textarea></div>
                            <label class="check span-2"><input type="checkbox" name="is_primary" value="1" @checked($contact->is_primary)> Interlocuteur principal</label>
                            <div class="span-2" style="display:flex;gap:8px;justify-content:flex-end">
                                <button class="btn btn-primary btn-sm" type="submit">Enregistrer</button>
                            </div>
                        </form>
                        <form method="post" action="{{ route('admin.sponsors.contacts.destroy', [$sponsor, $contact]) }}" data-confirm="Retirer cet interlocuteur ?" data-confirm-title="Supprimer ?" style="margin-top:6px">
                            @csrf @method('DELETE')
                            <button class="btn btn-danger btn-sm" type="submit"><x-admin.icon name="trash" :size="15" /> Retirer</button>
                        </form>
                    </details>
                @endforeach
                @if($canUpdate)
                    <form method="post" action="{{ route('admin.sponsors.contacts.store', $sponsor) }}" class="form-grid" style="padding:14px 16px;border-top:1px solid var(--line)">
                        @csrf
                        <div class="field"><label for="c-name">Nouvel interlocuteur <span class="req">*</span></label><input id="c-name" type="text" name="name" maxlength="160" required></div>
                        <div class="field"><label for="c-job">Fonction</label><input id="c-job" type="text" name="job_title" maxlength="160"></div>
                        <div class="field"><label for="c-mail">E-mail</label><input id="c-mail" type="email" name="email" maxlength="190"></div>
                        <div class="field"><label for="c-phone">Téléphone</label><input id="c-phone" type="text" name="phone" maxlength="40"></div>
                        <div class="span-2" style="display:flex;justify-content:flex-end"><button class="btn btn-secondary" type="submit"><x-admin.icon name="plus" :size="16" /> Ajouter</button></div>
                    </form>
                @endif
            </article>
        </div>

        <article class="card card-flush">
            <div class="card-head"><div><h2>Pistes de votre périmètre</h2><p class="sub">{{ $leads->count() }} piste(s) avec cette organisation.</p></div></div>
            @foreach($leads as $lead)
                <details style="border-top:1px solid var(--line);padding:10px 16px">
                    <summary style="cursor:pointer">
                        <strong>{{ $lead->title }}</strong>
                        <span class="badge badge-{{ $lead->status === 'won' ? 'success' : ($lead->status === 'lost' ? 'danger' : 'info') }}">{{ \App\Models\SponsorLead::STATUSES[$lead->status] }}</span>
                        <span class="text-sm text-muted">{{ $lead->club?->title ?? 'Fondation' }}@if($lead->amount) · {{ number_format((float) $lead->amount, 0, ',', ' ') }} TND @endif</span>
                    </summary>
                    <form method="post" action="{{ route('admin.sponsor-leads.update', $lead) }}" style="margin-top:10px;display:grid;gap:10px">
                        @csrf @method('PUT')
                        @include('admin.sponsors._lead-fields', ['lead' => $lead, 'contacts' => $sponsor->contacts])
                        <div style="display:flex;justify-content:flex-end"><button class="btn btn-primary btn-sm" type="submit">Enregistrer</button></div>
                    </form>
                    <form method="post" action="{{ route('admin.sponsor-leads.destroy', $lead) }}" data-confirm="Supprimer cette piste ?" data-confirm-title="Supprimer ?" style="margin-top:6px">
                        @csrf @method('DELETE')
                        <button class="btn btn-danger btn-sm" type="submit"><x-admin.icon name="trash" :size="15" /> Supprimer</button>
                    </form>
                </details>
            @endforeach

            @if($canLead)
                <div class="card-body" style="border-top:1px solid var(--line)">
                    <h3 style="margin:0 0 10px">Lancer une piste</h3>
                    <form method="post" action="{{ route('admin.sponsor-leads.store') }}" style="display:grid;gap:10px">
                        @csrf
                        @include('admin.sponsors._lead-fields', ['lead' => new \App\Models\SponsorLead(), 'contacts' => $sponsor->contacts])
                        <div style="display:flex;justify-content:flex-end"><button class="btn btn-primary" type="submit"><x-admin.icon name="plus" :size="16" /> Ajouter la piste</button></div>
                    </form>
                </div>
            @endif
        </article>
    </div>
@endsection
