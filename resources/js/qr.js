// Génère les QR codes de présence des activités (page imprimable côté organisateurs).
import QRCode from 'qrcode';

document.querySelectorAll('[data-qr]').forEach((el) => {
    QRCode.toString(el.getAttribute('data-qr') ?? '', { type: 'svg', margin: 2, errorCorrectionLevel: 'M', color: { dark: '#0f172a', light: '#ffffff' } })
        .then((svg) => {
            el.innerHTML = svg;
            const node = el.querySelector('svg');
            if (node) {
                node.setAttribute('role', 'img');
                node.setAttribute('aria-label', 'QR code de présence');
                node.style.width = '100%';
                node.style.height = 'auto';
            }
        })
        .catch(() => {
            el.textContent = 'QR code indisponible : utilisez le lien ci-dessous.';
        });
});
