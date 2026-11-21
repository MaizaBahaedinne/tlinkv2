{{-- Inscrits importés (nom, prénom, téléphone) : chacun complète sa fiche depuis la page publique avec son numéro. --}}
@php
    $completionUrl = route('event.complete', $event);
    $doneCount = $imports->where('status', 'completed')->count();
@endphp

<article class="card card-flush" style="margin-bottom:22px">
    <div class="card-head">
        <div>
            <h2>Fiches à compléter</h2>
            <div class="sub">
                @if($imports->isEmpty())
                    Importez la liste des inscrits que vous connaissez (nom, prénom, téléphone) : chacun complète ensuite sa fiche avec son numéro.
                @else
                    {{ $doneCount }} fiche(s) complétée(s) sur {{ $imports->count() }}.
                @endif
            </div>
        </div>
        <button type="button" class="btn btn-primary" data-modal-open="registrations-import"><x-admin.icon name="plus" :size="16" /> Importer une liste</button>
    </div>

    @if(session('import_invalid'))
        <div class="alert alert-warning" role="status" style="margin:0 18px 12px">
            <x-admin.icon name="eye" />
            <span>Lignes ignorées (nom, prénom et téléphone attendus) :
                @foreach(session('import_invalid') as $line)<br><code>{{ $line }}</code>@endforeach
            </span>
        </div>
    @endif

    <div class="card-body" style="padding-top:0">
        <label for="completion-link" class="hint">Lien à envoyer aux participants (ils y saisissent leur numéro de téléphone)</label>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px">
            <input id="completion-link" type="text" readonly value="{{ $completionUrl }}" style="flex:1;min-width:240px" onfocus="this.select()">
            <button type="button" class="btn btn-secondary" onclick="navigator.clipboard.writeText(document.getElementById('completion-link').value).then(() => { this.textContent = 'Lien copié' })">Copier le lien</button>
            <a href="{{ $completionUrl }}" class="btn btn-ghost" target="_blank" rel="noopener noreferrer"><x-admin.icon name="external" :size="16" /> Ouvrir</a>
        </div>
    </div>

    @if($imports->isNotEmpty())
        <div class="table-wrap">
            <table class="tbl">
                <thead>
                    <tr><th>Nom</th><th>Prénom</th><th>Téléphone</th><th class="hide-mobile">Release</th><th class="hide-mobile">Parcours déclaré</th><th>Fiche</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach($imports as $entry)
                        @php
                            $declared = $entry->declared ?? [];
                            $role = collect([$declared['scope'] ?? null, $declared['club'] ?? null, $declared['position'] ?? null])->filter()->implode(' · ');
                            $joined = $declared['joined'] ?? null;
                        @endphp
                        <tr>
                            <td class="cell-main">{{ $entry->last_name }}</td>
                            <td>{{ $entry->first_name }}</td>
                            <td class="nowrap">{{ $entry->phone }}</td>
                            <td class="hide-mobile text-sm">{{ $entry->release?->name ?? '—' }}</td>
                            <td class="hide-mobile text-sm">
                                @if($role !== '')<div>{{ $role }}</div>@endif
                                @if($joined)<div class="text-muted">Depuis le mandat {{ $joined['mandate'] }} : {{ collect([$joined['scope'] ?? null, $joined['club'] ?? null, $joined['position'] ?? null])->filter()->implode(' · ') }}</div>@endif
                                @if($transport = $declared['transport'] ?? null)<div class="text-muted">Transport : {{ collect([$transport['region'] ?? null, $transport['point'] ?? ($transport['mode'] ?? null)])->filter()->implode(' · ') }}</div>@endif
                                @if($companions = $declared['companions'] ?? [])<div class="text-muted">Accompagnant(s) : {{ collect($companions)->map(fn ($c) => $c['name'].' ('.$c['relationship'].')')->implode(', ') }}</div>@endif
                                @if($role === '' && ! $joined && ! $transport)—@endif
                            </td>
                            <td>
                                @if($entry->isCompleted())
                                    <x-admin.badge status="confirmed" label="Complétée" />
                                @else
                                    <x-admin.badge status="pending" label="À compléter" />
                                @endif
                            </td>
                            <td>
                                @unless($entry->isCompleted())
                                    <form method="post" action="{{ route('admin.events.imports.destroy', [$event, $entry]) }}" data-confirm="Retirer {{ $entry->fullName() }} de la liste ?" data-confirm-title="Retirer cette fiche ?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-ghost btn-icon btn-sm" aria-label="Retirer de la liste"><x-admin.icon name="trash" :size="14" /></button>
                                    </form>
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</article>

<dialog id="registrations-import" class="modal" aria-labelledby="registrations-import-title" @if($errors->has('list') || $errors->has('release_id')) data-modal-autoopen @endif>
    <div class="modal-head">
        <h2 id="registrations-import-title">Importer une liste d’inscrits</h2>
        <button type="button" class="btn btn-ghost btn-sm" data-modal-close aria-label="Fermer">✕</button>
    </div>
    <form method="post" action="{{ route('admin.events.imports.store', $event) }}" class="modal-form">
        @csrf
        <div class="modal-body">
            <div class="field">
                <label for="import-list">Liste (une personne par ligne : Nom, Prénom, Téléphone)</label>
                <textarea id="import-list" name="list" rows="12" required placeholder="Dhmaied&#9;Farah&#9;58 048 528" style="font-family:monospace">{{ old('list') }}</textarea>
                <p class="hint">Copiez-collez les colonnes depuis votre tableur. Un même numéro n’est importé qu’une fois.</p>
                @error('list')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div class="field" style="margin-top:12px">
                <label for="import-release">Release</label>
                <select id="import-release" name="release_id">
                    <option value="">Première release active de l’événement</option>
                    @foreach($releases as $release)
                        <option value="{{ $release->id }}" @selected((string) old('release_id') === (string) $release->id)>{{ $release->name }} — {{ rtrim(rtrim(number_format((float) $release->price, 2, ',', ' '), '0'), ',') }} TND</option>
                    @endforeach
                </select>
                @error('release_id')<p class="error">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
            <button type="submit" class="btn btn-primary"><x-admin.icon name="plus" :size="16" /> Importer</button>
        </div>
    </form>
</dialog>
