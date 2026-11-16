// Lecteur de guides d'utilisation : lecture automatique pas à pas, repère animé, suivi de la progression.
const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

document.querySelectorAll('[data-guide-root]').forEach(initGuide);

function initGuide(root) {
    const player = root.querySelector('[data-guide-player]');
    const textPanel = root.querySelector('[data-guide-text]');

    if (!player) {
        return;
    }

    const steps = Array.from(player.querySelectorAll('[data-step]'));
    const stage = player.querySelector('[data-guide-stage]');
    const segmentsBox = player.querySelector('[data-guide-segments]');
    const counter = player.querySelector('[data-guide-counter]');
    const doneBox = player.querySelector('[data-guide-finished]');
    const prevButton = player.querySelector('[data-guide-prev]');
    const nextButton = player.querySelector('[data-guide-next]');
    const toggleButton = player.querySelector('[data-guide-toggle]');
    const tabs = Array.from(root.querySelectorAll('[data-guide-tab]'));
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const progressUrl = player.dataset.progressUrl;
    const last = steps.length - 1;

    if (steps.length === 0) {
        return;
    }

    let index = Math.min(Math.max(parseInt(player.dataset.start || '0', 10) || 0, 0), last);
    let playing = !reduceMotion;
    let finished = false;
    let saveTimer = null;

    // Segments de la barre de progression : un bouton par étape, cliquable.
    const segments = steps.map((step, position) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'relative h-2 flex-1 overflow-hidden rounded-full bg-slate-200 outline-none focus-visible:ring-2 focus-visible:ring-slate-400';
        button.setAttribute('aria-label', `Aller à l’étape ${position + 1} : ${step.dataset.title ?? ''}`);
        button.addEventListener('click', () => show(position));
        segmentsBox.appendChild(button);

        return button;
    });

    function save(completed) {
        if (!progressUrl) {
            return;
        }

        clearTimeout(saveTimer);
        const send = () => fetch(progressUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ step: index, completed }),
            credentials: 'same-origin',
        }).catch(() => {});

        if (completed) {
            send();
        } else {
            saveTimer = setTimeout(send, 800);
        }
    }

    function syncPause() {
        player.classList.toggle('is-paused', !playing || document.hidden);

        if (toggleButton) {
            toggleButton.setAttribute('aria-label', playing ? 'Mettre en pause' : 'Lancer la lecture');
            toggleButton.querySelector('i').className = playing ? 'fa-solid fa-pause' : 'fa-solid fa-play';
        }
    }

    function paintSegments() {
        segments.forEach((segment, position) => {
            segment.replaceChildren();
            const fill = document.createElement('span');
            fill.className = 'absolute inset-y-0 left-0 rounded-full ' + (player.dataset.bar || 'bg-rose-500');

            if (finished || position < index) {
                fill.style.width = '100%';
            } else if (position === index && !reduceMotion) {
                fill.classList.add('guide-fill');
                fill.style.setProperty('--d', (parseInt(steps[position].dataset.duration || '7', 10) || 7) + 's');
                fill.addEventListener('animationend', next);
            } else {
                fill.style.width = '0';
            }

            segment.appendChild(fill);
            segment.setAttribute('aria-current', position === index && !finished ? 'step' : 'false');
        });
    }

    function show(position) {
        finished = false;
        index = Math.min(Math.max(position, 0), last);
        stage.hidden = false;
        doneBox.hidden = true;
        steps.forEach((step, i) => { step.hidden = i !== index; });
        counter.textContent = `Étape ${index + 1} sur ${steps.length}`;
        prevButton.disabled = index === 0;
        nextButton.querySelector('span').textContent = index === last ? 'Terminer' : 'Suivant';
        paintSegments();
        syncPause();
        save(false);
    }

    function finish() {
        finished = true;
        playing = false;
        stage.hidden = true;
        doneBox.hidden = false;
        steps.forEach((step) => { step.hidden = true; });
        counter.textContent = 'Guide terminé';
        paintSegments();
        syncPause();
        save(true);
    }

    function next() {
        if (finished) {
            return;
        }

        index < last ? show(index + 1) : finish();
    }

    function previous() {
        finished ? show(last) : show(index - 1);
    }

    function restart() {
        playing = !reduceMotion;
        show(0);
    }

    prevButton.addEventListener('click', previous);
    nextButton.addEventListener('click', next);
    if (reduceMotion) {
        toggleButton?.classList.add('hidden');
    }
    toggleButton?.addEventListener('click', () => {
        playing = !playing;
        syncPause();
    });
    player.querySelector('[data-guide-restart]')?.addEventListener('click', restart);
    document.addEventListener('visibilitychange', syncPause);

    document.addEventListener('keydown', (event) => {
        const target = event.target;

        if (player.offsetParent === null || (target && /^(INPUT|TEXTAREA|SELECT)$/.test(target.tagName)) || event.metaKey || event.ctrlKey || event.altKey) {
            return;
        }

        if (event.key === 'ArrowRight') {
            event.preventDefault();
            next();
        } else if (event.key === 'ArrowLeft') {
            event.preventDefault();
            previous();
        } else if (event.key === ' ' && (target === document.body || target === player) && toggleButton && !finished) {
            event.preventDefault();
            toggleButton.click();
        }
    });

    // Onglets : guide animé / notice écrite (la lecture se met en pause sur la notice).
    let resumeAfterText = false;

    function selectTab(name) {
        const text = name === 'text';

        tabs.forEach((tab) => {
            const active = tab.dataset.guideTab === name;
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
            tab.classList.toggle('bg-white', active);
            tab.classList.toggle('shadow-sm', active);
            tab.classList.toggle('text-slate-900', active);
            tab.classList.toggle('text-slate-500', !active);
        });

        player.classList.toggle('hidden', text);
        textPanel?.classList.toggle('hidden', !text);

        if (text) {
            resumeAfterText = playing;
            playing = false;
        } else if (resumeAfterText && !finished) {
            playing = true;
        }

        syncPause();
    }

    tabs.forEach((tab) => tab.addEventListener('click', () => selectTab(tab.dataset.guideTab)));
    root.querySelector('[data-guide-tabs]')?.classList.remove('hidden');
    root.querySelectorAll('[data-guide-print]').forEach((button) => button.addEventListener('click', () => window.print()));

    // Sans JavaScript, la notice écrite reste affichée ; avec JavaScript, on ouvre le guide animé.
    player.classList.remove('hidden');
    textPanel?.classList.add('hidden');
    show(index);
}
