import jsQR from 'jsqr';

// Pointage : la caméra lit le QR code de la carte de membre, le serveur contrôle la carte et enregistre l'entrée ou la sortie.
const root = document.querySelector('[data-checkpoint]');

if (root) {
    const scanUrl = root.getAttribute('data-scan-url');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    const target = root.querySelector('[data-cp-target]');
    const direction = root.querySelector('[data-cp-direction]');
    const video = root.querySelector('[data-cp-video]');
    const frame = root.querySelector('[data-cp-frame]');
    const startButton = root.querySelector('[data-cp-start]');
    const cameraMessage = root.querySelector('[data-cp-camera-message]');
    const manual = root.querySelector('[data-cp-manual]');
    const numberInput = root.querySelector('[data-cp-number]');
    const result = root.querySelector('[data-cp-result]');
    const present = root.querySelector('[data-cp-present]');
    const log = root.querySelector('[data-cp-log]');
    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d', { willReadFrequently: true });

    let stream = null;
    let running = false;
    let busy = false;
    let lastCode = '';
    let lastAt = 0;

    const text = (value) => document.createTextNode(String(value ?? ''));

    const element = (tag, style, ...children) => {
        const node = document.createElement(tag);
        if (style) node.setAttribute('style', style);
        children.forEach((child) => node.append(child));

        return node;
    };

    const beep = (ok) => {
        try {
            const audio = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audio.createOscillator();
            oscillator.frequency.value = ok ? 880 : 220;
            oscillator.connect(audio.destination);
            oscillator.start();
            oscillator.stop(audio.currentTime + (ok ? 0.12 : 0.35));
        } catch {
            // Le son est un confort : sans lui, le résultat reste affiché.
        }
        if (navigator.vibrate) navigator.vibrate(ok ? 60 : [120, 60, 120]);
    };

    const show = (data) => {
        const ok = data.ok === true;
        const color = ok ? (data.direction === 'in' ? '#15803d' : '#475569') : '#be123c';
        const box = element('div', `border-radius:16px;padding:16px;background:${ok ? '#f0fdf4' : '#fff1f2'};border:1px solid ${ok ? '#bbf7d0' : '#fecdd3'}`);

        box.append(element('div', `font-size:18px;font-weight:700;color:${color}`, text(data.message ?? (ok ? 'Pointage enregistré.' : 'Refusé.'))));

        if (data.member) {
            box.append(
                element('div', 'margin-top:8px;font-size:16px;font-weight:600', text(data.member.name)),
                element('div', 'font-size:13px;color:#64748b', text(`${data.member.entity} · ${data.member.number}${data.member.mandate ? ` · mandat ${data.member.mandate}` : ''}`)),
            );
        }

        if (ok && data.target) box.append(element('div', 'margin-top:8px;font-size:13px', text(`${data.target} · ${data.at}`)));
        if (data.note) box.append(element('div', 'margin-top:6px;font-size:13px;font-weight:600;color:#475569', text(data.note)));

        result.replaceChildren(box);

        if (ok && typeof data.present === 'number') present.textContent = `${data.present} personne(s) actuellement présente(s) sur cette cible.`;

        if (ok && !data.duplicate) {
            const item = element('li', 'display:flex;justify-content:space-between;gap:8px', text(`${data.member.name}`), element('span', `font-weight:600;color:${color}`, text(`${data.direction === 'in' ? 'Entrée' : 'Sortie'} · ${data.at}`)));
            log.prepend(item);
            while (log.children.length > 12) log.lastElementChild.remove();
        }

        beep(ok);
    };

    const send = async (fields) => {
        if (busy) return;
        busy = true;

        try {
            const response = await fetch(scanUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                body: JSON.stringify({ target: target.value, direction: direction.value, ...fields }),
            });
            const data = await response.json().catch(() => ({ ok: false, message: 'Réponse inattendue du serveur.' }));

            show(response.ok ? data : { ok: false, message: data.message ?? 'Pointage refusé.', member: data.member });
        } catch {
            show({ ok: false, message: 'Connexion impossible : le pointage n’a pas été enregistré.' });
        } finally {
            busy = false;
        }
    };

    const stop = () => {
        running = false;
        stream?.getTracks().forEach((track) => track.stop());
        stream = null;
    };

    const tick = () => {
        if (!running) return;

        if (video.readyState === video.HAVE_ENOUGH_DATA && video.videoWidth > 0 && !busy) {
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            context.drawImage(video, 0, 0, canvas.width, canvas.height);
            const image = context.getImageData(0, 0, canvas.width, canvas.height);
            const code = jsQR(image.data, image.width, image.height, { inversionAttempts: 'dontInvert' });

            // Un même QR code resté devant la caméra n'est pas renvoyé en boucle.
            if (code && (code.data !== lastCode || Date.now() - lastAt > 4000)) {
                lastCode = code.data;
                lastAt = Date.now();
                send({ code: code.data });
            }
        }

        requestAnimationFrame(tick);
    };

    startButton.addEventListener('click', async () => {
        if (!navigator.mediaDevices?.getUserMedia) {
            cameraMessage.textContent = 'Caméra indisponible ici (connexion sécurisée https requise). Utilisez la saisie du numéro.';

            return;
        }

        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
            video.srcObject = stream;
            await video.play();
            frame.hidden = false;
            startButton.hidden = true;
            cameraMessage.textContent = 'Présentez le QR code de la carte devant la caméra.';
            running = true;
            requestAnimationFrame(tick);
        } catch {
            cameraMessage.textContent = 'Accès à la caméra refusé ou impossible : autorisez-la, ou utilisez la saisie du numéro.';
        }
    });

    manual.addEventListener('submit', (event) => {
        event.preventDefault();
        const number = numberInput.value.trim();

        if (number !== '') {
            send({ number });
            numberInput.value = '';
        }
    });

    // Changer de cible repart d'un résultat vierge.
    target.addEventListener('change', () => {
        present.textContent = '';
        result.replaceChildren(element('p', 'margin:0;font-size:14px;color:#64748b', text('En attente d’un scan…')));
    });

    window.addEventListener('pagehide', stop);
}
