@extends('layouts.base', ['title' => 'Mon profil | T.Link'])

@section('content')
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    @include('profile.partials.owner-main')
</div>

<dialog id="modal-about" class="profile-modal profile-modal-md rounded-2xl border border-slate-200 p-0 backdrop:bg-slate-900/40">
    <form method="dialog" class="border-b border-slate-200 px-5 py-3">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-slate-900">Modifier a propos</h3>
            <button class="rounded-lg px-3 py-1 text-sm hover:bg-slate-100">Fermer</button>
        </div>
    </form>
    <form method="post" action="{{ route('profile.update') }}" class="space-y-4 px-5 py-5">
        @csrf
        @method('PATCH')
        <input type="hidden" name="section" value="basic">
        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="headline">Titre</label>
            <input id="headline" name="headline" type="text" value="{{ old('headline', $profile?->headline) }}" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="Ex: Responsable Communication - Tunivisions">
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="bio">A propos</label>
            <textarea id="bio" name="bio" rows="6" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="Votre resume professionnel">{{ old('bio', $profile?->bio) }}</textarea>
        </div>
        <button class="w-full rounded-xl bg-tlink-blue px-4 py-2 text-sm font-semibold text-white hover:bg-tlink-blue-700">Enregistrer</button>
    </form>
</dialog>

<form id="profile-media-form" method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="hidden">
    @csrf
    @method('PATCH')
    <input type="hidden" name="section" value="media">
    <input type="file" id="profile-media-source" accept="image/*" class="hidden">
    <input type="file" id="profile-avatar-file" name="avatar" accept="image/*" class="hidden">
    <input type="file" id="profile-cover-file" name="cover" accept="image/*" class="hidden">
</form>

<dialog id="modal-media" class="profile-modal rounded-2xl border border-slate-200 p-0 backdrop:bg-slate-900/40">
    <div class="border-b border-slate-200 px-5 py-3">
        <div class="flex items-center justify-between gap-3">
            <h3 id="media-title" class="text-lg font-semibold text-slate-900">Modifier media</h3>
            <button type="button" class="rounded-lg px-3 py-1 text-sm hover:bg-slate-100" onclick="closeMediaModal()">Fermer</button>
        </div>
    </div>
    <div class="space-y-4 px-5 py-5">
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <div id="profile-media-stage" class="relative mx-auto overflow-hidden rounded-2xl bg-slate-200 shadow-inner" data-kind="avatar">
                <img id="profile-media-preview" alt="Recadrage" class="absolute left-1/2 top-1/2 select-none" draggable="false" />
            </div>
        </div>
        <div class="grid gap-3 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="profile-media-zoom">Zoom</label>
                <input id="profile-media-zoom" type="range" min="1" max="3" step="0.01" value="1" class="w-full" oninput="updateMediaCrop()">
            </div>
            <div class="flex items-end gap-2">
                <button type="button" class="rounded-xl border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50" onclick="pickMediaSource()">Choisir un fichier</button>
                <span id="media-hint" class="text-xs text-slate-500">Deplacez l'image dans la zone pour recadrer.</span>
            </div>
        </div>
        <div class="flex gap-2">
            <button type="button" class="flex-1 rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700" onclick="applyMediaCrop()">Appliquer le recadrage</button>
            <button type="button" class="rounded-xl border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50" onclick="closeMediaModal()">Annuler</button>
        </div>
        <button type="button" id="media-submit-btn" class="hidden"></button>
    </div>
</dialog>

<dialog id="modal-professional" class="profile-modal rounded-2xl border border-slate-200 p-0 backdrop:bg-slate-900/40">
    <form method="dialog" class="border-b border-slate-200 px-5 py-3">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-slate-900">Modifier experience pro</h3>
            <button class="rounded-lg px-3 py-1 text-sm hover:bg-slate-100">Fermer</button>
        </div>
    </form>
    <form method="post" action="{{ route('profile.update') }}" class="space-y-4 px-5 py-5">
        @csrf
        @method('PATCH')
        <input type="hidden" name="section" value="professional_experiences">
        <div id="professional-list" class="space-y-3" data-row-kind="professional">
            @foreach($professionalExperiences as $index => $item)
                <div class="grid gap-2 rounded-xl border border-slate-200 p-3 md:grid-cols-2">
                    <input name="items[{{ $index }}][company]" value="{{ $item['company'] ?? '' }}" list="company-options" autocomplete="off" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Nom de societe">
                    <input name="items[{{ $index }}][role]" value="{{ $item['role'] ?? '' }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Poste">
                    <input type="date" name="items[{{ $index }}][start_date]" value="{{ $item['start_date'] ?? '' }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <input type="date" name="items[{{ $index }}][end_date]" value="{{ $item['end_date'] ?? '' }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <textarea name="items[{{ $index }}][description]" rows="3" class="md:col-span-2 rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Description">{{ $item['description'] ?? '' }}</textarea>
                </div>
            @endforeach
        </div>
        <button type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-xs" onclick="addProfileRow('professional-list')">Ajouter experience</button>
        <p class="text-xs text-slate-500">Choisissez votre société dans la liste ; si elle n'existe pas encore, elle sera ajoutée à la base et son logo s'affichera dès qu'il sera renseigné.</p>
        <button class="w-full rounded-xl bg-tlink-blue px-4 py-2 text-sm font-semibold text-white hover:bg-tlink-blue-700">Enregistrer</button>
    </form>
</dialog>

<dialog id="modal-education" class="profile-modal rounded-2xl border border-slate-200 p-0 backdrop:bg-slate-900/40">
    <form method="dialog" class="border-b border-slate-200 px-5 py-3">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-slate-900">Modifier formation</h3>
            <button class="rounded-lg px-3 py-1 text-sm hover:bg-slate-100">Fermer</button>
        </div>
    </form>
    <form method="post" action="{{ route('profile.update') }}" class="space-y-4 px-5 py-5">
        @csrf
        @method('PATCH')
        <input type="hidden" name="section" value="educations">
        <div id="education-list" class="space-y-3" data-row-kind="education">
            @foreach($educations as $index => $item)
                <div class="grid gap-2 rounded-xl border border-slate-200 p-3 md:grid-cols-2">
                    <select name="items[{{ $index }}][degree_type]" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        @foreach(['bac','licence','bts','master','ingenieur','doctorat','autre'] as $degree)
                            <option value="{{ $degree }}" @selected(($item['degree_type'] ?? '') === $degree)>{{ ucfirst($degree) }}</option>
                        @endforeach
                    </select>
                    <input name="items[{{ $index }}][diploma_name]" value="{{ $item['diploma_name'] ?? '' }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Nom de diplome">
                    <input name="items[{{ $index }}][school]" value="{{ $item['school'] ?? '' }}" list="school-options" autocomplete="off" class="rounded-lg border border-slate-300 px-3 py-2 text-sm md:col-span-2" placeholder="Ecole (choisissez dans la liste)">
                    <input type="date" name="items[{{ $index }}][start_date]" value="{{ $item['start_date'] ?? '' }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <input type="date" name="items[{{ $index }}][end_date]" value="{{ $item['end_date'] ?? '' }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
            @endforeach
        </div>
        <button type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-xs" onclick="addProfileRow('education-list')">Ajouter formation</button>
        <p class="text-xs text-slate-500">Les écoles de la liste proviennent des clubs de la Fondation : leur logo s'affiche sur votre profil.</p>
        <button class="w-full rounded-xl bg-tlink-blue px-4 py-2 text-sm font-semibold text-white hover:bg-tlink-blue-700">Enregistrer</button>
    </form>
</dialog>

<dialog id="modal-certification" class="profile-modal rounded-2xl border border-slate-200 p-0 backdrop:bg-slate-900/40">
    <form method="dialog" class="border-b border-slate-200 px-5 py-3">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-slate-900">Modifier certifications</h3>
            <button class="rounded-lg px-3 py-1 text-sm hover:bg-slate-100">Fermer</button>
        </div>
    </form>
    <form method="post" action="{{ route('profile.update') }}" class="space-y-4 px-5 py-5">
        @csrf
        @method('PATCH')
        <input type="hidden" name="section" value="certifications">
        <div id="certification-list" class="space-y-3" data-row-kind="certification">
            @foreach($certifications as $index => $item)
                <div class="grid gap-2 rounded-xl border border-slate-200 p-3 md:grid-cols-2">
                    <input name="items[{{ $index }}][name]" value="{{ $item['name'] ?? '' }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Nom certification">
                    <input name="items[{{ $index }}][issuer]" value="{{ $item['issuer'] ?? '' }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Organisme">
                    <input type="date" name="items[{{ $index }}][date_obtained]" value="{{ $item['date_obtained'] ?? '' }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <input type="url" name="items[{{ $index }}][url]" value="{{ $item['url'] ?? '' }}" maxlength="300" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Lien de vérification (https://…)">
                </div>
            @endforeach
        </div>
        <button type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-xs" onclick="addProfileRow('certification-list')">Ajouter certification</button>
        <p class="text-xs text-slate-500">Ajoutez ici vos certifications <strong>externes</strong>. Celles obtenues sur Tunivisions Academy s’affichent automatiquement.</p>
        <button class="w-full rounded-xl bg-tlink-blue px-4 py-2 text-sm font-semibold text-white hover:bg-tlink-blue-700">Enregistrer</button>
    </form>
</dialog>

<dialog id="modal-associative" class="profile-modal rounded-2xl border border-slate-200 p-0 backdrop:bg-slate-900/40">
    <form method="dialog" class="border-b border-slate-200 px-5 py-3">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-slate-900">Modifier experience associatif</h3>
            <button class="rounded-lg px-3 py-1 text-sm hover:bg-slate-100">Fermer</button>
        </div>
    </form>
    <form method="post" action="{{ route('profile.update') }}" class="space-y-4 px-5 py-5">
        @csrf
        @method('PATCH')
        <input type="hidden" name="section" value="associative_experiences">
        <div id="associative-list" class="space-y-3" data-row-kind="associative">
            @foreach($associativeExperiences as $index => $item)
                <div class="grid gap-2 rounded-xl border border-slate-200 p-3 md:grid-cols-2">
                    <input name="items[{{ $index }}][club_name]" value="{{ $item['club_name'] ?? '' }}" list="club-options" autocomplete="off" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Nom de club">
                    <input name="items[{{ $index }}][role]" value="{{ $item['role'] ?? '' }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Poste">
                    <input name="items[{{ $index }}][department]" value="{{ $item['department'] ?? '' }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm md:col-span-2" placeholder="Departement">
                    <input type="date" name="items[{{ $index }}][start_date]" value="{{ $item['start_date'] ?? '' }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <input type="date" name="items[{{ $index }}][end_date]" value="{{ $item['end_date'] ?? '' }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <textarea name="items[{{ $index }}][description]" rows="3" class="md:col-span-2 rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Description">{{ $item['description'] ?? '' }}</textarea>
                </div>
            @endforeach
        </div>
        <button type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-xs" onclick="addProfileRow('associative-list')">Ajouter experience</button>
        <p class="text-xs text-slate-500">Ajoutez ici vos expériences associatives <strong>externes</strong> (hors Tunivisions). Vos adhésions Tunivisions validées s’affichent automatiquement.</p>
        <button class="w-full rounded-xl bg-tlink-blue px-4 py-2 text-sm font-semibold text-white hover:bg-tlink-blue-700">Enregistrer</button>
    </form>
</dialog>

<dialog id="modal-skills" class="profile-modal profile-modal-sm rounded-2xl border border-slate-200 p-0 backdrop:bg-slate-900/40">
    <form method="dialog" class="border-b border-slate-200 px-5 py-3">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-slate-900">Modifier competences</h3>
            <button class="rounded-lg px-3 py-1 text-sm hover:bg-slate-100">Fermer</button>
        </div>
    </form>
    <form method="post" action="{{ route('profile.update') }}" class="space-y-4 px-5 py-5">
        @csrf
        @method('PATCH')
        <input type="hidden" name="section" value="skill_keywords">
        <textarea name="keywords" rows="4" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="Mots cles separes par virgule">{{ implode(', ', $skillKeywords->toArray()) }}</textarea>
        <button class="w-full rounded-xl bg-tlink-blue px-4 py-2 text-sm font-semibold text-white hover:bg-tlink-blue-700">Enregistrer</button>
    </form>
</dialog>

<dialog id="modal-languages" class="profile-modal profile-modal-md rounded-2xl border border-slate-200 p-0 backdrop:bg-slate-900/40">
    <form method="dialog" class="border-b border-slate-200 px-5 py-3">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-slate-900">Modifier langues</h3>
            <button class="rounded-lg px-3 py-1 text-sm hover:bg-slate-100">Fermer</button>
        </div>
    </form>
    <form method="post" action="{{ route('profile.update') }}" class="space-y-4 px-5 py-5">
        @csrf
        @method('PATCH')
        <input type="hidden" name="section" value="languages">
        <div id="language-list" class="space-y-3" data-row-kind="language">
            @foreach($languageItems as $index => $item)
                <div class="grid gap-2 rounded-xl border border-slate-200 p-3 md:grid-cols-2">
                    <input name="items[{{ $index }}][name]" value="{{ $item['name'] ?? '' }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Nom de langue">
                    <select name="items[{{ $index }}][level]" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        @foreach(['A1','A2','B1','B2','C1','C2','Natif'] as $level)
                            <option value="{{ $level }}" @selected(($item['level'] ?? '') === $level)>{{ $level }}</option>
                        @endforeach
                    </select>
                </div>
            @endforeach
        </div>
        <button type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-xs" onclick="addProfileRow('language-list')">Ajouter langue</button>
        <button class="w-full rounded-xl bg-tlink-blue px-4 py-2 text-sm font-semibold text-white hover:bg-tlink-blue-700">Enregistrer</button>
    </form>
</dialog>

<datalist id="company-options">
    @foreach($companyOptions as $option)<option value="{{ $option }}"></option>@endforeach
</datalist>
<datalist id="school-options">
    @foreach($schoolOptions as $option)<option value="{{ $option }}"></option>@endforeach
</datalist>
<datalist id="club-options">
    @foreach($clubOptions as $option)<option value="{{ $option }}"></option>@endforeach
</datalist>

<template id="tpl-professional">
    <div class="grid gap-2 rounded-xl border border-slate-200 p-3 md:grid-cols-2">
        <input name="items[__INDEX__][company]" list="company-options" autocomplete="off" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Nom de societe">
        <input name="items[__INDEX__][role]" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Poste">
        <input type="date" name="items[__INDEX__][start_date]" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <input type="date" name="items[__INDEX__][end_date]" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <textarea name="items[__INDEX__][description]" rows="3" class="md:col-span-2 rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Description"></textarea>
    </div>
</template>

<template id="tpl-education">
    <div class="grid gap-2 rounded-xl border border-slate-200 p-3 md:grid-cols-2">
        <select name="items[__INDEX__][degree_type]" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="bac">Bac</option>
            <option value="licence">Licence</option>
            <option value="bts">BTS</option>
            <option value="master">Master</option>
            <option value="ingenieur">Ingenieur</option>
            <option value="doctorat">Doctorat</option>
            <option value="autre">Autre</option>
        </select>
        <input name="items[__INDEX__][diploma_name]" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Nom de diplome">
        <input name="items[__INDEX__][school]" list="school-options" autocomplete="off" class="rounded-lg border border-slate-300 px-3 py-2 text-sm md:col-span-2" placeholder="Ecole (choisissez dans la liste)">
        <input type="date" name="items[__INDEX__][start_date]" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <input type="date" name="items[__INDEX__][end_date]" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>
</template>

<template id="tpl-certification">
    <div class="grid gap-2 rounded-xl border border-slate-200 p-3 md:grid-cols-2">
        <input name="items[__INDEX__][name]" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Nom certification">
        <input name="items[__INDEX__][issuer]" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Organisme">
        <input type="date" name="items[__INDEX__][date_obtained]" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <input type="url" name="items[__INDEX__][url]" maxlength="300" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Lien de vérification (https://…)">
    </div>
</template>

<template id="tpl-associative">
    <div class="grid gap-2 rounded-xl border border-slate-200 p-3 md:grid-cols-2">
        <input name="items[__INDEX__][club_name]" list="club-options" autocomplete="off" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Nom de club">
        <input name="items[__INDEX__][role]" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Poste">
        <input name="items[__INDEX__][department]" class="rounded-lg border border-slate-300 px-3 py-2 text-sm md:col-span-2" placeholder="Departement">
        <input type="date" name="items[__INDEX__][start_date]" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <input type="date" name="items[__INDEX__][end_date]" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <textarea name="items[__INDEX__][description]" rows="3" class="md:col-span-2 rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Description"></textarea>
    </div>
</template>

<template id="tpl-language">
    <div class="grid gap-2 rounded-xl border border-slate-200 p-3 md:grid-cols-2">
        <input name="items[__INDEX__][name]" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Nom de langue">
        <select name="items[__INDEX__][level]" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="A1">A1</option>
            <option value="A2">A2</option>
            <option value="B1">B1</option>
            <option value="B2">B2</option>
            <option value="C1">C1</option>
            <option value="C2">C2</option>
            <option value="Natif">Natif</option>
        </select>
    </div>
</template>

<script>
    function openProfileModal(id) {
        const dlg = document.getElementById(id);
        if (dlg && typeof dlg.showModal === 'function') {
            dlg.showModal();
        }
    }

    const mediaState = {
        kind: 'avatar',
        image: null,
        dragging: false,
        startX: 0,
        startY: 0,
        offsetX: 0,
        offsetY: 0,
        zoom: 1,
    };

    function openMediaModal(kind) {
        mediaState.kind = kind;
        mediaState.image = null;
        mediaState.dragging = false;
        mediaState.startX = 0;
        mediaState.startY = 0;
        mediaState.offsetX = 0;
        mediaState.offsetY = 0;
        mediaState.zoom = 1;

        const stage = document.getElementById('profile-media-stage');
        const title = document.getElementById('media-title');
        const hint = document.getElementById('media-hint');
        const zoom = document.getElementById('profile-media-zoom');
        const preview = document.getElementById('profile-media-preview');

        if (!stage || !title || !hint || !zoom || !preview) {
            return;
        }

        stage.dataset.kind = kind;
        title.textContent = kind === 'cover' ? 'Modifier cover' : 'Modifier avatar';
        hint.textContent = kind === 'cover'
            ? 'Recadrez la cover dans une zone large.'
            : 'Recadrez l avatar dans une zone carre.';
        zoom.value = '1';
        preview.src = '';
        preview.style.width = '0px';
        preview.style.height = '0px';
        preview.style.transform = 'translate(-50%, -50%)';

        pickMediaSource();
    }

    function closeMediaModal() {
        const dlg = document.getElementById('modal-media');
        if (dlg && typeof dlg.close === 'function') {
            dlg.close();
        }
    }

    function pickMediaSource() {
        const input = document.getElementById('profile-media-source');
        if (input) {
            input.value = '';
            input.click();
        }
    }

    function bindMediaInput() {
        const input = document.getElementById('profile-media-source');
        if (!input || input.dataset.bound === '1') {
            return;
        }

        input.dataset.bound = '1';
        input.addEventListener('change', handleMediaFileChange);
    }

    function handleMediaFileChange(event) {
        const file = event.target.files && event.target.files[0];
        if (!file) {
            return;
        }

        const preview = document.getElementById('profile-media-preview');
        const stage = document.getElementById('profile-media-stage');
        const dlg = document.getElementById('modal-media');

        if (!preview || !stage || !dlg) {
            return;
        }

        const objectUrl = URL.createObjectURL(file);
        preview.onload = () => {
            mediaState.image = preview;
            mediaState.zoom = 1;
            mediaState.offsetX = 0;
            mediaState.offsetY = 0;
            const zoom = document.getElementById('profile-media-zoom');
            if (zoom) {
                zoom.value = '1';
            }
            renderMediaPreview();
        };
        preview.src = objectUrl;

        if (typeof dlg.showModal === 'function') {
            dlg.showModal();
        }
    }

    function renderMediaPreview() {
        const stage = document.getElementById('profile-media-stage');
        const preview = document.getElementById('profile-media-preview');
        if (!stage || !preview || !preview.naturalWidth || !preview.naturalHeight) {
            return;
        }

        const rect = stage.getBoundingClientRect();
        const baseScale = Math.max(rect.width / preview.naturalWidth, rect.height / preview.naturalHeight);
        const scale = baseScale * mediaState.zoom;
        const width = preview.naturalWidth * scale;
        const height = preview.naturalHeight * scale;

        preview.style.width = `${width}px`;
        preview.style.height = `${height}px`;
        preview.style.transform = `translate(-50%, -50%) translate(${mediaState.offsetX}px, ${mediaState.offsetY}px)`;
    }

    function updateMediaCrop() {
        const zoom = document.getElementById('profile-media-zoom');
        if (!zoom) {
            return;
        }

        mediaState.zoom = Number(zoom.value);
        renderMediaPreview();
    }

    function applyMediaCrop() {
        const stage = document.getElementById('profile-media-stage');
        const preview = document.getElementById('profile-media-preview');
        const form = document.getElementById('profile-media-form');
        if (!stage || !preview || !form || !preview.naturalWidth || !preview.naturalHeight) {
            return;
        }

        const rect = stage.getBoundingClientRect();
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(rect.width * 2);
        canvas.height = Math.round(rect.height * 2);

        const ctx = canvas.getContext('2d');
        if (!ctx) {
            return;
        }

        ctx.scale(2, 2);

        const baseScale = Math.max(rect.width / preview.naturalWidth, rect.height / preview.naturalHeight);
        const scale = baseScale * mediaState.zoom;
        const width = preview.naturalWidth * scale;
        const height = preview.naturalHeight * scale;
        const x = rect.width / 2 - width / 2 + mediaState.offsetX;
        const y = rect.height / 2 - height / 2 + mediaState.offsetY;

        ctx.clearRect(0, 0, rect.width, rect.height);
        ctx.drawImage(preview, x, y, width, height);

        canvas.toBlob((blob) => {
            if (!blob) {
                return;
            }

            const fileName = `${mediaState.kind}-${Date.now()}.jpg`;
            const file = new File([blob], fileName, { type: 'image/jpeg' });
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);

            const targetInput = document.getElementById(mediaState.kind === 'avatar' ? 'profile-avatar-file' : 'profile-cover-file');
            if (targetInput) {
                targetInput.files = dataTransfer.files;
            }

            form.submit();
        }, 'image/jpeg', 0.92);
    }

    function bindMediaStage() {
        const stage = document.getElementById('profile-media-stage');
        if (!stage || stage.dataset.bound === '1') {
            return;
        }

        stage.dataset.bound = '1';

        stage.addEventListener('pointerdown', (event) => {
            mediaState.dragging = true;
            mediaState.startX = event.clientX;
            mediaState.startY = event.clientY;
            stage.setPointerCapture(event.pointerId);
        });

        stage.addEventListener('pointermove', (event) => {
            if (!mediaState.dragging) {
                return;
            }

            mediaState.offsetX += event.clientX - mediaState.startX;
            mediaState.offsetY += event.clientY - mediaState.startY;
            mediaState.startX = event.clientX;
            mediaState.startY = event.clientY;
            renderMediaPreview();
        });

        stage.addEventListener('pointerup', () => {
            mediaState.dragging = false;
        });

        stage.addEventListener('pointerleave', () => {
            mediaState.dragging = false;
        });
    }

    function addProfileRow(listId) {
        const list = document.getElementById(listId);
        if (!list) return;
        const kind = list.getAttribute('data-row-kind');
        const tpl = document.getElementById(`tpl-${kind}`);
        if (!tpl) return;

        const index = list.children.length;
        const html = tpl.innerHTML.replaceAll('__INDEX__', String(index));
        list.insertAdjacentHTML('beforeend', html);
    }

    bindMediaInput();
    bindMediaStage();
</script>

<style>
    .profile-modal {
        width: min(96vw, 64rem);
        margin: auto;
    }

    .profile-modal::backdrop {
        background: rgba(15, 23, 42, 0.55);
    }

    .profile-modal.profile-modal-md {
        width: min(96vw, 48rem);
    }

    .profile-modal.profile-modal-sm {
        width: min(96vw, 40rem);
    }

    #profile-media-stage[data-kind="avatar"] {
        width: min(72vw, 22rem);
        aspect-ratio: 1 / 1;
    }

    #profile-media-stage[data-kind="cover"] {
        width: 100%;
        aspect-ratio: 16 / 5;
        max-height: 24rem;
    }

    #profile-media-preview {
        touch-action: none;
    }
</style>
@endsection
