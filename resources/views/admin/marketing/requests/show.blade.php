@extends('admin.layouts.app')

@section('title', 'Demande d’accès')
@section('section', 'Marketing et partenariats')

@section('content')
    <x-admin.page-header :title="'Demande d’accès — '.$accessRequest->entityLabel()" subtitle="Suivi de la demande déposée auprès du digital manager de la Fondation.">
        <x-slot:actions>
            <a href="{{ route('admin.marketing.requests.index') }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Retour aux demandes</a>
        </x-slot:actions>
    </x-admin.page-header>

    <article class="card">
        <div class="card-body">
            @include('admin.marketing.partials.request-details')
        </div>
        @if($canCancel)
            <div class="form-actions">
                <x-admin.delete-button :action="route('admin.marketing.requests.destroy', $accessRequest)" message="La demande sera annulée." title="Annuler cette demande ?" />
            </div>
        @endif
    </article>
@endsection
