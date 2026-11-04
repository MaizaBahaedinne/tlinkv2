@if (auth()->check() && ($simulatedNode = \App\Support\RoleSimulator::nodeFor(auth()->user())))
    <div data-gate-exempt role="status" style="position:fixed;left:50%;bottom:16px;transform:translateX(-50%);z-index:99999;display:flex;align-items:center;gap:12px;flex-wrap:wrap;justify-content:center;max-width:calc(100vw - 24px);padding:10px 16px;border-radius:14px;background:#1f2a44;color:#fff;font:600 13px/1.3 system-ui,sans-serif;box-shadow:0 10px 30px rgba(0,0,0,.35)">
        <span>Simulation de rôle : vous agissez comme <strong>{{ $simulatedNode->title }}</strong></span>
        <form method="post" action="{{ route('simulation.stop') }}" style="margin:0">
            @csrf
            <button type="submit" style="cursor:pointer;border:0;border-radius:9px;background:#fff;color:#1f2a44;padding:6px 12px;font:700 13px system-ui,sans-serif">Quitter la simulation</button>
        </form>
    </div>
@endif
