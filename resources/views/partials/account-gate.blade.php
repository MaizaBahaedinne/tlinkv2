{{-- Compte non validé : alerte bloquante (style SweetAlert) tant que l'info session et le quiz ne sont pas réussis. --}}
@include('partials.gate-styles')

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

@include('partials.gate-script', ['gateId' => 'account-gate', 'focusId' => 'gate-primary'])
