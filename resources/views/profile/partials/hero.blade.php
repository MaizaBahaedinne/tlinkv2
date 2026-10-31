{{-- En-tête du profil (cover, avatar, identité, chiffres clés). $person = membre affiché, $editable = propriétaire du profil. --}}
<section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-soft">
    <div class="relative h-44 overflow-hidden bg-gradient-to-br from-tlink-blue via-tlink-blue to-slate-900 sm:h-60">
        @if($coverUrl)
            <img src="{{ $coverUrl }}" alt="" class="absolute inset-0 h-full w-full object-cover">
        @else
            <div class="absolute -right-10 -top-10 h-56 w-56 rounded-full bg-white/10"></div>
            <div class="absolute -bottom-16 left-1/3 h-48 w-48 rounded-full bg-white/5"></div>
        @endif
        <div class="absolute inset-0 bg-gradient-to-t from-slate-900/40 to-transparent"></div>
        @if($editable)
            <button type="button" class="absolute right-4 top-4 inline-flex items-center gap-2 rounded-xl bg-white/90 px-3 py-2 text-xs font-semibold text-slate-800 shadow-soft hover:bg-white" onclick="openMediaModal('cover')">
                <i class="fa-solid fa-camera"></i> Modifier la cover
            </button>
        @endif
    </div>

    <div class="px-5 pb-6 sm:px-8">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-end">
            <div class="relative z-10 -mt-16 w-fit shrink-0 sm:-mt-20">
                <div class="flex h-32 w-32 items-center justify-center overflow-hidden rounded-[2rem] border-4 border-white bg-tlink-blue text-4xl font-semibold text-white shadow-soft sm:h-36 sm:w-36">
                    @if($avatarUrl)
                        <img src="{{ $avatarUrl }}" alt="Avatar de {{ $person->name }}" class="h-full w-full object-cover">
                    @else
                        {{ $avatarInitials ?: 'TL' }}
                    @endif
                </div>
                @if($editable)
                    <button type="button" class="absolute -bottom-2 -right-2 flex h-9 w-9 items-center justify-center rounded-full border-2 border-white bg-slate-900 text-xs text-white shadow-soft hover:bg-slate-700" onclick="openMediaModal('avatar')" title="Modifier l'avatar" aria-label="Modifier l'avatar">
                        <i class="fa-solid fa-camera"></i>
                    </button>
                @endif
            </div>

            <div class="min-w-0 flex-1 sm:pb-1">
                @php
                    $isVerified = $profile?->identity_verified_at !== null;
                    $verification = $editable && ! $isVerified ? \App\Support\IdentityVerifications::latestFor($person) : null;
                @endphp
                <div class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1">
                    <h1 class="min-w-0 truncate font-display text-2xl font-semibold text-slate-900 sm:text-3xl">{{ $person->name }}</h1>
                    @if($isVerified)
                        <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-sky-100 px-2.5 py-1 text-xs font-bold text-sky-700" title="Identité vérifiée par la Fondation Tunivisions"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Profil vérifié</span>
                    @elseif($editable && $verification?->isPending())
                        <a href="{{ route('profile.verification') }}" class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-800 hover:bg-amber-200"><i class="fa-regular fa-clock" aria-hidden="true"></i> Vérification en cours</a>
                    @elseif($editable)
                        <a href="{{ route('profile.verification') }}" class="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-slate-300 bg-white px-2.5 py-1 text-xs font-bold text-slate-700 hover:bg-slate-50"><i class="fa-regular fa-circle-check" aria-hidden="true"></i> Vérifier mon profil</a>
                    @endif
                </div>
                <p class="mt-1 text-sm text-slate-600">{{ $profile->headline ?: ($editable ? 'Ajoutez un titre professionnel impactant.' : 'Membre Tunivisions') }}</p>
                @if($editable)
                    <p class="mt-1 truncate text-xs text-slate-500"><i class="fa-regular fa-envelope mr-1"></i>{{ $person->email }}</p>
                @endif
            </div>

            <div class="flex flex-wrap gap-2 sm:pb-1">
                @if($editable)
                    <button type="button" class="inline-flex items-center gap-2 rounded-xl bg-tlink-blue px-4 py-2 text-sm font-semibold text-white hover:opacity-90" onclick="openProfileModal('modal-about')">
                        <i class="fa-solid fa-pen text-xs"></i> Modifier le profil
                    </button>
                @elseif(\App\Support\Messenger\Messenger::enabled())
                    <form method="post" action="{{ route('messenger.direct', $person) }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-tlink-blue px-4 py-2 text-sm font-semibold text-white hover:opacity-90"><i class="fa-regular fa-comment-dots" aria-hidden="true"></i> Envoyer un message</button>
                    </form>
                @endif
            </div>
        </div>

        @include('profile.partials.highlights')

        <dl class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach([
                ['Posts', $stats['posts_count'], 'fa-regular fa-newspaper'],
                ['Commentaires', $stats['comments_count'], 'fa-regular fa-comment'],
                ['Réactions', $stats['reactions_count'], 'fa-regular fa-heart'],
                ['Badges', $earnedBadges->count(), 'fa-solid fa-medal'],
            ] as [$label, $value, $icon])
                <div class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white text-tlink-blue shadow-sm"><i class="{{ $icon }}"></i></span>
                    <div>
                        <dd class="text-xl font-semibold leading-none text-slate-900">{{ $value }}</dd>
                        <dt class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</dt>
                    </div>
                </div>
            @endforeach
        </dl>
    </div>
</section>

<nav class="sticky top-2 z-20 -mx-1 flex gap-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white/95 p-2 shadow-soft backdrop-blur" aria-label="Sections du profil">
    @foreach([['apropos', 'A propos', 'fa-regular fa-address-card'], ['badges', 'Badges', 'fa-solid fa-medal'], ['parcours', 'Parcours', 'fa-solid fa-route'], ['activite', 'Activité', 'fa-regular fa-newspaper']] as [$anchor, $text, $icon])
        <a href="#{{ $anchor }}" class="inline-flex shrink-0 items-center gap-2 rounded-xl px-3 py-2 text-sm font-medium text-slate-600 hover:bg-rose-50 hover:text-tlink-blue"><i class="{{ $icon }} text-xs"></i>{{ $text }}</a>
    @endforeach
</nav>
