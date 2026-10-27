{{-- « Notre équipe » et « Nos espaces » fusionnés : un onglet par espace, avec sa description et ses responsables. --}}
<section id="equipe" class="mx-auto mt-16 max-w-6xl scroll-mt-24 px-4" aria-labelledby="equipe-title">
    <div data-space-tabs data-reveal>
        <h2 id="equipe-title" class="font-display text-2xl font-bold text-brand-900">{{ __('site.about.spaces_title') }}</h2>
        <p class="mt-1 text-sm text-slate-600">{{ __('site.about.spaces_intro') }}</p>

        <div class="space-tablist" role="tablist" aria-label="{{ __('site.about.spaces_label') }}">
            @foreach($spaces as $i => $space)
                <button type="button" role="tab" class="space-tab" id="tab-{{ $space['slug'] }}"
                        aria-controls="{{ $space['slug'] }}" aria-selected="{{ $i === 0 ? 'true' : 'false' }}" tabindex="{{ $i === 0 ? '0' : '-1' }}"
                        data-space="{{ $space['slug'] }}">
                    <span>{{ $space['name'] }}</span>
                    <span class="space-count" aria-hidden="true">{{ $space['team']->sum(fn ($node) => $node->assignments->filter(fn ($assignment) => $assignment->person !== null)->count()) }}</span>
                </button>
            @endforeach
        </div>

        @foreach($spaces as $i => $space)
            <div id="{{ $space['slug'] }}" class="space-panel scroll-mt-24 {{ $i === 0 ? 'is-active' : '' }}" role="tabpanel" aria-labelledby="tab-{{ $space['slug'] }}">
                <div class="flex flex-wrap items-center gap-4">
                    @if($space['logo'])
                        <img src="{{ $space['logo'] }}" alt="" loading="lazy" class="h-14 w-auto max-w-40 object-contain">
                    @endif
                    <div class="min-w-0 flex-1">
                        <h3 class="font-display text-xl font-bold text-brand-800">{{ $space['name'] }}</h3>
                        @if($space['description'] !== '')
                            <p class="mt-1 text-sm text-slate-600">{{ $space['description'] }}</p>
                        @endif
                        @if($space['children'] !== [])
                            <p class="mt-2 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                <span class="font-semibold">{{ __('site.about.includes') }} :</span>
                                @foreach($space['children'] as $child)
                                    <span class="rounded-full bg-brand-100 px-2.5 py-0.5 font-semibold text-brand-800">{{ $child }}</span>
                                @endforeach
                            </p>
                        @endif
                    </div>
                </div>

                @php($card = 0)
                <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($space['team'] as $node)
                        @foreach($node->assignments as $assignment)
                            @php($person = $assignment->person)
                            @continue(! $person)
                            @php($photo = $person->photoUrl())
                            <div class="site-card space-card flex items-center gap-4 p-4" style="--i: {{ $card++ }}">
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

                @if($card === 0)
                    <p class="mt-5 rounded-xl bg-white/70 p-4 text-sm text-slate-500">{{ __('site.about.no_team') }}</p>
                @endif
            </div>
        @endforeach
    </div>
</section>

@push('scripts')
    <script>
        // Onglets des espaces et apparition des blocs au défilement.
        (function () {
            var root = document.querySelector('[data-space-tabs]');

            if (root) {
                var tabs = Array.prototype.slice.call(root.querySelectorAll('[role="tab"]'));
                var panels = {};

                root.querySelectorAll('[role="tabpanel"]').forEach(function (panel) { panels[panel.id] = panel; });

                var select = function (tab, focus, updateHash) {
                    tabs.forEach(function (other) {
                        var active = other === tab;
                        other.setAttribute('aria-selected', active ? 'true' : 'false');
                        other.tabIndex = active ? 0 : -1;
                        panels[other.dataset.space].classList.toggle('is-active', active);
                    });

                    if (focus) { tab.focus(); }
                    if (updateHash && window.history.replaceState) { window.history.replaceState(null, '', '#' + tab.dataset.space); }
                };

                tabs.forEach(function (tab, index) {
                    tab.addEventListener('click', function () { select(tab, false, true); });
                    tab.addEventListener('keydown', function (event) {
                        var rtl = document.documentElement.dir === 'rtl';
                        var next = null;

                        if (event.key === 'ArrowRight') { next = tabs[(index + (rtl ? -1 : 1) + tabs.length) % tabs.length]; }
                        if (event.key === 'ArrowLeft') { next = tabs[(index + (rtl ? 1 : -1) + tabs.length) % tabs.length]; }
                        if (event.key === 'Home') { next = tabs[0]; }
                        if (event.key === 'End') { next = tabs[tabs.length - 1]; }

                        if (next) { event.preventDefault(); select(next, true, true); }
                    });
                });

                // Les anciens liens (#equipe, #university…) ouvrent le bon onglet.
                var fromHash = function () {
                    var target = tabs.filter(function (tab) { return '#' + tab.dataset.space === window.location.hash; })[0];

                    if (target) { select(target, false, false); }
                };

                fromHash();
                window.addEventListener('hashchange', fromHash);
            }

            var items = document.querySelectorAll('[data-reveal]');

            if (!('IntersectionObserver' in window)) {
                items.forEach(function (item) { item.classList.add('is-visible'); });
            } else {
                var observer = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('is-visible');
                            observer.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.12 });

                items.forEach(function (item) { observer.observe(item); });
            }

            // Après une erreur de saisie dans la newsletter, on ramène le visiteur sur le formulaire.
            if (document.querySelector('[data-newsletter-error]')) {
                var form = document.getElementById('newsletter');
                if (form) { form.scrollIntoView(); }
            }
        })();
    </script>
@endpush
