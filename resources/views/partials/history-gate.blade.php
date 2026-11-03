@include('partials.gate-styles')
{{-- Question d'accueil obligatoire : ancien ou nouveau membre, pour reconstituer l'historique de la Fondation. --}}
@php($pendingEntry = auth()->user()->member_origin === 'alumni')

<div class="gate-backdrop" id="history-gate" role="alertdialog" aria-modal="true" aria-labelledby="history-gate-title" aria-describedby="history-gate-text">
    <div class="gate-box">
        <div class="gate-icon gate-icon-info" aria-hidden="true">?</div>
        @if($pendingEntry)
            <h2 class="gate-title" id="history-gate-title">Complète ton arrivée</h2>
            <p class="gate-text" id="history-gate-text">Indique le mandat où tu as rejoint le réseau et ton poste d’arrivée : ta première adhésion sera pré-remplie. Cette étape est nécessaire pour reconstituer l’historique de la Fondation.</p>
            <a href="{{ route('onboarding.history') }}" class="gate-btn" id="history-gate-primary">Continuer</a>
        @else
            <h2 class="gate-title" id="history-gate-title">Ancien ou nouveau membre ?</h2>
            <p class="gate-text" id="history-gate-text">Dis-nous si tu as déjà fait partie du réseau Tunivisions. Ta réponse nous aide à reconstituer l’historique de la Fondation.</p>
            <form method="post" action="{{ route('onboarding.origin') }}" class="gate-choices">
                @csrf
                <button type="submit" name="origin" value="alumni" class="gate-btn" id="history-gate-primary">Je suis un ancien membre</button>
                <button type="submit" name="origin" value="new" class="gate-btn gate-btn-outline">Je suis un nouveau membre</button>
            </form>
        @endif
        <form method="post" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="gate-link">Se déconnecter</button>
        </form>
    </div>
</div>

@include('partials.gate-script', ['gateId' => 'history-gate', 'focusId' => 'history-gate-primary'])
