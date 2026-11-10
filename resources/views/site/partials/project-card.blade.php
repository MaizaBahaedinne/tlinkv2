@php
    $figures = array_slice($project->figures(), 0, 3);
    $cover = $project->coverUrl();
    $logo = $project->logoUrl();
@endphp
<a href="{{ route('site.projects.show', ['slug' => $project->scope]) }}" class="site-card group flex h-full flex-col overflow-hidden transition hover:-translate-y-1 hover:shadow-xl">
    <div class="relative overflow-hidden">
        @if($cover)
            <img src="{{ $cover }}" alt="" loading="lazy" class="h-56 w-full object-cover transition duration-700 group-hover:scale-105">
        @elseif($logo)
            <div class="flex h-56 items-center justify-center bg-white p-6"><img src="{{ $logo }}" alt="" loading="lazy" class="max-h-full max-w-full object-contain"></div>
        @else
            <div class="flex h-56 items-center justify-center bg-gradient-to-br from-ink to-brand-800 font-display text-6xl font-extrabold text-white/80" aria-hidden="true">{{ mb_strtoupper(mb_substr($project->text('name'), 0, 1)) }}</div>
        @endif
        <span class="absolute start-4 top-4 rounded-full bg-brand-600 px-3 py-1 text-xs font-bold text-white shadow">{{ __('site.projects.status_'.$project->status) }}</span>
    </div>

    <div class="flex flex-1 flex-col p-6">
        <h2 class="font-display text-2xl font-extrabold text-ink group-hover:text-brand-600">{{ $project->text('name') }}</h2>
        @if($project->text('summary') !== '')
            <p class="mt-2 text-sm leading-6 text-slate-600">{{ $project->text('summary') }}</p>
        @endif

        @if($figures !== [])
            <dl class="mt-4 grid grid-cols-3 gap-2 border-t border-slate-100 pt-4">
                @foreach($figures as $figure)
                    <div>
                        <dd class="font-display text-xl font-extrabold text-brand-600">{{ $figure['value'] }}</dd>
                        <dt class="text-xs text-slate-500">{{ $figure['label'] }}</dt>
                    </div>
                @endforeach
            </dl>
        @endif

        <span class="mt-auto pt-4 text-sm font-bold text-brand-600">{{ __('site.read_more') }} →</span>
    </div>
</a>
