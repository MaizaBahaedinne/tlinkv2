// Messages vocaux de la messagerie : lecteur audio compact et enregistreur (MediaRecorder), partagés par la page et la mini-fenêtre.

export const MAX_SECONDS = 300;

const EXTENSIONS = { 'audio/webm': 'webm', 'video/webm': 'webm', 'audio/ogg': 'ogg', 'audio/mp4': 'm4a', 'video/mp4': 'm4a' };

function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) { node.className = className; }
    if (text !== undefined && text !== null) { node.textContent = text; }
    return node;
}

function icon(classes) {
    const node = el('i', classes);
    node.setAttribute('aria-hidden', 'true');
    return node;
}

export function formatDuration(total) {
    const seconds = Math.max(0, Math.round(total || 0));
    return Math.floor(seconds / 60) + ':' + String(seconds % 60).padStart(2, '0');
}

export function voiceSupported() {
    return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia && window.MediaRecorder);
}

// ───────────── Lecteur ─────────────

let playing = null;

/** Lecteur d'un message vocal ; `mine` adapte les couleurs à la bulle (bleue pour mes messages). */
export function audioPlayer(url, seconds, mine) {
    const wrap = el('div', 'flex min-w-[10rem] items-center gap-2');
    const button = el('button', 'grid h-8 w-8 shrink-0 place-items-center rounded-full ' + (mine ? 'bg-white/20 text-white' : 'bg-slate-100 text-tlink-blue'));
    button.type = 'button';
    button.setAttribute('aria-label', 'Écouter le message vocal');
    const glyph = icon('fa-solid fa-play text-xs');
    button.appendChild(glyph);

    const track = el('div', 'relative h-1.5 flex-1 overflow-hidden rounded-full ' + (mine ? 'bg-white/30' : 'bg-slate-200'));
    const fill = el('div', 'absolute inset-y-0 left-0 rounded-full ' + (mine ? 'bg-white' : 'bg-tlink-blue'));
    fill.style.width = '0%';
    track.appendChild(fill);
    const time = el('span', 'w-9 shrink-0 text-right text-[11px] tabular-nums ' + (mine ? 'text-white/80' : 'text-slate-500'), formatDuration(seconds));

    let audio = null;

    function reset() {
        glyph.className = 'fa-solid fa-play text-xs';
        button.setAttribute('aria-label', 'Écouter le message vocal');
        fill.style.width = '0%';
        time.textContent = formatDuration(seconds);
    }

    function ensure() {
        if (audio) { return audio; }
        audio = new Audio();
        audio.preload = 'none';
        audio.src = url;
        audio.addEventListener('timeupdate', function () {
            const total = Number.isFinite(audio.duration) && audio.duration > 0 ? audio.duration : seconds;
            fill.style.width = Math.min(100, (audio.currentTime / Math.max(total, 1)) * 100) + '%';
            time.textContent = formatDuration(audio.currentTime);
        });
        audio.addEventListener('pause', function () {
            glyph.className = 'fa-solid fa-play text-xs';
            button.setAttribute('aria-label', 'Écouter le message vocal');
        });
        audio.addEventListener('play', function () {
            glyph.className = 'fa-solid fa-pause text-xs';
            button.setAttribute('aria-label', 'Mettre en pause');
        });
        audio.addEventListener('ended', reset);
        audio.addEventListener('error', function () { time.textContent = 'Erreur'; });

        return audio;
    }

    button.addEventListener('click', function () {
        const media = ensure();

        if (!media.paused) {
            media.pause();
            return;
        }

        if (playing && playing !== media) { playing.pause(); }
        playing = media;
        media.play().catch(function () { time.textContent = 'Erreur'; });
    });

    track.addEventListener('click', function (event) {
        if (!audio || !Number.isFinite(audio.duration) || audio.duration <= 0) { return; }
        const rect = track.getBoundingClientRect();
        audio.currentTime = audio.duration * Math.min(1, Math.max(0, (event.clientX - rect.left) / rect.width));
    });

    [button, track, time].forEach(function (child) { wrap.appendChild(child); });

    return wrap;
}

// ───────────── Enregistreur ─────────────

function pickMime() {
    const candidates = ['audio/webm;codecs=opus', 'audio/mp4', 'audio/ogg;codecs=opus', 'audio/webm'];

    return candidates.find(function (type) { return window.MediaRecorder.isTypeSupported && window.MediaRecorder.isTypeSupported(type); }) || '';
}

function startRecording(onTick, onLimit) {
    return navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
        const mime = pickMime();
        const recorder = mime ? new MediaRecorder(stream, { mimeType: mime }) : new MediaRecorder(stream);
        const chunks = [];
        const startedAt = Date.now();

        recorder.addEventListener('dataavailable', function (event) { if (event.data && event.data.size) { chunks.push(event.data); } });
        recorder.start();

        const timer = setInterval(function () {
            const elapsed = Math.floor((Date.now() - startedAt) / 1000);
            onTick(elapsed);
            if (elapsed >= MAX_SECONDS) { onLimit(); }
        }, 250);

        function release() {
            clearInterval(timer);
            stream.getTracks().forEach(function (track) { track.stop(); });
        }

        return {
            stop: function () {
                return new Promise(function (resolve) {
                    recorder.addEventListener('stop', function () {
                        release();
                        const type = String(recorder.mimeType || mime || 'audio/webm').split(';')[0];
                        resolve({ blob: new Blob(chunks, { type: type }), type: type, seconds: Math.min(MAX_SECONDS, Math.max(1, Math.round((Date.now() - startedAt) / 1000))) });
                    });
                    if (recorder.state !== 'inactive') { recorder.stop(); } else { release(); resolve(null); }
                });
            },
            cancel: function () {
                release();
                if (recorder.state !== 'inactive') { recorder.stop(); }
            },
        };
    });
}

/**
 * Bouton micro et barre d'enregistrement. `onSend(file, seconds)` reçoit le message vocal terminé,
 * `onToggle(recording)` permet de masquer le champ de saisie pendant l'enregistrement.
 */
export function voiceRecorder(options) {
    const compact = !!options.compact;
    const mic = el('button', 'grid shrink-0 place-items-center text-slate-500 hover:bg-slate-100 ' + (compact ? 'h-9 w-9 rounded-full' : 'h-11 w-11 rounded-xl'));
    mic.type = 'button';
    mic.setAttribute('aria-label', 'Enregistrer un message vocal');
    mic.appendChild(icon('fa-solid fa-microphone'));
    mic.hidden = !voiceSupported();

    const bar = el('div', 'flex items-center gap-3 rounded-2xl bg-rose-50 px-3 ' + (compact ? 'h-9' : 'h-11'));
    bar.hidden = true;
    const dot = el('span', 'h-2.5 w-2.5 animate-pulse rounded-full bg-red-600');
    const clock = el('span', 'flex-1 text-sm font-semibold tabular-nums text-red-700', '0:00');
    const cancel = el('button', 'text-slate-500 hover:text-red-600');
    cancel.type = 'button';
    cancel.setAttribute('aria-label', 'Annuler l’enregistrement');
    cancel.appendChild(icon('fa-regular fa-trash-can'));
    const send = el('button', 'grid h-8 w-8 place-items-center rounded-full bg-tlink-blue text-white hover:opacity-90');
    send.type = 'button';
    send.setAttribute('aria-label', 'Envoyer le message vocal');
    send.appendChild(icon('fa-solid fa-paper-plane text-xs'));
    [dot, clock, cancel, send].forEach(function (child) { bar.appendChild(child); });

    let session = null;
    let starting = false;

    function close() {
        session = null;
        bar.hidden = true;
        mic.hidden = !voiceSupported();
        options.onToggle(false);
    }

    function finish() {
        const current = session;
        if (!current) { return; }
        current.stop().then(function (result) {
            close();
            if (!result || result.blob.size === 0) { return options.onError('Enregistrement vide, réessayez.'); }
            const extension = EXTENSIONS[result.type] || 'webm';
            options.onSend(new File([result.blob], 'vocal.' + extension, { type: result.type }), result.seconds);
        });
    }

    mic.addEventListener('click', function () {
        if (session || starting) { return; }
        starting = true;
        options.onError('');
        startRecording(function (elapsed) { clock.textContent = formatDuration(elapsed) + ' / ' + formatDuration(MAX_SECONDS); }, finish).then(function (started) {
            starting = false;
            session = started;
            clock.textContent = '0:00 / ' + formatDuration(MAX_SECONDS);
            bar.hidden = false;
            options.onToggle(true);
        }).catch(function (error) {
            starting = false;
            options.onError(error && error.name === 'NotAllowedError'
                ? 'Autorisez le micro dans votre navigateur ou dans les réglages de l’application pour enregistrer un message vocal.'
                : 'Micro indisponible sur cet appareil.');
        });
    });

    cancel.addEventListener('click', function () {
        if (session) { session.cancel(); }
        close();
    });
    send.addEventListener('click', finish);

    return { mic: mic, bar: bar };
}
