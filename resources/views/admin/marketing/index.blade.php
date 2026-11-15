@extends('admin.layouts.app')

@section('title', 'Pages et comptes marketing')
@section('section', 'Marketing et partenariats')

@section('content')
    <x-admin.page-header title="Pages et comptes marketing" subtitle="Une ligne par entité : ses pages Facebook et Instagram (affichées sur sa page) et leurs accès chiffrés.">
        <x-slot:actions>
            @if($canCreate)
                <a href="{{ route('admin.marketing.accounts.create') }}" class="btn btn-primary"><x-admin.icon name="plus" :size="16" /> Ajouter une page</a>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    <article class="card card-flush">
        <form method="get" class="card-body" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
            <div class="field" style="min-width:200px;flex:1">
                <label for="q">Recherche</label>
                <input id="q" type="search" name="q" value="{{ $filters['q'] }}" placeholder="Entité, nom de la page, identifiant…" maxlength="80">
            </div>
            <div class="field">
                <label for="type">Type d’entité</label>
                <select id="type" name="type">
                    <option value="">Tous</option>
                    @foreach(\App\Support\MarketingEntities::TYPES as $key => $label)
                        <option value="{{ $key }}" @selected($filters['type'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <label style="display:flex;gap:6px;align-items:center;padding-bottom:10px"><input type="checkbox" name="missing" value="1" @checked($filters['missing'])> Pages manquantes uniquement</label>
            @if($filters['entity'])<input type="hidden" name="entity" value="{{ $filters['entity'] }}">@endif
            <button class="btn btn-secondary" type="submit"><x-admin.icon name="search" :size="16" /> Filtrer</button>
            @if($filters['q'] || $filters['type'] || $filters['missing'] || $filters['entity'])<a href="{{ route('admin.marketing.accounts.index') }}" class="btn btn-ghost">Réinitialiser</a>@endif
        </form>

        <p class="sub" style="padding:0 20px 12px;margin:0">
            @if($missing > 0)
                <strong>{{ $missing }}</strong> page(s) Facebook ou Instagram à renseigner.
            @else
                Toutes les entités ont renseigné leurs pages Facebook et Instagram.
            @endif
        </p>

        @if($entities->isEmpty())
            <x-admin.empty icon="building" title="Aucune entité à afficher" text="Aucune entité de votre périmètre ne correspond à ce filtre." />
        @else
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Entité</th>
                            @foreach(\App\Models\MarketingAccount::REQUIRED as $key)<th>{{ \App\Models\MarketingAccount::PLATFORMS[$key] }}</th>@endforeach
                            <th class="hide-mobile">Autres accès</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($entities as $row)
                            <tr>
                                <td>
                                    <span class="cell-main">{{ $row['entity']['label'] }}</span>
                                    <div class="cell-sub">{{ $row['entity']['type_label'] }}</div>
                                </td>
                                @foreach(\App\Models\MarketingAccount::REQUIRED as $key)
                                    @php $cell = $row['cells'][$key]; @endphp
                                    <td>
                                        @if($cell['state'] === 'none')
                                            <span class="badge badge-danger">Manquante</span>
                                            @if($canCreate)<div class="cell-sub"><a href="{{ route('admin.marketing.accounts.create', ['entity' => $row['entity']['key'], 'platform' => $key]) }}">Renseigner</a></div>@endif
                                        @else
                                            <x-admin.badge status="active" :label="$cell['state'] === 'complete' ? 'Page et accès' : 'Page renseignée'" />
                                        @endif
                                        @foreach($cell['accounts'] as $account)
                                            @include('admin.marketing.partials.account-line', ['account' => $account])
                                        @endforeach
                                    </td>
                                @endforeach
                                <td class="hide-mobile">
                                    @forelse($row['others'] as $account)
                                        <div style="margin-bottom:6px">
                                            <span class="badge badge-neutral">{{ $account->platformLabel() }}</span>
                                            @include('admin.marketing.partials.account-line', ['account' => $account])
                                        </div>
                                    @empty
                                        <span class="cell-sub">—</span>
                                    @endforelse
                                    @if($canCreate)<div class="cell-sub"><a href="{{ route('admin.marketing.accounts.create', ['entity' => $row['entity']['key'], 'platform' => 'email']) }}">+ Ajouter un accès</a></div>@endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-body">{{ $entities->links() }}</div>
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
