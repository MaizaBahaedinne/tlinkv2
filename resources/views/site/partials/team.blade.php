@foreach($groups as $scope => $nodes)
    <div id="{{ $scope }}" class="mt-8 scroll-mt-24">
        <h3 class="font-display text-xl font-bold text-brand-800">{{ __('site.scopes.'.$scope.'.name') }}</h3>
        <p class="mt-1 text-sm text-slate-600">{{ __('site.scopes.'.$scope.'.text') }}</p>

        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($nodes as $node)
                @foreach($node->assignments as $assignment)
                    @php($person = $assignment->person)
                    @continue(! $person)
                    @php($photo = $person->photoUrl())
                    <div class="site-card flex items-center gap-4 p-4">
                        @if($photo)
                            <img src="{{ $photo }}" alt="" loading="lazy" class="h-16 w-16 shrink-0 rounded-full object-cover">
                        @else
                            <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-brand-100 font-display text-xl font-bold text-brand-800">{{ mb_strtoupper(mb_substr($person->displayName(), 0, 1)) }}</span>
                        @endif
                        <div class="min-w-0">
                            <p class="font-display font-bold text-brand-900">{{ $person->displayName() }}</p>
                            <p class="text-sm text-slate-600">{{ $node->title }}</p>
                            @if($node->subtitle)
                                <p class="text-xs text-slate-500">{{ $node->subtitle }}</p>
                            @endif
                            @if($person->job_title && $person->job_title !== $node->title)
                                <p class="text-xs text-slate-500">{{ $person->job_title }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            @endforeach
        </div>
    </div>
@endforeach
