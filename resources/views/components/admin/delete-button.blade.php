@props(['action', 'message' => 'Cette action est définitive.', 'title' => 'Supprimer cet élément ?'])
<form method="post" action="{{ $action }}" data-confirm="{{ $message }}" data-confirm-title="{{ $title }}">
    @csrf
    @method('DELETE')
    <button type="submit" class="btn btn-danger btn-icon btn-sm" title="Supprimer" aria-label="Supprimer">
        <x-admin.icon name="trash" :size="15" />
    </button>
</form>
