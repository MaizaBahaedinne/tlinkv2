@extends('admin.layouts.app')

@section('title', 'Feuille de route')
@section('section', 'Feuille de route')

@section('content')
    <x-admin.page-header :title="$roadmap['project_name']" subtitle="Suivi module par module de l’avancement du produit. Chaque mise à jour est tracée dans le journal d’audit." />

    <section class="stats">
        <x-admin.stat label="Avancement global" :value="$progress.'%'" icon="activity" tone="blue" :hint="$total.' actions au total'" />
        <x-admin.stat label="Terminées" :value="$counts['done']" icon="check" tone="green" />
        <x-admin.stat label="En cours" :value="$counts['in_progress']" icon="clock" tone="cyan" />
        <x-admin.stat label="À faire" :value="$counts['todo']" icon="inbox" tone="amber" />
        <x-admin.stat label="Bloquées" :value="$counts['blocked']" icon="flag" tone="red" />
    </section>

    @foreach($roadmap['modules'] as $module)
        <article class="card card-flush">
            <div class="card-head">
                <div>
                    <h2>{{ $module['title'] }}</h2>
                    <p class="sub">{{ $module['goal'] }}</p>
                </div>
                <div style="min-width: 180px">
                    <div class="text-sm text-muted" style="text-align:right; margin-bottom:6px"><strong style="color: var(--text)">{{ $module['progress'] }}%</strong> complété</div>
                    <div class="progress"><span style="width: {{ $module['progress'] }}%"></span></div>
                </div>
            </div>
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>État</th>
                            <th class="hide-mobile">Mise à jour</th>
                            <th>Modifier</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($module['actions'] as $action)
                            <tr>
                                <td>
                                    <div class="cell-main">{{ $action['label'] }}</div>
                                    @if(($action['note'] ?? '') !== '')
                                        <div class="cell-sub">{{ $action['note'] }}</div>
                                    @endif
                                </td>
                                <td><x-admin.badge :status="$action['status']" /></td>
                                <td class="hide-mobile text-sm text-muted nowrap">{{ $action['updated_at'] ?? '—' }}</td>
                                <td>
                                    <form method="post" action="{{ route('admin.roadmap.update') }}" class="inline-form">
                                        @csrf
                                        <input type="hidden" name="module_id" value="{{ $module['id'] }}">
                                        <input type="hidden" name="action_id" value="{{ $action['id'] }}">
                                        <select name="status" aria-label="État">
                                            @foreach($statuses as $value => $label)
                                                <option value="{{ $value }}" @selected($action['status'] === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <input type="text" name="note" value="{{ $action['note'] ?? '' }}" maxlength="300" placeholder="Note courte" aria-label="Note" style="min-width: 200px">
                                        <button type="submit" class="btn btn-secondary btn-sm">Enregistrer</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </article>
    @endforeach
@endsection
