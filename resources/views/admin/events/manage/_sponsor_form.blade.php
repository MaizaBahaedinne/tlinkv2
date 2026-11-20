@php
    $isNew = ! $sponsor->exists;
@endphp
<form method="post" action="{{ $action }}" class="form-grid" enctype="multipart/form-data">
    @csrf
    @if(! $isNew) @method('PUT') @endif
    <div class="field"><label>Nom <span class="req">*</span></label><input name="name" value="{{ $sponsor->name }}" required maxlength="160"></div>
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
