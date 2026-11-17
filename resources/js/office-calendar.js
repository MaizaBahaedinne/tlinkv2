// Calendrier de « Mon bureau » : filtres par type et accès direct à un jour dans l'agenda.
const root = document.querySelector('[data-cal]');

if (root) {
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const filters = Array.from(root.querySelectorAll('[data-cal-filter]'));
    const agenda = root.querySelector('[data-cal-agenda]');
    const maxChips = 3;
    const active = new Set(filters.map((button) => button.dataset.calFilter));

    function apply() {
        root.querySelectorAll('[data-cal-cell]').forEach((cell) => {
            let shown = 0;
            let total = 0;

            cell.querySelectorAll('[data-cal-chip]').forEach((chip) => {
                if (!active.has(chip.dataset.kind)) {
                    chip.hidden = true;

                    return;
                }

                total += 1;
                chip.hidden = shown >= maxChips;
                shown = Math.min(shown + 1, maxChips);
            });

            cell.querySelectorAll('[data-cal-dot]').forEach((dot) => {
                dot.hidden = !active.has(dot.dataset.kind);
            });

            const more = cell.querySelector('[data-cal-more]');

            if (more) {
                const rest = total - shown;
                more.hidden = rest <= 0;
                more.textContent = `+${rest} autre${rest > 1 ? 's' : ''}`;
            }
        });

        root.querySelectorAll('[data-cal-item]').forEach((item) => {
            item.hidden = !active.has(item.dataset.kind);
        });

        root.querySelectorAll('[data-cal-group]').forEach((group) => {
            group.hidden = !Array.from(group.querySelectorAll('[data-cal-item]')).some((item) => !item.hidden);
        });
    }

    filters.forEach((button) => {
        button.addEventListener('click', () => {
            const kind = button.dataset.calFilter;
            const on = !active.has(kind);

            on ? active.add(kind) : active.delete(kind);
            button.setAttribute('aria-pressed', on ? 'true' : 'false');
            apply();
        });
    });

    function scrollToDay(day, highlight) {
        const group = agenda?.querySelector(`[data-agenda-day="${day}"]`);

        if (!group || group.hidden) {
            return;
        }

        agenda.scrollTo({ top: Math.max(0, group.offsetTop - 4), behavior: highlight && !reduceMotion ? 'smooth' : 'auto' });

        if (highlight) {
            group.classList.add('ring-2', 'ring-tlink-blue');
            setTimeout(() => group.classList.remove('ring-2', 'ring-tlink-blue'), 1600);
        }
    }

    root.querySelectorAll('[data-cal-pick]').forEach((button) => {
        button.addEventListener('click', () => scrollToDay(button.dataset.calPick, true));
    });

    const today = new Date();
    const key = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
    const firstFuture = Array.from(agenda?.querySelectorAll('[data-agenda-day]') ?? []).find((group) => group.dataset.agendaDay >= key);

    if (firstFuture) {
        scrollToDay(firstFuture.dataset.agendaDay, false);
    }

    apply();
}
