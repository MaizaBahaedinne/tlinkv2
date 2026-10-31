@extends('admin.layouts.app')

@section('title', 'Avis boutique')
@section('section', 'Tunivisions Stores')

@section('content')
    <x-admin.page-header title="Avis clients" subtitle="Avis laissés par les membres après la livraison d’un article. Un avis masqué n’est plus visible ni compté dans la note." />

    @include('admin.store._tabs')

    <article class="card card-flush">
        <div class="tabs">
            <a href="{{ route('admin.store.reviews.index') }}" class="tab {{ $status === '' ? 'is-active' : '' }}">Tous</a>
            <a href="{{ route('admin.store.reviews.index', ['status' => 'published']) }}" class="tab {{ $status === 'published' ? 'is-active' : '' }}">Publiés</a>
            <a href="{{ route('admin.store.reviews.index', ['status' => 'hidden']) }}" class="tab {{ $status === 'hidden' ? 'is-active' : '' }}">Masqués</a>
        </div>

        @if($reviews->count())
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Article</th>
                            <th>Membre</th>
                            <th>Note</th>
                            <th>Avis</th>
                            <th>Statut</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($reviews as $review)
                            <tr>
                                <td>
                                    <span class="cell-main">{{ $review->product?->name ?? 'Article supprimé' }}</span>
                                    <div class="cell-sub">{{ $review->created_at->translatedFormat('d M Y') }}</div>
                                </td>
                                <td>{{ $review->user?->name ?? 'Compte supprimé' }}</td>
                                <td><span class="stars" aria-label="{{ $review->rating }} sur 5">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</span></td>
                                <td>
                                    @if($review->title)<strong>{{ $review->title }}</strong>@endif
                                    <div class="review-text">{{ $review->body ?: '—' }}</div>
                                </td>
                                <td><span class="badge badge-{{ $review->status === 'published' ? 'success' : 'neutral' }}">{{ $review->status === 'published' ? 'Publié' : 'Masqué' }}</span></td>
                                <td class="cell-actions">
                                    <form method="post" action="{{ route('admin.store.reviews.update', $review) }}" style="display:inline">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="status" value="{{ $review->status === 'published' ? 'hidden' : 'published' }}">
                                        <button type="submit" class="btn btn-secondary btn-sm">{{ $review->status === 'published' ? 'Masquer' : 'Republier' }}</button>
                                    </form>
                                    <form method="post" action="{{ route('admin.store.reviews.destroy', $review) }}" style="display:inline" onsubmit="return confirm('Supprimer cet avis ?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-ghost btn-sm btn-icon" aria-label="Supprimer l’avis"><x-admin.icon name="trash" :size="15" /></button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $reviews->links('admin.partials.pagination') }}
        @else
            <x-admin.empty icon="inbox" title="Aucun avis" text="Les avis des membres apparaissent ici une fois leurs commandes livrées." />
        @endif
    </article>
@endsection
