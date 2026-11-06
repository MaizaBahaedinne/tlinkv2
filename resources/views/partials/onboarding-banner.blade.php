@php
    $onboardingStep = \App\Support\Onboarding::applies(auth()->user()) ? \App\Support\Onboarding::current(auth()->user()) : null;
@endphp
@if($onboardingStep !== null && ! request()->routeIs('integration.*'))
    <div class="fixed inset-x-0 bottom-0 z-40 border-t border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 shadow-soft" role="status">
        <div class="mx-auto flex max-w-3xl flex-wrap items-center justify-between gap-2">
            <span><strong>Intégration en cours</strong> — étape : {{ \App\Support\Onboarding::STEPS[$onboardingStep] }}.</span>
            <a href="{{ route('integration.show') }}" class="rounded-lg bg-amber-600 px-4 py-1.5 font-semibold text-white hover:bg-amber-700">Voir mon intégration</a>
        </div>
    </div>
@endif
