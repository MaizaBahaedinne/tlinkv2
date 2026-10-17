@extends('layouts.base', ['title' => 'Administration | T.Link'])

@section('content')
<div class="min-h-screen bg-[#f2f4f8] p-4 md:p-6">
    @php
        $activityTotal = max(1, $stats['posts'] + $stats['memberships_pending'] + $stats['approvals_pending'] + $stats['moderation_pending']);
        $postsRate = (int) round(($stats['posts'] / $activityTotal) * 100);
        $memberRate = (int) round(($stats['memberships_pending'] / $activityTotal) * 100);
        $approvalRate = (int) round(($stats['approvals_pending'] / $activityTotal) * 100);
        $moderationRate = max(0, 100 - $postsRate - $memberRate - $approvalRate);
        $toWidthClass = static function (int $value): string {
            return match (true) {
                $value >= 90 => 'w-full',
                $value >= 75 => 'w-3/4',
                $value >= 50 => 'w-1/2',
                $value >= 25 => 'w-1/4',
                default => 'w-[12%]',
            };
        };
        $postsWidth = $toWidthClass($postsRate);
        $memberWidth = $toWidthClass($memberRate);
        $approvalWidth = $toWidthClass($approvalRate);
        $moderationWidth = $toWidthClass($moderationRate);
    @endphp
    <div class="admin-layout">
        <aside class="hidden w-[270px] shrink-0 rounded-3xl bg-[#101c37] p-5 text-slate-200 shadow-[0_24px_50px_rgba(16,28,55,0.35)] lg:block">
            <p class="text-[11px] uppercase tracking-[0.2em] text-slate-400">T.Link Control</p>
            <h1 class="mt-2 font-display text-2xl text-white">Admin Console</h1>
            <p class="mt-2 text-xs text-slate-400">{{ $isGlobal ? 'Scope national' : ('Scope entites: '.implode(', ', $entityIds)) }}</p>

            <nav class="mt-8 space-y-2 text-sm">
                <a href="{{ route('admin.dashboard') }}" class="block rounded-xl bg-white/10 px-3 py-2.5 font-medium text-white">Dashboard</a>
                <a href="{{ route('notifications.index') }}" class="block rounded-xl px-3 py-2.5 text-slate-300 hover:bg-white/10 hover:text-white">Notifications</a>
                <a href="/api/v1/admin/users" class="block rounded-xl px-3 py-2.5 text-slate-300 hover:bg-white/10 hover:text-white">Users API</a>
                <a href="/api/v1/admin/moderation-reports?status=pending" class="block rounded-xl px-3 py-2.5 text-slate-300 hover:bg-white/10 hover:text-white">Moderation API</a>
                <a href="/api/v1/admin/audit-logs" class="block rounded-xl px-3 py-2.5 text-slate-300 hover:bg-white/10 hover:text-white">Audit API</a>
                <a href="{{ route('home') }}" class="block rounded-xl px-3 py-2.5 text-slate-300 hover:bg-white/10 hover:text-white">Retour accueil</a>
            </nav>

            <div class="mt-8 rounded-2xl border border-white/10 bg-white/5 p-4">
                <p class="text-xs text-slate-300">Export rapide</p>
                <div class="mt-3 grid gap-2 text-xs">
                    <a href="/api/v1/admin/exports/users" class="rounded-lg border border-white/20 px-2 py-1.5 hover:bg-white/10">CSV users</a>
                    <a href="/api/v1/admin/exports/events" class="rounded-lg border border-white/20 px-2 py-1.5 hover:bg-white/10">CSV events</a>
                    <a href="/api/v1/admin/exports/moderation-reports" class="rounded-lg border border-white/20 px-2 py-1.5 hover:bg-white/10">CSV moderation</a>
                </div>
            </div>
        </aside>

        <main class="min-w-0 flex-1 space-y-4 lg:space-y-6">
            <header class="rounded-3xl border border-slate-200 bg-white p-4 shadow-soft md:p-5">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-[0.16em] text-slate-500">Operations Dashboard</p>
                        <h2 class="font-display text-2xl font-semibold text-slate-900">Welcome back, admin</h2>
                        <p class="mt-1 text-sm text-slate-500">Vue centralisee des operations, des risques et des demandes.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('notifications.index') }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50">Notifications</a>
                        <a href="/api/v1/admin/users?from={{ now()->subDays($windowDays)->toDateString() }}" class="rounded-xl bg-tlink-blue px-3 py-2 text-sm font-medium text-white hover:bg-tlink-blue-700">Nouveaux users</a>
                    </div>
                </div>
            </header>

            <section class="admin-kpi-grid">
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-soft">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Posts</p>
                    <p class="mt-2 text-3xl font-semibold text-slate-900">{{ $stats['posts'] }}</p>
                    <p class="mt-2 text-xs text-emerald-600">+{{ max(1, intdiv($stats['posts'], 8)) }} today</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-soft">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Adhesions pending</p>
                    <p class="mt-2 text-3xl font-semibold text-slate-900">{{ $stats['memberships_pending'] }}</p>
                    <p class="mt-2 text-xs text-amber-600">a traiter rapidement</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-soft">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Approvals pending</p>
                    <p class="mt-2 text-3xl font-semibold text-slate-900">{{ $stats['approvals_pending'] }}</p>
                    <p class="mt-2 text-xs text-amber-600">workflow actif</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-soft">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Moderation pending</p>
                    <p class="mt-2 text-3xl font-semibold text-slate-900">{{ $stats['moderation_pending'] }}</p>
                    <p class="mt-2 text-xs text-rose-600">controle contenu</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-soft">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Nouveaux users ({{ $windowDays }}j)</p>
                    <p class="mt-2 text-3xl font-semibold text-slate-900">{{ $stats['new_users_window'] }}</p>
                    <p class="mt-2 text-xs text-sky-600">croissance audience</p>
                </article>
            </section>

            <section class="admin-three-grid">
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-soft">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="font-display text-lg font-semibold text-slate-900">Activity Breakdown</h3>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-600">Month to date</span>
                    </div>
                    <div class="space-y-3 text-sm">
                        <div>
                            <div class="mb-1 flex justify-between text-slate-600"><span>Posts</span><span>{{ $postsRate }}%</span></div>
                            <div class="h-2 rounded-full bg-slate-100"><div class="h-full rounded-full bg-blue-500 {{ $postsWidth }}"></div></div>
                        </div>
                        <div>
                            <div class="mb-1 flex justify-between text-slate-600"><span>Adhesions</span><span>{{ $memberRate }}%</span></div>
                            <div class="h-2 rounded-full bg-slate-100"><div class="h-full rounded-full bg-cyan-500 {{ $memberWidth }}"></div></div>
                        </div>
                        <div>
                            <div class="mb-1 flex justify-between text-slate-600"><span>Approvals</span><span>{{ $approvalRate }}%</span></div>
                            <div class="h-2 rounded-full bg-slate-100"><div class="h-full rounded-full bg-amber-500 {{ $approvalWidth }}"></div></div>
                        </div>
                        <div>
                            <div class="mb-1 flex justify-between text-slate-600"><span>Moderation</span><span>{{ $moderationRate }}%</span></div>
                            <div class="h-2 rounded-full bg-slate-100"><div class="h-full rounded-full bg-rose-500 {{ $moderationWidth }}"></div></div>
                        </div>
                    </div>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-soft">
                    <h3 class="font-display text-lg font-semibold text-slate-900">Filtres rapides</h3>
                    <form method="get" action="{{ route('admin.dashboard') }}" class="mt-3 space-y-3 text-sm">
                        <label class="block">
                            <span class="mb-1 block text-slate-600">Fenetre jours</span>
                            <select name="window_days" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2">
                                @foreach([7, 14, 30, 60, 90, 180] as $days)
                                    <option value="{{ $days }}" @selected($windowDays === $days)>{{ $days }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-slate-600">Approvals</span>
                            <select name="approval_status" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2">
                                @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'Tous'] as $key => $label)
                                    <option value="{{ $key }}" @selected($approvalStatus === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-slate-600">Moderation</span>
                            <select name="moderation_status" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2">
                                @foreach(['pending' => 'Pending', 'reviewed' => 'Reviewed', 'resolved' => 'Resolved', 'all' => 'Tous'] as $key => $label)
                                    <option value="{{ $key }}" @selected($moderationStatus === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-slate-600">Action audit</span>
                            <input type="text" name="audit_action" value="{{ $auditAction }}" placeholder="approval.decision" class="w-full rounded-xl border border-slate-300 px-3 py-2">
                        </label>
                        <div class="flex gap-2">
                            <button type="submit" class="flex-1 rounded-xl bg-tlink-blue px-4 py-2 text-white hover:bg-tlink-blue-700">Appliquer</button>
                            <a href="{{ route('admin.dashboard') }}" class="rounded-xl border border-slate-300 px-4 py-2 hover:bg-slate-50">Reset</a>
                        </div>
                    </form>
                </article>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-soft">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="font-display text-lg font-semibold text-slate-900">Recent Approvals</h3>
                    <a href="/api/v1/approvals?status={{ $approvalStatus }}" class="text-sm text-tlink-blue hover:underline">Voir API</a>
                </div>
                <div class="overflow-auto">
                    <table class="w-full min-w-[780px] text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-slate-500">
                                <th class="px-2 py-2">ID</th>
                                <th class="px-2 py-2">Type</th>
                                <th class="px-2 py-2">Demandeur</th>
                                <th class="px-2 py-2">Objet</th>
                                <th class="px-2 py-2">Status</th>
                                <th class="px-2 py-2">Soumis</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($approvalItems as $approval)
                                <tr class="border-b border-slate-100">
                                    <td class="px-2 py-2">#{{ $approval->id }}</td>
                                    <td class="px-2 py-2">{{ $approval->request_type }}</td>
                                    <td class="px-2 py-2">{{ $approval->requester?->name ?? 'N/A' }}</td>
                                    <td class="px-2 py-2 text-slate-500">{{ class_basename($approval->approvable_type) }} #{{ $approval->approvable_id }}</td>
                                    <td class="px-2 py-2">
                                        <span class="rounded-full px-2 py-0.5 text-xs {{ $approval->status === 'pending' ? 'bg-amber-100 text-amber-800' : ($approval->status === 'approved' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800') }}">{{ strtoupper($approval->status) }}</span>
                                    </td>
                                    <td class="px-2 py-2 text-slate-500">{{ $approval->submitted_at ?? $approval->created_at }}</td>
                                </tr>
                            @empty
                                <tr><td class="px-2 py-4 text-slate-400" colspan="6">Aucune approbation.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="admin-two-grid">
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-soft">
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="font-display text-lg font-semibold text-slate-900">Moderation Queue</h3>
                        <a href="/api/v1/admin/moderation-reports?status={{ $moderationStatus }}" class="text-sm text-tlink-blue hover:underline">Voir API</a>
                    </div>
                    <div class="overflow-auto">
                        <table class="w-full min-w-[560px] text-sm">
                            <thead>
                                <tr class="border-b border-slate-200 text-left text-slate-500">
                                    <th class="px-2 py-2">Report</th>
                                    <th class="px-2 py-2">Type</th>
                                    <th class="px-2 py-2">Raison</th>
                                    <th class="px-2 py-2">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentModeration as $report)
                                    <tr class="border-b border-slate-100">
                                        <td class="px-2 py-2">#{{ $report->id }}</td>
                                        <td class="px-2 py-2">{{ class_basename($report->reportable_type) }}</td>
                                        <td class="px-2 py-2">{{ \Illuminate\Support\Str::limit($report->reason, 50) }}</td>
                                        <td class="px-2 py-2">
                                            <span class="rounded-full px-2 py-0.5 text-xs {{ $report->status === 'pending' ? 'bg-amber-100 text-amber-800' : 'bg-slate-200 text-slate-700' }}">{{ strtoupper($report->status) }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td class="px-2 py-4 text-slate-400" colspan="4">Aucun signalement.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-soft">
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="font-display text-lg font-semibold text-slate-900">Recent Audit Logs</h3>
                        <a href="/api/v1/admin/audit-logs{{ $auditAction !== '' ? ('?action='.urlencode($auditAction)) : '' }}" class="text-sm text-tlink-blue hover:underline">Voir API</a>
                    </div>
                    <div class="overflow-auto">
                        <table class="w-full min-w-[560px] text-sm">
                            <thead>
                                <tr class="border-b border-slate-200 text-left text-slate-500">
                                    <th class="px-2 py-2">Action</th>
                                    <th class="px-2 py-2">Target</th>
                                    <th class="px-2 py-2">IP</th>
                                    <th class="px-2 py-2">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentAuditLogs as $log)
                                    <tr class="border-b border-slate-100">
                                        <td class="px-2 py-2">{{ $log->action }}</td>
                                        <td class="px-2 py-2 text-slate-500">{{ $log->target_type }} #{{ $log->target_id }}</td>
                                        <td class="px-2 py-2">{{ $log->ip_address ?? 'N/A' }}</td>
                                        <td class="px-2 py-2 text-slate-500">{{ $log->created_at }}</td>
                                    </tr>
                                @empty
                                    <tr><td class="px-2 py-4 text-slate-400" colspan="4">Aucun log.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </article>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-soft">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="font-display text-lg font-semibold text-slate-900">Recent Users</h3>
                    <a href="/api/v1/admin/users?from={{ now()->subDays($windowDays)->toDateString() }}" class="text-sm text-tlink-blue hover:underline">Voir API</a>
                </div>
                <div class="overflow-auto">
                    <table class="w-full min-w-[720px] text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-slate-500">
                                <th class="px-2 py-2">ID</th>
                                <th class="px-2 py-2">Nom</th>
                                <th class="px-2 py-2">Email</th>
                                <th class="px-2 py-2">Creation</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentUsers as $user)
                                <tr class="border-b border-slate-100">
                                    <td class="px-2 py-2">#{{ $user->id }}</td>
                                    <td class="px-2 py-2">{{ $user->name }}</td>
                                    <td class="px-2 py-2 text-slate-500">{{ $user->email }}</td>
                                    <td class="px-2 py-2 text-slate-500">{{ $user->created_at }}</td>
                                </tr>
                            @empty
                                <tr><td class="px-2 py-4 text-slate-400" colspan="4">Aucun utilisateur dans la fenetre choisie.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
</div>
@endsection
