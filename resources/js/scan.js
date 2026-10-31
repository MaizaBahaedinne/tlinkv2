import jsQR from 'jsqr';

// Scanner de QR code de présence : la caméra lit le code, puis on ouvre le formulaire d'avis de l'activité.
// Seul un chemin de l'application correspondant à une présence est accepté (pas de redirection vers un site tiers).
const PRESENCE_PATH = /^\/activites\/\d+\/presence\/[A-Za-z0-9]+$/;

const root = document.querySelector('[data-scanner]');

if (root) {
    const video = root.querySelector('[data-scanner-video]');
    const startButton = root.querySelector('[data-scanner-start]');
    const message = root.querySelector('[data-scanner-message]');
    const frame = root.querySelector('[data-scanner-frame]');
    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d', { willReadFrequently: true });
    let stream = null;
    let running = false;

    const say = (text, isError = false) => {
        message.textContent = text;
        message.classList.toggle('text-rose-700', isError);
        message.classList.toggle('text-slate-600', !isError);
    };

    const stop = () => {
        running = false;
        stream?.getTracks().forEach((track) => track.stop());
        stream = null;
    };

    const target = (text) => {
        try {
            const url = new URL(text, window.location.origin);

            return url.origin === window.location.origin && PRESENCE_PATH.test(url.pathname) ? url.pathname : null;
        } catch {
            return null;
        }
    };

    const tick = () => {
        if (!running) return;

        if (video.readyState === video.HAVE_ENOUGH_DATA && video.videoWidth > 0) {
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            context.drawImage(video, 0, 0, canvas.width, canvas.height);
            const image = context.getImageData(0, 0, canvas.width, canvas.height);
            const code = jsQR(image.data, image.width, image.height, { inversionAttempts: 'dontInvert' });

            if (code) {
                const path = target(code.data);

                if (path) {
                    stop();
                    say('QR code reconnu, ouverture du formulaire…');
                    window.location.assign(path);

                    return;
                }

                say('Ce QR code n’est pas un QR code de présence T.Link. Visez celui affiché par les organisateurs.', true);
            }
        }

        requestAnimationFrame(tick);
    };

    const start = async () => {
        if (!navigator.mediaDevices?.getUserMedia) {
            say('Votre navigateur ne permet pas d’utiliser la caméra ici (connexion sécurisée https requise).', true);

            return;
        }

        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
            video.srcObject = stream;
            await video.play();
            frame.classList.remove('hidden');
            startButton.classList.add('hidden');
            say('Placez le QR code dans le cadre.');
            running = true;
            requestAnimationFrame(tick);
        } catch (error) {
            const denied = error && (error.name === 'NotAllowedError' || error.name === 'SecurityError');
            say(denied ? 'Accès à la caméra refusé : autorisez-la dans les réglages de votre navigateur ou de l’application, puis réessayez.' : 'Impossible d’ouvrir la caméra de cet appareil.', true);
        }
    };

    startButton.addEventListener('click', start);
    window.addEventListener('pagehide', stop);
}
