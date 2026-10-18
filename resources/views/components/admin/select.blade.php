{{-- options: [valeur => libelle] ; parents: [valeur => id_parent] (pour les selects dependants) --}}
@props(['name', 'label', 'options' => [], 'value' => null, 'required' => false, 'hint' => null, 'placeholder' => 'Sélectionner…', 'parents' => [], 'span' => false])
@php($current = (string) old($name, $value))
<div class="field {{ $span ? 'span-2' : '' }} {{ $errors->has($name) ? 'has-error' : '' }}">
    <label for="f-{{ $name }}">{{ $label }} @if($required)<span class="req">*</span>@endif</label>
    <select id="f-{{ $name }}" name="{{ $name }}" @if($required) required @endif {{ $attributes }}>
        @if($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}"
                    @if(isset($parents[$optionValue])) data-parent="{{ $parents[$optionValue] }}" @endif
                    @selected($current === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    @if($hint)<p class="hint">{{ $hint }}</p>@endif
    @error($name)<p class="error">{{ $message }}</p>@enderror
</div>
