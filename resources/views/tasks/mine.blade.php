@extends('layouts.base', ['title' => 'Mes tâches | T.Link'])

@section('content')
@php
    $me = auth()->user();
    $teamStyles = ['event' => 'bg-rose-50 text-tlink-blue', 'marketing' => 'bg-violet-50 text-violet-700', 'hr' => 'bg-emerald-50 text-emerald-700', 'sponsoring' => 'bg-amber-50 text-amber-700'];
    $columnStyles = ['todo' => 'fa-regular fa-circle', 'doing' => 'fa-solid fa-spinner', 'done' => 'fa-solid fa-circle-check text-emerald-600'];
@endphp
<div class="min-h-screen bg-tlink-sand pb-24 text-slate-800 lg:pb-12">
    @include('partials.app-header')

    <div class="app-container space-y-6 py-6">
        <div>
            <h1 class="font-display text-2xl font-semibold text-tlink-blue">Mes tâches</h1>
            <p class="max-w-2xl text-sm text-slate-600">Les tâches des activités de club qui vous sont confiées, seul ou avec d’autres membres.</p>
        </div>

        @if($tasks->isEmpty())
            <p class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-10 text-center text-sm text-slate-500">Aucune tâche ne vous est confiée pour le moment.</p>
        @else
            <div class="grid gap-4 lg:grid-cols-3">
                @foreach(\App\Models\ActivityTask::STATUSES as $status => $statusLabel)
                    <section class="rounded-3xl border border-slate-200 bg-white/70 p-3" aria-label="{{ $statusLabel }}">
                        <h2 class="flex items-center gap-2 px-2 pb-2 font-display text-sm font-semibold text-slate-700">
                            <i class="{{ $columnStyles[$status] }} text-slate-500" aria-hidden="true"></i>{{ $statusLabel }}
                            <span class="rounded-full bg-slate-100 px-2 text-xs text-slate-500">{{ $columns[$status]->count() }}</span>
                        </h2>
                        <div class="space-y-2">
                            @forelse($columns[$status] as $task)
                                @php
                                    $activity = $task->activity;
                                    $href = \App\Support\Challenges\ActivityAccess::canManageActivity($me, $activity)
                                        ? route('admin.activities.tasks.board', $activity)
                                        : ($activity->isPublished() ? route('activities.show', $activity) : null);
                                    $others = $task->assignees->where('id', '!=', $me->id);
                                    $isNew = in_array($task->id, $fresh, true);
                                @endphp
                                <article class="rounded-2xl border bg-white p-3 shadow-sm {{ $isNew ? 'border-tlink-blue ring-1 ring-tlink-blue/30' : 'border-slate-200' }}">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $teamStyles[$task->team] ?? 'bg-slate-100 text-slate-600' }}">{{ $task->teamLabel() }}</span>
                                        @if($isNew)<span class="rounded-full bg-tlink-blue px-2 py-0.5 text-[11px] font-bold text-white">Nouvelle</span>@endif
                                    </div>
                                    <p class="mt-2 text-sm font-semibold {{ $task->isDone() ? 'text-slate-500 line-through' : 'text-slate-900' }}">{{ $task->label }}</p>
                                    @if($task->notes)<p class="mt-1 text-xs text-slate-500">{{ \Illuminate\Support\Str::limit($task->notes, 160) }}</p>@endif
                                    <p class="mt-2 text-xs text-slate-600">
                                        @if($href)<a href="{{ $href }}" class="font-semibold text-tlink-blue hover:underline">{{ $activity->title }}</a>@else{{ $activity->title }}@endif
                                        · {{ $activity->club?->title }}
                                    </p>
                                    <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                                        @if($task->due_at)
                                            <span class="{{ $task->isLate() ? 'font-semibold text-rose-700' : 'text-slate-500' }}"><i class="fa-regular fa-calendar mr-1" aria-hidden="true"></i>{{ $task->isLate() ? 'En retard · ' : '' }}{{ $task->due_at->format('d/m/Y') }}</span>
                                        @endif
                                        @if($others->isNotEmpty())
                                            <span class="text-slate-500"><i class="fa-solid fa-user-group mr-1" aria-hidden="true"></i>Avec {{ $others->pluck('name')->implode(', ') }}</span>
                                        @endif
                                    </p>
                                </article>
                            @empty
                                <p class="px-2 py-3 text-xs text-slate-400">Rien ici.</p>
                            @endforelse
                        </div>
                    </section>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
