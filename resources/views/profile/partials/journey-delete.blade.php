{{-- Bouton de suppression d'un bloc saisi à la main dans le parcours (réservé au propriétaire du profil). --}}
@if($editable && isset($item['_i']))
    <form method="post" action="{{ route('profile.journey.destroy', [$section, $item['_i']]) }}" class="absolute right-2 top-2" onsubmit="return confirm('Supprimer ce bloc de votre parcours ?')">
        @csrf
        @method('DELETE')
        <button type="submit" title="Supprimer ce bloc" aria-label="Supprimer ce bloc" class="flex h-7 w-7 items-center justify-center rounded-full text-slate-400 hover:bg-rose-50 hover:text-rose-600"><i class="fa-solid fa-trash-can text-xs" aria-hidden="true"></i></button>
    </form>
@endif
