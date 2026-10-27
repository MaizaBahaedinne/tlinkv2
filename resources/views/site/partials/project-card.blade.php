@php
    $figures = array_slice($project->figures(), 0, 3);
    $cover = $project->coverUrl();
    $logo = $project->logoUrl();
@endphp
<a href="{{ route('site.projects.show', ['slug' => $project->scope]) }}" class="site-card group flex h-full flex-col overflow-hidden transition hover:-translate-y-1 hover:shadow-lg">
    @if($cover)
        <img src="{{ $cover }}" alt="" loading="lazy" class="h-44 w-full object-cover">
    @elseif($logo)
        <div class="flex h-44 items-center justify-center bg-white p-6"><img src="{{ $logo }}" alt="" loading="lazy" class="max-h-full max-w-full object-contain"></div>
    @else
        <div class="flex h-44 items-center justify-center bg-gradient-to-br from-brand-900 to-brand-800 font-display text-5xl font-bold text-white/80" aria-hidden="true">{{ mb_strtoupper(mb_substr($project->text('name'), 0, 1)) }}</div>
    @endif

    <div class="flex flex-1 flex-col p-5">
        <span class="w-fit rounded-full bg-brand-100 px-2.5 py-0.5 text-xs font-bold text-brand-800">{{ __('site.projects.status_'.$project->status) }}</span>
        <h2 class="mt-3 font-display text-xl font-bold text-brand-900 group-hover:text-brand-700">{{ $project->text('name') }}</h2>
        @if($project->text('summary') !== '')
            <p class="mt-2 text-sm leading-6 text-slate-600">{{ $project->text('summary') }}</p>
        @endif

        @if($figures !== [])
            <dl class="mt-4 grid grid-cols-3 gap-2 border-t border-slate-100 pt-4">
                @foreach($figures as $figure)
                    <div>
                        <dd class="font-display text-xl font-bold text-brand-800">{{ $figure['value'] }}</dd>
                        <dt class="text-xs text-slate-500">{{ $figure['label'] }}</dt>
                    </div>
                @endforeach
            </dl>
        @endif

        <span class="mt-auto pt-4 text-sm font-bold text-brand-600">{{ __('site.read_more') }} →</span>
    </div>
</a>
