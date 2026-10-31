@if($editable)
    <p class="text-sm text-muted">Aucune photo n’est hébergée sur la plateforme : la preuve de l’activité est une publication de la page Facebook du club.</p>
    <div class="form-grid">
        <x-admin.input name="proof_url" type="url" label="Preuve de l’activité : publication Facebook" :value="$report->proofs()->first()?->url" required :maxlength="500" :span="true" placeholder="https://www.facebook.com/…" hint="Lien de la publication (photos, vidéo ou album) sur la page Facebook du club. Obligatoire pour soumettre." />
        <x-admin.input name="poster_url" type="url" label="Affiche de l’activité (lien, facultatif)" :value="$report->posters()->first()?->url" :maxlength="500" :span="true" placeholder="https://…" hint="Lien de la publication de l’affiche." />
    </div>

    <p class="text-sm text-muted" style="margin-top:16px">Retombées médias : publications Facebook et Instagram, passages TV et radio, articles de magazine.</p>
    @php
        $media = array_pad($mediaRows, max(3, count($mediaRows) + 1), []);
    @endphp
    <div class="field">
        <div data-rows="media">
            @foreach($media as $i => $row)
                <div class="form-grid" style="margin-top:8px" data-row>
                    <div class="field">
                        <select name="media[{{ $i }}][kind]" aria-label="Type de retombée">
                            <option value="">Type…</option>
                            @foreach(\App\Models\ActivityReport::MEDIA_KINDS as $kind => $label)<option value="{{ $kind }}" @selected(($row['kind'] ?? '') === $kind)>{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <div class="field"><input type="text" name="media[{{ $i }}][label]" maxlength="160" placeholder="Précision (chaîne, émission, magazine, date)" aria-label="Précision" value="{{ $row['label'] ?? '' }}"></div>
                    <div class="field span-2 {{ $errors->has('media.'.$i.'.url') ? 'has-error' : '' }}"><input type="url" name="media[{{ $i }}][url]" maxlength="500" placeholder="Lien (https://…)" aria-label="Lien" value="{{ $row['url'] ?? '' }}">@error('media.'.$i.'.url')<p class="error">{{ $message }}</p>@enderror</div>
                </div>
            @endforeach
        </div>
        <button type="button" class="btn btn-secondary btn-sm" data-add-row="media" style="margin-top:8px">+ Ajouter une retombée</button>
    </div>
@else
    <div class="text-sm">
        <p><strong>Preuve de l’activité</strong></p>
        @forelse($report->proofs() as $proof)
            @if($proof->href())<p><a href="{{ $proof->href() }}" target="_blank" rel="noopener noreferrer">Voir la publication Facebook</a></p>@endif
        @empty
            <p class="text-muted">Aucune.</p>
        @endforelse

        @foreach($report->posters() as $poster)
            @if($poster->href())<p style="margin-top:10px"><strong>Affiche</strong> · <a href="{{ $poster->href() }}" target="_blank" rel="noopener noreferrer">Voir</a></p>@endif
        @endforeach

        <p style="margin-top:14px"><strong>Retombées médias</strong></p>
        @forelse($report->mediaLinks() as $item)
            <p>{{ \App\Models\ActivityReport::MEDIA_KINDS[$item->kind] ?? $item->kind }}@if($item->label) — {{ $item->label }}@endif @if($item->url)· <a href="{{ $item->url }}" target="_blank" rel="noopener noreferrer">Voir</a>@endif</p>
        @empty
            <p class="text-muted">Aucune retombée déclarée.</p>
        @endforelse
    </div>
@endif
