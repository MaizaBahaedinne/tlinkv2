@extends('layouts.base', ['title' => 'Administration | T.Link'])

@section('content')
<div class="min-h-screen bg-tlink-sand px-4 py-8">
    <div class="mx-auto w-full max-w-7xl">
        <header class="card mb-6 overflow-hidden">
            <div class="bg-gradient-to-r from-tlink-blue to-tlink-cyan px-6 py-5 text-white">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-[0.18em] text-white/80">T.Link Console</p>
                        <h1 class="font-display text-2xl font-semibold">Administration</h1>
                        <p class="mt-1 text-sm text-white/85">
                            Scope: {{ $isGlobal ? 'National' : ('Entites '.implode(', ', $entityIds)) }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('notifications.index') }}" class="rounded-xl border border-white/40 bg-white/10 px-3 py-2 text-sm font-medium hover:bg-white/20">Notifications</a>
                        <a href="/api/v1/admin/users" class="rounded-xl border border-white/40 bg-white/10 px-3 py-2 text-sm font-medium hover:bg-white/20">Utilisateurs API</a>
                        <a href="{{ route('home') }}" class="rounded-xl border border-white/40 bg-white/10 px-3 py-2 text-sm font-medium hover:bg-white/20">Retour accueil</a>
                    </div>
                </div>
            </div>
            <div class="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-5">
                <article class="rounded-xl border border-slate-200 bg-white p-4">
                    <p class="text-xs uppercase text-slate-500">Posts</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $stats['posts'] }}</p>
                </article>
                <article class="rounded-xl border border-slate-200 bg-white p-4">
                    <p class="text-xs uppercase text-slate-500">Adhesions pending</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $stats['memberships_pending'] }}</p>
                </article>
                <article class="rounded-xl border border-slate-200 bg-white p-4">
                    <p class="text-xs uppercase text-slate-500">Approvals pending</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $stats['approvals_pending'] }}</p>
                </article>
                <article class="rounded-xl border border-slate-200 bg-white p-4">
                    <p class="text-xs uppercase text-slate-500">Moderation pending</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $stats['moderation_pending'] }}</p>
                </article>
                <article class="rounded-xl border border-slate-200 bg-white p-4">
                    <p class="text-xs uppercase text-slate-500">Nouveaux users ({{ $windowDays }}j)</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $stats['new_users_window'] }}</p>
                </article>
            </div>
        </header>

        <section class="card mb-6 p-4">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-display text-lg font-semibold text-tlink-blue">Filtres de pilotage</h2>
                <a href="{{ route('admin.dashboard') }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Reinitialiser</a>
            </div>
            <form method="get" action="{{ route('admin.dashboard') }}" class="grid gap-3 md:grid-cols-4">
                <label class="text-sm">
                    <span class="mb-1 block text-slate-600">Fenetre (jours)</span>
                    <select name="window_days" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2">
                        @foreach([7, 14, 30, 60, 90, 180] as $days)
                            <option value="{{ $days }}" @selected($windowDays === $days)>{{ $days }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm">
                    <span class="mb-1 block text-slate-600">Approvals</span>
                    <select name="approval_status" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2">
                        @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'Tous'] as $key => $label)
                            <option value="{{ $key }}" @selected($approvalStatus === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm">
                    <span class="mb-1 block text-slate-600">Moderation</span>
                    <select name="moderation_status" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2">
                        @foreach(['pending' => 'Pending', 'reviewed' => 'Reviewed', 'resolved' => 'Resolved', 'all' => 'Tous'] as $key => $label)
                            <option value="{{ $key }}" @selected($moderationStatus === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm">
                    <span class="mb-1 block text-slate-600">Recherche action audit</span>
                    <input
                        type="text"
                        name="audit_action"
                        value="{{ $auditAction }}"
                        placeholder="ex: approval.decision"
                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2"
                    >
                </label>
                <div class="md:col-span-4 flex gap-2">
                    <button type="submit" class="rounded-lg bg-tlink-blue px-4 py-2 text-sm font-medium text-white hover:bg-tlink-blue-700">Appliquer</button>
                    <a href="/api/v1/admin/exports/users" class="rounded-lg border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Export users</a>
                    <a href="/api/v1/admin/exports/moderation-reports" class="rounded-lg border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Export moderation</a>
                </div>
            </form>
        </section>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="card p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="font-display text-lg font-semibold text-tlink-blue">Approvals recentes</h2>
                    <a href="/api/v1/approvals?status={{ $approvalStatus }}" class="text-sm text-tlink-blue hover:underline">Voir API</a>
                </div>
                <div class="overflow-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-slate-500">
                                <th class="py-1">ID</th>
                                <th class="py-1">Type</th>
                                <th class="py-1">Demandeur</th>
                                <th class="py-1">Statut</th>
                                <th class="py-1">Soumis</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($approvalItems as $approval)
                            <tr class="border-t border-slate-100">
                                <td class="py-2">#{{ $approval->id }}</td>
                                <td class="py-2">{{ $approval->request_type }}</td>
                                <td class="py-2">{{ $approval->requester?->name ?? 'N/A' }}</td>
                                <td class="py-2">
                                    <span class="rounded-full px-2 py-0.5 text-xs {{ $approval->status === 'pending' ? 'bg-amber-100 text-amber-800' : ($approval->status === 'approved' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800') }}">
                                        {{ strtoupper($approval->status) }}
                                    </span>
                                </td>
                                <td class="py-2 text-xs text-slate-500">{{ $approval->submitted_at ?? $approval->created_at }}</td>
                            </tr>
                        @empty
                            <tr><td class="py-3 text-slate-400" colspan="5">Aucune approbation.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="card p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="font-display text-lg font-semibold text-tlink-blue">Moderation recente</h2>
                    <a href="/api/v1/admin/moderation-reports?status={{ $moderationStatus }}" class="text-sm text-tlink-blue hover:underline">Voir API</a>
                </div>
                <div class="overflow-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-slate-500">
                                <th class="py-1">Report</th>
                                <th class="py-1">Type</th>
                                <th class="py-1">Raison</th>
                                <th class="py-1">Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($recentModeration as $report)
                            <tr class="border-t border-slate-100">
                                <td class="py-2">#{{ $report->id }}</td>
                                <td class="py-2">{{ class_basename($report->reportable_type) }}</td>
                                <td class="py-2">{{ \Illuminate\Support\Str::limit($report->reason, 42) }}</td>
                                <td class="py-2">
                                    <span class="rounded-full px-2 py-0.5 text-xs {{ $report->status === 'pending' ? 'bg-amber-100 text-amber-800' : 'bg-slate-200 text-slate-700' }}">{{ strtoupper($report->status) }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td class="py-3 text-slate-400" colspan="4">Aucun signalement.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="card p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="font-display text-lg font-semibold text-tlink-blue">Utilisateurs recents</h2>
                    <a href="/api/v1/admin/users?from={{ now()->subDays($windowDays)->toDateString() }}" class="text-sm text-tlink-blue hover:underline">Voir API</a>
                </div>
                <div class="overflow-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-slate-500">
                                <th class="py-1">ID</th>
                                <th class="py-1">Nom</th>
                                <th class="py-1">Email</th>
                                <th class="py-1">Creation</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($recentUsers as $user)
                            <tr class="border-t border-slate-100">
                                <td class="py-2">#{{ $user->id }}</td>
                                <td class="py-2">{{ $user->name }}</td>
                                <td class="py-2">{{ $user->email }}</td>
                                <td class="py-2 text-xs text-slate-500">{{ $user->created_at }}</td>
                            </tr>
                        @empty
                            <tr><td class="py-3 text-slate-400" colspan="4">Aucun utilisateur dans la fenetre choisie.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="card p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="font-display text-lg font-semibold text-tlink-blue">Audit logs recents</h2>
                    <a href="/api/v1/admin/audit-logs{{ $auditAction !== '' ? ('?action='.urlencode($auditAction)) : '' }}" class="text-sm text-tlink-blue hover:underline">Voir API</a>
                </div>
                <div class="overflow-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-slate-500">
                                <th class="py-1">Action</th>
                                <th class="py-1">Target</th>
                                <th class="py-1">IP</th>
                                <th class="py-1">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($recentAuditLogs as $log)
                            <tr class="border-t border-slate-100">
                                <td class="py-2">{{ $log->action }}</td>
                                <td class="py-2">{{ $log->target_type }} #{{ $log->target_id }}</td>
                                <td class="py-2">{{ $log->ip_address ?? 'N/A' }}</td>
                                <td class="py-2 text-xs text-slate-500">{{ $log->created_at }}</td>
                            </tr>
                        @empty
                            <tr><td class="py-3 text-slate-400" colspan="4">Aucun log.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</div>
@endsection
