@extends('layouts.base', ['title' => 'Charte des réseaux sociaux | T.Link'])

@section('content')
<div class="min-h-screen bg-tlink-sand pb-24 text-slate-800 lg:pb-12">
    @include('partials.app-header')

    <div class="app-container max-w-3xl space-y-6 py-6">
        <a href="{{ route('marketing-access.index') }}" class="text-sm font-semibold text-tlink-blue hover:underline">← Mes accès réseaux sociaux</a>

        <div>
            <h1 class="font-display text-2xl font-semibold text-tlink-blue">{{ $charter['title'] }}</h1>
            <p class="text-sm text-slate-600">Version {{ $charter['version'] }} · pages demandées : {{ $accessRequest->platformLabels() }}</p>
        </div>

        <article class="whitespace-pre-line rounded-2xl border border-slate-200 bg-white p-5 text-sm leading-relaxed text-slate-700 shadow-sm">{{ $charter['body'] }}</article>

        @if($signed)
            <p class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">Signée par {{ $accessRequest->charter_signed_name }} le {{ $accessRequest->charter_signed_at->format('d/m/Y à H:i') }}.</p>
            @if($accessRequest->charter_signature)
                <img src="{{ $accessRequest->charter_signature }}" alt="Signature de {{ $accessRequest->charter_signed_name }}" class="h-28 w-auto max-w-full rounded-xl border border-slate-200 bg-white p-2">
            @endif
        @elseif($accessRequest->status === 'approved')
            <form method="post" action="{{ route('marketing-access.sign', $accessRequest) }}" class="space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                @csrf
                <label class="flex items-start gap-2 text-sm">
                    <input type="checkbox" name="accept" value="1" class="mt-1" required>
                    <span>J’ai lu la charte d’usage des réseaux sociaux de {{ $accessRequest->entityLabel() }} et je m’engage à la respecter.</span>
                </label>
                @error('accept')<p class="text-sm text-rose-700">{{ $message }}</p>@enderror
                <div>
                    <label for="signed_name" class="block text-sm font-semibold">Nom complet (signature)</label>
                    <input id="signed_name" name="signed_name" type="text" value="{{ old('signed_name', auth()->user()->name) }}" maxlength="160" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                    @error('signed_name')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <p class="block text-sm font-semibold">Signature (au doigt ou à la souris)</p>
                    <div class="mt-1 rounded-xl border-2 border-dashed border-slate-300 bg-white">
                        <canvas id="signature-pad" class="block h-40 w-full cursor-crosshair rounded-xl" style="touch-action:none" aria-label="Zone de signature"></canvas>
                    </div>
                    <input type="hidden" name="signature" id="signature-data" value="">
                    <div class="mt-2 flex items-center justify-between">
                        <p class="text-xs text-slate-500">Dessinez votre signature dans le cadre.</p>
                        <button type="button" id="signature-clear" class="text-xs font-semibold text-tlink-blue hover:underline">Effacer</button>
                    </div>
                    <p id="signature-error" class="mt-1 text-sm text-rose-700" role="alert" @if(! $errors->has('signature')) hidden @endif>{{ $errors->first('signature') }}</p>
                </div>
                <button type="submit" class="rounded-full bg-tlink-blue px-5 py-2 text-sm font-semibold text-white hover:opacity-90">Signer et accéder aux comptes</button>
            </form>
        @else
            <p class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">Cette charte n’est pas à signer actuellement ({{ $accessRequest->statusLabel() }}).</p>
        @endif
    </div>
</div>

@if(! $signed && $accessRequest->status === 'approved')
<script>
    (function () {
        var canvas = document.getElementById('signature-pad');
        if (!canvas) { return; }

        var form = canvas.closest('form');
        var field = document.getElementById('signature-data');
        var error = document.getElementById('signature-error');
        var ctx = canvas.getContext('2d');
        var drawing = false;
        var dirty = false;
        var last = null;

        function resize() {
            var ratio = Math.max(window.devicePixelRatio || 1, 1);
            var rect = canvas.getBoundingClientRect();
            var keep = dirty ? canvas.toDataURL() : null;
            canvas.width = Math.round(rect.width * ratio);
            canvas.height = Math.round(rect.height * ratio);
            ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
            ctx.lineWidth = 2.5;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.strokeStyle = '#0f172a';

            if (keep) {
                var img = new Image();
                img.onload = function () { ctx.drawImage(img, 0, 0, rect.width, rect.height); };
                img.src = keep;
            }
        }

        function point(e) {
            var rect = canvas.getBoundingClientRect();
            return { x: e.clientX - rect.left, y: e.clientY - rect.top };
        }

        canvas.addEventListener('pointerdown', function (e) {
            e.preventDefault();
            canvas.setPointerCapture(e.pointerId);
            drawing = true;
            last = point(e);
            ctx.beginPath();
            ctx.arc(last.x, last.y, 1.2, 0, Math.PI * 2);
            ctx.fillStyle = '#0f172a';
            ctx.fill();
            dirty = true;
        });

        canvas.addEventListener('pointermove', function (e) {
            if (!drawing) { return; }
            e.preventDefault();
            var now = point(e);
            ctx.beginPath();
            ctx.moveTo(last.x, last.y);
            ctx.lineTo(now.x, now.y);
            ctx.stroke();
            last = now;
        });

        ['pointerup', 'pointercancel', 'pointerleave'].forEach(function (name) {
            canvas.addEventListener(name, function () { drawing = false; });
        });

        document.getElementById('signature-clear').addEventListener('click', function () {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            dirty = false;
            field.value = '';
        });

        form.addEventListener('submit', function (e) {
            if (!dirty) {
                e.preventDefault();
                error.textContent = 'Dessinez votre signature dans le cadre avant de signer.';
                error.hidden = false;
                return;
            }
            field.value = canvas.toDataURL('image/png');
        });

        window.addEventListener('resize', resize);
        resize();
    })();
</script>
@endif
@endsection
