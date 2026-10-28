{{-- Compte non validé : alerte bloquante (style SweetAlert) tant que l'info session et le quiz ne sont pas réussis. --}}
<style>
    .gate-backdrop { position: fixed; inset: 0; z-index: 9000; display: flex; align-items: center; justify-content: center; padding: 16px; background: rgba(15, 23, 42, .62); backdrop-filter: blur(3px); animation: gate-fade .2s ease-out; }
    .gate-box { width: 100%; max-width: 430px; padding: 32px 28px 24px; border-radius: 22px; background: #fff; text-align: center; box-shadow: 0 30px 70px rgba(15, 23, 42, .35); animation: gate-pop .25s ease-out; }
    .gate-icon { display: grid; place-items: center; width: 76px; height: 76px; margin: 0 auto 18px; border: 4px solid #fde68a; border-radius: 999px; color: #d97706; font: 700 38px/1 system-ui, sans-serif; }
    .gate-title { margin: 0 0 10px; color: #0f172a; font: 700 24px/1.2 var(--font-display, system-ui), sans-serif; }
    .gate-text { margin: 0 0 22px; color: #475569; font-size: 15px; line-height: 1.55; }
    .gate-btn { display: inline-block; width: 100%; padding: 12px 18px; border: 0; border-radius: 12px; background: #d00031; color: #fff; font: 700 15px system-ui, sans-serif; text-decoration: none; cursor: pointer; }
    .gate-btn:hover { background: #a30027; }
    .gate-link { margin-top: 14px; padding: 6px; border: 0; background: none; color: #64748b; font: 600 13px system-ui, sans-serif; text-decoration: underline; cursor: pointer; }
    @keyframes gate-fade { from { opacity: 0; } to { opacity: 1; } }
    @keyframes gate-pop { from { opacity: 0; transform: scale(.92); } to { opacity: 1; transform: scale(1); } }
</style>

<div class="gate-backdrop" id="account-gate" role="alertdialog" aria-modal="true" aria-labelledby="gate-title" aria-describedby="gate-text">
    <div class="gate-box">
        <div class="gate-icon" aria-hidden="true">!</div>
        <h2 class="gate-title" id="gate-title">Compte non validé</h2>
        <p class="gate-text" id="gate-text">Regarde l'info session et réussis le quiz pour valider ton compte. Tant que ce n'est pas fait, tu ne peux pas naviguer sur T.Link.</p>
        <a href="{{ route('info-session.show') }}" class="gate-btn" id="gate-primary">Valider mon compte</a>
        <form method="post" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="gate-link">Se déconnecter</button>
        </form>
    </div>
</div>

<script>
    (function () {
        var gate = document.getElementById('account-gate');
        if (!gate) { return; }

        document.body.style.overflow = 'hidden';

        // Tout le reste de la page est inerte : ni clic, ni clavier, ni lecteur d'écran.
        Array.prototype.forEach.call(document.body.children, function (el) {
            if (el === gate || el.tagName === 'SCRIPT' || el.tagName === 'STYLE' || el.hasAttribute('data-gate-exempt')) { return; }
            el.setAttribute('inert', '');
        });

        var primary = document.getElementById('gate-primary');
        if (primary) { primary.focus(); }

        gate.addEventListener('keydown', function (event) {
            if (event.key !== 'Tab') { return; }
            var items = gate.querySelectorAll('a[href], button');
            var first = items[0];
            var last = items[items.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        });
    })();
</script>
