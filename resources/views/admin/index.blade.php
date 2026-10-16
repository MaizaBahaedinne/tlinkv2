@extends('layouts.base', ['title' => 'Administration | T.Link'])

@section('content')
<div class="min-h-screen bg-tlink-sand px-4 py-8">
    <div class="mx-auto w-full max-w-7xl">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="font-display text-2xl font-semibold text-tlink-blue">Administration T.Link</h1>
                <p class="text-sm text-slate-600">
                    Scope: {{ $isGlobal ? 'National' : ('Entites '.implode(', ', $entityIds)) }}
                </p>
            </div>
            <a href="{{ route('home') }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50">Retour</a>
        </div>

        <div class="grid gap-4 md:grid-cols-4">
            <div class="card p-4"><p class="text-xs text-slate-500">Posts</p><p class="text-2xl font-semibold">{{ $stats['posts'] }}</p></div>
            <div class="card p-4"><p class="text-xs text-slate-500">Adhesions pending</p><p class="text-2xl font-semibold">{{ $stats['memberships_pending'] }}</p></div>
            <div class="card p-4"><p class="text-xs text-slate-500">Approvals pending</p><p class="text-2xl font-semibold">{{ $stats['approvals_pending'] }}</p></div>
            <div class="card p-4"><p class="text-xs text-slate-500">Moderation pending</p><p class="text-2xl font-semibold">{{ $stats['moderation_pending'] }}</p></div>
        </div>

        <div class="mt-6 grid gap-4 lg:grid-cols-2">
            <section class="card p-4">
                <h2 class="mb-3 font-display text-lg font-semibold text-tlink-blue">Audit logs recents</h2>
                <div class="overflow-auto">
                    <table class="w-full text-sm">
                        <thead><tr class="text-left text-slate-500"><th class="py-1">Action</th><th class="py-1">Target</th><th class="py-1">Date</th></tr></thead>
                        <tbody>
                        @forelse($recentAuditLogs as $log)
                            <tr class="border-t border-slate-100"><td class="py-2">{{ $log->action }}</td><td class="py-2">{{ $log->target_type }} #{{ $log->target_id }}</td><td class="py-2">{{ $log->created_at }}</td></tr>
                        @empty
                            <tr><td class="py-2 text-slate-400" colspan="3">Aucun log</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="card p-4">
                <h2 class="mb-3 font-display text-lg font-semibold text-tlink-blue">Moderation recente</h2>
                <div class="overflow-auto">
                    <table class="w-full text-sm">
                        <thead><tr class="text-left text-slate-500"><th class="py-1">Report ID</th><th class="py-1">Status</th><th class="py-1">Date</th></tr></thead>
                        <tbody>
                        @forelse($recentModeration as $report)
                            <tr class="border-t border-slate-100"><td class="py-2">#{{ $report->id }}</td><td class="py-2">{{ $report->status }}</td><td class="py-2">{{ $report->created_at }}</td></tr>
                        @empty
                            <tr><td class="py-2 text-slate-400" colspan="3">Aucun signalement</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</div>
@endsection
