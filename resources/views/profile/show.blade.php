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
                    <h2 class="font-display text-xl font-semibold text-slate-900">Experience</h2>
                    <div class="mt-4 space-y-3">
                        @forelse ($experienceItems as $item)
                            <div class="rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700">{{ $item }}</div>
                        @empty
                            <p class="text-sm text-slate-500">Aucune experience ajoutee pour le moment.</p>
                        @endforelse
                    </div>
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
                    <h2 class="font-display text-xl font-semibold text-slate-900">Formation</h2>
                    <div class="mt-4 space-y-3">
                        @forelse ($educationItems as $item)
                            <div class="rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700">{{ $item }}</div>
                        @empty
                            <p class="text-sm text-slate-500">Aucune formation ajoutee pour le moment.</p>
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
                    <h2 class="font-display text-lg font-semibold text-slate-900">Competences</h2>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @forelse ($skills as $skill)
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700">{{ $skill }}</span>
                        @empty
                            <p class="text-sm text-slate-500">Ajoutez vos competences clefs.</p>
                        @endforelse
                    </div>
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
                    <h2 class="font-display text-lg font-semibold text-slate-900">Langues</h2>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @forelse ($languages as $language)
                            <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700">{{ $language }}</span>
                        @empty
                            <p class="text-sm text-slate-500">Ajoutez vos langues.</p>
                        @endforelse
                    </div>
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
                    <h2 class="font-display text-lg font-semibold text-slate-900">Modifier le profil</h2>
                    <form method="post" action="{{ route('profile.update') }}" class="mt-4 grid gap-3">
                        @csrf
                        @method('PATCH')

                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="headline">Titre</label>
                            <input id="headline" name="headline" type="text" value="{{ old('headline', $profile?->headline) }}" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="Ex: Responsable Communication - Tunivisions">
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="bio">A propos</label>
                            <textarea id="bio" name="bio" rows="4" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="Votre resume professionnel">{{ old('bio', $profile?->bio) }}</textarea>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="experience">Experience</label>
                            <textarea id="experience" name="experience" rows="4" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="Une ligne par experience">{{ old('experience', $profile?->experience) }}</textarea>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="education">Formation</label>
                            <textarea id="education" name="education" rows="3" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="Une ligne par formation">{{ old('education', $profile?->education) }}</textarea>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="skills">Competences</label>
                            <input id="skills" name="skills" type="text" value="{{ old('skills', $profile?->skills) }}" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="Leadership, Gestion projet, Design thinking">
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="languages">Langues</label>
                            <input id="languages" name="languages" type="text" value="{{ old('languages', $profile?->languages) }}" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="Francais, Arabe, Anglais">
                        </div>

                        <button type="submit" class="mt-2 rounded-xl bg-tlink-blue px-4 py-2 text-sm font-semibold text-white hover:bg-tlink-blue-700">Enregistrer les modifications</button>
                    </form>
                </section>
            </aside>
        </div>
    </div>
</div>
@endsection
