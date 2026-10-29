@extends('layouts.base', ['title' => 'Notifications | T.Link'])

@section('content')
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    <div class="app-container py-6">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="font-display text-2xl font-semibold text-tlink-blue">Notifications</h1>
                <p class="text-sm text-slate-600">Historique des alertes de ton compte.</p>
            </div>
            <div class="flex gap-2">
                <form method="post" action="{{ route('notifications.mark-all-read') }}">
                    @csrf
                    <button class="rounded-xl border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50" type="submit">Tout marquer lu</button>
                </form>
                <a href="{{ route('home') }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50">Retour</a>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="divide-y divide-slate-100">
                @forelse($notifications as $notification)
                    <div class="flex items-start justify-between gap-3 p-4 {{ $notification->read_at ? 'bg-white' : 'bg-rose-50/60' }}">
                        <div>
                            @php
                                $target = $notification->data['url'] ?? null;
                            @endphp
                            @if(is_string($target) && str_starts_with($target, '/') && ! str_starts_with($target, '//'))
                                <a href="{{ $target }}" class="font-semibold text-slate-800 hover:text-tlink-blue hover:underline">{{ $notification->title }}</a>
                            @else
                                <p class="font-semibold text-slate-800">{{ $notification->title }}</p>
                            @endif
                            <p class="mt-1 text-sm text-slate-600">{{ $notification->body }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $notification->created_at }}</p>
                        </div>
                        @if(!$notification->read_at)
                            <form method="post" action="{{ route('notifications.mark-read', $notification) }}">
                                @csrf
                                <button class="rounded-lg bg-tlink-blue px-2 py-1 text-xs font-medium text-white hover:bg-tlink-blue-700" type="submit">Marquer lu</button>
                            </form>
                        @endif
                    </div>
                @empty
                    <div class="p-6 text-sm text-slate-500">Aucune notification.</div>
                @endforelse
            </div>
        </div>

        <div class="mt-4">{{ $notifications->links() }}</div>
    </div>
</div>
@endsection
