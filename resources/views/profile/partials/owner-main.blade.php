@php
    $card = 'rounded-3xl border border-slate-200 bg-white p-6 shadow-soft';
    $editBtn = 'inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50';
    $steps = [
        ['Photo de profil', filled($avatarUrl), 'avatar'],
        ['Photo de couverture', filled($coverUrl), 'cover'],
        ['Titre professionnel', filled($profile->headline), 'modal-about'],
        ['Bio', filled($profile->bio), 'modal-about'],
        ['Compétences', $skillKeywords->isNotEmpty(), 'modal-skills'],
        ['Langues', $languageItems->isNotEmpty(), 'modal-languages'],
        ['Expérience pro', $professionalExperiences->isNotEmpty(), 'modal-professional'],
        ['Formation', $educations->isNotEmpty(), 'modal-education'],
    ];
    $done = collect($steps)->where(1, true)->count();
    $percent = (int) round($done / count($steps) * 100);
@endphp

<main class="app-container space-y-5 py-6">
    @if($errors->any())
        <p class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $errors->first() }}</p>
    @endif

    @include('profile.partials.hero', ['person' => $user, 'editable' => true])

    <div class="grid gap-5 lg:grid-cols-12">
        <div class="space-y-5 lg:col-span-8">
            <section id="apropos" class="scroll-mt-20 {{ $card }}">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="font-display text-xl font-semibold text-slate-900">A propos</h2>
                    <button type="button" class="{{ $editBtn }}" onclick="openProfileModal('modal-about')"><i class="fa-solid fa-pen text-[10px]"></i> Modifier</button>
                </div>
                <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $profile->bio ?: 'Ajoutez une bio claire pour presenter votre impact, vos valeurs et vos objectifs.' }}</p>
            </section>

            <section id="badges" class="scroll-mt-20 {{ $card }}">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="font-display text-xl font-semibold text-slate-900">Badges</h2>
                    <a href="{{ route('badges.index') }}" class="{{ $editBtn }}">Voir les missions</a>
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

            <section id="activite" class="scroll-mt-20 {{ $card }}">
                <h2 class="font-display text-xl font-semibold text-slate-900">Activite recente</h2>
                <div class="mt-4 space-y-3">
                    @forelse ($recentPosts as $post)
                        <article class="rounded-2xl bg-slate-50 px-4 py-3">
                            <p class="text-xs text-slate-500">
                                {{ optional($post->published_at)->format('d/m/Y H:i') ?: 'Brouillon' }} · {{ $post->visibility }}
                            </p>
                            <p class="mt-1 line-clamp-3 text-sm text-slate-700">{{ $post->content }}</p>
                            <p class="mt-2 text-xs text-slate-500"><i class="fa-regular fa-heart mr-1"></i>{{ $post->reactions_count }} reactions · <i class="fa-regular fa-comment mr-1"></i>{{ $post->comments_count }} commentaires</p>
                        </article>
                    @empty
                        <p class="text-sm text-slate-500">Aucun post publie pour le moment.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <aside class="space-y-5 lg:col-span-4">
            @if($percent < 100)
                <section class="{{ $card }}">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="font-display text-lg font-semibold text-slate-900">Profil complété</h2>
                        <span class="text-sm font-semibold text-tlink-blue">{{ $percent }}%</span>
                    </div>
                    <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-tlink-blue" style="width: {{ $percent }}%"></div></div>
                    <ul class="mt-4 space-y-2">
                        @foreach($steps as [$label, $ok, $target])
                            <li class="flex items-center gap-2 text-sm {{ $ok ? 'text-slate-400 line-through' : 'text-slate-700' }}">
                                <i class="{{ $ok ? 'fa-solid fa-circle-check text-emerald-500' : 'fa-regular fa-circle text-slate-300' }} text-sm"></i>
                                @if($ok)
                                    {{ $label }}
                                @else
                                    <button type="button" class="text-left hover:text-tlink-blue hover:underline" onclick="{{ in_array($target, ['avatar', 'cover']) ? "openMediaModal('{$target}')" : "openProfileModal('{$target}')" }}">{{ $label }}</button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <section class="{{ $card }}">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="font-display text-lg font-semibold text-slate-900">Competence</h2>
                    <button type="button" class="{{ $editBtn }}" onclick="openProfileModal('modal-skills')"><i class="fa-solid fa-pen text-[10px]"></i> Modifier</button>
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    @forelse ($skillKeywords as $skill)
                        <span class="rounded-full bg-rose-50 px-3 py-1 text-xs font-medium text-tlink-blue">{{ $skill }}</span>
                    @empty
                        <p class="text-sm text-slate-500">Ajoutez vos competences clefs.</p>
                    @endforelse
                </div>
            </section>

            <section class="{{ $card }}">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="font-display text-lg font-semibold text-slate-900">Langue</h2>
                    <button type="button" class="{{ $editBtn }}" onclick="openProfileModal('modal-languages')"><i class="fa-solid fa-pen text-[10px]"></i> Modifier</button>
                </div>
                <div class="mt-3 space-y-2">
                    @forelse ($languageItems as $language)
                        <div class="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-700">
                            <span class="font-medium">{{ $language['name'] ?? 'Langue' }}</span>
                            <span class="rounded-full bg-white px-2 py-0.5 text-xs font-semibold text-tlink-blue shadow-sm">{{ $language['level'] ?? '-' }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Ajoutez vos langues.</p>
                    @endforelse
                </div>
            </section>
        </aside>
    </div>
</main>
