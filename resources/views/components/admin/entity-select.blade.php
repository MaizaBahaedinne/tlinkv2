{{-- groups: [libelle du type => [cle de l'entite => libelle]] --}}
@props(['name' => 'entity', 'label' => 'Entité', 'groups' => [], 'value' => null, 'required' => false, 'hint' => null, 'placeholder' => 'Choisir une entité', 'span' => false])
@php($current = (string) old($name, $value))
<div class="field {{ $span ? 'span-2' : '' }} {{ $errors->has($name) ? 'has-error' : '' }}">
    <label for="f-{{ $name }}">{{ $label }} @if($required)<span class="req">*</span>@endif</label>
    <select id="f-{{ $name }}" name="{{ $name }}" @if($required) required @endif {{ $attributes }}>
        @if($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach($groups as $groupLabel => $options)
            <optgroup label="{{ $groupLabel }}">
                @foreach($options as $optionValue => $optionLabel)
                    <option value="{{ $optionValue }}" @selected($current === (string) $optionValue)>{{ $optionLabel }}</option>
                @endforeach
            </optgroup>
        @endforeach
    </select>
    @if($hint)<p class="hint">{{ $hint }}</p>@endif
    @error($name)<p class="error">{{ $message }}</p>@enderror
</div>
