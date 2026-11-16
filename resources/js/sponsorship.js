// Parrainage : QR code du poste choisi, copie du lien, partage natif, et recherche dans l'organigramme horizontal.
import QRCode from 'qrcode';

document.querySelectorAll('[data-sponsor-qr]').forEach(async (holder) => {
    try {
        const image = await QRCode.toDataURL(holder.getAttribute('data-sponsor-qr'), { width: 480, margin: 2, errorCorrectionLevel: 'M', color: { dark: '#0f172a', light: '#ffffff' } });
        holder.innerHTML = '';
        const img = document.createElement('img');
        img.src = image;
        img.alt = holder.getAttribute('data-alt') ?? 'QR code';
        img.className = 'h-full w-full';
        holder.appendChild(img);

        const download = holder.closest('[data-sponsor-panel]')?.querySelector('[data-sponsor-download]');
        if (download) {
            download.href = image;
            download.hidden = false;
        }
    } catch {
        holder.textContent = 'QR code indisponible : utilisez le lien.';
    }
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

// Recherche : les postes qui ne correspondent pas sont estompés (l'arbre reste lisible), le premier résultat est centré.
const search = document.querySelector('[data-sp-search]');
const onlyOpen = document.querySelector('[data-sp-open]');
const counter = document.querySelector('[data-sp-count]');

if (search) {
    const cards = [...document.querySelectorAll('[data-sp-node]')];

    const apply = (scroll) => {
        const term = search.value.trim().toLowerCase();
        const filtering = term !== '' || onlyOpen?.checked;
        let first = null;
        let hits = 0;

        cards.forEach((card) => {
            const match = (term === '' || card.dataset.search.includes(term)) && (!onlyOpen?.checked || card.dataset.open === '1');
            card.classList.toggle('sp-dim', filtering && !match);
            card.classList.toggle('sp-hit', filtering && match);

            if (filtering && match) {
                hits += 1;
                first ??= card;
            }
        });

        counter.textContent = filtering ? `${hits} poste${hits > 1 ? 's' : ''} trouvé${hits > 1 ? 's' : ''}` : '';

        if (scroll && first) {
            first.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' });
        }
    };

    search.addEventListener('input', () => apply(true));
    onlyOpen?.addEventListener('change', () => apply(true));
}
