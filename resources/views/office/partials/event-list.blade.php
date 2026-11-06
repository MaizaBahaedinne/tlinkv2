<ul class="mt-2 divide-y divide-slate-100">
    @forelse($rows as $row)
        @php
            $event = $row['event'];
        @endphp
        <li class="flex items-center gap-3 py-3">
            <div class="flex h-11 w-11 shrink-0 flex-col items-center justify-center rounded-xl bg-tlink-blue text-white">
                <span class="text-sm font-bold leading-none">{{ $event->starts_at->format('d') }}</span>
                <span class="mt-0.5 text-[10px] uppercase">{{ $event->starts_at->translatedFormat('M') }}</span>
            </div>
            <div class="min-w-0 flex-1">
                @if($row['url'])
                    <a href="{{ $row['url'] }}" class="text-sm font-semibold text-slate-800 hover:underline">{{ $event->title }}</a>
                @else
                    <p class="text-sm font-semibold text-slate-800">{{ $event->title }}</p>
                @endif
                <p class="text-xs text-slate-500">{{ $event->starts_at->translatedFormat('d F Y, H:i') }}@if($place = collect([$event->location, $event->governorate?->name])->filter()->implode(' · ')) · {{ $place }}@endif</p>
                @if($row['registration'])
                    <span class="mt-1 inline-block rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">{{ $row['registration'] }}</span>
                @endif
            </div>
        </li>
    @empty
        <li class="py-2 text-sm text-slate-500">{{ $empty }}</li>
    @endforelse
</ul>
