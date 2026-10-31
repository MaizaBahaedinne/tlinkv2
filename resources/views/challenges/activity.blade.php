@extends('layouts.base', ['title' => $activity->title.' | Défis'])

@php
    $club = $activity->club;
    $logo = $club->logoUrl();
    $cover = $activity->coverUrl();
    $full = $activity->capacity !== null && $registered >= $activity->capacity;
    $over = $activity->endsAt()->isPast();
    $ends = $activity->ends_at ? $activity->ends_at->format($activity->ends_at->isSameDay($activity->starts_at) ? 'H:i' : 'd/m/Y H:i') : null;
    $coOrganizers = $activity->organizers->where('status', 'accepted');
    $initials = collect(preg_split('/\s+/', trim($club->title)))->filter()->take(2)->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->implode('');
    $closedLabel = $over ? 'Activité terminée' : 'Inscriptions closes';
@endphp

@section('content')
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    <div class="app-container space-y-5 py-6">
        <a href="{{ route('challenges.show', $activity->challenge) }}" class="text-sm font-semibold text-slate-500 hover:text-tlink-blue"><i class="fa-solid fa-arrow-left mr-1" aria-hidden="true"></i>{{ $activity->challenge->title }}</a>

        @if(session('error'))<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">@foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>@endif

        {{-- Bandeau : couverture (ou fond T.Link), logo du club, titre et inscription rapide --}}
        <header class="relative isolate overflow-hidden rounded-3xl border border-slate-200 bg-gradient-to-br from-tlink-blue to-tlink-blue-700 shadow-soft">
            @if($cover)
                <img src="{{ $cover }}" alt="" class="absolute inset-0 -z-10 h-full w-full object-cover">
            @endif
            <div class="absolute inset-0 -z-10 bg-gradient-to-t from-slate-950/85 via-slate-900/40 to-slate-900/10"></div>

            <div class="flex min-h-[18rem] flex-col justify-end gap-5 p-6 text-white sm:min-h-[22rem] sm:p-8">
                <div class="flex flex-wrap items-center gap-2 text-xs font-semibold">
                    <span class="rounded-full bg-white/20 px-3 py-1 backdrop-blur">{{ $activity->typeLabel() }}</span>
                    <span class="rounded-full bg-white/20 px-3 py-1 backdrop-blur">{{ $activity->in_theme ? 'Dans la thématique' : 'Hors thématique' }}</span>
                    @if($activity->status !== 'published')<span class="rounded-full bg-amber-400 px-3 py-1 text-amber-950">{{ \App\Models\ClubActivity::STATUSES[$activity->status] }} · non visible des membres</span>@endif
                </div>

                <div class="flex items-end gap-4">
                    @if($logo)
                        <img src="{{ $logo }}" alt="Logo {{ $club->title }}" class="h-16 w-16 shrink-0 rounded-2xl bg-white object-contain p-1.5 shadow-lg ring-2 ring-white/70 sm:h-20 sm:w-20">
                    @else
                        <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-white text-xl font-bold text-tlink-blue shadow-lg ring-2 ring-white/70 sm:h-20 sm:w-20" aria-hidden="true">{{ $initials }}</span>
                    @endif
                    <div class="min-w-0">
                        <h1 class="font-display text-2xl font-semibold leading-tight sm:text-4xl">{{ $activity->title }}</h1>
                        <p class="mt-1 text-sm text-white/85">Organisée par <strong>{{ $club->title }}</strong>@foreach($coOrganizers as $organizer), <strong>{{ $organizer->club?->title }}</strong>@endforeach</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-4">
                    <ul class="flex flex-wrap gap-x-5 gap-y-2 text-sm font-medium">
                        <li><i class="fa-regular fa-calendar mr-1.5" aria-hidden="true"></i>{{ $activity->starts_at->translatedFormat('l d F Y') }}</li>
                        <li><i class="fa-regular fa-clock mr-1.5" aria-hidden="true"></i>{{ $activity->starts_at->format('H:i') }}@if($ends) → {{ $ends }}@endif</li>
                        <li><i class="fa-solid fa-location-dot mr-1.5" aria-hidden="true"></i>{{ $activity->location ?: 'Lieu à préciser' }}</li>
                    </ul>

                    <div>
                        @if($active && $registration->status === 'waitlisted')
                            <a href="#inscription" class="inline-flex items-center gap-2 rounded-xl bg-amber-400 px-5 py-2.5 text-sm font-bold text-amber-950"><i class="fa-regular fa-clock" aria-hidden="true"></i> Sur liste d’attente</a>
                        @elseif($active)
                            <a href="#inscription" class="inline-flex items-center gap-2 rounded-xl bg-emerald-500 px-5 py-2.5 text-sm font-bold text-white"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Vous êtes inscrit</a>
                        @elseif(! $activity->registrationOpen())
                            <span class="inline-flex items-center gap-2 rounded-xl bg-white/20 px-5 py-2.5 text-sm font-semibold backdrop-blur">{{ $closedLabel }}</span>
                        @elseif($reason)
                            <a href="#inscription" class="inline-flex items-center gap-2 rounded-xl bg-white/20 px-5 py-2.5 text-sm font-semibold backdrop-blur"><i class="fa-solid fa-lock" aria-hidden="true"></i> Accès restreint</a>
                        @elseif($activity->fields() === [])
                            <form method="post" action="{{ route('activities.register', $activity) }}">
                                @csrf
                                <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-white px-6 py-3 text-sm font-bold text-tlink-blue shadow-lg hover:bg-slate-100"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> {{ $full ? 'Rejoindre la liste d’attente' : 'M’inscrire' }}</button>
                            </form>
                        @else
                            <a href="#inscription" class="inline-flex items-center gap-2 rounded-xl bg-white px-6 py-3 text-sm font-bold text-tlink-blue shadow-lg hover:bg-slate-100"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> {{ $full ? 'Rejoindre la liste d’attente' : 'M’inscrire' }}</a>
                        @endif
                    </div>
                </div>
            </div>
        </header>

        <div class="grid gap-5 lg:grid-cols-3">
            <div class="space-y-5 lg:col-span-2">
                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
                    <h2 class="font-display text-xl font-semibold text-slate-900">À propos</h2>
                    @if($activity->description)
                        <p class="mt-3 whitespace-pre-line text-sm leading-relaxed text-slate-700">{{ $activity->description }}</p>
                    @else
                        <p class="mt-3 text-sm text-slate-500">Aucune description pour le moment.</p>
                    @endif

                    <dl class="mt-6 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-2xl bg-slate-50 px-4 py-3"><dt class="text-xs font-semibold uppercase text-slate-500">Quand</dt><dd class="mt-1 text-sm font-semibold">{{ $activity->starts_at->format('d/m/Y H:i') }}@if($activity->ends_at) → {{ $activity->ends_at->format($activity->ends_at->isSameDay($activity->starts_at) ? 'H:i' : 'd/m/Y H:i') }}@endif</dd></div>
                        <div class="rounded-2xl bg-slate-50 px-4 py-3"><dt class="text-xs font-semibold uppercase text-slate-500">Où</dt><dd class="mt-1 text-sm font-semibold">{{ $activity->location ?: 'À préciser' }}</dd></div>
                        <div class="rounded-2xl bg-slate-50 px-4 py-3"><dt class="text-xs font-semibold uppercase text-slate-500">Public</dt><dd class="mt-1 text-sm font-semibold">{{ $activity->audienceLabel() }}@if($activity->department) · {{ $activity->department->title }}@endif</dd></div>
                        <div class="rounded-2xl bg-slate-50 px-4 py-3"><dt class="text-xs font-semibold uppercase text-slate-500">Places</dt><dd class="mt-1 text-sm font-semibold">{{ $registered }}{{ $activity->capacity ? ' / '.$activity->capacity : '' }} inscrit(s)</dd></div>
                    </dl>

                    @if($activity->capacity)
                        @php($fill = min(100, (int) round($registered / $activity->capacity * 100)))
                        <div class="mt-4" aria-hidden="true"><div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full {{ $full ? 'bg-amber-500' : 'bg-tlink-blue' }}" style="width: {{ $fill }}%"></div></div></div>
                    @endif
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
                    <h2 class="font-display text-xl font-semibold text-slate-900">Organisateurs</h2>
                    <ul class="mt-3 flex flex-wrap gap-3">
                        @foreach($coOrganizers->pluck('club')->prepend($club)->filter() as $organizerClub)
                            <li class="flex items-center gap-3 rounded-2xl border border-slate-200 px-3 py-2">
                                @if($organizerClub->logoUrl())
                                    <img src="{{ $organizerClub->logoUrl() }}" alt="" class="h-10 w-10 rounded-xl bg-white object-contain">
                                @else
                                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-sm font-bold text-slate-500" aria-hidden="true">{{ mb_strtoupper(mb_substr($organizerClub->title, 0, 1)) }}</span>
                                @endif
                                <span class="text-sm font-semibold text-slate-800">{{ $organizerClub->title }}@if($organizerClub->is($club)) <span class="block text-xs font-normal text-slate-500">Club porteur</span>@endif</span>
                            </li>
                        @endforeach
                    </ul>
                </section>

                @if($canManage)
                    <p class="flex flex-wrap gap-2 text-sm">
                        <a href="{{ route('admin.activities.show', $activity) }}" class="rounded-xl border border-slate-300 bg-white px-3 py-1.5 font-semibold text-slate-700 hover:bg-slate-50">Gérer l’activité</a>
                        <a href="{{ route('admin.activities.qr', $activity) }}" class="rounded-xl border border-slate-300 bg-white px-3 py-1.5 font-semibold text-slate-700 hover:bg-slate-50">QR code de présence</a>
                    </p>
                @endif
            </div>

            {{-- Inscription : colonne latérale, toujours visible à côté de la description --}}
            <aside class="lg:col-span-1">
                <section id="inscription" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft lg:sticky lg:top-24">
                    <h2 class="font-display text-xl font-semibold text-slate-900">Inscription</h2>

                    @if($active && $registration->checked_in_at)
                        <p class="mt-3 inline-flex items-center gap-2 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Présence validée le {{ $registration->checked_in_at->format('d/m/Y à H:i') }}.</p>
                    @elseif($active)
                        <p class="mt-3 text-sm text-slate-700">
                            @if($registration->status === 'waitlisted')
                                <strong class="text-amber-700">Vous êtes sur liste d’attente.</strong> Vous serez inscrit si une place se libère.
                            @else
                                <strong class="text-emerald-700">Vous êtes inscrit.</strong> Le jour de l’activité, ouvrez le menu de votre compte, choisissez « Scanner un QR code », visez le QR code affiché par les organisateurs puis remplissez le formulaire d’avis : l’envoi valide votre présence {{ $activity->checkinOpen() ? '(ouvert maintenant)' : '(ouvert dès 1 h avant le début)' }}.
                            @endif
                        </p>
                        @if($registration->status === 'registered' && $activity->checkinOpen())
                            <a href="{{ route('activities.scan') }}" class="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-tlink-blue px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90"><i class="fa-solid fa-qrcode" aria-hidden="true"></i> Scanner maintenant</a>
                        @endif
                        <form method="post" action="{{ route('activities.cancel', $activity) }}" class="mt-3">@csrf @method('DELETE')<button type="submit" class="w-full rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Annuler mon inscription</button></form>
                    @elseif(! $activity->registrationOpen())
                        <p class="mt-3 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600">{{ $over ? 'Cette activité est terminée : les inscriptions sont closes.' : 'Les inscriptions sont closes.' }}</p>
                    @elseif($reason)
                        <p class="mt-3 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600"><i class="fa-solid fa-lock mr-2" aria-hidden="true"></i>{{ $reason }}</p>
                    @else
                        <p class="mt-2 text-sm text-slate-600">
                            @if($full)Les places sont toutes prises : vous serez placé sur liste d’attente.
                            @elseif($activity->capacity)Plus que {{ $activity->capacity - $registered }} place(s) sur {{ $activity->capacity }}.
                            @else Inscription gratuite, places illimitées.@endif
                        </p>
                        <form method="post" action="{{ route('activities.register', $activity) }}" class="mt-4 space-y-4">
                            @csrf
                            @foreach($activity->fields() as $i => $field)
                                <div>
                                    <label for="answer-{{ $i }}" class="text-sm font-semibold text-slate-700">{{ $field['label'] }}@if($field['required'] ?? false) <span class="text-rose-600">*</span>@endif</label>
                                    @if($field['type'] === 'textarea')
                                        <textarea id="answer-{{ $i }}" name="answers[{{ $i }}]" rows="3" maxlength="1000" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-tlink-blue">{{ old('answers.'.$i) }}</textarea>
                                    @elseif($field['type'] === 'choice')
                                        <select id="answer-{{ $i }}" name="answers[{{ $i }}]" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-tlink-blue">
                                            <option value="">—</option>
                                            @foreach($field['options'] as $option)<option value="{{ $option }}" @selected(old('answers.'.$i) === $option)>{{ $option }}</option>@endforeach
                                        </select>
                                    @elseif($field['type'] === 'checkbox')
                                        <label class="mt-1 flex items-center gap-2 text-sm"><input id="answer-{{ $i }}" type="checkbox" name="answers[{{ $i }}]" value="1" class="accent-tlink-blue" @checked(old('answers.'.$i))> Oui</label>
                                    @else
                                        <input id="answer-{{ $i }}" type="text" name="answers[{{ $i }}]" maxlength="255" value="{{ old('answers.'.$i) }}" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-tlink-blue">
                                    @endif
                                </div>
                            @endforeach
                            <button type="submit" class="w-full rounded-xl bg-tlink-blue px-5 py-3 text-sm font-semibold text-white hover:opacity-90"><i class="fa-solid fa-user-plus mr-1" aria-hidden="true"></i> {{ $full ? 'Rejoindre la liste d’attente' : 'M’inscrire' }}</button>
                        </form>
                    @endif
                </section>
            </aside>
        </div>
    </div>
</div>
@endsection
