@extends('layouts.base', ['title' => $activity->title.' | Défis'])

@section('content')
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    <div class="app-container space-y-5 py-6">
        <a href="{{ route('challenges.show', $activity->challenge) }}" class="text-sm font-semibold text-slate-500 hover:text-tlink-blue"><i class="fa-solid fa-arrow-left mr-1" aria-hidden="true"></i>{{ $activity->challenge->title }}</a>

        @if(session('error'))<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">@foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>@endif

        <header class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $activity->typeLabel() }} · {{ $activity->in_theme ? 'Dans la thématique' : 'Hors thématique' }}</p>
                    <h1 class="font-display text-2xl font-semibold text-slate-900">{{ $activity->title }}</h1>
                    <p class="mt-1 text-sm text-slate-600">Organisée par <strong>{{ $activity->club->title }}</strong>@foreach($activity->organizers->where('status', 'accepted') as $organizer), <strong>{{ $organizer->club?->title }}</strong>@endforeach</p>
                </div>
                @if($activity->status !== 'published')<span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-800">{{ \App\Models\ClubActivity::STATUSES[$activity->status] }} — non visible des membres</span>@endif
            </div>

            <dl class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-2xl bg-slate-50 px-4 py-3"><dt class="text-xs font-semibold uppercase text-slate-500">Quand</dt><dd class="mt-1 text-sm font-semibold">{{ $activity->starts_at->format('d/m/Y H:i') }}@if($activity->ends_at) → {{ $activity->ends_at->format($activity->ends_at->isSameDay($activity->starts_at) ? 'H:i' : 'd/m/Y H:i') }}@endif</dd></div>
                <div class="rounded-2xl bg-slate-50 px-4 py-3"><dt class="text-xs font-semibold uppercase text-slate-500">Où</dt><dd class="mt-1 text-sm font-semibold">{{ $activity->location ?: 'À préciser' }}</dd></div>
                <div class="rounded-2xl bg-slate-50 px-4 py-3"><dt class="text-xs font-semibold uppercase text-slate-500">Public</dt><dd class="mt-1 text-sm font-semibold">{{ $activity->audienceLabel() }}@if($activity->department) · {{ $activity->department->title }}@endif</dd></div>
                <div class="rounded-2xl bg-slate-50 px-4 py-3"><dt class="text-xs font-semibold uppercase text-slate-500">Places</dt><dd class="mt-1 text-sm font-semibold">{{ $registered }}{{ $activity->capacity ? ' / '.$activity->capacity : '' }} inscrit(s)</dd></div>
            </dl>

            @if($activity->description)<p class="mt-5 whitespace-pre-line text-sm text-slate-700">{{ $activity->description }}</p>@endif
            @if($canManage)
                <p class="mt-4 flex flex-wrap gap-2 text-sm">
                    <a href="{{ route('admin.activities.show', $activity) }}" class="rounded-xl border border-slate-300 px-3 py-1.5 font-semibold text-slate-700 hover:bg-slate-50">Gérer l’activité</a>
                    <a href="{{ route('admin.activities.qr', $activity) }}" class="rounded-xl border border-slate-300 px-3 py-1.5 font-semibold text-slate-700 hover:bg-slate-50">QR code de présence</a>
                </p>
            @endif
        </header>

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
            <h2 class="font-display text-xl font-semibold text-slate-900">Inscription</h2>

            @if($active && $registration->checked_in_at)
                <p class="mt-3 inline-flex items-center gap-2 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Présence validée le {{ $registration->checked_in_at->format('d/m/Y à H:i') }}.</p>
            @elseif($active)
                <p class="mt-3 text-sm text-slate-700">
                    @if($registration->status === 'waitlisted')
                        <strong class="text-amber-700">Vous êtes sur liste d’attente.</strong> Vous serez inscrit si une place se libère.
                    @else
                        <strong class="text-emerald-700">Vous êtes inscrit.</strong> Le jour de l’activité, ouvrez le menu de votre compte, choisissez « Scanner un QR code », visez le QR code affiché par les organisateurs puis remplissez le formulaire d’avis : l’envoi valide votre présence {{ $activity->checkinOpen() ? '(ouvert maintenant)' : '(ouvert dès 1 h avant le début)' }}.
                        @if($activity->checkinOpen())<a href="{{ route('activities.scan') }}" class="ml-1 font-semibold text-tlink-blue hover:underline"><i class="fa-solid fa-qrcode" aria-hidden="true"></i> Scanner maintenant</a>@endif
                    @endif
                </p>
                <form method="post" action="{{ route('activities.cancel', $activity) }}" class="mt-3">@csrf @method('DELETE')<button type="submit" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Annuler mon inscription</button></form>
            @elseif(! $activity->registrationOpen())
                <p class="mt-3 text-sm text-slate-500">Les inscriptions sont closes.</p>
            @elseif($reason)
                <p class="mt-3 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600"><i class="fa-solid fa-lock mr-2" aria-hidden="true"></i>{{ $reason }}</p>
            @else
                <form method="post" action="{{ route('activities.register', $activity) }}" class="mt-3 space-y-4">
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
                    <button type="submit" class="rounded-xl bg-tlink-blue px-5 py-2.5 text-sm font-semibold text-white hover:opacity-90">{{ $activity->capacity !== null && $registered >= $activity->capacity ? 'Rejoindre la liste d’attente' : 'M’inscrire' }}</button>
                </form>
            @endif
        </section>
    </div>
</div>
@endsection
