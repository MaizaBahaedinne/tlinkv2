{{-- Adhésions et mandats en cours --}}
<section id="en-cours" class="card grid divide-y divide-slate-100 md:grid-cols-2 md:divide-x md:divide-y-0">
    <div class="p-5">
        <div class="flex items-center justify-between gap-2">
            <h2 class="font-display text-lg font-semibold text-slate-900"><i class="fa-solid fa-id-card mr-2 text-slate-400" aria-hidden="true"></i>Mes adhésions en cours</h2>
            <a href="{{ route('memberships.index') }}" class="text-xs font-semibold text-tlink-blue hover:underline">Demander</a>
        </div>
        <ul class="mt-3 space-y-2">
            @forelse($space['memberships'] as $row)
                <li class="rounded-xl bg-slate-50 px-4 py-3">
                    <p class="text-sm font-semibold text-slate-800">{{ $row['place'] }}</p>
                    <p class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                        <span class="rounded-full px-2 py-0.5 font-medium {{ $row['membership']->status === 'approved' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $row['label'] }}</span>
                        @if($scope = \App\Support\OrgScopes::label($row['membership']->scope))<span>{{ $scope }}</span>@endif
                        @if($since = $fmt($row['membership']->starts_at))<span>depuis le {{ $since }}</span>@endif
                        @if($until = $fmt($row['membership']->ends_at))<span>jusqu’au {{ $until }}</span>@endif
                    </p>
                </li>
            @empty
                <li class="text-sm text-slate-500">Aucune adhésion en cours.</li>
            @endforelse
        </ul>
    </div>

    <div class="p-5">
        <h2 class="font-display text-lg font-semibold text-slate-900"><i class="fa-solid fa-briefcase mr-2 text-slate-400" aria-hidden="true"></i>Mes mandats en cours</h2>
        <ul class="mt-3 space-y-2">
            @forelse($space['mandates'] as $row)
                <li class="rounded-xl bg-slate-50 px-4 py-3">
                    <p class="text-sm font-semibold text-slate-800">{{ $row['node']->title }}</p>
                    <p class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                        @if($row['club'])<span class="rounded-full bg-slate-100 px-2 py-0.5 font-medium text-slate-600">{{ $row['club']->title }}</span>@endif
                        @if($since = $fmt($row['assignment']->starts_at))<span>depuis le {{ $since }}</span>@endif
                        @if($until = $fmt($row['assignment']->ends_at))<span>jusqu’au {{ $until }}</span>@endif
                    </p>
                </li>
            @empty
                <li class="text-sm text-slate-500">Aucun mandat en cours.</li>
            @endforelse
        </ul>
    </div>
</section>
