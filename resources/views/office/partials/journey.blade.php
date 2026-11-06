{{-- Parcours --}}
<section id="parcours" class="card p-5">
    <h2 class="font-display text-lg font-semibold text-slate-900"><i class="fa-solid fa-route mr-2 text-slate-400" aria-hidden="true"></i>Mon parcours</h2>
    <p class="text-xs text-slate-500">Adhésions, mandats et événements, du plus récent au plus ancien.</p>

    <ol class="mt-4 max-h-[28rem] space-y-4 overflow-y-auto border-s-2 border-slate-100 ps-5">
        @forelse($space['journey'] as $step)
            <li class="relative">
                <span class="absolute -start-[1.9rem] top-0.5 grid h-6 w-6 place-items-center rounded-full text-[11px] {{ $step['ongoing'] ? 'bg-tlink-blue text-white' : 'bg-slate-200 text-slate-600' }}"><i class="{{ $step['icon'] }}" aria-hidden="true"></i></span>
                <p class="text-sm font-semibold text-slate-800">
                    @if($step['url'])<a href="{{ $step['url'] }}" class="hover:underline">{{ $step['title'] }}</a>@else{{ $step['title'] }}@endif
                </p>
                <p class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                    <span>{{ $fmt($step['from']) }}@if($step['to']) → {{ $fmt($step['to']) }}@elseif($step['ongoing']) → aujourd’hui @endif</span>
                    @if($step['place'])<span>· {{ $step['place'] }}</span>@endif
                    @if($step['status'])<span class="rounded-full bg-slate-100 px-2 py-0.5 font-medium text-slate-600">{{ $step['status'] }}</span>@endif
                </p>
            </li>
        @empty
            <li class="text-sm text-slate-500">Votre parcours apparaîtra ici dès votre première adhésion, mandat ou participation.</li>
        @endforelse
    </ol>
</section>
