@extends('layouts.base', ['title' => $member->name.' | T.Link'])

@section('content')
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    <main class="app-container space-y-5 py-6">
        @include('profile.partials.hero', ['person' => $member, 'editable' => false])

        @if($positions->isNotEmpty() || $clubs->isNotEmpty())
            <ul class="flex flex-wrap gap-2" aria-label="Postes et clubs">
                @foreach($positions as $position)
                    <li class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white py-1 pl-1 pr-3 text-xs font-semibold text-slate-700 shadow-sm">
                        <x-org-logo :url="$position['club']?->logoUrl()" :name="$position['club']?->title ?? ''" icon="fa-solid fa-briefcase" size="sm" class="!h-14 !w-14 !rounded-full" />
                        {{ $position['title'] }}
                    </li>
                @endforeach
                @foreach($clubs as $club)
                    <li class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white py-1 pl-1 pr-3 text-xs font-semibold text-slate-700 shadow-sm">
                        <x-org-logo :url="$club->logoUrl()" :name="$club->title" icon="fa-solid fa-people-group" size="sm" class="!h-14 !w-14 !rounded-full" />
                        {{ $club->title }}
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="grid gap-4 lg:grid-cols-12">
            <div class="space-y-4 lg:col-span-8">
                @if($profile->bio)
                    <section id="apropos" class="scroll-mt-20 rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
                        <h2 class="font-display text-xl font-semibold text-slate-900">A propos</h2>
                        <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $profile->bio }}</p>
                    </section>
                @endif

                @if($earnedBadges->isNotEmpty())
                    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft" id="badges">
                        <h2 class="font-display text-xl font-semibold text-slate-900">Badges</h2>
                        <ul class="mt-4 flex flex-wrap gap-4">
                            @foreach($earnedBadges as $earned)
                                <li class="flex w-24 flex-col items-center gap-1 text-center">
                                    <x-badge-medal :badge="$earned->badge" />
                                    <span class="text-xs font-semibold text-slate-800">{{ $earned->badge->name }}</span>
                                    <span class="rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $earned->badge->chip() }}">{{ $earned->badge->tierLabel() }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @include('profile.partials.details', ['editable' => false])

                @if($recentPosts->isNotEmpty())
                    <section id="activite" class="scroll-mt-20 rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
                        <h2 class="font-display text-xl font-semibold text-slate-900">Activite recente</h2>
                        <div class="mt-4 space-y-3">
                            @foreach($recentPosts as $post)
                                <article class="rounded-2xl border border-slate-200 px-4 py-3">
                                    <p class="text-xs text-slate-500">{{ optional($post->published_at)->format('d/m/Y H:i') }}</p>
                                    <p class="mt-1 line-clamp-3 text-sm text-slate-700">{{ $post->content }}</p>
                                    <p class="mt-2 text-xs text-slate-500">{{ $post->reactions_count }} reactions · {{ $post->comments_count }} commentaires</p>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>

            <aside class="space-y-4 lg:col-span-4">
                @if($skillKeywords->isNotEmpty())
                    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
                        <h2 class="font-display text-lg font-semibold text-slate-900">Competence</h2>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach($skillKeywords as $skill)
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700">{{ $skill }}</span>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if($languageItems->isNotEmpty())
                    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
                        <h2 class="font-display text-lg font-semibold text-slate-900">Langue</h2>
                        <div class="mt-3 space-y-2">
                            @foreach($languageItems as $language)
                                <div class="flex items-center justify-between rounded-xl bg-rose-50 px-3 py-2 text-xs font-medium text-tlink-blue">
                                    <span>{{ $language['name'] ?? 'Langue' }}</span>
                                    <span>{{ $language['level'] ?? '-' }}</span>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif
            </aside>
        </div>
    </main>
</div>
@endsection
