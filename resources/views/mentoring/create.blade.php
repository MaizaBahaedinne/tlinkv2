@extends('layouts.base', ['title' => 'Nouvelle demande de mentorat | T.Link'])

@section('content')
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    <div class="app-container space-y-5 py-6">
        <div>
            <h1 class="font-display text-2xl font-semibold text-tlink-blue">Nouvelle demande</h1>
            <p class="text-sm text-slate-600">Seuls les managers mentors et le mentor qui vous accompagnera peuvent lire votre demande.</p>
        </div>

        @include('mentoring.partials.tabs')

        <form method="post" action="{{ route('mentoring.store') }}" class="space-y-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
            @csrf

            @if($errors->any())
                <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
                    @foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach
                </div>
            @endif

            <fieldset>
                <legend class="text-sm font-semibold text-slate-700">Cela concerne</legend>
                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                    @foreach(\App\Models\MentoringTicket::AUDIENCES as $value => $label)
                        <label class="flex cursor-pointer items-center gap-3 rounded-2xl border border-slate-200 px-4 py-3 text-sm font-semibold has-[:checked]:border-tlink-blue has-[:checked]:bg-rose-50 has-[:checked]:text-tlink-blue">
                            <input type="radio" name="audience" value="{{ $value }}" class="accent-tlink-blue" @checked(old('audience', 'personal') === $value)>
                            <span>{{ $value === 'personal' ? 'Moi, à titre personnel' : 'Mon collectif (club, projet, chapter…)' }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <div id="collective-fields" class="grid gap-4 rounded-2xl bg-slate-50 p-4 sm:grid-cols-3" @if(old('audience', 'personal') !== 'collective') hidden @endif>
                <div>
                    <label for="node_id" class="text-sm font-semibold text-slate-700">Mon club</label>
                    <select id="node_id" name="node_id" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-tlink-blue">
                        <option value="">Un autre collectif</option>
                        @foreach($clubs as $club)
                            <option value="{{ $club->id }}" @selected((int) old('node_id') === $club->id)>{{ $club->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="collective_type" class="text-sm font-semibold text-slate-700">Type de collectif</label>
                    <select id="collective_type" name="collective_type" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-tlink-blue">
                        @foreach(\App\Models\MentoringTicket::COLLECTIVES as $value => $label)
                            <option value="{{ $value }}" @selected(old('collective_type', 'project') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="collective_name" class="text-sm font-semibold text-slate-700">Nom du collectif</label>
                    <input id="collective_name" type="text" name="collective_name" value="{{ old('collective_name') }}" maxlength="160" placeholder="Si ce n’est pas l’un de vos clubs" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-tlink-blue">
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label for="type" class="text-sm font-semibold text-slate-700">Nature</label>
                    <select id="type" name="type" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-tlink-blue">
                        @foreach(\App\Models\MentoringTicket::TYPES as $value => $label)
                            <option value="{{ $value }}" @selected(old('type', 'request') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label for="title" class="text-sm font-semibold text-slate-700">Titre</label>
                    <input id="title" type="text" name="title" value="{{ old('title') }}" maxlength="160" required placeholder="En une phrase, de quoi s’agit-il ?" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-tlink-blue">
                </div>
            </div>

            <div>
                <label for="description" class="text-sm font-semibold text-slate-700">Décrivez la situation</label>
                <textarea id="description" name="description" rows="8" maxlength="{{ \App\Support\Mentoring\Mentoring::MAX_TEXT }}" required placeholder="Contexte, personnes ou équipes concernées, ce qui s’est passé, ce que vous avez déjà essayé, ce que vous attendez d’un mentor…" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm leading-relaxed outline-none focus:border-tlink-blue">{{ old('description') }}</textarea>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-3">
                <a href="{{ route('mentoring.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold hover:bg-slate-50">Annuler</a>
                <button type="submit" class="rounded-xl bg-tlink-blue px-5 py-2 text-sm font-semibold text-white hover:opacity-90">Envoyer ma demande</button>
            </div>
        </form>
    </div>
</div>

<script>
    (function () {
        var box = document.getElementById('collective-fields');
        var radios = document.querySelectorAll('input[name="audience"]');
        function sync() {
            var selected = document.querySelector('input[name="audience"]:checked');
            box.hidden = !selected || selected.value !== 'collective';
        }
        radios.forEach(function (radio) { radio.addEventListener('change', sync); });
        sync();
    })();
</script>
@endsection
