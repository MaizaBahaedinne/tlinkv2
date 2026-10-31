@extends('layouts.base', ['title' => 'Vérifier mon profil | T.Link'])

@section('content')
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    <main class="app-container mx-auto max-w-3xl space-y-5 py-6">
        <a href="{{ route('profile.show') }}" class="text-sm font-semibold text-slate-600 hover:text-slate-900"><i class="fa-solid fa-arrow-left mr-1" aria-hidden="true"></i> Retour à mon profil</a>

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
            <div class="flex items-center gap-3">
                <span class="flex h-11 w-11 items-center justify-center rounded-full bg-sky-100 text-sky-600"><i class="fa-solid fa-circle-check text-xl" aria-hidden="true"></i></span>
                <div>
                    <h1 class="font-display text-2xl font-semibold text-slate-900">Vérifier mon profil</h1>
                    <p class="text-sm text-slate-600">Obtenez le badge « Profil vérifié » à côté de votre nom.</p>
                </div>
            </div>

            @if($verified)
                <p class="mt-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                    <i class="fa-solid fa-circle-check mr-1" aria-hidden="true"></i> Votre profil est vérifié.
                </p>
            @elseif($latest?->isPending())
                <p class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <i class="fa-regular fa-clock mr-1" aria-hidden="true"></i>
                    <strong>Demande en cours d’examen</strong> depuis le {{ $latest->created_at->format('d/m/Y') }}. Un administrateur vérifie vos documents à la main ; vous serez notifié de la décision.
                </p>
            @else
                @if($latest?->status === 'rejected')
                    <div class="mt-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                        <strong>Votre dernière demande a été refusée.</strong>
                        @if($latest->rejection_reason)<p class="mt-1">Motif : {{ $latest->rejection_reason }}</p>@endif
                        <p class="mt-1">Vous pouvez reprendre de nouvelles photos ci-dessous.</p>
                    </div>
                @endif

                @if($errors->any())
                    <ul class="mt-5 list-disc space-y-1 rounded-2xl border border-rose-200 bg-rose-50 px-8 py-3 text-sm text-rose-800">
                        @foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach
                    </ul>
                @endif

                <form id="verification-form" method="post" action="{{ route('profile.verification.store') }}" enctype="multipart/form-data" class="mt-6 space-y-6">
                    @csrf

                    <div class="rounded-2xl bg-slate-50 p-4 text-sm text-slate-700">
                        <p class="font-semibold text-slate-900">Vous allez prendre 3 photos avec la caméra de votre appareil :</p>
                        <ol class="mt-2 list-decimal space-y-1 ps-5">
                            <li>La <strong>face avant</strong> de votre carte d’identité nationale <strong>tunisienne</strong>.</li>
                            <li>Le <strong>verso</strong> de la carte.</li>
                            <li>Votre <strong>visage de face</strong> (selfie).</li>
                        </ol>
                        <p class="mt-2">Un cadre à l’écran vous guide : placez-y la carte ou votre visage, puis appuyez sur « Prendre la photo ». Bonne lumière, pas de reflet.</p>
                    </div>

                    <div class="rounded-2xl border border-slate-200 p-4">
                        <p class="text-sm font-semibold text-slate-900"><i class="fa-regular fa-id-card mr-1 text-slate-500" aria-hidden="true"></i>Informations de la carte d’identité <span class="text-rose-600">*</span></p>
                        <p class="mt-1 text-xs text-slate-500">Recopiez-les exactement comme sur la carte : l’administrateur les compare aux photos.</p>
                        <div class="mt-3 grid gap-4 sm:grid-cols-3">
                            <div>
                                <label for="v-cin" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Numéro de la carte</label>
                                <input id="v-cin" name="cin_number" type="text" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" minlength="8" autocomplete="off" required value="{{ old('cin_number') }}" placeholder="8 chiffres" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm tracking-widest">
                            </div>
                            <div>
                                <label for="v-issued-on" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Date de délivrance</label>
                                <input id="v-issued-on" name="card_issued_on" type="date" min="1990-01-01" max="{{ now()->toDateString() }}" required value="{{ old('card_issued_on') }}" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                            </div>
                            <div>
                                <label for="v-issued-place" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Ville de délivrance</label>
                                <input id="v-issued-place" name="card_issued_place" type="text" maxlength="100" minlength="2" autocomplete="off" required value="{{ old('card_issued_place') }}" placeholder="Ex : Tunis" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                            </div>
                        </div>
                    </div>

                    @foreach([
                        ['id_front', 'Carte d’identité — recto', 'fa-regular fa-id-card', 'card', 'environment', 'Placez la face avant de la carte dans le cadre, à plat, bien éclairée. Les 4 coins doivent être visibles.'],
                        ['id_back', 'Carte d’identité — verso', 'fa-solid fa-id-card', 'card', 'environment', 'Retournez la carte et placez le verso dans le cadre. Le texte doit rester net, sans reflet.'],
                        ['selfie', 'Selfie de vérification', 'fa-regular fa-face-smile', 'selfie', 'user', 'Regardez l’objectif, visage de face, centré dans l’ovale. Sans lunettes de soleil ni casquette.'],
                    ] as [$name, $label, $icon, $kind, $facing, $tip])
                        <div data-slot="{{ $name }}" data-kind="{{ $kind }}" data-facing="{{ $facing }}" data-label="{{ $label }}" data-tip="{{ $tip }}" class="rounded-2xl border border-slate-200 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <p class="text-sm font-semibold text-slate-900"><i class="{{ $icon }} mr-1 text-slate-500" aria-hidden="true"></i>{{ $label }} <span class="text-rose-600">*</span></p>
                                <button type="button" data-open class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">
                                    <i class="fa-solid fa-camera" aria-hidden="true"></i> <span data-open-label>Prendre la photo</span>
                                </button>
                            </div>
                            <img data-preview alt="Photo prise : {{ $label }}" class="mt-3 hidden max-h-56 rounded-xl border border-slate-200 object-contain">
                            <p data-done class="mt-2 hidden text-sm font-semibold text-emerald-700"><i class="fa-solid fa-circle-check mr-1" aria-hidden="true"></i> Photo prête</p>
                            {{-- Secours si la caméra est refusée ou indisponible : ouvre l'appareil photo du téléphone. --}}
                            <input data-fallback name="{{ $name }}" type="file" accept="image/jpeg,image/png,image/webp" capture="{{ $facing }}" class="hidden">
                        </div>
                    @endforeach

                    <label class="flex items-start gap-3 text-sm text-slate-700">
                        <input type="checkbox" name="consent" value="1" required class="mt-1 h-4 w-4 rounded border-slate-300">
                        <span>J’accepte que ces documents soient examinés par un administrateur de la Fondation Tunivisions, uniquement pour vérifier mon identité. Ils sont conservés chiffrés, jamais affichés sur mon profil, et <strong>supprimés dès que la décision est prise</strong>.</span>
                    </label>

                    <div>
                        <button id="verification-submit" type="submit" disabled class="w-full rounded-xl bg-tlink-blue px-4 py-3 text-sm font-semibold text-white hover:bg-tlink-blue-700 disabled:cursor-not-allowed disabled:opacity-50">Envoyer pour vérification</button>
                        <p id="verification-missing" class="mt-2 text-center text-xs text-slate-500">Prenez les 3 photos pour pouvoir envoyer votre demande.</p>
                    </div>
                </form>
            @endif
        </section>
    </main>
</div>

@unless($verified || $latest?->isPending())
    {{-- Fenêtre caméra : flux vidéo + cadre de cadrage ; la photo est recadrée sur le cadre. --}}
    <dialog id="camera-dialog" class="w-[min(96vw,720px)] overflow-hidden rounded-2xl border-0 bg-white p-0 shadow-2xl backdrop:bg-slate-900/70">
        <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
            <h2 id="camera-title" class="text-base font-semibold text-slate-900">Prendre la photo</h2>
            <button type="button" id="camera-close" class="rounded-lg px-3 py-1 text-sm hover:bg-slate-100">Fermer</button>
        </div>

        <div class="bg-black">
            <div id="camera-stage" class="relative mx-auto w-fit max-w-full overflow-hidden">
                <video id="camera-video" playsinline muted autoplay class="block max-h-[62vh] max-w-full"></video>
                <div id="camera-guide" class="pointer-events-none absolute hidden border-2 border-white" style="box-shadow:0 0 0 9999px rgba(0,0,0,.55)">
                    <span class="absolute -left-0.5 -top-0.5 h-5 w-5 border-l-4 border-t-4 border-emerald-400" data-corner></span>
                    <span class="absolute -right-0.5 -top-0.5 h-5 w-5 border-r-4 border-t-4 border-emerald-400" data-corner></span>
                    <span class="absolute -bottom-0.5 -left-0.5 h-5 w-5 border-b-4 border-l-4 border-emerald-400" data-corner></span>
                    <span class="absolute -bottom-0.5 -right-0.5 h-5 w-5 border-b-4 border-r-4 border-emerald-400" data-corner></span>
                </div>
            </div>
        </div>

        <div class="space-y-3 px-4 py-4">
            <p id="camera-tip" class="text-sm text-slate-700"></p>
            <div id="camera-error" class="hidden rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">
                <p id="camera-error-text"></p>
                <button type="button" id="camera-fallback" class="mt-2 rounded-lg bg-rose-700 px-3 py-1.5 text-xs font-semibold text-white">Utiliser l’appareil photo du téléphone</button>
            </div>
            <button type="button" id="camera-shot" class="w-full rounded-xl bg-tlink-blue px-4 py-3 text-sm font-semibold text-white hover:bg-tlink-blue-700 disabled:opacity-50" disabled>
                <i class="fa-solid fa-camera mr-1" aria-hidden="true"></i> Prendre la photo
            </button>
        </div>
    </dialog>

    <script>
    (function () {
        var form = document.getElementById('verification-form');
        var dialog = document.getElementById('camera-dialog');
        if (!form || !dialog) return;

        var video = document.getElementById('camera-video');
        var guide = document.getElementById('camera-guide');
        var shot = document.getElementById('camera-shot');
        var errorBox = document.getElementById('camera-error');
        var errorText = document.getElementById('camera-error-text');
        var submit = document.getElementById('verification-submit');
        var missing = document.getElementById('verification-missing');
        var slots = Array.prototype.slice.call(form.querySelectorAll('[data-slot]'));
        var current = null;
        var stream = null;
        var CARD_RATIO = 85.6 / 54; // format ID-1 d'une carte d'identité

        function stopStream() {
            if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; }
            video.srcObject = null;
        }

        function showError(message) {
            errorText.textContent = message;
            errorBox.classList.remove('hidden');
            shot.disabled = true;
            guide.classList.add('hidden');
        }

        // Cadre de guidage en fractions de l'image : carte horizontale ou ovale de visage.
        function frame() {
            var w = video.videoWidth, h = video.videoHeight, fw, fh;
            if (current.dataset.kind === 'card') {
                fw = 0.92; fh = fw * w / (CARD_RATIO * h);
                if (fh > 0.9) { fh = 0.9; fw = fh * CARD_RATIO * h / w; }
            } else {
                fh = 0.82; fw = fh * h / w * 0.78;
                if (fw > 0.9) { fw = 0.9; fh = fw * w / (h * 0.78); }
            }
            return { fw: fw, fh: fh };
        }

        function drawGuide() {
            var f = frame();
            guide.style.width = (f.fw * 100) + '%';
            guide.style.height = (f.fh * 100) + '%';
            guide.style.left = ((1 - f.fw) / 2 * 100) + '%';
            guide.style.top = ((1 - f.fh) / 2 * 100) + '%';
            var selfie = current.dataset.kind === 'selfie';
            guide.style.borderRadius = selfie ? '50%' : '14px';
            guide.style.borderStyle = selfie ? 'solid' : 'dashed';
            guide.querySelectorAll('[data-corner]').forEach(function (c) { c.style.display = selfie ? 'none' : 'block'; });
            guide.classList.remove('hidden');
        }

        function openCamera(slot) {
            current = slot;
            document.getElementById('camera-title').textContent = slot.dataset.label;
            document.getElementById('camera-tip').textContent = slot.dataset.tip;
            errorBox.classList.add('hidden');
            shot.disabled = true;
            guide.classList.add('hidden');
            video.style.transform = slot.dataset.facing === 'user' ? 'scaleX(-1)' : 'none';
            dialog.showModal();

            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                showError('La caméra n’est pas disponible sur ce navigateur (ou la page n’est pas en https).');
                return;
            }

            navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: slot.dataset.facing }, width: { ideal: 1920 }, height: { ideal: 1080 } },
                audio: false
            }).then(function (s) {
                if (!dialog.open) { s.getTracks().forEach(function (t) { t.stop(); }); return; }
                stream = s;
                video.srcObject = s;
                video.onloadedmetadata = function () { drawGuide(); shot.disabled = false; };
            }).catch(function (error) {
                showError(error && error.name === 'NotAllowedError'
                    ? 'L’accès à la caméra a été refusé. Autorisez la caméra dans votre navigateur, ou utilisez l’appareil photo du téléphone.'
                    : 'Impossible d’ouvrir la caméra sur cet appareil.');
            });
        }

        function closeCamera() { stopStream(); if (dialog.open) dialog.close(); }

        function setPreview(slot, file) {
            var img = slot.querySelector('[data-preview]');
            if (img.dataset.url) URL.revokeObjectURL(img.dataset.url);
            img.dataset.url = URL.createObjectURL(file);
            img.src = img.dataset.url;
            img.classList.remove('hidden');
            slot.querySelector('[data-done]').classList.remove('hidden');
            slot.querySelector('[data-open-label]').textContent = 'Reprendre la photo';
            refresh();
        }

        function refresh() {
            var ready = slots.every(function (s) { return s.querySelector('[data-fallback]').files.length === 1; });
            submit.disabled = !ready;
            missing.classList.toggle('hidden', ready);
        }

        // Recadre l'image sur le cadre (avec une petite marge) puis la place dans le champ du formulaire.
        shot.addEventListener('click', function () {
            var w = video.videoWidth, h = video.videoHeight;
            if (!w || !h || !current) return;
            var f = frame(), selfie = current.dataset.kind === 'selfie';
            var mx = selfie ? 0.2 : 0.04, my = selfie ? 0.1 : 0.04;
            var cw = Math.min(w, f.fw * w * (1 + mx * 2)), ch = Math.min(h, f.fh * h * (1 + my * 2));
            var sx = (w - cw) / 2, sy = (h - ch) / 2;
            var canvas = document.createElement('canvas');
            canvas.width = Math.round(cw); canvas.height = Math.round(ch);
            canvas.getContext('2d').drawImage(video, sx, sy, cw, ch, 0, 0, canvas.width, canvas.height);
            var slot = current;
            canvas.toBlob(function (blob) {
                if (!blob) { showError('La photo n’a pas pu être enregistrée. Réessayez.'); return; }
                var file = new File([blob], slot.dataset.slot + '.jpg', { type: 'image/jpeg' });
                var transfer = new DataTransfer();
                transfer.items.add(file);
                slot.querySelector('[data-fallback]').files = transfer.files;
                setPreview(slot, file);
                closeCamera();
            }, 'image/jpeg', 0.92);
        });

        slots.forEach(function (slot) {
            slot.querySelector('[data-open]').addEventListener('click', function () { openCamera(slot); });
            var fallback = slot.querySelector('[data-fallback]');
            fallback.addEventListener('change', function () {
                if (fallback.files.length) setPreview(slot, fallback.files[0]);
            });
        });

        document.getElementById('camera-close').addEventListener('click', closeCamera);
        dialog.addEventListener('close', stopStream);
        document.getElementById('camera-fallback').addEventListener('click', function () {
            var slot = current;
            closeCamera();
            if (slot) slot.querySelector('[data-fallback]').click();
        });
    })();
    </script>
@endunless
@endsection
