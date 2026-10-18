@props(['name', 'label', 'value' => null, 'required' => false, 'hint' => null, 'placeholder' => null, 'maxlength' => null, 'rows' => 4, 'span' => true])
<div class="field {{ $span ? 'span-2' : '' }} {{ $errors->has($name) ? 'has-error' : '' }}">
    <label for="f-{{ $name }}">{{ $label }} @if($required)<span class="req">*</span>@endif</label>
    <textarea id="f-{{ $name }}" name="{{ $name }}" rows="{{ $rows }}"
              @if($required) required @endif
              @if($placeholder) placeholder="{{ $placeholder }}" @endif
              @if($maxlength) maxlength="{{ $maxlength }}" @endif
              {{ $attributes }}>{{ old($name, $value) }}</textarea>
    @if($hint)<p class="hint">{{ $hint }}</p>@endif
    @error($name)<p class="error">{{ $message }}</p>@enderror
</div>
