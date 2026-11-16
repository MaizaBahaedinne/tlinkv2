// Parrainage : QR code d'un poste (généré à l'ouverture du panneau), copie du lien et partage natif du téléphone.
import QRCode from 'qrcode';

document.querySelectorAll('[data-sponsor-panel]').forEach((panel) => {
    panel.addEventListener('toggle', async () => {
        const holder = panel.querySelector('[data-sponsor-qr]');

        if (!panel.open || !holder || holder.dataset.ready) {
            return;
        }

        holder.dataset.ready = '1';

        try {
            const url = holder.getAttribute('data-sponsor-qr');
            const image = await QRCode.toDataURL(url, { width: 480, margin: 2, errorCorrectionLevel: 'M', color: { dark: '#0f172a', light: '#ffffff' } });
            holder.innerHTML = '';
            const img = document.createElement('img');
            img.src = image;
            img.alt = holder.getAttribute('data-alt') ?? 'QR code';
            img.className = 'h-full w-full';
            holder.appendChild(img);

            const download = panel.querySelector('[data-sponsor-download]');
            if (download) {
                download.href = image;
                download.hidden = false;
            }
        } catch {
            holder.textContent = 'QR code indisponible : utilisez le lien.';
        }
    });
});

document.addEventListener('click', async (event) => {
    const copy = event.target.closest('[data-sponsor-copy]');
    const share = event.target.closest('[data-sponsor-share]');

    if (copy) {
        const input = document.getElementById(copy.getAttribute('data-sponsor-copy'));
        const label = copy.textContent;

        try {
            await navigator.clipboard.writeText(input.value);
        } catch {
            input.select();
            document.execCommand('copy');
        }

        copy.textContent = 'Lien copié';
        setTimeout(() => { copy.textContent = label; }, 1800);
    }

    if (share) {
        try {
            await navigator.share({ title: share.getAttribute('data-title'), text: share.getAttribute('data-text'), url: share.getAttribute('data-url') });
        } catch {
            // partage annulé
        }
    }
});

if (!navigator.share) {
    document.querySelectorAll('[data-sponsor-share]').forEach((button) => { button.hidden = true; });
}
