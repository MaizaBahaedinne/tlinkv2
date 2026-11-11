// Carte de membre : le QR code est demandé au serveur et renouvelé toutes les 30 secondes (une capture d'écran devient vite inutile).
import QRCode from 'qrcode';

document.querySelectorAll('[data-card]').forEach((card) => {
    const url = card.getAttribute('data-code-url');
    const qr = card.querySelector('[data-card-qr]');
    const bar = card.querySelector('[data-card-bar]');
    const status = card.querySelector('[data-card-status]');
    let timer = null;

    const say = (text) => {
        status.textContent = text;
    };

    const animate = (seconds) => {
        if (!bar) return;
        bar.style.transition = 'none';
        bar.style.transform = 'scaleX(1)';
        void bar.offsetWidth;
        bar.style.transition = `transform ${seconds}s linear`;
        bar.style.transform = 'scaleX(0)';
    };

    const draw = async (code) => {
        const svg = await QRCode.toString(code, { type: 'svg', margin: 0, errorCorrectionLevel: 'M', color: { dark: '#0f172a', light: '#ffffff' } });
        qr.innerHTML = svg;
        const node = qr.querySelector('svg');
        if (node) {
            node.setAttribute('role', 'img');
            node.setAttribute('aria-label', 'QR code de votre carte de membre');
            node.style.width = '100%';
            node.style.height = 'auto';
        }
    };

    const refresh = async () => {
        clearTimeout(timer);

        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' });
            const data = await response.json();

            if (!data.valid) {
                qr.textContent = data.message ?? 'Carte indisponible.';
                say(data.message ?? 'Carte indisponible.');

                return;
            }

            await draw(data.code);
            say('Code actualisé automatiquement.');
            animate(data.refresh_in);
            timer = setTimeout(refresh, Math.max(3, data.refresh_in - 2) * 1000);
        } catch {
            say('Connexion perdue : le code affiché peut être périmé. Reconnectez-vous pour l’actualiser.');
            timer = setTimeout(refresh, 8000);
        }
    };

    refresh();
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) refresh();
    });
});
