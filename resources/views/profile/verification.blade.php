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
                        <p class="mt-1">Vous pouvez envoyer de nouvelles photos ci-dessous.</p>
                    </div>
                @endif

                @if($errors->any())
                    <ul class="mt-5 list-disc space-y-1 rounded-2xl border border-rose-200 bg-rose-50 px-8 py-3 text-sm text-rose-800">
                        @foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach
                    </ul>
                @endif

                <form method="post" action="{{ route('profile.verification.store') }}" enctype="multipart/form-data" class="mt-6 space-y-6">
                    @csrf

                    <div class="rounded-2xl bg-slate-50 p-4 text-sm text-slate-700">
                        <p class="font-semibold text-slate-900">Pour que votre demande soit acceptée :</p>
                        <ul class="mt-2 list-disc space-y-1 ps-5">
                            <li>Carte d’identité nationale <strong>tunisienne</strong>, <strong>recto et verso</strong>, entière et bien cadrée (les 4 coins visibles).</li>
                            <li>Photos nettes, lumineuses, sans reflet ni doigt devant le texte.</li>
                            <li>Selfie <strong>visage de face</strong>, bien cadré, sans lunettes de soleil ni casquette.</li>
                        </ul>
                    </div>

                    @foreach([
                        ['id_front', 'Carte d’identité — recto', 'fa-regular fa-id-card', 'Cadrez la carte entière, à plat, sur un fond uni.', false],
                        ['id_back', 'Carte d’identité — verso', 'fa-solid fa-id-card', 'Même consigne que pour le recto.', false],
                        ['selfie', 'Selfie de vérification', 'fa-regular fa-face-smile', 'Visage de face, centré, bien éclairé.', true],
                    ] as [$name, $label, $icon, $hint, $camera])
                        <div>
                            <label for="v-{{ $name }}" class="mb-1 block text-sm font-semibold text-slate-900"><i class="{{ $icon }} mr-1 text-slate-500" aria-hidden="true"></i>{{ $label }} <span class="text-rose-600">*</span></label>
                            <p class="mb-2 text-xs text-slate-500">{{ $hint }} JPG, PNG ou WebP, 6 Mo maximum.</p>
                            <input id="v-{{ $name }}" name="{{ $name }}" type="file" accept="image/jpeg,image/png,image/webp" @if($camera) capture="user" @endif required data-preview="p-{{ $name }}"
                                   class="block w-full rounded-xl border border-slate-300 bg-white text-sm file:mr-3 file:border-0 file:bg-slate-900 file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-white">
                            <img id="p-{{ $name }}" alt="Aperçu : {{ $label }}" class="mt-3 hidden max-h-56 rounded-xl border border-slate-200 object-contain">
                        </div>
                    @endforeach

                    <label class="flex items-start gap-3 text-sm text-slate-700">
                        <input type="checkbox" name="consent" value="1" required class="mt-1 h-4 w-4 rounded border-slate-300">
                        <span>J’accepte que ces documents soient examinés par un administrateur de la Fondation Tunivisions, uniquement pour vérifier mon identité. Ils sont conservés chiffrés, jamais affichés sur mon profil, et <strong>supprimés dès que la décision est prise</strong>.</span>
                    </label>

                    <button type="submit" class="w-full rounded-xl bg-tlink-blue px-4 py-3 text-sm font-semibold text-white hover:bg-tlink-blue-700">Envoyer pour vérification</button>
                </form>
            @endif
        </section>
    </main>
</div>

<script>
    document.querySelectorAll('input[data-preview]').forEach(function (input) {
        input.addEventListener('change', function () {
            var img = document.getElementById(input.dataset.preview);
            var file = input.files && input.files[0];
            if (!img || !file) { if (img) img.classList.add('hidden'); return; }
            img.src = URL.createObjectURL(file);
            img.classList.remove('hidden');
        });
    });
</script>
@endsection
