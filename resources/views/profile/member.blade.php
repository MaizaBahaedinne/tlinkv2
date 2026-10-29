@extends('layouts.base', ['title' => $member->name.' | T.Link'])

@section('content')
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    <main class="app-container space-y-5 py-6">
        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-soft">
            <div class="relative h-44 overflow-hidden bg-gradient-to-br from-tlink-blue to-slate-900 sm:h-56">
                @if($coverUrl)
                    <img src="{{ $coverUrl }}" alt="" class="absolute inset-0 h-full w-full object-cover">
                @endif
                <div class="absolute inset-0 bg-gradient-to-t from-slate-900/50 via-transparent to-transparent"></div>
            </div>

            <div class="px-4 pb-6 sm:px-8">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:gap-6">
                    <div class="relative z-10 -mt-14 w-fit shrink-0 sm:-mt-16">
                        <div class="flex h-28 w-28 items-center justify-center overflow-hidden rounded-3xl border-4 border-white bg-tlink-blue text-3xl font-semibold text-white shadow-soft sm:h-32 sm:w-32 sm:text-4xl">
                            @if($avatarUrl)
                                <img src="{{ $avatarUrl }}" alt="Avatar de {{ $member->name }}" class="h-full w-full object-cover">
                            @else
                                {{ $avatarInitials ?: 'TL' }}
                            @endif
                        </div>
                    </div>
                    <div class="min-w-0 flex-1 sm:pb-1">
                        <h1 class="font-display text-2xl font-semibold text-slate-900">{{ $member->name }}</h1>
                        <p class="text-sm text-slate-600">{{ $profile->headline ?: 'Membre Tunivisions' }}</p>
                        @include('profile.partials.highlights')
                    </div>
                    @if(\App\Support\Messenger\Messenger::enabled())
                        <form method="post" action="{{ route('messenger.direct', $member) }}" class="sm:pb-1">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-tlink-blue px-4 py-2 text-sm font-semibold text-white hover:opacity-90"><i class="fa-regular fa-comment-dots" aria-hidden="true"></i> Envoyer un message</button>
                        </form>
                    @endif
                </div>

                @if($positions->isNotEmpty() || $clubs->isNotEmpty())
                    <ul class="mt-5 flex flex-wrap gap-2" aria-label="Postes et clubs">
                        @foreach($positions as $position)
                            <li class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 py-1 pl-1 pr-3 text-xs font-semibold text-slate-700">
                                <x-org-logo :url="$position['club']?->logoUrl()" :name="$position['club']?->title ?? ''" icon="fa-solid fa-briefcase" size="sm" class="!h-14 !w-14 !rounded-full" />
                                {{ $position['title'] }}
                            </li>
                        @endforeach
                        @foreach($clubs as $club)
                            <li class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 py-1 pl-1 pr-3 text-xs font-semibold text-slate-700">
                                <x-org-logo :url="$club->logoUrl()" :name="$club->title" icon="fa-solid fa-people-group" size="sm" class="!h-14 !w-14 !rounded-full" />
                                {{ $club->title }}
                            </li>
                        @endforeach
                    </ul>
                @endif

                <div class="mt-6 grid gap-3 sm:grid-cols-3">
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
                @if($profile->bio)
                    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
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
                    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
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
