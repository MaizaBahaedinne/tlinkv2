@extends('layouts.base', ['title' => 'Administration | T.Link'])

@section('content')
<style>
.adm-page{min-height:100vh;padding:20px;background:linear-gradient(180deg,#eef3fb,#f5f7fb);font-family:'Manrope',ui-sans-serif,system-ui,sans-serif;color:#0f172a}
.adm-shell{max-width:1500px;margin:0 auto;display:grid;grid-template-columns:280px minmax(0,1fr);gap:18px}
.adm-sidebar{background:#0f1d38;border-radius:22px;padding:20px;color:#d8e3f4;position:sticky;top:14px;height:calc(100vh - 28px);overflow:auto;box-shadow:0 20px 48px rgba(12,26,52,.3)}
.adm-brand-kicker{font-size:11px;letter-spacing:.18em;text-transform:uppercase;color:#97abc8}
.adm-brand-title{margin:8px 0 0;font-family:'Space Grotesk',ui-sans-serif,system-ui,sans-serif;font-size:32px;line-height:1.05;color:#f5f9ff}
.adm-brand-sub{margin-top:10px;font-size:12px;color:#b7c8df}
.adm-nav{margin-top:20px;display:grid;gap:8px}
.adm-nav a{display:block;border-radius:12px;padding:10px 12px;color:#d3deef;text-decoration:none;font-size:14px;border:1px solid transparent}
.adm-nav a:hover{background:rgba(255,255,255,.08);border-color:rgba(255,255,255,.14);color:#fff}
.adm-nav a.is-active{background:rgba(255,255,255,.14);border-color:rgba(255,255,255,.18);color:#fff;font-weight:700}
.adm-exports{margin-top:20px;border:1px solid rgba(255,255,255,.15);border-radius:16px;padding:12px;background:rgba(255,255,255,.05)}
.adm-exports p{margin:0 0 8px;font-size:12px;color:#c6d5ea}
.adm-exports a{display:block;border-radius:10px;border:1px solid rgba(255,255,255,.18);padding:7px 9px;color:#e5eefb;text-decoration:none;font-size:12px;margin-top:7px}
.adm-main{min-width:0;display:grid;gap:14px}
.adm-card{background:#fff;border:1px solid #dbe4f1;border-radius:18px;padding:16px;box-shadow:0 14px 30px rgba(15,46,95,.08)}
.adm-top{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap}
.adm-title{margin:4px 0 0;font-family:'Space Grotesk',ui-sans-serif,system-ui,sans-serif;font-size:36px;line-height:1}
.adm-kicker{margin:0;font-size:12px;text-transform:uppercase;letter-spacing:.12em;color:#64748b}
.adm-sub{margin:8px 0 0;color:#64748b;font-size:15px}
.adm-actions{display:flex;gap:8px;flex-wrap:wrap}
.adm-btn{display:inline-block;padding:9px 13px;border-radius:11px;border:1px solid #cfd9e8;background:#fff;color:#0f172a;text-decoration:none;font-size:14px;font-weight:600;cursor:pointer}
.adm-btn.primary{background:#0f2e5f;color:#fff;border-color:#0f2e5f}
.adm-kpis{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px}
.adm-kpi{border:1px solid #dbe4f1;border-radius:14px;padding:12px;background:#fff}
.adm-kpi .l{margin:0;font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:#64748b}
.adm-kpi .v{margin:6px 0 0;font-size:30px;font-weight:800}
.adm-kpi .n{margin:6px 0 0;font-size:12px;color:#64748b}
.adm-grid-2-1{display:grid;grid-template-columns:minmax(0,2fr) minmax(0,1fr);gap:14px}
.adm-grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.adm-h{display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:10px}
.adm-h h3{margin:0;font-family:'Space Grotesk',ui-sans-serif,system-ui,sans-serif;font-size:21px}
.adm-link{font-size:13px;font-weight:600;color:#0f2e5f;text-decoration:none}
.adm-form{display:grid;gap:9px}
.adm-form label{display:grid;gap:4px;font-size:13px;color:#475569}
.adm-form select,.adm-form input{border:1px solid #cbd5e1;border-radius:10px;padding:9px;background:#fff;font-size:14px}
.adm-bars{display:grid;gap:10px}
.adm-bar-head{display:flex;justify-content:space-between;font-size:13px;color:#475569;margin-bottom:5px}
.adm-track{height:9px;background:#e2e8f0;border-radius:999px;overflow:hidden}
.adm-fill{height:100%;border-radius:999px}
.adm-w-10{width:10%}.adm-w-20{width:20%}.adm-w-30{width:30%}.adm-w-40{width:40%}.adm-w-50{width:50%}
.adm-w-60{width:60%}.adm-w-70{width:70%}.adm-w-80{width:80%}.adm-w-90{width:90%}.adm-w-100{width:100%}
.adm-table-wrap{overflow:auto;border:1px solid #e2e8f0;border-radius:12px}
.adm-table{width:100%;min-width:700px;border-collapse:collapse;font-size:13px}
.adm-table th,.adm-table td{text-align:left;padding:9px 10px;border-bottom:1px solid #eef2f7;vertical-align:top}
.adm-table th{font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:#64748b;background:#f8fafc}
.adm-muted{color:#64748b}
.adm-pill{display:inline-block;border-radius:999px;padding:3px 9px;font-size:11px;font-weight:700}
.adm-pill.pending{background:#fef3c7;color:#92400e}.adm-pill.approved{background:#dcfce7;color:#166534}.adm-pill.rejected{background:#fee2e2;color:#991b1b}.adm-pill.neutral{background:#e2e8f0;color:#334155}
@media (max-width:1280px){.adm-kpis{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media (max-width:1100px){.adm-shell{grid-template-columns:1fr}.adm-sidebar{position:static;height:auto}.adm-grid-2-1,.adm-grid-2{grid-template-columns:1fr}}
@media (max-width:820px){.adm-page{padding:12px}.adm-title{font-size:30px}.adm-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media (max-width:560px){.adm-kpis{grid-template-columns:1fr}}
</style>

@php
    $activityTotal = max(1, $stats['posts'] + $stats['memberships_pending'] + $stats['approvals_pending'] + $stats['moderation_pending']);
    $postsRate = (int) round(($stats['posts'] / $activityTotal) * 100);
    $memberRate = (int) round(($stats['memberships_pending'] / $activityTotal) * 100);
    $approvalRate = (int) round(($stats['approvals_pending'] / $activityTotal) * 100);
    $moderationRate = max(0, 100 - $postsRate - $memberRate - $approvalRate);
    $toStepClass = static function (int $value): string {
        $step = max(10, min(100, (int) (ceil($value / 10) * 10)));
        return 'adm-w-'.$step;
    };
    $postsWidthClass = $toStepClass($postsRate);
    $memberWidthClass = $toStepClass($memberRate);
    $approvalWidthClass = $toStepClass($approvalRate);
    $moderationWidthClass = $toStepClass($moderationRate);
@endphp

<div class="adm-page">
    <div class="adm-shell">
        <aside class="adm-sidebar">
            <p class="adm-brand-kicker">T.Link Control</p>
            <h1 class="adm-brand-title">Admin Console</h1>
            <p class="adm-brand-sub">{{ $isGlobal ? 'Scope national' : ('Scope entites: '.implode(', ', $entityIds)) }}</p>
            <nav class="adm-nav">
                <a href="{{ route('admin.dashboard') }}" class="is-active">Dashboard</a>
                <a href="{{ route('notifications.index') }}">Notifications</a>
                <a href="/api/v1/admin/users">Users API</a>
                <a href="/api/v1/admin/moderation-reports?status=pending">Moderation API</a>
                <a href="/api/v1/admin/audit-logs">Audit API</a>
                <a href="{{ route('home') }}">Retour accueil</a>
            </nav>
            <div class="adm-exports">
                <p>Export rapide</p>
                <a href="/api/v1/admin/exports/users">CSV users</a>
                <a href="/api/v1/admin/exports/events">CSV events</a>
                <a href="/api/v1/admin/exports/moderation-reports">CSV moderation</a>
            </div>
        </aside>

        <main class="adm-main">
            <section class="adm-card">
                <div class="adm-top">
                    <div>
                        <p class="adm-kicker">Operations Dashboard</p>
                        <h2 class="adm-title">Welcome back, admin</h2>
                        <p class="adm-sub">Vue centralisee des operations, des risques et des demandes.</p>
                    </div>
                    <div class="adm-actions">
                        <a href="{{ route('notifications.index') }}" class="adm-btn">Notifications</a>
                        <a href="/api/v1/admin/users?from={{ now()->subDays($windowDays)->toDateString() }}" class="adm-btn primary">Nouveaux users</a>
                    </div>
                </div>
            </section>

            <section class="adm-kpis">
                <article class="adm-kpi"><p class="l">Posts</p><p class="v">{{ $stats['posts'] }}</p><p class="n">+{{ max(1, intdiv($stats['posts'], 8)) }} today</p></article>
                <article class="adm-kpi"><p class="l">Adhesions pending</p><p class="v">{{ $stats['memberships_pending'] }}</p><p class="n">a traiter rapidement</p></article>
                <article class="adm-kpi"><p class="l">Approvals pending</p><p class="v">{{ $stats['approvals_pending'] }}</p><p class="n">workflow actif</p></article>
                <article class="adm-kpi"><p class="l">Moderation pending</p><p class="v">{{ $stats['moderation_pending'] }}</p><p class="n">controle contenu</p></article>
                <article class="adm-kpi"><p class="l">Nouveaux users ({{ $windowDays }}j)</p><p class="v">{{ $stats['new_users_window'] }}</p><p class="n">croissance audience</p></article>
            </section>

            <section class="adm-grid-2-1">
                <article class="adm-card">
                    <div class="adm-h"><h3>Activity Breakdown</h3><span class="adm-pill neutral">Month to date</span></div>
                    <div class="adm-bars">
                        <div><div class="adm-bar-head"><span>Posts</span><span>{{ $postsRate }}%</span></div><div class="adm-track"><div class="adm-fill {{ $postsWidthClass }}" style="background:#3b82f6;"></div></div></div>
                        <div><div class="adm-bar-head"><span>Adhesions</span><span>{{ $memberRate }}%</span></div><div class="adm-track"><div class="adm-fill {{ $memberWidthClass }}" style="background:#06b6d4;"></div></div></div>
                        <div><div class="adm-bar-head"><span>Approvals</span><span>{{ $approvalRate }}%</span></div><div class="adm-track"><div class="adm-fill {{ $approvalWidthClass }}" style="background:#f59e0b;"></div></div></div>
                        <div><div class="adm-bar-head"><span>Moderation</span><span>{{ $moderationRate }}%</span></div><div class="adm-track"><div class="adm-fill {{ $moderationWidthClass }}" style="background:#ef4444;"></div></div></div>
                    </div>
                </article>
                <article class="adm-card">
                    <div class="adm-h"><h3>Filtres rapides</h3></div>
                    <form method="get" action="{{ route('admin.dashboard') }}" class="adm-form">
                        <label><span>Fenetre jours</span><select name="window_days">@foreach([7,14,30,60,90,180] as $days)<option value="{{ $days }}" @selected($windowDays === $days)>{{ $days }}</option>@endforeach</select></label>
                        <label><span>Approvals</span><select name="approval_status">@foreach(['pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected','all'=>'Tous'] as $key => $label)<option value="{{ $key }}" @selected($approvalStatus === $key)>{{ $label }}</option>@endforeach</select></label>
                        <label><span>Moderation</span><select name="moderation_status">@foreach(['pending'=>'Pending','reviewed'=>'Reviewed','resolved'=>'Resolved','all'=>'Tous'] as $key => $label)<option value="{{ $key }}" @selected($moderationStatus === $key)>{{ $label }}</option>@endforeach</select></label>
                        <label><span>Action audit</span><input type="text" name="audit_action" value="{{ $auditAction }}" placeholder="approval.decision"></label>
                        <div class="adm-actions"><button type="submit" class="adm-btn primary">Appliquer</button><a href="{{ route('admin.dashboard') }}" class="adm-btn">Reset</a></div>
                    </form>
                </article>
            </section>

            <section class="adm-card">
                <div class="adm-h"><h3>Recent Approvals</h3><a href="/api/v1/approvals?status={{ $approvalStatus }}" class="adm-link">Voir API</a></div>
                <div class="adm-table-wrap">
                    <table class="adm-table">
                        <thead><tr><th>ID</th><th>Type</th><th>Demandeur</th><th>Objet</th><th>Status</th><th>Soumis</th></tr></thead>
                        <tbody>
                            @forelse($approvalItems as $approval)
                                <tr>
                                    <td>#{{ $approval->id }}</td><td>{{ $approval->request_type }}</td><td>{{ $approval->requester?->name ?? 'N/A' }}</td>
                                    <td class="adm-muted">{{ class_basename($approval->approvable_type) }} #{{ $approval->approvable_id }}</td>
                                    <td>@if($approval->status==='pending')<span class="adm-pill pending">PENDING</span>@elseif($approval->status==='approved')<span class="adm-pill approved">APPROVED</span>@else<span class="adm-pill rejected">REJECTED</span>@endif</td>
                                    <td class="adm-muted">{{ $approval->submitted_at ?? $approval->created_at }}</td>
                                </tr>
                            @empty
                                <tr><td class="adm-muted" colspan="6">Aucune approbation.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="adm-grid-2">
                <article class="adm-card">
                    <div class="adm-h"><h3>Moderation Queue</h3><a href="/api/v1/admin/moderation-reports?status={{ $moderationStatus }}" class="adm-link">Voir API</a></div>
                    <div class="adm-table-wrap"><table class="adm-table" style="min-width:620px"><thead><tr><th>Report</th><th>Type</th><th>Raison</th><th>Status</th></tr></thead><tbody>
                        @forelse($recentModeration as $report)
                        <tr><td>#{{ $report->id }}</td><td>{{ class_basename($report->reportable_type) }}</td><td>{{ \Illuminate\Support\Str::limit($report->reason, 50) }}</td><td>@if($report->status==='pending')<span class="adm-pill pending">PENDING</span>@else<span class="adm-pill neutral">{{ strtoupper($report->status) }}</span>@endif</td></tr>
                        @empty
                        <tr><td class="adm-muted" colspan="4">Aucun signalement.</td></tr>
                        @endforelse
                    </tbody></table></div>
                </article>

                <article class="adm-card">
                    <div class="adm-h"><h3>Recent Audit Logs</h3><a href="/api/v1/admin/audit-logs{{ $auditAction !== '' ? ('?action='.urlencode($auditAction)) : '' }}" class="adm-link">Voir API</a></div>
                    <div class="adm-table-wrap"><table class="adm-table" style="min-width:620px"><thead><tr><th>Action</th><th>Target</th><th>IP</th><th>Date</th></tr></thead><tbody>
                        @forelse($recentAuditLogs as $log)
                        <tr><td>{{ $log->action }}</td><td class="adm-muted">{{ $log->target_type }} #{{ $log->target_id }}</td><td>{{ $log->ip_address ?? 'N/A' }}</td><td class="adm-muted">{{ $log->created_at }}</td></tr>
                        @empty
                        <tr><td class="adm-muted" colspan="4">Aucun log.</td></tr>
                        @endforelse
                    </tbody></table></div>
                </article>
            </section>

            <section class="adm-card">
                <div class="adm-h"><h3>Recent Users</h3><a href="/api/v1/admin/users?from={{ now()->subDays($windowDays)->toDateString() }}" class="adm-link">Voir API</a></div>
                <div class="adm-table-wrap"><table class="adm-table" style="min-width:760px"><thead><tr><th>ID</th><th>Nom</th><th>Email</th><th>Creation</th></tr></thead><tbody>
                    @forelse($recentUsers as $user)
                    <tr><td>#{{ $user->id }}</td><td>{{ $user->name }}</td><td class="adm-muted">{{ $user->email }}</td><td class="adm-muted">{{ $user->created_at }}</td></tr>
                    @empty
                    <tr><td class="adm-muted" colspan="4">Aucun utilisateur dans la fenetre choisie.</td></tr>
                    @endforelse
                </tbody></table></div>
            </section>
        </main>
    </div>
</div>
@endsection
