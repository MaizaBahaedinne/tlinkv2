@if (session()->has('impersonator_id') && auth()->check())
    <div role="status" style="position:fixed;left:50%;bottom:16px;transform:translateX(-50%);z-index:99999;display:flex;align-items:center;gap:12px;flex-wrap:wrap;justify-content:center;max-width:calc(100vw - 24px);padding:10px 16px;border-radius:14px;background:#7d0020;color:#fff;font:600 13px/1.3 system-ui,sans-serif;box-shadow:0 10px 30px rgba(0,0,0,.35)">
        <span>Connecté en tant que <strong>{{ auth()->user()->name }}</strong></span>
        <form method="post" action="{{ route('impersonation.stop') }}" style="margin:0">
            @csrf
            <button type="submit" style="cursor:pointer;border:0;border-radius:9px;background:#fff;color:#7d0020;padding:6px 12px;font:700 13px system-ui,sans-serif">Revenir à mon compte</button>
        </form>
    </div>
@endif
