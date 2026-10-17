@extends('layouts.base', ['title' => 'Roadmap Projet | T.Link'])

@section('content')
<style>
.rm-page { min-height: 100vh; background: #f4f7fb; padding: 18px; font-family: 'Manrope', ui-sans-serif, system-ui, sans-serif; color: #0f172a; }
.rm-wrap { max-width: 1320px; margin: 0 auto; display: grid; gap: 14px; }
.rm-card { background: #fff; border: 1px solid #dbe4f0; border-radius: 16px; padding: 16px; box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05); }
.rm-top { display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; align-items: flex-start; }
.rm-kicker { margin: 0; font-size: 11px; text-transform: uppercase; letter-spacing: 0.12em; color: #64748b; }
.rm-title { margin: 6px 0 0; font-family: 'Space Grotesk', ui-sans-serif, system-ui, sans-serif; font-size: 34px; line-height: 1; }
.rm-sub { margin: 8px 0 0; font-size: 14px; color: #64748b; }
.rm-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.rm-btn { display: inline-block; text-decoration: none; border-radius: 10px; border: 1px solid #cfd9e8; background: #fff; color: #0f172a; font-size: 13px; font-weight: 600; padding: 8px 12px; }
.rm-btn:hover { background: #f8fafc; }
.rm-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 10px; }
.rm-kpi { border: 1px solid #e2e8f0; border-radius: 12px; padding: 10px; background: #fff; }
.rm-kpi p { margin: 0; }
.rm-kpi .k { font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; }
.rm-kpi .v { margin-top: 6px; font-size: 26px; font-weight: 800; }
.rm-track { height: 9px; border-radius: 999px; background: #e2e8f0; overflow: hidden; }
.rm-fill { height: 100%; border-radius: 999px; background: linear-gradient(90deg, #2563eb 0%, #0ea5e9 100%); }
.rm-modules { display: grid; gap: 12px; }
.rm-module-head { display: flex; justify-content: space-between; gap: 10px; align-items: baseline; flex-wrap: wrap; }
.rm-module-title { margin: 0; font-family: 'Space Grotesk', ui-sans-serif, system-ui, sans-serif; font-size: 22px; }
.rm-module-goal { margin: 6px 0 0; color: #64748b; font-size: 13px; }
.rm-module-progress { min-width: 160px; text-align: right; font-size: 12px; color: #334155; }
.rm-table-wrap { margin-top: 12px; border: 1px solid #e2e8f0; border-radius: 12px; overflow: auto; }
.rm-table { width: 100%; min-width: 980px; border-collapse: collapse; font-size: 13px; }
.rm-table th, .rm-table td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #eef2f7; vertical-align: middle; }
.rm-table th { background: #f8fafc; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; }
.rm-pill { display: inline-block; border-radius: 999px; padding: 2px 8px; font-size: 10px; font-weight: 700; }
.rm-pill.todo { background: #e2e8f0; color: #334155; }
.rm-pill.in_progress { background: #dbeafe; color: #1d4ed8; }
.rm-pill.done { background: #dcfce7; color: #166534; }
.rm-pill.blocked { background: #fee2e2; color: #991b1b; }
.rm-row-form { display: flex; gap: 8px; align-items: center; }
.rm-row-form select, .rm-row-form input { border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; font-size: 12px; padding: 6px 8px; }
.rm-row-form input { width: 230px; }
.rm-row-form button { border: 1px solid #0f2e5f; background: #0f2e5f; color: #fff; border-radius: 8px; padding: 6px 10px; font-size: 12px; font-weight: 700; cursor: pointer; }
.rm-row-form button:hover { background: #0b2348; }
.rm-updated { color: #64748b; font-size: 12px; }
@media (max-width: 1180px) { .rm-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
@media (max-width: 760px) { .rm-page { padding: 10px; } .rm-title { font-size: 28px; } .rm-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 520px) { .rm-grid { grid-template-columns: 1fr; } }
</style>

<div class="rm-page">
    <div class="rm-wrap">
        <section class="rm-card">
            <div class="rm-top">
                <div>
                    <p class="rm-kicker">Pilotage Produit</p>
                    <h1 class="rm-title">{{ $roadmap['project_name'] }}</h1>
                    <p class="rm-sub">Une seule page de pilotage. Mise a jour module par module et action par action.</p>
                </div>
                <div class="rm-actions">
                    <a href="{{ route('home') }}" class="rm-btn">Retour accueil</a>
                    <a href="{{ route('notifications.index') }}" class="rm-btn">Notifications</a>
                </div>
            </div>
            <div style="margin-top: 14px;" class="rm-grid">
                <article class="rm-kpi"><p class="k">Avancement global</p><p class="v">{{ $summary['global_progress'] }}%</p></article>
                <article class="rm-kpi"><p class="k">Actions totales</p><p class="v">{{ $summary['total_actions'] }}</p></article>
                <article class="rm-kpi"><p class="k">Terminees</p><p class="v">{{ $summary['done_actions'] }}</p></article>
                <article class="rm-kpi"><p class="k">En cours</p><p class="v">{{ $summary['in_progress_actions'] }}</p></article>
                <article class="rm-kpi"><p class="k">Bloquees</p><p class="v">{{ $summary['blocked_actions'] }}</p></article>
            </div>
            <div style="margin-top: 12px;">
                <div class="rm-track"><div class="rm-fill" style="width: {{ $summary['global_progress'] }}%;"></div></div>
            </div>
        </section>

        <section class="rm-modules">
            @foreach($roadmap['modules'] as $module)
                <article class="rm-card">
                    <div class="rm-module-head">
                        <div>
                            <h2 class="rm-module-title">{{ $module['title'] }}</h2>
                            <p class="rm-module-goal">{{ $module['goal'] }}</p>
                        </div>
                        <div class="rm-module-progress">Progression module: <strong>{{ $module['progress'] ?? 0 }}%</strong></div>
                    </div>
                    <div style="margin-top: 8px;" class="rm-track"><div class="rm-fill" style="width: {{ $module['progress'] ?? 0 }}%;"></div></div>

                    <div class="rm-table-wrap">
                        <table class="rm-table">
                            <thead>
                                <tr>
                                    <th>Action</th>
                                    <th>Etat</th>
                                    <th>Note</th>
                                    <th>Derniere maj</th>
                                    <th>Mise a jour</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($module['actions'] as $action)
                                    <tr>
                                        <td>{{ $action['label'] }}</td>
                                        <td><span class="rm-pill {{ $action['status'] }}">{{ strtoupper($action['status']) }}</span></td>
                                        <td>{{ $action['note'] !== '' ? $action['note'] : '-' }}</td>
                                        <td class="rm-updated">{{ $action['updated_at'] ?? '-' }}</td>
                                        <td>
                                            <form class="rm-row-form" method="post" action="{{ route('admin.roadmap.action.update') }}">
                                                @csrf
                                                <input type="hidden" name="module_id" value="{{ $module['id'] }}">
                                                <input type="hidden" name="action_id" value="{{ $action['id'] }}">
                                                <select name="status" required>
                                                    @foreach($statusOptions as $statusValue => $statusLabel)
                                                        <option value="{{ $statusValue }}" @selected($action['status'] === $statusValue)>{{ $statusLabel }}</option>
                                                    @endforeach
                                                </select>
                                                <input type="text" name="note" value="{{ $action['note'] }}" maxlength="300" placeholder="Commentaire court">
                                                <button type="submit">Mettre a jour</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </article>
            @endforeach
        </section>
    </div>
</div>
@endsection
