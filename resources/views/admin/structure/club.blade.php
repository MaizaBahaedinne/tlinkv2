@extends('admin.layouts.app')

@section('title', $club->title)
@section('section', 'Organisation')

@php
    $total = $positions->count();
    $percent = $total > 0 ? (int) round($filled / $total * 100) : 0;
    $vacant = $total - $filled;
    $president = $positions->first(fn ($position) => $position->role_key === 'president' || str_starts_with($position->title, 'Président'));
    $presidentName = $president ? ($assignments[$president->id] ?? collect())->first()?->person?->displayName() : null;
    $mandateHint = $mandate ? 'Mandat '.$mandate->label : 'Aucun mandat en cours';
    $levelLabel = \App\Models\OrgNode::CATEGORIES[$club->category] ?? ($club->category ?: '—');
    $columnTotals = [];
    foreach ($matrix as $cells) {
        foreach ($cells as $mandateId => $count) {
            $columnTotals[$mandateId] = ($columnTotals[$mandateId] ?? 0) + $count;
        }
    }
@endphp

@section('content')
    <x-admin.page-header :title="$club->title" subtitle="Fiche du club">
        <x-slot:actions>
            <x-admin.badge :status="$club->status ?? 'draft'" :label="\App\Models\OrgNode::CLUB_STATUSES[$club->status] ?? null" />
            @if($canStatus)
                @php($activating = $club->status !== 'active')
                <form method="post" action="{{ route('admin.clubs.set_status', $club) }}" @unless($activating) data-confirm="Un club inactif n’apparaît plus sur le site, dans l’API ni dans les statistiques. Désactiver « {{ $club->title }} » ?" data-confirm-title="Désactiver le club" @endunless>
                    @csrf
                    <input type="hidden" name="status" value="{{ $activating ? 'active' : 'inactive' }}">
                    <button type="submit" class="btn btn-secondary"><x-admin.icon :name="$activating ? 'check' : 'x'" :size="16" /> {{ $activating ? 'Activer le club' : 'Désactiver le club' }}</button>
                </form>
            @endif
            <a href="{{ route('admin.clubs.index') }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Clubs</a>
            <a href="{{ route('admin.postes.edit', $club) }}" class="btn btn-secondary"><x-admin.icon name="edit" :size="16" /> {{ $canEdit ? 'Modifier la fiche' : 'Fiche' }}</a>
        </x-slot:actions>
    </x-admin.page-header>

    <section class="stats" style="margin-bottom:22px">
        <x-admin.stat label="Postes pourvus" :value="$filled.' / '.$total" icon="users" tone="cyan" :hint="$vacant.' vacant(s)'" />
        <x-admin.stat label="Adhérents" :value="$currentMembers ?? '—'" icon="user" tone="green" :hint="$mandateHint" />
        <x-admin.stat label="Activités" :value="$mandateActivities ?? '—'" icon="calendar" tone="amber" :hint="$mandateHint" />
        <x-admin.stat label="Niveau du club" :value="$levelLabel" icon="award" tone="blue" />
    </section>

    <article class="card card-flush" style="margin-bottom:22px">
        <div class="card-head">
            <div>
                <h2>Organigramme du club</h2>
                <p class="sub">Le club à gauche, un niveau de postes par colonne vers la droite. Les postes suivent le modèle du type de club (Admin > Modèles de clubs).@if($canInvite) Sur un poste vacant, invitez un candidat par lien, QR code ou e-mail.@endif</p>
            </div>
            @if($total > 0)
                <span class="badge {{ $vacant > 0 ? 'badge-warning' : 'badge-success' }}">{{ $vacant > 0 ? $vacant.' poste(s) vacant(s)' : 'Tous les postes sont pourvus' }}</span>
            @endif
        </div>
        <div class="card-body">
            @if($total > 0)
                <div class="progress" style="margin-bottom:14px"><span style="width:{{ $percent }}%"></span></div>
                <div class="hz-legend">
                    <span><i class="is-dept"></i>Département</span>
                    <span><i></i>Poste pourvu</span>
                    <span><i class="is-vacant"></i>Poste vacant</span>
                    <span>{{ $filled }} / {{ $total }} postes pourvus ({{ $percent }} %)</span>
                </div>
                <div class="hz-scroll">
                    <ul class="hz-tree">
                        <li class="hz-node">
                            <div class="hz-card is-club">
                                <div class="hz-head"><span class="hz-title">{{ $club->title }}</span></div>
                                <div class="hz-meta">{{ $levelLabel }}@if($presidentName) · Président : {{ $presidentName }}@endif</div>
                            </div>
                            <ul class="hz-kids">
                                @foreach($tree[$club->id] ?? [] as $child)
                                    @include('admin.structure.partials.club-chart-node', ['item' => $child, 'tree' => $tree, 'assignments' => $assignments, 'canInvite' => $canInvite, 'club' => $club])
                                @endforeach
                            </ul>
                        </li>
                    </ul>
                </div>
            @else
                <x-admin.empty icon="network" title="Aucune équipe" text="Ce club n’a pas encore de postes. Utilisez « Compléter les équipes » dans la liste des clubs." />
            @endif
        </div>
    </article>

    <div class="grid-3-1" style="margin-bottom:22px">
    <article class="card card-flush">
        <div class="card-head">
            <div>
                <h2>Adhésions par mandat et par département</h2>
                <p class="sub">Adhésions approuvées du club ; le département est celui du poste visé ou occupé.</p>
            </div>
        </div>
        @if($mandates->isNotEmpty() && $departments !== [])
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Département</th>
                            @foreach($mandates as $item)
                                <th style="text-align:right">{{ $item->label }}@if($mandate && $item->id === $mandate->id) <span class="badge badge-info">En cours</span>@endif</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($departments as $key => $label)
                            <tr>
                                <td class="cell-main">{{ $label }}</td>
                                @foreach($mandates as $item)
                                    <td style="text-align:right">{{ $matrix[$key][$item->id] ?? 0 }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                        <tr>
                            <td class="cell-main">Total</td>
                            @foreach($mandates as $item)
                                <td style="text-align:right;font-weight:700">{{ $columnTotals[$item->id] ?? 0 }}</td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>
        @else
            <div class="card-body"><p class="text-sm text-muted">Aucune adhésion à afficher : aucun mandat ou aucun département défini.</p></div>
        @endif
    </article>

        <article class="card card-flush" style="align-self:start">
            <div class="card-head"><div><h2>Le club</h2></div></div>
            <div class="card-body">
                <dl class="kv" style="grid-template-columns:1fr">
                    <div><dt>Président</dt><dd>{{ $presidentName ?? 'Vacant' }}</dd></div>
                    <div><dt>Établissement</dt><dd>{{ $club->institution_name ?? '—' }}</dd></div>
                    <div><dt>Gouvernorat</dt><dd>{{ $club->governorate?->name ?? '—' }}</dd></div>
                    <div><dt>Fondé le</dt><dd>{{ $club->founded_on?->format('d/m/Y') ?? '—' }}</dd></div>
                    <div><dt>Charte</dt><dd>{{ $club->charter_signed ? 'Signée' : 'Non signée' }}</dd></div>
                    <div><dt>Contact</dt><dd>{{ $club->email ?? '—' }}</dd></div>
                </dl>
            </div>
        </article>
    </div>

    <div class="grid-2">
        @foreach([['Activités à venir', $upcomingActivities, 'Aucune activité à venir.'], ['Activités passées', $pastActivities, 'Aucune activité passée.']] as [$heading, $list, $empty])
            <article class="card card-flush">
                <div class="card-head"><div><h2>{{ $heading }}</h2></div></div>
                <div class="card-body">
                    @forelse($list as $activity)
                        <div style="margin-bottom:12px">
                            <a href="{{ route('admin.activities.show', $activity) }}" class="cell-main">{{ $activity->title }}</a>
                            <div class="cell-sub">
                                {{ $activity->starts_at?->translatedFormat('d M Y, H:i') }}
                                @if($activity->challenge) · {{ $activity->challenge->title }} @endif
                                @if($activity->status !== 'published') · {{ $activityStatuses[$activity->status] ?? $activity->status }} @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-muted">{{ $empty }}</p>
                    @endforelse
                </div>
            </article>
        @endforeach
    </div>

    @if($canInvite)
        @php($reopen = $errors->any() ? (int) old('_invite_node') : 0)
        <dialog id="invite-dialog" class="modal" aria-labelledby="invite-title" style="width:min(520px, calc(100vw - 32px))" data-reopen="{{ $reopen ?: '' }}">
            <div class="modal-head">
                <div>
                    <h2 id="invite-title">Inviter pour un poste</h2>
                    <p class="sub" data-invite-sub></p>
                </div>
                <button type="button" class="btn btn-ghost btn-sm" data-modal-close aria-label="Fermer">✕</button>
            </div>
            <div class="modal-body" style="display:block">
                <div class="share-tabs" role="tablist" aria-label="Façon d’inviter">
                    <button type="button" class="btn btn-primary btn-sm" data-share-tab="link"><x-admin.icon name="external" :size="14" /> Lien</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-share-tab="qr">QR code</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-share-tab="mail"><x-admin.icon name="mail" :size="14" /> E-mail</button>
                </div>

                <section class="share-pane" data-share-pane="link">
                    <label for="invite-link" class="text-sm text-muted">Lien d’inscription au poste, à partager comme vous le souhaitez</label>
                    <div class="share-link" style="margin-top:6px">
                        <input id="invite-link" type="text" readonly data-invite-link onfocus="this.select()">
                        <button type="button" class="btn btn-secondary" data-invite-copy>Copier</button>
                    </div>
                    <p class="hint" style="margin-top:8px" data-invite-copied hidden>Lien copié.</p>
                    <p class="hint" style="margin-top:8px">La personne crée son compte (ou se connecte) puis dépose sa candidature : elle est examinée par l’équipe avant toute attribution.</p>
                </section>

                <section class="share-pane" data-share-pane="qr" hidden>
                    <div class="share-qr" data-invite-qr>QR code</div>
                    <p class="hint" style="text-align:center">À afficher ou imprimer : il ouvre la page d’inscription au poste.</p>
                </section>

                <section class="share-pane" data-share-pane="mail" hidden>
                    <form method="post" action="#" data-invite-form class="form-grid">
                        @csrf
                        <input type="hidden" name="_invite_node" value="{{ old('_invite_node') }}">
                        @if($reopen)
                            <div class="alert alert-error span-2" role="alert"><span>@foreach($errors->all() as $message){{ $message }} @endforeach</span></div>
                        @endif
                        <div class="field"><label for="invite-first">Prénom <span class="req">*</span></label><input id="invite-first" name="first_name" required maxlength="120" value="{{ $reopen ? old('first_name') : '' }}"></div>
                        <div class="field"><label for="invite-last">Nom <span class="req">*</span></label><input id="invite-last" name="last_name" required maxlength="120" value="{{ $reopen ? old('last_name') : '' }}"></div>
                        <div class="field span-2"><label for="invite-email">E-mail <span class="req">*</span></label><input id="invite-email" type="email" name="email" required maxlength="255" value="{{ $reopen ? old('email') : '' }}"></div>
                        <p class="hint span-2" data-invite-single hidden>Ce poste n’a qu’un titulaire : une seule invitation à la fois.</p>
                        <div class="form-actions span-2"><button type="submit" class="btn btn-primary"><x-admin.icon name="send" :size="16" /> Envoyer l’invitation</button></div>
                    </form>
                </section>
            </div>
        </dialog>

        @push('scripts')
            @vite(['resources/js/qr.js'])
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    var dialog = document.getElementById('invite-dialog');
                    if (!dialog) { return; }

                    var tabs = dialog.querySelectorAll('[data-share-tab]');
                    var panes = dialog.querySelectorAll('[data-share-pane]');
                    var qr = dialog.querySelector('[data-invite-qr]');
                    var link = dialog.querySelector('[data-invite-link]');
                    var form = dialog.querySelector('[data-invite-form]');
                    var node = form.querySelector('[name="_invite_node"]');
                    var title = '';
                    var drawn = null;

                    function draw() {
                        if (drawn === link.value) { return; }
                        drawn = link.value;
                        qr.textContent = 'Génération…';
                        document.dispatchEvent(new CustomEvent('qr:render', { detail: { el: qr, text: link.value, label: 'QR code du poste ' + title } }));
                        window.setTimeout(function () {
                            if (qr.textContent === 'Génération…') { qr.textContent = 'QR code indisponible : utilisez le lien.'; drawn = null; }
                        }, 2500);
                    }

                    function show(name) {
                        tabs.forEach(function (tab) {
                            var on = tab.getAttribute('data-share-tab') === name;
                            tab.classList.toggle('btn-primary', on);
                            tab.classList.toggle('btn-secondary', !on);
                        });
                        panes.forEach(function (pane) { pane.hidden = pane.getAttribute('data-share-pane') !== name; });
                        if (name === 'qr') { draw(); }
                    }

                    function open(button, tab) {
                        title = button.getAttribute('data-title');
                        link.value = button.getAttribute('data-url');
                        form.setAttribute('action', button.getAttribute('data-action'));
                        node.value = button.getAttribute('data-node');
                        dialog.querySelector('[data-invite-sub]').textContent = title;
                        dialog.querySelector('[data-invite-single]').hidden = button.getAttribute('data-single') !== '1';
                        dialog.querySelector('[data-invite-copied]').hidden = true;
                        drawn = null;
                        show(tab || 'link');
                        if (!dialog.open) { dialog.showModal(); }
                    }

                    document.querySelectorAll('[data-invite-open]').forEach(function (button) {
                        button.addEventListener('click', function () { open(button, 'link'); });
                    });
                    tabs.forEach(function (tab) {
                        tab.addEventListener('click', function () { show(tab.getAttribute('data-share-tab')); });
                    });
                    dialog.querySelector('[data-invite-copy]').addEventListener('click', function () {
                        var done = function () { dialog.querySelector('[data-invite-copied]').hidden = false; };
                        if (navigator.clipboard && navigator.clipboard.writeText) {
                            navigator.clipboard.writeText(link.value).then(done, function () { link.select(); document.execCommand('copy'); done(); });
                        } else {
                            link.select();
                            document.execCommand('copy');
                            done();
                        }
                    });

                    // Une invitation par e-mail refusée : on rouvre la fenêtre sur l'onglet E-mail avec le message d'erreur.
                    var reopen = dialog.getAttribute('data-reopen');
                    if (reopen) {
                        var button = document.querySelector('[data-invite-open][data-node="' + reopen + '"]');
                        if (button) { open(button, 'mail'); }
                    }
                });
            </script>
        @endpush
    @endif
@endsection
