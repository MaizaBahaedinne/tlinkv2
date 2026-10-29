{{-- Dock T.Link Messenger : liste flottante en bas à droite + fenêtres de discussion (ordinateur uniquement). --}}
@php
    $dockUser = auth()->user();
    $dockProfile = $dockUser?->profile;
@endphp
<div id="mg-dock"
     class="pointer-events-none fixed bottom-0 right-4 z-40 hidden items-end gap-3 md:flex [&>*]:pointer-events-auto"
     data-me="{{ $dockUser->id }}"
     data-me-name="{{ $dockUser->name }}"
     data-me-avatar="{{ $dockProfile?->photoUrl() }}"
     data-csrf="{{ csrf_token() }}"
     data-url-index="{{ route('messenger.index') }}"
     data-url-list="{{ route('messenger.conversations') }}"
     data-url-members="{{ route('messenger.members') }}"
     data-url-direct="{{ route('messenger.direct', 0) }}"
     data-url-show="{{ route('messenger.show', 0) }}"
     data-url-messages="{{ route('messenger.messages', 0) }}"
     data-url-send="{{ route('messenger.send', 0) }}"
     data-url-read="{{ route('messenger.read', 0) }}"
     data-url-typing="{{ route('messenger.typing', 0) }}"
     data-url-delete="{{ route('messenger.messages.destroy', 0) }}"></div>
@vite(['resources/js/messenger-dock.js'])
