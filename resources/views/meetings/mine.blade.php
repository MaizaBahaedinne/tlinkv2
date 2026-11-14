@extends('layouts.base', ['title' => 'Mes réunions | T.Link'])

@section('content')
@php
    $responseStyles = ['pending' => 'bg-amber-50 text-amber-700', 'accepted' => 'bg-emerald-50 text-emerald-700', 'declined' => 'bg-rose-50 text-rose-700'];
    $outcomeStyles = ['adopted' => 'text-emerald-700', 'rejected' => 'text-rose-700', 'postponed' => 'text-amber-700', 'info' => 'text-slate-600', 'pending' => 'text-slate-500'];
@endphp
<div class="min-h-screen bg-tlink-sand pb-24 text-slate-800 lg:pb-12">
    @include('partials.app-header')

    <div class="app-container space-y-6 py-6">
        <div>
            <h1 class="font-display text-2xl font-semibold text-tlink-blue">Mes réunions</h1>
            <p class="max-w-2xl text-sm text-slate-600">Les réunions auxquelles vous êtes invité(e) : confirmez votre présence et retrouvez l’ordre du jour et les décisions prises.</p>
        </div>

        <section class="space-y-3" aria-label="Réunions à venir">
            <h2 class="font-display text-lg font-semibold text-slate-800">À venir</h2>
            @forelse($upcoming as $row)
                @php $meeting = $row->meeting; @endphp
                <article class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">{{ $meeting->title }}</p>
                            <p class="mt-1 text-xs text-slate-600">
                                <i class="fa-regular fa-calendar mr-1" aria-hidden="true"></i>{{ $meeting->starts_at->translatedFormat('l d F Y \à H:i') }} · {{ $meeting->duration_minutes }} min
                                @if($meeting->location) · <i class="fa-solid fa-location-dot mr-1" aria-hidden="true"></i>{{ $meeting->location }}@endif
                                @if($meeting->club) · {{ $meeting->club->title }}@endif
                            </p>
                            @if($meeting->online_url)<p class="mt-1 text-xs"><a class="font-semibold text-tlink-blue hover:underline" href="{{ $meeting->online_url }}" target="_blank" rel="noopener noreferrer">Rejoindre en ligne</a></p>@endif
                        </div>
                        <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $responseStyles[$row->response] ?? '' }}">{{ \App\Models\MeetingAttendee::RESPONSES[$row->response] }}</span>
                    </div>
                    @if($meeting->subject)<p class="mt-2 text-sm text-slate-600">{{ $meeting->subject }}</p>@endif
                    @if($meeting->items->isNotEmpty())
                        <ol class="mt-3 list-decimal space-y-1 pl-5 text-sm text-slate-700">
                            @foreach($meeting->items as $item)<li>{{ $item->title }}@if($item->duration_minutes) <span class="text-xs text-slate-400">({{ $item->duration_minutes }} min)</span>@endif</li>@endforeach
                        </ol>
                    @endif
                    <form method="post" action="{{ route('meetings.respond', $meeting) }}" class="mt-3 flex flex-wrap gap-2">
                        @csrf
                        <button name="response" value="accepted" class="rounded-full bg-emerald-600 px-4 py-1.5 text-sm font-semibold text-white hover:bg-emerald-700" type="submit">Je viendrai</button>
                        <button name="response" value="declined" class="rounded-full border border-slate-300 bg-white px-4 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50" type="submit">Je ne pourrai pas</button>
                    </form>
                </article>
            @empty
                <p class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-8 text-center text-sm text-slate-500">Aucune réunion à venir.</p>
            @endforelse
        </section>

        @if($past->isNotEmpty())
            <section class="space-y-3" aria-label="Réunions passées">
                <h2 class="font-display text-lg font-semibold text-slate-800">Passées</h2>
                @foreach($past as $row)
                    @php $meeting = $row->meeting; @endphp
                    <article class="rounded-3xl border border-slate-200 bg-white/80 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-sm font-semibold text-slate-900">{{ $meeting->title }}</p>
                            <span class="text-xs text-slate-500">{{ $meeting->starts_at->format('d/m/Y H:i') }} · {{ \App\Models\Meeting::STATUSES[$meeting->status] }}@if($row->attendance) · {{ \App\Models\MeetingAttendee::ATTENDANCE[$row->attendance] }}@endif</span>
                        </div>
                        @if($meeting->status === 'held' && $meeting->items->isNotEmpty())
                            <ul class="mt-2 space-y-1 text-sm">
                                @foreach($meeting->items as $item)
                                    <li><span class="font-medium text-slate-800">{{ $item->title }}</span>
                                        <span class="text-xs font-semibold {{ $outcomeStyles[$item->outcome] ?? '' }}">· {{ \App\Models\MeetingAgendaItem::OUTCOMES[$item->outcome] }}</span>
                                        @if($item->decision)<p class="text-xs text-slate-600">{{ $item->decision }}</p>@endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </article>
                @endforeach
            </section>
        @endif
    </div>
</div>
@endsection
