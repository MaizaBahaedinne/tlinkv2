<x-mail::layout>
    {{-- Header --}}
    <x-slot:header>
        <x-mail::header :url="config('app.url')">
            T.Link
        </x-mail::header>
    </x-slot:header>

    {{-- Body --}}
    {{ $slot }}

    {{-- Subcopy --}}
    @isset($subcopy)
        <x-slot:subcopy>
            <x-mail::subcopy>
                {{ $subcopy }}
            </x-mail::subcopy>
        </x-slot:subcopy>
    @endisset

    {{-- Footer --}}
    <x-slot:footer>
        <x-mail::footer>
            T.Link est la plateforme de la communauté de la Fondation Tunivisions. Ce message vous a été envoyé automatiquement.
            © {{ date('Y') }} Fondation Tunivisions. Tous droits réservés.
        </x-mail::footer>
    </x-slot:footer>
</x-mail::layout>
