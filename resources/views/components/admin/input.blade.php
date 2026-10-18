@props(['name', 'label', 'type' => 'text', 'value' => null, 'required' => false, 'hint' => null, 'placeholder' => null, 'maxlength' => null, 'span' => false])
<div class="field {{ $span ? 'span-2' : '' }} {{ $errors->has($name) ? 'has-error' : '' }}">
    <label for="f-{{ $name }}">{{ $label }} @if($required)<span class="req">*</span>@endif</label>
    <input id="f-{{ $name }}" type="{{ $type }}" name="{{ $name }}" value="{{ old($name, $value) }}"
           @if($required) required @endif
           @if($placeholder) placeholder="{{ $placeholder }}" @endif
           @if($maxlength) maxlength="{{ $maxlength }}" @endif
           {{ $attributes }}>
    @if($hint)<p class="hint">{{ $hint }}</p>@endif
    @error($name)<p class="error">{{ $message }}</p>@enderror
</div>
