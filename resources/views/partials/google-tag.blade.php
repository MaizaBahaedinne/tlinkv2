@php($googleTagId = \App\Support\SiteSettings::googleTagId())
@if($googleTagId)
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $googleTagId }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());

        gtag('config', @json($googleTagId));
    </script>
@endif
