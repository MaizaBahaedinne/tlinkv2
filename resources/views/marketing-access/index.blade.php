@extends('layouts.base', ['title' => 'Mes accès réseaux sociaux | T.Link'])

@section('content')
<div class="min-h-screen bg-tlink-sand pb-24 text-slate-800 lg:pb-12">
    @include('partials.app-header')

    <div class="app-container space-y-6 py-6">
        <div>
            <h1 class="font-display text-2xl font-semibold text-tlink-blue">Mes accès réseaux sociaux</h1>
            <p class="max-w-2xl text-sm text-slate-600">Demandes d’accès aux pages Facebook et Instagram de votre club, chapitre ou projet. Une fois la demande validée par le digital manager de la Fondation, signez la charte d’usage pour voir les accès.</p>
        </div>

        @if(session('status'))
            <p class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('status') }}</p>
        @endif

        @forelse($requests as $item)
            @php($mine = $item->beneficiary_id === $me->id)
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="font-display text-lg font-semibold text-slate-900">{{ $item->entityLabel() }} — {{ $item->platformLabels() }}</h2>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ $item->statusLabel() }}</span>
                </div>
                <p class="mt-1 text-xs text-slate-500">
                    @if($mine)Pour vous @else Pour {{ $item->beneficiary?->name }} @endif · demandé par {{ $item->requester?->name }} le {{ $item->created_at->format('d/m/Y') }}
                </p>
                @if($item->review_note && in_array($item->status, ['approved', 'refused', 'revoked'], true))
                    <p class="mt-2 rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-700">{{ $item->review_note }}</p>
                @endif
                @if($item->meta_portfolio && $mine)
                    <p class="mt-2 text-sm text-slate-700"><span class="font-semibold">Portfolio Meta attribué :</span> {{ $item->meta_portfolio }}</p>
                @endif

                @if($mine && $item->status === 'approved')
                    <a href="{{ route('marketing-access.charter', $item) }}" class="mt-3 inline-flex rounded-full bg-tlink-blue px-4 py-2 text-sm font-semibold text-white hover:opacity-90">Lire et signer la charte</a>
                @elseif($mine && $item->status === 'active')
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" class="rounded-full bg-tlink-blue px-4 py-2 text-sm font-semibold text-white hover:opacity-90" data-reveal-access="{{ route('marketing-access.secrets', $item) }}">Afficher les accès</button>
                        <a href="{{ route('marketing-access.charter', $item) }}" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Charte signée</a>
                    </div>
                    <div class="mt-3 hidden space-y-2" data-access-result></div>
                @elseif(! $mine && $item->status === 'approved')
                    <p class="mt-3 text-sm text-slate-600">Validée : {{ $item->beneficiary?->name }} doit signer la charte pour obtenir l’accès.</p>
                @endif
            </article>
        @empty
            <p class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-10 text-center text-sm text-slate-500">Aucune demande d’accès aux réseaux sociaux.</p>
        @endforelse
    </div>
</div>

<script>
    (function () {
        function esc(value) {
            var el = document.createElement('div');
            el.textContent = value == null ? '' : String(value);
            return el.innerHTML;
        }

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-reveal-access]');
            if (!btn) { return; }
            var box = btn.parentElement.nextElementSibling;
            btn.disabled = true;

            fetch(btn.getAttribute('data-reveal-access'), { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
                .then(function (res) {
                    box.classList.remove('hidden');

                    if (!res.ok) { box.innerHTML = '<p class="text-sm text-rose-700">' + esc(res.data.message || 'Consultation impossible.') + '</p>'; return; }

                    var html = '<p class="text-xs text-slate-500">Ces accès sont personnels et masqués au bout de 60 secondes. Cette consultation est enregistrée.</p>';
                    res.data.accounts.forEach(function (account) {
                        html += '<div class="rounded-xl bg-slate-50 px-3 py-2 text-sm"><p class="font-semibold">' + esc(account.platform) + ' — ' + esc(account.name) + '</p>'
                            + (account.profile_url ? '<p><a class="text-tlink-blue underline" target="_blank" rel="noopener noreferrer" href="' + esc(account.profile_url) + '">Ouvrir la page</a></p>' : '')
                            + '<p>Identifiant : <span class="font-mono">' + esc(account.login || '—') + '</span></p>'
                            + '<p>Mot de passe : <span class="font-mono">' + esc(account.password || 'non déposé') + '</span></p></div>';
                    });
                    if (res.data.meta_portfolio) { html += '<p class="text-sm"><span class="font-semibold">Portfolio Meta :</span> ' + esc(res.data.meta_portfolio) + '</p>'; }
                    box.innerHTML = html;
                    setTimeout(function () { box.innerHTML = ''; box.classList.add('hidden'); }, 60000);
                })
                .catch(function () { alert('Consultation impossible. Réessayez.'); })
                .finally(function () { btn.disabled = false; });
        });
    })();
</script>
@endsection
