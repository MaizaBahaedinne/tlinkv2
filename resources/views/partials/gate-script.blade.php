{{-- Rend le reste de la page inerte et garde le focus dans l'alerte bloquante. Variables : $gateId, $focusId. --}}
<script>
    (function () {
        var gate = document.getElementById(@json($gateId));
        if (!gate) { return; }

        document.body.style.overflow = 'hidden';

        // Tout le reste de la page est inerte : ni clic, ni clavier, ni lecteur d'écran.
        Array.prototype.forEach.call(document.body.children, function (el) {
            if (el === gate || el.tagName === 'SCRIPT' || el.tagName === 'STYLE' || el.hasAttribute('data-gate-exempt')) { return; }
            el.setAttribute('inert', '');
        });

        var primary = document.getElementById(@json($focusId));
        if (primary) { primary.focus(); }

        gate.addEventListener('keydown', function (event) {
            if (event.key !== 'Tab') { return; }
            var items = gate.querySelectorAll('a[href], button');
            var first = items[0];
            var last = items[items.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        });
    })();
</script>
