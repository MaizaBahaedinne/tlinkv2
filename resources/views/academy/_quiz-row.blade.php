<li class="flex items-center gap-3 py-2 text-sm">
    <i class="fa-solid {{ $isPassed ? 'fa-circle-check text-emerald-600' : 'fa-clipboard-question text-tlink-blue' }}" aria-hidden="true"></i>
    @if($canAccess)
        <a href="{{ route('academy.quizzes.show', $quiz) }}" class="flex-1 font-semibold text-slate-800 hover:text-tlink-blue">{{ $quiz->title }}</a>
    @else
        <span class="flex-1 font-semibold text-slate-500">{{ $quiz->title }} <i class="fa-solid fa-lock ml-1 text-xs" aria-hidden="true"></i></span>
    @endif
    @if($quiz->is_required)<span class="rounded-full bg-rose-50 px-2 py-0.5 text-[11px] font-bold text-tlink-blue">Requis</span>@endif
    <span class="text-xs text-slate-500">{{ $isPassed ? 'Réussi' : $quiz->questions_count.' question(s) · '.$quiz->pass_percent.' % pour réussir' }}</span>
</li>
