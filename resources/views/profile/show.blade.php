@extends('layouts.base', ['title' => 'Mon profil | T.Link'])

@section('content')
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    <main class="app-container space-y-5 py-6">
        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-soft">
            {{-- Cover : en arrière-plan, la photo de profil la chevauche (z-10). --}}
            <div class="relative h-56 overflow-hidden bg-gradient-to-br from-tlink-blue to-slate-900 sm:h-72">
                @if($coverUrl)
                    <img src="{{ $coverUrl }}" alt="" class="absolute inset-0 h-full w-full object-cover">
                @endif
                <div class="absolute inset-0 bg-gradient-to-t from-slate-900/70 via-slate-900/10 to-transparent"></div>
                <h1 class="absolute bottom-4 left-4 right-4 truncate font-display text-2xl font-semibold text-white drop-shadow sm:left-[11.5rem] sm:text-3xl">{{ $user->name }}</h1>
                <button type="button" class="absolute right-4 top-4 inline-flex items-center gap-2 rounded-xl bg-white/90 px-3 py-2 text-xs font-semibold text-slate-800 shadow-soft hover:bg-white" onclick="openMediaModal('cover')">
                    <i class="fa-solid fa-camera"></i> Modifier la cover
                </button>
            </div>

            <div class="px-4 pb-6 sm:px-8">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:gap-6">
                    <div class="relative z-10 -mt-14 w-fit shrink-0 sm:-mt-16">
                        <div class="flex h-28 w-28 items-center justify-center overflow-hidden rounded-3xl border-4 border-white bg-tlink-blue text-3xl font-semibold text-white shadow-soft sm:h-32 sm:w-32 sm:text-4xl">
                            @if($avatarUrl)
                                <img src="{{ $avatarUrl }}" alt="Avatar" class="h-full w-full object-cover">
                            @else
                                {{ $avatarInitials ?: 'TL' }}
                            @endif
                        </div>
                        <button type="button" class="absolute -bottom-2 -right-2 flex h-9 w-9 items-center justify-center rounded-full border-2 border-white bg-slate-900 text-xs text-white shadow-soft hover:bg-slate-700" onclick="openMediaModal('avatar')" title="Modifier l'avatar" aria-label="Modifier l'avatar">
                            <i class="fa-solid fa-camera"></i>
                        </button>
                    </div>
                    <div class="min-w-0 flex-1 sm:pb-1">
                        <p class="text-sm text-slate-600">{{ $profile->headline ?: 'Ajoutez un titre professionnel impactant.' }}</p>
                        @include('profile.partials.highlights')
                        <p class="mt-1 truncate text-xs text-slate-500">{{ $user->email }}</p>
                    </div>
                    <div class="sm:pb-1">
                        <button type="button" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-medium hover:bg-slate-50" onclick="openProfileModal('modal-about')">
                            <i class="fa-solid fa-pen mr-1 text-xs"></i> Modifier le titre et la bio
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-4 lg:grid-cols-12">
            <div class="space-y-4 lg:col-span-8">
                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="font-display text-xl font-semibold text-slate-900">A propos</h2>
                        <button type="button" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold hover:bg-slate-50" onclick="openProfileModal('modal-about')">Modifier</button>
                    </div>
                    <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $profile->bio ?: 'Ajoutez une bio claire pour presenter votre impact, vos valeurs et vos objectifs.' }}</p>
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft" id="badges">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="font-display text-xl font-semibold text-slate-900">Badges</h2>
                        <a href="{{ route('badges.index') }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold hover:bg-slate-50">Voir les missions</a>
                    </div>
                    @if($earnedBadges->isEmpty())
                        <p class="mt-3 text-sm text-slate-500">Aucun badge pour l'instant : relevez des missions pour en obtenir.</p>
                    @else
                        <ul class="mt-4 flex flex-wrap gap-4">
                            @foreach($earnedBadges as $earned)
                                <li class="flex w-24 flex-col items-center gap-1 text-center">
                                    <x-badge-medal :badge="$earned->badge" />
                                    <span class="text-xs font-semibold text-slate-800">{{ $earned->badge->name }}</span>
                                    <span class="rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $earned->badge->chip() }}">{{ $earned->badge->tierLabel() }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>

                @include('profile.partials.details', ['editable' => true])

                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
                    <h2 class="font-display text-xl font-semibold text-slate-900">Activite recente</h2>
                    <div class="mt-4 space-y-3">
                        @forelse ($recentPosts as $post)
                            <article class="rounded-2xl border border-slate-200 px-4 py-3">
                                <p class="text-xs text-slate-500">
                                    {{ optional($post->published_at)->format('d/m/Y H:i') ?: 'Brouillon' }} · {{ $post->visibility }}
                                </p>
                                <p class="mt-1 line-clamp-3 text-sm text-slate-700">{{ $post->content }}</p>
                                <p class="mt-2 text-xs text-slate-500">{{ $post->reactions_count }} reactions · {{ $post->comments_count }} commentaires</p>
                            </article>
                        @empty
                            <p class="text-sm text-slate-500">Aucun post publie pour le moment.</p>
                        @endforelse
                    </div>
                </section>
            </div>

            <aside class="space-y-4 lg:col-span-4">
                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="font-display text-lg font-semibold text-slate-900">Competence</h2>
                        <button type="button" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold hover:bg-slate-50" onclick="openProfileModal('modal-skills')">Modifier</button>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @forelse ($skillKeywords as $skill)
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700">{{ $skill }}</span>
                        @empty
                            <p class="text-sm text-slate-500">Ajoutez vos competences clefs.</p>
                        @endforelse
                    </div>
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="font-display text-lg font-semibold text-slate-900">Langue</h2>
                        <button type="button" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold hover:bg-slate-50" onclick="openProfileModal('modal-languages')">Modifier</button>
                    </div>
                    <div class="mt-3 space-y-2">
                        @forelse ($languageItems as $language)
                            <div class="flex items-center justify-between rounded-xl bg-rose-50 px-3 py-2 text-xs font-medium text-tlink-blue">
                                <span>{{ $language['name'] ?? 'Langue' }}</span>
                                <span>{{ $language['level'] ?? '-' }}</span>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">Ajoutez vos langues.</p>
                        @endforelse
                    </div>
                </section>
            </aside>
        </div>
    </main>
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
                    <input type="date" name="items[{{ $index }}][date_obtained]" value="{{ $item['date_obtained'] ?? '' }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm md:col-span-2">
                </div>
            @endforeach
        </div>
        <button type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-xs" onclick="addProfileRow('certification-list')">Ajouter certification</button>
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
        <input type="date" name="items[__INDEX__][date_obtained]" class="rounded-lg border border-slate-300 px-3 py-2 text-sm md:col-span-2">
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
