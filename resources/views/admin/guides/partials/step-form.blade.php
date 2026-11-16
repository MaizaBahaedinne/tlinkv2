@php
    $exists = $step->exists;
    $key = $exists ? $step->id : 'new';
    $formId = 'step-'.$key;
    $imageUrl = $step->imageUrl();
    $submitted = (string) ($stepOld['_step'] ?? '') === (string) $key; // seule l'étape soumise reprend la saisie refusée
    $val = fn (string $name, $default = null) => $submitted && array_key_exists($name, $stepOld) ? $stepOld[$name] : $default;
    $err = fn (string $name) => $submitted ? $errors->first($name) : '';
@endphp
<article class="card card-flush" id="etape-{{ $key }}" style="margin-bottom:16px">
    <div class="card-head" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;justify-content:space-between">
        <strong>{{ $exists ? 'Étape '.$number.' — '.$step->title : 'Ajouter une étape' }}</strong>
        @if($exists)
            <div style="display:flex;gap:6px;align-items:center">
                @unless($isFirst)
                    <form method="post" action="{{ route('admin.guides.steps.move', [$guide, $step]) }}">
                        @csrf
                        <input type="hidden" name="direction" value="up">
                        <button type="submit" class="btn btn-ghost btn-sm" title="Monter" aria-label="Monter l’étape">↑ Monter</button>
                    </form>
                @endunless
                @unless($isLast)
                    <form method="post" action="{{ route('admin.guides.steps.move', [$guide, $step]) }}">
                        @csrf
                        <input type="hidden" name="direction" value="down">
                        <button type="submit" class="btn btn-ghost btn-sm" title="Descendre" aria-label="Descendre l’étape">↓ Descendre</button>
                    </form>
                @endunless
                <x-admin.delete-button :action="route('admin.guides.steps.destroy', [$guide, $step])" title="Supprimer cette étape ?" message="L’étape « {{ $step->title }} » et son image seront supprimées." />
            </div>
        @endif
    </div>

    <form method="post" enctype="multipart/form-data" data-step-form id="{{ $formId }}"
          action="{{ $exists ? route('admin.guides.steps.update', [$guide, $step]) : route('admin.guides.steps.store', $guide) }}">
        @csrf
        @if($exists)
            @method('PUT')
        @endif
        <input type="hidden" name="_step" value="{{ $key }}">

        <div class="card-body">
            <div class="form-grid">
                <div class="field span-2 {{ $err('title') ? 'has-error' : '' }}">
                    <label for="{{ $formId }}-title">Titre de l’étape <span class="req">*</span></label>
                    <input id="{{ $formId }}-title" type="text" name="title" value="{{ $val('title', $step->title) }}" required maxlength="160">
                    @if($err('title'))<p class="error">{{ $err('title') }}</p>@endif
                </div>
                <div class="field span-2">
                    <label for="{{ $formId }}-body">Texte</label>
                    <textarea id="{{ $formId }}-body" name="body" rows="3" maxlength="3000">{{ $val('body', $step->body) }}</textarea>
                    <p class="hint">Expliquez simplement l’action à réaliser.</p>
                </div>
                <div class="field">
                    <label for="{{ $formId }}-notice_type">Type de notice</label>
                    <select id="{{ $formId }}-notice_type" name="notice_type">
                        @foreach(\App\Models\GuideStep::NOTICES as $value => $notice)
                            <option value="{{ $value }}" @selected($val('notice_type', $step->notice_type ?? 'info') === $value)>{{ $notice['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="{{ $formId }}-notice">Texte de la notice</label>
                    <textarea id="{{ $formId }}-notice" name="notice" rows="2" maxlength="1000">{{ $val('notice', $step->notice) }}</textarea>
                    <p class="hint">Facultatif : astuce, information ou point d’attention affiché en encadré.</p>
                </div>
            </div>

            <h3>Illustration</h3>
            <div class="form-grid">
                <div class="field">
                    <label for="{{ $formId }}-icon">Icône (sans image)</label>
                    <select id="{{ $formId }}-icon" name="icon">
                        <option value="">Par défaut</option>
                        @foreach(\App\Models\Guide::ICONS as $value => $label)
                            <option value="{{ $value }}" @selected($val('icon', $step->icon) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field {{ $err('image') ? 'has-error' : '' }}">
                    <label for="{{ $formId }}-image">Capture d’écran ou GIF</label>
                    <input id="{{ $formId }}-image" type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif">
                    <p class="hint">jpg, png, webp ou gif — 5 Mo maximum.</p>
                    @if($err('image'))<p class="error">{{ $err('image') }}</p>@endif
                    @if($imageUrl)
                        <label class="check"><input type="checkbox" name="remove_image" value="1"> Retirer l’image actuelle</label>
                    @endif
                </div>
                <div class="field {{ $err('duration') ? 'has-error' : '' }}">
                    <label for="{{ $formId }}-duration">Durée (secondes)</label>
                    <input id="{{ $formId }}-duration" type="number" name="duration" value="{{ $val('duration', $step->duration) }}" min="3" max="60">
                    <p class="hint">Temps d’affichage en lecture automatique (3 à 60).</p>
                    @if($err('duration'))<p class="error">{{ $err('duration') }}</p>@endif
                </div>
            </div>

            <div class="field" style="margin-top:12px">
                <label>Repère animé (cliquez sur l’aperçu pour le placer)</label>
                <div data-hotspot-stage style="position:relative;display:inline-block;max-width:100%;border:1px dashed #cbd5e1;border-radius:10px;overflow:hidden;cursor:crosshair;min-width:240px;min-height:90px;background:#f8fafc">
                    <img data-hotspot-image src="{{ $imageUrl }}" alt="" style="display:block;max-width:100%;max-height:340px" @if(! $imageUrl) hidden @endif>
                    <span data-hotspot-empty class="text-sm text-muted" style="display:block;padding:30px 18px" @if($imageUrl) hidden @endif>Choisissez une image pour placer le repère.</span>
                    <span data-hotspot-marker hidden style="position:absolute;width:22px;height:22px;margin:-11px 0 0 -11px;border-radius:999px;background:rgba(225,29,72,.85);border:3px solid #fff;box-shadow:0 0 0 2px rgba(225,29,72,.5);pointer-events:none"></span>
                </div>
            </div>
            <div class="form-grid">
                <div class="field {{ $err('hotspot_x') ? 'has-error' : '' }}">
                    <label for="{{ $formId }}-hx">Repère — horizontal (%)</label>
                    <input id="{{ $formId }}-hx" type="number" name="hotspot_x" value="{{ $val('hotspot_x', $step->hotspot_x) }}" min="0" max="100">
                    <p class="hint">0 = gauche, 100 = droite.</p>
                    @if($err('hotspot_x'))<p class="error">{{ $err('hotspot_x') }}</p>@endif
                </div>
                <div class="field {{ $err('hotspot_y') ? 'has-error' : '' }}">
                    <label for="{{ $formId }}-hy">Repère — vertical (%)</label>
                    <input id="{{ $formId }}-hy" type="number" name="hotspot_y" value="{{ $val('hotspot_y', $step->hotspot_y) }}" min="0" max="100">
                    <p class="hint">0 = haut, 100 = bas. Videz les deux champs pour ne pas afficher de repère.</p>
                    @if($err('hotspot_y'))<p class="error">{{ $err('hotspot_y') }}</p>@endif
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">{{ $exists ? 'Enregistrer l’étape' : 'Ajouter l’étape' }}</button>
        </div>
    </form>
</article>
