@props(['id', 'title', 'action', 'method' => 'POST', 'submit' => 'Enregistrer', 'icon' => null, 'files' => false])
{{-- Fenêtre modale de formulaire. S'ouvre au chargement si ce formulaire vient d'échouer (champ caché _form). --}}
@php($open = old('_form') === $id && $errors->any())
<dialog id="{{ $id }}" class="modal" aria-labelledby="{{ $id }}-title" style="width:min(560px, calc(100vw - 32px))" @if($open) data-modal-autoopen @endif>
    <div class="modal-head">
        <h2 id="{{ $id }}-title">{{ $title }}</h2>
        <button type="button" class="btn btn-ghost btn-sm" data-modal-close aria-label="Fermer">✕</button>
    </div>
    <form method="post" action="{{ $action }}" class="modal-form" @if($files) enctype="multipart/form-data" @endif>
        @csrf
        @if(strtoupper($method) !== 'POST')
            @method($method)
        @endif
        <input type="hidden" name="_form" value="{{ $id }}">
        <div class="modal-body">
            @if($open)
                <div class="alert alert-error" role="alert" style="grid-column:1/-1">
                    <span>@foreach($errors->all() as $message){{ $message }}{{ ! $loop->last ? ' ' : '' }}@endforeach</span>
                </div>
            @endif
            {{ $slot }}
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
            <button type="submit" class="btn btn-primary">@if($icon)<x-admin.icon :name="$icon" :size="16" /> @endif{{ $submit }}</button>
        </div>
    </form>
</dialog>
