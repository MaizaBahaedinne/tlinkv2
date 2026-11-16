@extends('admin.layouts.app')

@php
    $pageTitle = $guide->exists ? $guide->title : 'Nouveau guide';
    // Une saisie refusée dans une étape ne doit pas remplir le formulaire du guide (mêmes noms de champs).
    $stepOld = old('_step') !== null ? old() : [];
    if ($stepOld !== []) {
        session()->forget('_old_input');
    }
@endphp
@section('title', $pageTitle)
@section('section', 'Guides d’utilisation')

@section('content')
    <x-admin.page-header :title="$pageTitle" subtitle="Un guide est une suite d’étapes courtes : un titre, un texte, une capture ou un GIF avec un repère animé, et si besoin une notice.">
        <x-slot:actions>
            <a href="{{ route('admin.guides.index') }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Retour aux guides</a>
            @if($guide->exists)
                <a href="{{ route('guides.show', $guide->slug) }}" class="btn btn-secondary" target="_blank" rel="noopener"><x-admin.icon name="external" :size="16" /> Aperçu</a>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    @if($errors->any())
        <div class="alert alert-error" role="alert">
            <ul style="margin:0;padding-left:18px">
                @foreach($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="post" action="{{ $guide->exists ? route('admin.guides.update', $guide) : route('admin.guides.store') }}" class="card card-flush">
        @csrf
        @if($guide->exists)
            @method('PUT')
        @endif

        <div class="card-body">
            <div class="form-grid">
                <x-admin.input name="title" label="Titre" :value="$guide->title" :required="true" :maxlength="160" placeholder="Ma carte de membre et son QR code" />
                <x-admin.input name="category" label="Catégorie" :value="$guide->category" :maxlength="60" hint="Les guides de même catégorie sont regroupés dans la bibliothèque." />
                <x-admin.textarea name="summary" label="Résumé" :value="$guide->summary" :rows="3" :maxlength="500" hint="Une ou deux phrases qui disent à quoi sert le guide." />
            </div>

            <h3>Présentation</h3>
            <div class="form-grid">
                <div class="field">
                    <label for="f-audience">Public</label>
                    <select id="f-audience" name="audience">
                        @foreach(\App\Models\Guide::AUDIENCES as $value => $label)
                            <option value="{{ $value }}" @selected(old('audience', $guide->audience) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="f-icon">Icône</label>
                    <select id="f-icon" name="icon">
                        @foreach(\App\Models\Guide::ICONS as $value => $label)
                            <option value="{{ $value }}" @selected(old('icon', $guide->icon) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="f-accent">Couleur</label>
                    <select id="f-accent" name="accent">
                        @foreach(\App\Models\Guide::ACCENTS as $value => $accent)
                            <option value="{{ $value }}" @selected(old('accent', $guide->accent) === $value)>{{ $accent['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <x-admin.input name="sort_order" type="number" label="Ordre d’affichage" :value="$guide->sort_order" hint="Les plus petits nombres apparaissent en premier." />
                @if($guide->exists)
                    <div class="field">
                        <label for="f-status">Statut</label>
                        <select id="f-status" name="status">
                            @foreach(\App\Models\Guide::STATUSES as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', $guide->status) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="hint">Un guide en brouillon n’est visible que de l’administration.</p>
                    </div>
                @endif
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><x-admin.icon name="check" :size="16" /> {{ $guide->exists ? 'Enregistrer le guide' : 'Créer le guide' }}</button>
        </div>
    </form>

    @if($guide->exists)
        <h2 id="etapes" style="margin:28px 0 12px">Étapes ({{ $guide->steps->count() }})</h2>
        <p class="text-sm text-muted" style="margin:0 0 14px">
            Sans image, l’étape affiche une icône animée. Avec une capture ou un GIF, cliquez sur l’aperçu pour placer le repère : un curseur animé glissera jusqu’à cet endroit pendant la lecture.
        </p>

        @foreach($guide->steps as $step)
            @include('admin.guides.partials.step-form', ['guide' => $guide, 'step' => $step, 'number' => $loop->iteration, 'isFirst' => $loop->first, 'isLast' => $loop->last, 'stepOld' => $stepOld])
        @endforeach

        @include('admin.guides.partials.step-form', ['guide' => $guide, 'step' => new \App\Models\GuideStep(['duration' => \App\Models\GuideStep::DEFAULT_DURATION]), 'number' => null, 'isFirst' => true, 'isLast' => true, 'stepOld' => $stepOld])
    @endif
@endsection

@push('scripts')
<script>
    document.querySelectorAll('[data-step-form]').forEach(function (form) {
        var stage = form.querySelector('[data-hotspot-stage]');
        var fileInput = form.querySelector('input[type="file"]');
        var inputX = form.querySelector('[name="hotspot_x"]');
        var inputY = form.querySelector('[name="hotspot_y"]');
        var marker = form.querySelector('[data-hotspot-marker]');
        var image = form.querySelector('[data-hotspot-image]');

        function placeMarker() {
            if (!marker || !inputX || !inputY) { return; }
            var hasPoint = inputX.value !== '' && inputY.value !== '';
            marker.hidden = !hasPoint || !image || image.hidden;
            if (hasPoint) {
                marker.style.left = inputX.value + '%';
                marker.style.top = inputY.value + '%';
            }
        }

        if (stage) {
            stage.addEventListener('click', function (event) {
                if (image.hidden) { return; }
                var rect = image.getBoundingClientRect();
                var x = Math.round(((event.clientX - rect.left) / rect.width) * 100);
                var y = Math.round(((event.clientY - rect.top) / rect.height) * 100);
                inputX.value = Math.min(100, Math.max(0, x));
                inputY.value = Math.min(100, Math.max(0, y));
                placeMarker();
            });
        }

        [inputX, inputY].forEach(function (input) { if (input) { input.addEventListener('input', placeMarker); } });

        if (fileInput && image) {
            fileInput.addEventListener('change', function () {
                var file = fileInput.files && fileInput.files[0];
                if (!file) { return; }
                var reader = new FileReader();
                reader.onload = function (e) {
                    image.src = e.target.result;
                    image.hidden = false;
                    var empty = form.querySelector('[data-hotspot-empty]');
                    if (empty) { empty.hidden = true; }
                    placeMarker();
                };
                reader.readAsDataURL(file);
            });
        }

        placeMarker();
    });
</script>
@endpush
