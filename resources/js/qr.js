// Génère les QR codes des pages d'administration : présence d'une activité, lien d'inscription à un poste.
// Un élément [data-qr="texte"] est rendu au chargement ; pour un contenu choisi après coup (fenêtre partagée),
// on envoie l'événement « qr:render » avec { el, text, label }.
import QRCode from 'qrcode';

function render(el, text, label) {
    return QRCode.toString(text ?? '', { type: 'svg', margin: 2, errorCorrectionLevel: 'M', color: { dark: '#0f172a', light: '#ffffff' } })
        .then((svg) => {
            el.innerHTML = svg;
            const node = el.querySelector('svg');
            if (node) {
                node.setAttribute('role', 'img');
                node.setAttribute('aria-label', label ?? 'QR code de présence');
                node.style.width = '100%';
                node.style.height = 'auto';
            }
        })
        .catch(() => {
            el.textContent = 'QR code indisponible : utilisez le lien.';
        });
}

document.querySelectorAll('[data-qr]').forEach((el) => {
    render(el, el.getAttribute('data-qr'), el.getAttribute('data-qr-label') ?? undefined);
});

document.addEventListener('qr:render', (event) => {
    const { el, text, label } = event.detail ?? {};

    if (el) {
        render(el, text, label);
    }
});
