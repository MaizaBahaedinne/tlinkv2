@php($current = $scope ?? null)
<nav class="mt-8 flex flex-wrap gap-2" aria-label="{{ __('site.home.scopes_title') }}">
    <a href="{{ route($routeName) }}"
       class="rounded-full border px-4 py-1.5 text-sm font-semibold {{ $current === null ? 'border-brand-800 bg-brand-800 text-white' : 'border-slate-300 text-slate-700 hover:bg-slate-100' }}"
       @if($current === null) aria-current="true" @endif>{{ __('site.all_scopes') }}</a>
    @foreach(\App\Support\OrgScopes::slugs() as $slug)
        <a href="{{ route($routeName, ['scope' => $slug]) }}"
           class="rounded-full border px-4 py-1.5 text-sm font-semibold {{ $current === $slug ? 'border-brand-800 bg-brand-800 text-white' : 'border-slate-300 text-slate-700 hover:bg-slate-100' }}"
           @if($current === $slug) aria-current="true" @endif>{{ __('site.scopes.'.$slug.'.name') }}</a>
    @endforeach
</nav>
