@php
    $isNew = ! $speaker->exists;
@endphp
<form method="post" action="{{ $action }}" class="form-grid" enctype="multipart/form-data">
    @csrf
    @if(! $isNew) @method('PUT') @endif
    <div class="field"><label>Nom <span class="req">*</span></label><input name="name" value="{{ $speaker->name }}" required maxlength="160"></div>
    <div class="field">
        <label>Rôle <span class="req">*</span></label>
        <select name="kind" required>
            @foreach(\App\Models\EventSpeaker::KINDS as $code => $label)
                <option value="{{ $code }}" @selected($speaker->kind === $code)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label>Origine <span class="req">*</span></label>
        <select name="origin" required>
            @foreach(\App\Models\EventSpeaker::ORIGINS as $code => $label)
                <option value="{{ $code }}" @selected($speaker->origin === $code)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="field"><label>E-mail du compte (Tunivisions)</label><input type="email" name="user_email" value="{{ $speaker->user?->email }}" maxlength="255" placeholder="Optionnel"></div>
    <div class="field"><label>Organisation</label><input name="organization" value="{{ $speaker->organization }}" maxlength="160"></div>
    <div class="field"><label>Fonction</label><input name="job_title" value="{{ $speaker->job_title }}" maxlength="160"></div>
    <div class="field"><label>Site / profil</label><input type="url" name="website_url" value="{{ $speaker->website_url }}" maxlength="500" placeholder="https://"></div>
    <div class="field">
        <label>Photo</label>
        <input type="file" name="photo" accept="image/png,image/jpeg,image/webp">
        @if(! $isNew && $speaker->photoUrl())
            <label class="check" style="margin-top:6px"><input type="checkbox" name="photo_remove" value="1"> Supprimer la photo</label>
        @endif
    </div>
    <div class="field span-2"><label>Biographie</label><textarea name="bio" rows="3" maxlength="2000">{{ $speaker->bio }}</textarea></div>
    <div class="form-actions span-2"><button type="submit" class="btn btn-primary"><x-admin.icon name="check" :size="16" /> {{ $isNew ? 'Ajouter l’intervenant' : 'Enregistrer' }}</button></div>
</form>
