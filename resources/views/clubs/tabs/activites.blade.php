@php
    $sections = [['Activités à venir', $upcoming, 'Aucune activité à venir pour le moment.'], ['Activités passées', $past, 'Aucune activité passée.']];
@endphp
<div class="space-y-5">
    @foreach($sections as [$heading, $items, $empty])
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
            <h2 class="font-display text-lg font-semibold text-slate-900">{{ $heading }}</h2>
            <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                @forelse($items as $item)
                    @php $tag = $item['url'] ? 'a' : 'div'; @endphp
                    <li>
                        <{{ $tag }} @if($item['url']) href="{{ $item['url'] }}" @endif class="flex h-full gap-3 rounded-2xl border border-slate-200 p-3 {{ $item['url'] ? 'transition hover:border-tlink-blue/40 hover:bg-slate-50' : '' }}">
                            <span class="flex h-16 w-16 shrink-0 flex-col items-center justify-center rounded-xl bg-tlink-blue/10 text-tlink-blue">
                                <span class="text-xl font-bold leading-none">{{ $item['starts_at']->format('d') }}</span>
                                <span class="text-[11px] font-semibold uppercase">{{ $item['starts_at']->translatedFormat('M Y') }}</span>
                            </span>
                            <span class="min-w-0">
                                <span class="block text-xs font-semibold text-rose-700">{{ $item['type'] }}</span>
                                <span class="block truncate text-sm font-semibold text-slate-900">{{ $item['title'] }}</span>
                                <span class="block text-xs text-slate-500">{{ $item['starts_at']->format('H:i') !== '00:00' ? $item['starts_at']->format('H:i') : '' }}@if($item['location']) {{ $item['starts_at']->format('H:i') !== '00:00' ? '·' : '' }} {{ $item['location'] }}@endif</span>
                            </span>
                        </{{ $tag }}>
                    </li>
                @empty
                    <li class="rounded-2xl bg-slate-50 px-4 py-6 text-center text-sm text-slate-500 sm:col-span-2">{{ $empty }}</li>
                @endforelse
            </ul>
        </section>
    @endforeach
</div>
