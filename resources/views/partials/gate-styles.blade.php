{{-- Styles partagés des alertes bloquantes (compte non validé, question d'accueil). --}}
<style>
    .gate-backdrop { position: fixed; inset: 0; z-index: 9000; display: flex; align-items: center; justify-content: center; padding: 16px; background: rgba(15, 23, 42, .62); backdrop-filter: blur(3px); animation: gate-fade .2s ease-out; }
    .gate-box { width: 100%; max-width: 430px; padding: 32px 28px 24px; border-radius: 22px; background: #fff; text-align: center; box-shadow: 0 30px 70px rgba(15, 23, 42, .35); animation: gate-pop .25s ease-out; }
    .gate-icon { display: grid; place-items: center; width: 76px; height: 76px; margin: 0 auto 18px; border: 4px solid #fde68a; border-radius: 999px; color: #d97706; font: 700 38px/1 system-ui, sans-serif; }
    .gate-icon-info { border-color: #bae6fd; color: #0369a1; }
    .gate-title { margin: 0 0 10px; color: #0f172a; font: 700 24px/1.2 var(--font-display, system-ui), sans-serif; }
    .gate-text { margin: 0 0 22px; color: #475569; font-size: 15px; line-height: 1.55; }
    .gate-btn { display: inline-block; width: 100%; padding: 12px 18px; border: 0; border-radius: 12px; background: #d00031; color: #fff; font: 700 15px system-ui, sans-serif; text-decoration: none; cursor: pointer; }
    .gate-btn:hover { background: #a30027; }
    .gate-btn-outline { background: #fff; color: #0f172a; box-shadow: inset 0 0 0 2px #cbd5e1; }
    .gate-btn-outline:hover { background: #f8fafc; }
    .gate-choices { display: grid; gap: 10px; margin: 0 0 4px; }
    .gate-link { margin-top: 14px; padding: 6px; border: 0; background: none; color: #64748b; font: 600 13px system-ui, sans-serif; text-decoration: underline; cursor: pointer; }
    @keyframes gate-fade { from { opacity: 0; } to { opacity: 1; } }
    @keyframes gate-pop { from { opacity: 0; transform: scale(.92); } to { opacity: 1; transform: scale(1); } }
</style>
