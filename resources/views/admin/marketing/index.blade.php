@extends('admin.layouts.app')

@section('title', 'Comptes marketing')
@section('section', 'Marketing et partenariats')

@section('content')
    <x-admin.page-header title="Comptes marketing" subtitle="Coffre des accès partagés Facebook, Instagram et e-mail. Chaque club dépose les accès de ses pages : si le responsable change, l’équipe garde la main. Les mots de passe sont chiffrés et chaque consultation est tracée.">
        <x-slot:actions>
            @if($canCreate)
                <a href="{{ route('admin.marketing.accounts.create') }}" class="btn btn-primary"><x-admin.icon name="plus" :size="16" /> Ajouter un accès</a>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    <article class="card card-flush" style="margin-bottom:16px">
        <details @if($missing > 0) open @endif>
            <summary class="card-head" style="cursor:pointer">
                <div>
                    <h2>Accès obligatoires par club</h2>
                    <p class="sub">
                        @if($missing > 0)
                            <strong>{{ $missing }}</strong> accès Facebook ou Instagram manquant(s) : chaque club doit les renseigner.
                        @else
                            Tous les clubs ont renseigné leurs accès Facebook et Instagram.
                        @endif
                    </p>
                </div>
            </summary>
            @if($compliance->isEmpty())
                <x-admin.empty icon="building" title="Aucun club dans votre périmètre" />
            @else
                <div class="table-wrap">
                    <table class="tbl">
                        <thead><tr><th>Club</th>@foreach(\App\Models\MarketingAccount::PLATFORMS as $label)<th>{{ $label }}</th>@endforeach</tr></thead>
                        <tbody>
                            @foreach($compliance as $row)
                                <tr>
                                    <td><span class="cell-main">{{ $row['club']->title }}</span></td>
                                    @foreach(\App\Models\MarketingAccount::PLATFORMS as $key => $label)
                                        <td>
                                            @if($row['platforms'][$key])
                                                <x-admin.badge status="active" label="Renseigné" />
                                            @elseif(in_array($key, \App\Models\MarketingAccount::REQUIRED, true))
                                                <span class="badge badge-danger">Manquant</span>
                                                @if($canCreate)<a class="text-sm" href="{{ route('admin.marketing.accounts.create', ['club' => $row['club']->id, 'platform' => $key]) }}">Renseigner</a>@endif
                                            @else
                                                <span class="text-sm text-muted">Facultatif</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </details>
    </article>

    <article class="card card-flush">
        <form method="get" class="card-body" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
            <div class="field" style="min-width:200px">
                <label for="q">Recherche</label>
                <input id="q" type="search" name="q" value="{{ $filters['q'] }}" placeholder="Nom de la page, identifiant…" maxlength="80">
            </div>
            <div class="field">
                <label for="platform">Plateforme</label>
                <select id="platform" name="platform">
                    <option value="">Toutes</option>
                    @foreach(\App\Models\MarketingAccount::PLATFORMS as $key => $label)
                        <option value="{{ $key }}" @selected($filters['platform'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="club">Club</label>
                <select id="club" name="club">
                    <option value="">Tous</option>
                    @foreach($clubOptions as $id => $title)
                        <option value="{{ $id }}" @selected($filters['club'] === $id)>{{ $title }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn btn-secondary" type="submit"><x-admin.icon name="search" :size="16" /> Filtrer</button>
        </form>

        @if($accounts->count())
            <div class="table-wrap">
                <table class="tbl">
                    <thead><tr><th>Plateforme</th><th>Compte</th><th class="hide-mobile">Club</th><th class="hide-mobile">Double authentification</th><th class="hide-mobile">Mot de passe</th><th></th></tr></thead>
                    <tbody>
                        @foreach($accounts as $account)
                            <tr>
                                <td><x-admin.badge :status="$account->platform" :label="$account->platformLabel()" /></td>
                                <td>
                                    <span class="cell-main">{{ $account->name }}</span>
                                    <div class="cell-sub">{{ $account->login }}@if($account->profile_url) · <a href="{{ $account->profile_url }}" target="_blank" rel="noopener noreferrer">Ouvrir</a>@endif</div>
                                </td>
                                <td class="hide-mobile">{{ $account->club?->title ?? 'Fondation' }}</td>
                                <td class="hide-mobile">{{ \App\Models\MarketingAccount::TWO_FACTOR[$account->two_factor] ?? '—' }}</td>
                                <td class="hide-mobile">{{ $account->password_changed_at ? 'Changé '.$account->password_changed_at->diffForHumans() : '—' }}</td>
                                <td class="cell-actions">
                                    <button type="button" class="btn btn-secondary btn-sm" data-reveal="{{ route('admin.marketing.accounts.secret', $account) }}" data-name="{{ $account->name }}"><x-admin.icon name="eye" :size="15" /> Révéler</button>
                                    @if($canUpdate)<a href="{{ route('admin.marketing.accounts.edit', $account) }}" class="btn btn-secondary btn-sm"><x-admin.icon name="edit" :size="15" /> Modifier</a>@endif
                                    @if($canDelete)<x-admin.delete-button :action="route('admin.marketing.accounts.destroy', $account)" :message="'L’accès « '.$account->name.' » sera retiré du coffre.'" title="Supprimer cet accès ?" />@endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-body">{{ $accounts->links() }}</div>
        @else
            <x-admin.empty icon="key" title="Aucun accès enregistré" text="Ajoutez les accès Facebook, Instagram et e-mail de votre club." />
        @endif
    </article>

    <dialog id="secret-dlg" style="max-width:480px;width:calc(100% - 32px);border:1px solid var(--line);border-radius:14px;padding:0">
        <div class="card-head"><div><h2 id="secret-title">Accès</h2><p class="sub">Affiché 60 secondes, puis masqué. Cette consultation est inscrite au journal d’audit.</p></div></div>
        <div class="card-body" style="display:grid;gap:12px">
            <p id="secret-error" class="error" role="alert" hidden></p>
            <div class="field"><label>Identifiant</label><div style="display:flex;gap:8px"><input id="secret-login" type="text" readonly><button type="button" class="btn btn-secondary btn-sm" data-copy="secret-login">Copier</button></div></div>
            <div class="field"><label>Mot de passe</label><div style="display:flex;gap:8px"><input id="secret-password" type="text" readonly autocomplete="off"><button type="button" class="btn btn-secondary btn-sm" data-copy="secret-password">Copier</button></div></div>
            <div class="field" id="secret-codes-wrap" hidden><label>Codes de secours</label><textarea id="secret-codes" rows="4" readonly></textarea></div>
            <div style="display:flex;justify-content:flex-end"><button type="button" class="btn btn-primary" id="secret-close">Fermer</button></div>
        </div>
    </dialog>

    <script>
        (function () {
            var dlg = document.getElementById('secret-dlg');
            var timer = null;
            var fields = ['secret-login', 'secret-password', 'secret-codes'];

            function clear() {
                fields.forEach(function (id) { document.getElementById(id).value = ''; });
                document.getElementById('secret-codes-wrap').hidden = true;
                clearTimeout(timer);
            }

            function close() { clear(); if (dlg.open) { dlg.close(); } }

            document.getElementById('secret-close').addEventListener('click', close);
            dlg.addEventListener('close', clear);

            document.addEventListener('click', function (e) {
                var copy = e.target.closest('[data-copy]');
                if (copy) {
                    var input = document.getElementById(copy.getAttribute('data-copy'));
                    if (navigator.clipboard) { navigator.clipboard.writeText(input.value); copy.textContent = 'Copié'; setTimeout(function () { copy.textContent = 'Copier'; }, 1500); }
                    return;
                }

                var btn = e.target.closest('[data-reveal]');
                if (!btn) { return; }
                btn.disabled = true;

                fetch(btn.getAttribute('data-reveal'), { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
                    .then(function (res) {
                        var error = document.getElementById('secret-error');
                        document.getElementById('secret-title').textContent = btn.getAttribute('data-name');
                        error.hidden = res.ok;
                        error.textContent = res.ok ? '' : (res.data.message || 'Consultation impossible.');

                        if (res.ok) {
                            document.getElementById('secret-login').value = res.data.login || '';
                            document.getElementById('secret-password').value = res.data.password || '';
                            document.getElementById('secret-codes').value = res.data.recovery_codes || '';
                            document.getElementById('secret-codes-wrap').hidden = !res.data.recovery_codes;
                            timer = setTimeout(close, 60000);
                        }

                        dlg.showModal();
                    })
                    .catch(function () { alert('Consultation impossible. Réessayez.'); })
                    .finally(function () { btn.disabled = false; });
            });
        })();
    </script>
@endsection
