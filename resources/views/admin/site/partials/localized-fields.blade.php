@php
    // Champs traduisibles de l'onglet ($tab) : une section par langue, puis des sous-sections thématiques.
    $tabKeys = collect($localized)->filter(fn ($type, $key) => \App\Support\SiteSettings::tabOf($key) === $tab);
@endphp
@foreach($locales as $code => $meta)
    <section class="lang-section" aria-labelledby="lang-{{ $tab }}-{{ $code }}">
        <h3 class="lang-title" id="lang-{{ $tab }}-{{ $code }}">
            <span class="lang-badge">{{ strtoupper($code) }}</span> {{ $meta['name'] }}
            @if($code !== array_key_first($locales))
                <small>Vide : reprend le français</small>
            @endif
        </h3>
        <div class="lang-body">
            @foreach($tabKeys as $key => $type)
                @if(isset(\App\Support\SiteSettings::SECTIONS[$key]))
                    <h4 class="lang-sub">{{ \App\Support\SiteSettings::SECTIONS[$key] }}</h4>
                @endif
                @php
                    $field = $key.'.'.$code;
                @endphp
                <div class="field {{ $errors->has($field) ? 'has-error' : '' }}">
                    <label for="f-{{ $key }}-{{ $code }}">{{ \App\Support\SiteSettings::LABELS[$key] }}</label>
                    @if($type === 'textarea')
                        <textarea id="f-{{ $key }}-{{ $code }}" name="{{ $key }}[{{ $code }}]" rows="4" maxlength="3000" dir="{{ $meta['dir'] }}">{{ old($field, $site->stored($key, $code)) }}</textarea>
                    @else
                        <input id="f-{{ $key }}-{{ $code }}" type="text" name="{{ $key }}[{{ $code }}]" maxlength="200" dir="{{ $meta['dir'] }}" value="{{ old($field, $site->stored($key, $code)) }}">
                    @endif
                    @error($field)<p class="error">{{ $message }}</p>@enderror
                </div>
            @endforeach
        </div>
    </section>
@endforeach
