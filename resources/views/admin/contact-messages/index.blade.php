@extends('admin.layouts.app')

@section('title', 'Messages du site')
@section('section', 'Messages du site')

@section('content')
    <x-admin.page-header title="Messages du site" subtitle="Messages envoyés via le formulaire de contact du site public." />

    <article class="card card-flush">
        <div class="tabs">
            @foreach($statuses as $code => $label)
                <a href="{{ route('admin.messages.index', ['status' => $code]) }}" class="tab {{ $status === $code ? 'is-active' : '' }}">{{ $label }} <span class="n">{{ $counts[$code] ?? 0 }}</span></a>
            @endforeach
            <a href="{{ route('admin.messages.index', ['status' => 'all']) }}" class="tab {{ $status === 'all' ? 'is-active' : '' }}">Tous <span class="n">{{ array_sum($counts) }}</span></a>
        </div>

        @if($messages->count())
            <div class="list">
                @foreach($messages as $message)
                    <div class="list-item" style="align-items:flex-start;flex-wrap:wrap">
                        <div class="grow" style="min-width:260px">
                            <div style="display:flex;gap:10px;align-items:center;margin-bottom:6px">
                                <span class="badge badge-{{ $message->status === 'new' ? 'warning' : 'neutral' }}">{{ $statuses[$message->status] ?? $message->status }}</span>
                                <span class="text-sm text-muted">{{ $message->created_at?->format('d/m/Y H:i') }} · {{ strtoupper($message->locale) }}</span>
                            </div>
                            <p style="margin:0 0 4px"><strong>{{ $message->subject }}</strong></p>
                            <p class="text-sm text-muted" style="margin:0 0 8px">{{ $message->name }} · <a href="mailto:{{ $message->email }}">{{ $message->email }}</a></p>
                            <div style="white-space:pre-line">{{ $message->message }}</div>
                        </div>

                        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                            @if($message->status === 'new')
                                <form method="post" action="{{ route('admin.messages.update', $message) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="read">
                                    <button type="submit" class="btn btn-success btn-sm"><x-admin.icon name="check" :size="15" /> Marquer lu</button>
                                </form>
                            @endif
                            @if($message->status !== 'archived')
                                <form method="post" action="{{ route('admin.messages.update', $message) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="archived">
                                    <button type="submit" class="btn btn-secondary btn-sm">Archiver</button>
                                </form>
                            @endif
                            <x-admin.delete-button :action="route('admin.messages.destroy', $message)" message="Le message sera supprimé définitivement." title="Supprimer le message ?" />
                        </div>
                    </div>
                @endforeach
            </div>
            {{ $messages->links('admin.partials.pagination') }}
        @else
            <x-admin.empty icon="file-text" title="Aucun message" text="Aucun message dans cette catégorie." />
        @endif
    </article>
@endsection
