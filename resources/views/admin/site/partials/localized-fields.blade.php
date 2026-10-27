@php
    // Champs traduisibles à afficher dans cet onglet ($tab) : un bloc par champ, une saisie par langue.
@endphp
@foreach($localized as $key => $type)
    @continue(\App\Support\SiteSettings::tabOf($key) !== $tab)
    @if(isset(\App\Support\SiteSettings::SECTIONS[$key]))
        <h4 style="margin:24px 0 10px">{{ \App\Support\SiteSettings::SECTIONS[$key] }}</h4>
    @endif
    <div class="form-grid" style="margin-bottom:16px">
        @foreach($locales as $code => $meta)
            @php
                $field = $key.'.'.$code;
            @endphp
            <div class="field span-2 {{ $errors->has($field) ? 'has-error' : '' }}">
                <label for="f-{{ $key }}-{{ $code }}">{{ \App\Support\SiteSettings::LABELS[$key] }} ({{ $meta['name'] }})</label>
                @if($type === 'textarea')
                    <textarea id="f-{{ $key }}-{{ $code }}" name="{{ $key }}[{{ $code }}]" rows="4" maxlength="3000" dir="{{ $meta['dir'] }}">{{ old($field, $site->stored($key, $code)) }}</textarea>
                @else
                    <input id="f-{{ $key }}-{{ $code }}" type="text" name="{{ $key }}[{{ $code }}]" maxlength="200" dir="{{ $meta['dir'] }}" value="{{ old($field, $site->stored($key, $code)) }}">
                @endif
                @error($field)<p class="error">{{ $message }}</p>@enderror
            </div>
        @endforeach
    </div>
@endforeach
