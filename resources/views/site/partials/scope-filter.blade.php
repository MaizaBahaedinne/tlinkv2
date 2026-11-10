@php($current = $scope ?? null)
<nav class="flex flex-wrap gap-2" aria-label="{{ __('site.home.scopes_title') }}">
    <a href="{{ route($routeName) }}"
       class="rounded-full border px-5 py-2 text-sm font-bold transition {{ $current === null ? 'border-brand-600 bg-brand-600 text-white' : 'border-slate-300 text-slate-700 hover:border-ink hover:text-ink' }}"
       @if($current === null) aria-current="true" @endif>{{ __('site.all_scopes') }}</a>
    @foreach(\App\Support\OrgScopes::slugs() as $slug)
        <a href="{{ route($routeName, ['scope' => $slug]) }}"
           class="rounded-full border px-5 py-2 text-sm font-bold transition {{ $current === $slug ? 'border-brand-600 bg-brand-600 text-white' : 'border-slate-300 text-slate-700 hover:border-ink hover:text-ink' }}"
           @if($current === $slug) aria-current="true" @endif>{{ __('site.scopes.'.$slug.'.name') }}</a>
    @endforeach
</nav>
