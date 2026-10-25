@extends('admin.layouts.app')

@section('title', 'Site public')
@section('section', 'Site public')

@section('content')
    <x-admin.page-header title="Site public" subtitle="Textes et coordonnées affichés sur le site de la Fondation. Les actualités, événements, équipe et clubs proviennent directement de T.Link.">
        <x-slot:actions>
            <a href="{{ $publicUrl }}" target="_blank" rel="noopener" class="btn btn-secondary"><x-admin.icon name="external" :size="16" /> Voir le site</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form method="post" action="{{ route('admin.site.update') }}" class="card card-flush" style="max-width:860px" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="card-body">
            <h3 style="margin-top:0">Textes</h3>
            <p class="text-sm text-muted">Laissez vide pour utiliser le texte par défaut. Une langue vide reprend le français.</p>

            @foreach($localized as $key => $type)
                <div class="form-grid" style="margin-bottom:16px">
                    @foreach($locales as $code => $meta)
                        @php($field = $key.'.'.$code)
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

            <h3>Coordonnées et réseaux</h3>
            <div class="form-grid">
                <x-admin.input name="contact_email" type="email" label="E-mail de contact" :value="$site->stored('contact_email')" :maxlength="190" />
                <x-admin.input name="contact_phone" label="Téléphone" :value="$site->stored('contact_phone')" :maxlength="40" />
                <x-admin.input name="contact_address" label="Adresse" :value="$site->stored('contact_address')" :maxlength="255" :span="true" />
                <x-admin.input name="facebook_url" type="url" label="Facebook" :value="$site->stored('facebook_url')" placeholder="https://" :maxlength="255" />
                <x-admin.input name="instagram_url" type="url" label="Instagram" :value="$site->stored('instagram_url')" placeholder="https://" :maxlength="255" />
                <x-admin.input name="linkedin_url" type="url" label="LinkedIn" :value="$site->stored('linkedin_url')" placeholder="https://" :maxlength="255" />
                <x-admin.input name="youtube_url" type="url" label="YouTube" :value="$site->stored('youtube_url')" placeholder="https://" :maxlength="255" />
            </div>

            <h3>Visuels Hero (carrousel)</h3>
            <p class="text-sm text-muted">Le Hero affiche ces 3 images en fond plein écran, en rotation automatique (une image à la fois). Formats acceptés : jpg, png, webp (max 6 Mo).</p>
            <div class="form-grid">
                <div class="field span-2 {{ $errors->has('hero_bg_main_file') ? 'has-error' : '' }}">
                    <label for="f-hero-bg-main">Image 1 du carrousel Hero</label>
                    <input id="f-hero-bg-main" type="file" name="hero_bg_main_file" accept="image/png,image/jpeg,image/webp">
                    <p class="hint">Actuelle :</p>
                    <img src="{{ $site->heroImage('hero_bg_main', asset('logoFondation-white.webp')) }}" alt="Hero principal" style="margin-top:6px;max-width:100%;height:88px;object-fit:contain;background:#6e0019;border-radius:10px;padding:8px">
                    <label class="check" style="margin-top:8px"><input type="checkbox" name="hero_bg_main_remove" value="1"> Supprimer cette image et revenir au visuel par défaut</label>
                    @error('hero_bg_main_file')<p class="error">{{ $message }}</p>@enderror
                </div>

                <div class="field {{ $errors->has('hero_bg_mark_a_file') ? 'has-error' : '' }}">
                    <label for="f-hero-bg-mark-a">Image 2 du carrousel Hero</label>
                    <input id="f-hero-bg-mark-a" type="file" name="hero_bg_mark_a_file" accept="image/png,image/jpeg,image/webp">
                    <img src="{{ $site->heroImage('hero_bg_mark_a', asset('favicon-white-180x180.png')) }}" alt="Hero motif A" style="margin-top:6px;max-width:100%;height:72px;object-fit:contain;background:#6e0019;border-radius:10px;padding:8px">
                    <label class="check" style="margin-top:8px"><input type="checkbox" name="hero_bg_mark_a_remove" value="1"> Supprimer</label>
                    @error('hero_bg_mark_a_file')<p class="error">{{ $message }}</p>@enderror
                </div>

                <div class="field {{ $errors->has('hero_bg_mark_b_file') ? 'has-error' : '' }}">
                    <label for="f-hero-bg-mark-b">Image 3 du carrousel Hero</label>
                    <input id="f-hero-bg-mark-b" type="file" name="hero_bg_mark_b_file" accept="image/png,image/jpeg,image/webp">
                    <img src="{{ $site->heroImage('hero_bg_mark_b', asset('favicon-white-180x180.png')) }}" alt="Hero motif B" style="margin-top:6px;max-width:100%;height:72px;object-fit:contain;background:#6e0019;border-radius:10px;padding:8px">
                    <label class="check" style="margin-top:8px"><input type="checkbox" name="hero_bg_mark_b_remove" value="1"> Supprimer</label>
                    @error('hero_bg_mark_b_file')<p class="error">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><x-admin.icon name="check" :size="16" /> Enregistrer</button>
        </div>
    </form>
@endsection
