<div class="grid gap-5 lg:grid-cols-3">
    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft lg:col-span-2">
        <h2 class="font-display text-lg font-semibold text-slate-900">Présentation</h2>
        @if(filled($description))
            <p class="mt-3 whitespace-pre-line text-sm leading-relaxed text-slate-700">{{ $description }}</p>
        @else
            <p class="mt-3 rounded-2xl bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">Ce club n’a pas encore rédigé sa présentation.</p>
        @endif
    </section>

    <aside class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
        <h2 class="font-display text-lg font-semibold text-slate-900">Informations</h2>
        <dl class="mt-3 space-y-3 text-sm">
            @if($founded)
                <div><dt class="text-xs text-slate-500">Création</dt><dd class="font-medium text-slate-800">{{ $founded->translatedFormat('j F Y') }}</dd></div>
            @endif
            @if($location)
                <div><dt class="text-xs text-slate-500">Localisation</dt><dd class="font-medium text-slate-800">{{ $location }}</dd></div>
            @endif
            @if($institution)
                <div><dt class="text-xs text-slate-500">Établissement{{ $institutionType ? ' ('.\Illuminate\Support\Str::lower($institutionType).')' : '' }}</dt><dd class="font-medium text-slate-800">{{ $institution }}</dd></div>
            @endif
            @if($scope)
                <div><dt class="text-xs text-slate-500">Périmètre</dt><dd class="font-medium text-slate-800">{{ $scope }}</dd></div>
            @endif
            <div><dt class="text-xs text-slate-500">Statut</dt><dd class="font-medium text-slate-800">{{ $status }}</dd></div>
            <div><dt class="text-xs text-slate-500">Charte</dt><dd class="font-medium text-slate-800">{{ $charter ? 'Signée' : 'Non signée' }}</dd></div>
            @if($email)
                <div><dt class="text-xs text-slate-500">Contact</dt><dd class="break-all font-medium"><a href="mailto:{{ $email }}" class="text-tlink-blue hover:underline">{{ $email }}</a></dd></div>
            @endif
        </dl>
    </aside>
</div>
