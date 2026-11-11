@extends('admin.layouts.app')

@section('title', 'Pointage')
@section('section', 'Cartes et pointage')

@section('content')
    <x-admin.page-header title="Pointage" subtitle="Scannez la carte de membre (QR code) pour enregistrer une entrée ou une sortie sur une activité, un événement ou un local.">
        <x-slot:actions>
            <a href="{{ route('admin.checkpoint-history.index') }}" class="btn btn-secondary"><x-admin.icon name="clock" :size="16" /> Historique</a>
        </x-slot:actions>
    </x-admin.page-header>

    @if(! $hasTargets)
        <article class="card">
            <x-admin.empty icon="calendar" title="Rien à pointer pour le moment" text="Aucune activité ni aucun événement publié n’a lieu dans les deux jours autour d’aujourd’hui, et aucun local n’est défini dans votre périmètre.">
                <a href="{{ route('admin.premises.index') }}" class="btn btn-secondary btn-sm">Gérer les locaux</a>
            </x-admin.empty>
        </article>
    @else
        <div class="grid-2" data-checkpoint data-scan-url="{{ route('admin.checkpoint.scan') }}">
            <article class="card">
                <div class="card-head"><div><h2>1. Que pointe-t-on ?</h2></div></div>
                <div class="card-body">
                    <div class="field" style="margin-bottom:14px">
                        <label for="cp-target">Cible</label>
                        <select id="cp-target" data-cp-target>
                            @foreach($groups as $key => $group)
                                @if($group['items'] !== [])
                                    <optgroup label="{{ $group['label'] }}">
                                        @foreach($group['items'] as $id => $label)
                                            <option value="{{ $key }}:{{ $id }}" @selected($selected === $key.':'.$id)>{{ $label }}</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                            @endforeach
                        </select>
                    </div>

                    <div class="field" style="margin-bottom:14px">
                        <label for="cp-direction">Sens</label>
                        <select id="cp-direction" data-cp-direction>
                            <option value="auto">Automatique (entrée, puis sortie au passage suivant)</option>
                            <option value="in">Entrée uniquement</option>
                            <option value="out">Sortie uniquement</option>
                        </select>
                    </div>

                    <div data-cp-frame hidden style="position:relative;overflow:hidden;border-radius:16px;background:#000;margin-bottom:14px">
                        <video data-cp-video playsinline muted style="width:100%;aspect-ratio:1/1;object-fit:cover;display:block"></video>
                        <div style="position:absolute;inset:12%;border:4px solid rgba(255,255,255,.8);border-radius:18px;pointer-events:none"></div>
                    </div>

                    <p data-cp-camera-message class="text-sm text-muted" role="status" aria-live="polite" style="margin:0 0 10px"></p>
                    <button type="button" class="btn btn-primary" data-cp-start><x-admin.icon name="eye" :size="16" /> Ouvrir la caméra</button>

                    <form data-cp-manual style="margin-top:18px;display:flex;gap:8px;align-items:flex-end">
                        <div class="field" style="flex:1;margin:0">
                            <label for="cp-number">Ou saisir le numéro de carte</label>
                            <input id="cp-number" type="text" data-cp-number placeholder="TL-XXXX-XXXX" maxlength="20" autocomplete="off" autocapitalize="characters">
                        </div>
                        <button type="submit" class="btn btn-secondary">Pointer</button>
                    </form>
                </div>
            </article>

            <article class="card">
                <div class="card-head"><div><h2>2. Résultat</h2></div></div>
                <div class="card-body">
                    <div data-cp-result role="status" aria-live="polite" style="min-height:150px">
                        <p class="text-sm text-muted" style="margin:0">En attente d’un scan…</p>
                    </div>
                    <p data-cp-present class="text-sm" style="margin:14px 0 0"></p>
                    <h3 style="margin:18px 0 8px;font-size:14px">Derniers pointages de cette session</h3>
                    <ul data-cp-log style="list-style:none;margin:0;padding:0;display:grid;gap:6px" class="text-sm"></ul>
                </div>
            </article>
        </div>
    @endif
@endsection

@push('scripts')
    @vite(['resources/js/checkpoint.js'])
@endpush
