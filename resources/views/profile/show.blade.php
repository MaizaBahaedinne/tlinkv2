@extends('layouts.base', ['title' => 'Mon profil | T.Link'])

@section('content')
<div class="min-h-screen bg-[#eef3fb] px-4 py-8">
    <div class="mx-auto w-full max-w-6xl space-y-4">
        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-soft">
            <div class="h-32 bg-[radial-gradient(circle_at_20%_20%,#38bdf8_0,#1d4ed8_45%,#0f172a_100%)]"></div>
            <div class="px-6 pb-6">
                <div class="-mt-14 flex flex-wrap items-end justify-between gap-4">
                    <div class="flex items-end gap-4">
                        <div class="flex h-28 w-28 items-center justify-center rounded-3xl border-4 border-white bg-tlink-blue text-3xl font-semibold text-white shadow-soft">
                            {{ $avatarInitials ?: 'TL' }}
                        </div>
                        <div class="pb-1">
                            <h1 class="font-display text-2xl font-semibold text-slate-900">{{ $user->name }}</h1>
                            <p class="text-sm text-slate-600">{{ $profile->headline ?: 'Ajoutez un titre professionnel impactant.' }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $user->email }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 pb-1">
                        <a href="{{ route('home') }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50">Retour accueil</a>
                    </div>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Posts</p>
                        <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $stats['posts_count'] }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Commentaires</p>
                        <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $stats['comments_count'] }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Reactions</p>
                        <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $stats['reactions_count'] }}</p>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-4 lg:grid-cols-12">
            <div class="space-y-4 lg:col-span-8">
                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
                    <h2 class="font-display text-xl font-semibold text-slate-900">A propos</h2>
                    <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $profile->bio ?: 'Ajoutez une bio claire pour presenter votre impact, vos valeurs et vos objectifs.' }}</p>
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="font-display text-xl font-semibold text-slate-900">Experience pro</h2>
                        <button type="button" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold hover:bg-slate-50" onclick="openProfileModal('modal-professional')">Modifier</button>
                    </div>
                    <div class="mt-4 space-y-3">
                        @forelse ($professionalExperiences as $item)
                            <article class="rounded-2xl border border-slate-200 px-4 py-3">
                                <p class="text-sm font-semibold text-slate-900">{{ $item['role'] ?? 'Poste' }} · {{ $item['company'] ?? 'Societe' }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $item['start_date'] ?? '-' }} - {{ $item['end_date'] ?? 'En cours' }}</p>
                                <p class="mt-2 text-sm text-slate-700">{{ $item['description'] ?? '' }}</p>
                            </article>
                        @empty
                            <p class="text-sm text-slate-500">Aucune experience professionnelle ajoutee.</p>
                        @endforelse
                    </div>
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="font-display text-xl font-semibold text-slate-900">Formation</h2>
                        <button type="button" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold hover:bg-slate-50" onclick="openProfileModal('modal-education')">Modifier</button>
                    </div>
                    <div class="mt-4 space-y-3">
                        @forelse ($educations as $item)
                            <article class="rounded-2xl border border-slate-200 px-4 py-3">
                                <p class="text-sm font-semibold text-slate-900">{{ $item['degree_type'] ?? 'Diplome' }} · {{ $item['diploma_name'] ?? 'Intitule' }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $item['school'] ?? 'Ecole' }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $item['start_date'] ?? '-' }} - {{ $item['end_date'] ?? 'En cours' }}</p>
                            </article>
                        @empty
                            <p class="text-sm text-slate-500">Aucune formation ajoutee.</p>
                        @endforelse
                    </div>
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="font-display text-xl font-semibold text-slate-900">Certification</h2>
                        <button type="button" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold hover:bg-slate-50" onclick="openProfileModal('modal-certification')">Modifier</button>
                    </div>
                    <div class="mt-4 space-y-3">
                        @forelse ($certifications as $item)
                            <article class="rounded-2xl border border-slate-200 px-4 py-3">
                                <p class="text-sm font-semibold text-slate-900">{{ $item['name'] ?? 'Certification' }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $item['issuer'] ?? 'Organisme' }} · {{ $item['date_obtained'] ?? '-' }}</p>
                            </article>
                        @empty
                            <p class="text-sm text-slate-500">Aucune certification ajoutee.</p>
                        @endforelse
                    </div>
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="font-display text-xl font-semibold text-slate-900">Experience associatif</h2>
                        <button type="button" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold hover:bg-slate-50" onclick="openProfileModal('modal-associative')">Modifier</button>
                    </div>
                    <div class="mt-4 space-y-3">
                        @forelse ($associativeExperiences as $item)
                            <article class="rounded-2xl border border-slate-200 px-4 py-3">
                                <p class="text-sm font-semibold text-slate-900">{{ $item['role'] ?? 'Poste' }} · {{ $item['club_name'] ?? 'Club' }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $item['department'] ?? 'Departement' }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $item['start_date'] ?? '-' }} - {{ $item['end_date'] ?? 'En cours' }}</p>
                                <p class="mt-2 text-sm text-slate-700">{{ $item['description'] ?? '' }}</p>
                            </article>
                        @empty
                            <p class="text-sm text-slate-500">Aucune experience associative ajoutee.</p>
                        @endforelse
                    </div>
                </section>

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
                            <div class="flex items-center justify-between rounded-xl bg-blue-50 px-3 py-2 text-xs font-medium text-blue-700">
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
    </div>
</div>

<dialog id="modal-professional" class="w-full max-w-4xl rounded-2xl border border-slate-200 p-0 backdrop:bg-slate-900/40">
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
                    <input name="items[{{ $index }}][company]" value="{{ $item['company'] ?? '' }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Nom de societe">
                    <input name="items[{{ $index }}][role]" value="{{ $item['role'] ?? '' }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Poste">
                    <input type="date" name="items[{{ $index }}][start_date]" value="{{ $item['start_date'] ?? '' }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <input type="date" name="items[{{ $index }}][end_date]" value="{{ $item['end_date'] ?? '' }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <textarea name="items[{{ $index }}][description]" rows="3" class="md:col-span-2 rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Description">{{ $item['description'] ?? '' }}</textarea>
                </div>
            @endforeach
        </div>
        <button type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-xs" onclick="addProfileRow('professional-list')">Ajouter experience</button>
        <button class="w-full rounded-xl bg-tlink-blue px-4 py-2 text-sm font-semibold text-white hover:bg-tlink-blue-700">Enregistrer</button>
    </form>
</dialog>

<dialog id="modal-education" class="w-full max-w-4xl rounded-2xl border border-slate-200 p-0 backdrop:bg-slate-900/40">
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
                    <input name="items[{{ $index }}][school]" value="{{ $item['school'] ?? '' }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm md:col-span-2" placeholder="Ecole">
                    <input type="date" name="items[{{ $index }}][start_date]" value="{{ $item['start_date'] ?? '' }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <input type="date" name="items[{{ $index }}][end_date]" value="{{ $item['end_date'] ?? '' }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
            @endforeach
        </div>
        <button type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-xs" onclick="addProfileRow('education-list')">Ajouter formation</button>
        <button class="w-full rounded-xl bg-tlink-blue px-4 py-2 text-sm font-semibold text-white hover:bg-tlink-blue-700">Enregistrer</button>
    </form>
</dialog>

<dialog id="modal-certification" class="w-full max-w-4xl rounded-2xl border border-slate-200 p-0 backdrop:bg-slate-900/40">
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

<dialog id="modal-associative" class="w-full max-w-4xl rounded-2xl border border-slate-200 p-0 backdrop:bg-slate-900/40">
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
                    <input name="items[{{ $index }}][club_name]" value="{{ $item['club_name'] ?? '' }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Nom de club">
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

<dialog id="modal-skills" class="w-full max-w-2xl rounded-2xl border border-slate-200 p-0 backdrop:bg-slate-900/40">
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

<dialog id="modal-languages" class="w-full max-w-3xl rounded-2xl border border-slate-200 p-0 backdrop:bg-slate-900/40">
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

<template id="tpl-professional">
    <div class="grid gap-2 rounded-xl border border-slate-200 p-3 md:grid-cols-2">
        <input name="items[__INDEX__][company]" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Nom de societe">
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
        <input name="items[__INDEX__][school]" class="rounded-lg border border-slate-300 px-3 py-2 text-sm md:col-span-2" placeholder="Ecole">
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
        <input name="items[__INDEX__][club_name]" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Nom de club">
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
</script>
@endsection
