@php
    $isNew = ! $sponsor->exists;
    $hasDirectory = $isNew && (($directorySponsors ?? collect())->isNotEmpty() || ($directoryPartners ?? collect())->isNotEmpty());
    $relationLabels = \App\Models\Sponsor::RELATIONS;
@endphp
<form method="post" action="{{ $action }}" class="form-grid" enctype="multipart/form-data">
    @csrf
    @if(! $isNew) @method('PUT') @endif
    @if($hasDirectory)
        <div class="field span-2">
            <label for="directory-pick">Choisir dans ma base de partenaires</label>
            <select id="directory-pick" name="directory">
                <option value="">— Saisir un nouveau sponsor / partenaire —</option>
                @if($directorySponsors->isNotEmpty())
                    <optgroup label="Carnet de partenaires">
                        @foreach($directorySponsors as $entry)
                            <option value="s:{{ $entry->id }}" @selected(old('directory') === 's:'.$entry->id)>{{ $entry->name }} — {{ $relationLabels[$entry->relation] ?? $entry->relation }}{{ $entry->city ? ' ('.$entry->city.')' : '' }}</option>
                        @endforeach
                    </optgroup>
                @endif
                @if($directoryPartners->isNotEmpty())
                    <optgroup label="Partenaires du site (avec logo)">
                        @foreach($directoryPartners as $entry)
                            <option value="p:{{ $entry->id }}" @selected(old('directory') === 'p:'.$entry->id)>{{ $entry->name }}</option>
                        @endforeach
                    </optgroup>
                @endif
            </select>
            <p class="hint">Le nom, le site web et le logo (partenaires du site) sont repris automatiquement ; ce que vous saisissez ci-dessous reste prioritaire.</p>
        </div>
    @endif
    <div class="field"><label>Nom @if(! $hasDirectory)<span class="req">*</span>@endif</label><input name="name" value="{{ $sponsor->name }}" @if(! $hasDirectory) required @endif maxlength="160" @if($hasDirectory) placeholder="Repris du choix ci-dessus" @endif></div>
    <div class="field">
        <label>Niveau <span class="req">*</span></label>
        <select name="level_id" required>
            @foreach($levels as $option)
                <option value="{{ $option->id }}" @selected($sponsor->level_id === $option->id)>{{ $option->name }} ({{ $option->kindLabel() }})</option>
            @endforeach
        </select>
    </div>
    <div class="field"><label>Site web</label><input type="url" name="website_url" value="{{ $sponsor->website_url }}" maxlength="500" placeholder="https://"></div>
    <div class="field">
        <label>Logo</label>
        <input type="file" name="logo" accept="image/png,image/jpeg,image/webp">
        @if(! $isNew && $sponsor->logoUrl())
            <label class="check" style="margin-top:6px"><input type="checkbox" name="logo_remove" value="1"> Supprimer le logo</label>
        @endif
    </div>
    <div class="field span-2"><label>Description</label><textarea name="description" rows="2" maxlength="1000">{{ $sponsor->description }}</textarea></div>
    <div class="form-actions span-2"><button type="submit" class="btn btn-primary"><x-admin.icon name="check" :size="16" /> {{ $isNew ? 'Ajouter' : 'Enregistrer' }}</button></div>
</form>
