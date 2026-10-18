@extends('admin.layouts.app')

@section('title', 'Modération')
@section('section', 'Modération')

@section('content')
    <x-admin.page-header title="Modération" subtitle="Signalements des membres sur les publications. Validez-les, rejetez-les ou supprimez le contenu." />

    <article class="card card-flush">
        <div class="tabs">
            @foreach($statuses as $code => $label)
                <a href="{{ route('admin.moderation.index', ['status' => $code]) }}" class="tab {{ $status === $code ? 'is-active' : '' }}">{{ $label }} <span class="n">{{ $counts[$code] ?? 0 }}</span></a>
            @endforeach
            <a href="{{ route('admin.moderation.index', ['status' => 'all']) }}" class="tab {{ $status === 'all' ? 'is-active' : '' }}">Tous <span class="n">{{ array_sum($counts) }}</span></a>
        </div>

        @if($reports->count())
            <div class="list">
                @foreach($reports as $report)
                    @php($post = $posts->get($report->reportable_id))
                    <div class="list-item" style="align-items:flex-start;flex-wrap:wrap">
                        <div class="grow" style="min-width:260px">
                            <div style="display:flex;gap:10px;align-items:center;margin-bottom:6px">
                                <x-admin.badge :status="$report->status" />
                                <span class="text-sm text-muted">Signalé par {{ $people[$report->reported_by] ?? 'un membre' }} · {{ $report->created_at?->format('d/m/Y H:i') }}</span>
                            </div>
                            <p style="margin:0 0 8px"><strong>Motif :</strong> {{ $report->reason }}</p>
                            <div class="card-body" style="padding:12px 14px;border:1px solid var(--line);border-radius:10px;background:#fbfcfe">
                                @if($post)
                                    <div class="text-sm text-muted" style="margin-bottom:4px">Publication de {{ $post->author?->name ?? 'inconnu' }}</div>
                                    <div>{{ \Illuminate\Support\Str::limit(strip_tags((string) $post->content), 220) }}</div>
                                @else
                                    <span class="text-muted">Publication supprimée ou indisponible.</span>
                                @endif
                            </div>
                            @if($report->reviewed_at)
                                <p class="text-sm text-muted" style="margin:8px 0 0">Traité par {{ $people[$report->reviewed_by] ?? '—' }} le {{ $report->reviewed_at->format('d/m/Y H:i') }}</p>
                            @endif
                        </div>

                        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                            @if($report->status === 'pending')
                                <form method="post" action="{{ route('admin.moderation.update', $report) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="reviewed">
                                    <button type="submit" class="btn btn-success btn-sm"><x-admin.icon name="check" :size="15" /> Valider</button>
                                </form>
                                <form method="post" action="{{ route('admin.moderation.update', $report) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="rejected">
                                    <button type="submit" class="btn btn-secondary btn-sm"><x-admin.icon name="x" :size="15" /> Rejeter</button>
                                </form>
                            @endif
                            @if($post)
                                <x-admin.delete-button :action="route('admin.posts.destroy', $post)" message="La publication signalée sera supprimée définitivement." title="Supprimer la publication ?" />
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            {{ $reports->links('admin.partials.pagination') }}
        @else
            <x-admin.empty icon="flag" title="Aucun signalement" text="Aucun signalement dans cette catégorie." />
        @endif
    </article>
@endsection
