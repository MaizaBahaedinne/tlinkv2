/*
 * Recadrage d'avatar pour l'administration.
 * L'image choisie est recadree (zone carree, zoom, deplacement) dans le navigateur ;
 * le resultat (JPEG 512x512) est place dans le champ fichier du formulaire et envoye avec lui.
 */
(function () {
    'use strict';

    var OUTPUT_SIZE = 512;
    var MAX_ZOOM = 3;

    document.querySelectorAll('[data-avatar-field]').forEach(init);

    function init(field) {
        var source = field.querySelector('[data-avatar-source]');
        var output = field.querySelector('[data-avatar-output]');
        var pick = field.querySelector('[data-avatar-pick]');
        var removeBox = field.querySelector('[data-avatar-remove]');
        var photo = field.querySelector('[data-avatar-photo]');
        var initials = field.querySelector('[data-avatar-initials]');
        var dialog = field.querySelector('[data-avatar-dialog]');
        var stage = field.querySelector('[data-avatar-stage]');
        var image = field.querySelector('[data-avatar-image]');
        var zoomInput = field.querySelector('[data-avatar-zoom]');
        var applyBtn = field.querySelector('[data-avatar-apply]');
        var cancelBtn = field.querySelector('[data-avatar-cancel]');
        var error = field.querySelector('[data-avatar-error]');

        if (!source || !output || !pick || !dialog || !stage || !image || !zoomInput) {
            return;
        }

        var state = { zoom: 1, x: 0, y: 0, dragging: false, lastX: 0, lastY: 0, url: null };

        function stageSize() {
            return stage.getBoundingClientRect().width;
        }

        function scaledSize() {
            var base = Math.max(stageSize() / image.naturalWidth, stageSize() / image.naturalHeight);
            var scale = base * state.zoom;

            return { w: image.naturalWidth * scale, h: image.naturalHeight * scale };
        }

        // L'image doit toujours recouvrir entierement la zone de recadrage.
        function clamp() {
            var size = scaledSize();
            var maxX = Math.max(0, (size.w - stageSize()) / 2);
            var maxY = Math.max(0, (size.h - stageSize()) / 2);

            state.x = Math.min(maxX, Math.max(-maxX, state.x));
            state.y = Math.min(maxY, Math.max(-maxY, state.y));
        }

        function render() {
            if (!image.naturalWidth) {
                return;
            }

            clamp();
            var size = scaledSize();
            image.style.width = size.w + 'px';
            image.style.height = size.h + 'px';
            image.style.transform = 'translate(-50%, -50%) translate(' + state.x + 'px, ' + state.y + 'px)';
        }

        function showError(message) {
            if (error) {
                error.textContent = message || '';
                error.hidden = !message;
            }
        }

        function release() {
            if (state.url) {
                URL.revokeObjectURL(state.url);
                state.url = null;
            }
        }

        function close() {
            if (typeof dialog.close === 'function') {
                dialog.close();
            }
            release();
        }

        pick.addEventListener('click', function () {
            source.value = '';
            source.click();
        });

        source.addEventListener('change', function () {
            var file = source.files && source.files[0];

            if (!file) {
                return;
            }

            if (!/^image\/(jpeg|png|webp|gif)$/.test(file.type)) {
                showError('Choisissez une image JPEG, PNG ou WebP.');
                return;
            }

            showError('');
            release();
            state.url = URL.createObjectURL(file);
            state.zoom = 1;
            state.x = 0;
            state.y = 0;
            zoomInput.value = '1';
            image.onload = function () {
                render();
            };
            image.src = state.url;

            if (typeof dialog.showModal === 'function') {
                dialog.showModal();
            }
        });

        zoomInput.max = String(MAX_ZOOM);
        zoomInput.addEventListener('input', function () {
            state.zoom = Number(zoomInput.value);
            render();
        });

        stage.addEventListener('wheel', function (event) {
            event.preventDefault();
            var next = Math.min(MAX_ZOOM, Math.max(1, state.zoom - event.deltaY * 0.002));
            state.zoom = next;
            zoomInput.value = String(next);
            render();
        }, { passive: false });

        stage.addEventListener('pointerdown', function (event) {
            state.dragging = true;
            state.lastX = event.clientX;
            state.lastY = event.clientY;
            stage.setPointerCapture(event.pointerId);
        });

        stage.addEventListener('pointermove', function (event) {
            if (!state.dragging) {
                return;
            }

            state.x += event.clientX - state.lastX;
            state.y += event.clientY - state.lastY;
            state.lastX = event.clientX;
            state.lastY = event.clientY;
            render();
        });

        ['pointerup', 'pointercancel'].forEach(function (name) {
            stage.addEventListener(name, function () {
                state.dragging = false;
            });
        });

        cancelBtn && cancelBtn.addEventListener('click', close);
        dialog.addEventListener('cancel', release);

        applyBtn && applyBtn.addEventListener('click', function () {
            if (!image.naturalWidth) {
                return;
            }

            var size = stageSize();
            var scaled = scaledSize();
            var canvas = document.createElement('canvas');
            canvas.width = OUTPUT_SIZE;
            canvas.height = OUTPUT_SIZE;

            var ctx = canvas.getContext('2d');
            var ratio = OUTPUT_SIZE / size;

            // Fond blanc pour les PNG / WebP transparents (sortie JPEG).
            ctx.fillStyle = '#fff';
            ctx.fillRect(0, 0, OUTPUT_SIZE, OUTPUT_SIZE);
            ctx.drawImage(
                image,
                (size / 2 - scaled.w / 2 + state.x) * ratio,
                (size / 2 - scaled.h / 2 + state.y) * ratio,
                scaled.w * ratio,
                scaled.h * ratio
            );

            canvas.toBlob(function (blob) {
                if (!blob) {
                    showError('Le recadrage a échoué. Essayez une autre image.');
                    return;
                }

                var file = new File([blob], 'avatar-' + Date.now() + '.jpg', { type: 'image/jpeg' });
                var transfer = new DataTransfer();
                transfer.items.add(file);
                output.files = transfer.files;

                if (photo) {
                    photo.src = canvas.toDataURL('image/jpeg', 0.85);
                    photo.hidden = false;
                }
                if (initials) {
                    initials.hidden = true;
                }
                if (removeBox) {
                    removeBox.checked = false;
                }

                close();
            }, 'image/jpeg', 0.92);
        });

        removeBox && removeBox.addEventListener('change', function () {
            if (removeBox.checked) {
                output.value = '';
                if (photo) {
                    photo.hidden = true;
                }
                if (initials) {
                    initials.hidden = false;
                }
            } else if (photo && photo.dataset.original) {
                // Le nouveau recadrage a ete abandonne : on revient a la photo actuelle.
                photo.src = photo.dataset.original;
                photo.hidden = false;
                if (initials) {
                    initials.hidden = true;
                }
            }
        });
    }
})();
