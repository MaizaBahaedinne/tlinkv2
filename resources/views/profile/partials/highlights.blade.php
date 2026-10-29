{{-- Entreprise actuelle et établissement principal du membre, avec leur logo, sous son titre. --}}
@if(($highlights ?? collect())->isNotEmpty())
    <ul class="mt-3 flex flex-wrap gap-2" aria-label="Entreprise et établissement">
        @foreach($highlights as $highlight)
            <li class="inline-flex max-w-full items-center gap-2 rounded-full border border-slate-200 bg-slate-50 py-1 pl-1 pr-3 text-xs text-slate-700" data-highlight="{{ $highlight['kind'] }}">
                <x-org-logo :url="$highlight['logo_url']" :name="$highlight['name']" :icon="$highlight['kind'] === 'school' ? 'fa-solid fa-graduation-cap' : 'fa-solid fa-building'" size="sm" class="!h-14 !w-14 !rounded-full" />
                <span class="min-w-0 truncate">
                    @if($highlight['url'])
                        <a href="{{ $highlight['url'] }}" target="_blank" rel="noopener noreferrer" class="font-semibold hover:underline">{{ $highlight['name'] }}</a>
                    @else
                        <span class="font-semibold">{{ $highlight['name'] }}</span>
                    @endif
                    @if(filled($highlight['role']))<span class="text-slate-500"> · {{ $highlight['role'] }}</span>@endif
                </span>
            </li>
        @endforeach
    </ul>
@endif
