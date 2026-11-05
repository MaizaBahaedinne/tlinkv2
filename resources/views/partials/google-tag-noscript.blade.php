@php($googleTagId = \App\Support\SiteSettings::googleTagId())
@if($googleTagId && str_starts_with($googleTagId, 'GTM-'))
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $googleTagId }}" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->
@endif
