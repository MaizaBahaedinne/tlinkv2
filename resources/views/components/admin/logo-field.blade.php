{{-- Champ logo : bouton « Choisir un logo », aperçu immédiat, contrôle du format et de la taille avant l'envoi. --}}
@props(['current' => null, 'name' => '', 'form' => null, 'icon' => 'graduation', 'maxMb' => 3, 'hint' => null, 'label' => 'Logo'])
@php
    $inputId = 'logo-'.\Illuminate\Support\Str::random(6);
    $showError = $errors->has('logo') && ($form === null || old('_form') === $form);
@endphp
<div class="field logo-field {{ $showError ? 'has-error' : '' }}" data-logo-field data-max-mb="{{ $maxMb }}">
    <label for="{{ $inputId }}">{{ $label }}</label>
    <div class="logo-field-row">
        <span class="logo-field-preview">
            <img data-logo-preview data-original="{{ $current }}" src="{{ $current }}" alt="Logo {{ $name }}" @if(! $current) hidden @endif>
            <span data-logo-placeholder @if($current) hidden @endif><x-admin.icon :name="$icon" :size="22" /></span>
        </span>
        <div class="logo-field-actions">
            <button type="button" class="btn btn-secondary btn-sm" data-logo-pick><x-admin.icon name="edit" :size="15" /> {{ $current ? 'Changer le logo' : 'Choisir un logo' }}</button>
            <span class="text-sm text-muted" data-logo-name>{{ $hint ?? 'jpg, png ou webp — '.$maxMb.' Mo max' }}</span>
            @if($current)
                <label class="check"><input type="checkbox" name="remove_logo" value="1" data-logo-remove> <span class="text-sm">Retirer le logo</span></label>
            @endif
        </div>
    </div>
    <input id="{{ $inputId }}" type="file" name="logo" accept="image/png,image/jpeg,image/webp" data-logo-input hidden>
    <p class="error" data-logo-error @if(! $showError) hidden @endif>{{ $showError ? $errors->first('logo') : '' }}</p>
</div>
