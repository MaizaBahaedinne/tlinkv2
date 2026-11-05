@extends('admin.layouts.app')

@section('title', 'Déploiement')
@section('section', 'Déploiement')

@section('content')
    <x-admin.page-header title="Déploiement et état du serveur" subtitle="Version en ligne, migrations en attente et contrôles de production. Le code, les dépendances et les migrations se déploient depuis le serveur : cette page n’en lance aucun.">
        <x-slot:actions>
            <a href="{{ route('admin.releases.index') }}" class="btn btn-secondary"><x-admin.icon name="layers" :size="16" /> Versions et changements</a>
        </x-slot:actions>
    </x-admin.page-header>

    @if($maintenance !== null)
        <div class="alert alert-error" role="status">
            <x-admin.icon name="flag" />
            <span>Le site est en <strong>mode maintenance</strong> : les visiteurs voient une page 503.
                @if(! empty($maintenance['secret']))
                    Lien de contournement : <a href="{{ url('/'.$maintenance['secret']) }}">{{ url('/'.$maintenance['secret']) }}</a> (dépose un cookie sur votre navigateur).
                @endif
                En cas de blocage : <code>php artisan up</code> sur le serveur.</span>
        </div>
    @endif

    <section class="stats">
        <x-admin.stat label="Version en ligne" :value="$latest?->version ?? '—'" icon="layers" tone="blue" :hint="$latest ? $latest->title : 'Aucune version publiée'" :href="route('admin.releases.index')" />
        <x-admin.stat label="Commit déployé" :value="$release['commit'] ? substr($release['commit'], 0, 8) : 'Inconnu'" icon="activity" tone="green" :hint="$release['branch'] ? 'Branche '.$release['branch'] : 'Fichier .release.json absent'" />
        <x-admin.stat label="Déployé le" :value="$release['deployed_at'] ? \Illuminate\Support\Carbon::parse($release['deployed_at'])->timezone(config('app.timezone'))->format('d/m/Y H:i') : '—'" icon="calendar" tone="amber" />
        <x-admin.stat label="Migrations en attente" :value="count($pending)" icon="flag" :tone="$pending ? 'red' : 'green'" />
    </section>

    <article class="card card-flush">
        <div class="card-body">
            <h3 style="margin-top:0">Contrôles</h3>
            <div class="list">
                @foreach($checks as $check)
                    <div class="list-item" style="align-items:center">
                        <div class="grow">
                            <strong style="display:inline">{{ $check['label'] }}</strong>
                            <span class="text-sm text-muted"> — {{ $check['detail'] }}</span>
                        </div>
                        <x-admin.badge :status="$check['ok'] ? 'approved' : 'pending'" :label="$check['ok'] ? 'OK' : 'À vérifier'" />
                    </div>
                @endforeach
            </div>
        </div>
    </article>

    @if($pending)
        <article class="card card-flush" style="margin-top:16px">
            <div class="card-body">
                <h3 style="margin-top:0">Migrations à exécuter</h3>
                <ul class="text-sm" style="margin:0 0 12px;padding-left:18px">
                    @foreach($pending as $name)
                        <li>{{ $name }}</li>
                    @endforeach
                </ul>
                <p class="text-sm text-muted" style="margin:0">Sur le serveur : <code>php artisan migrate --force</code>, puis <code>php artisan optimize:clear</code>.</p>
            </div>
        </article>
    @endif

    <article class="card card-flush" style="margin-top:16px">
        <div class="card-body">
            <h3 style="margin-top:0">Environnement</h3>
            <div class="list">
                @foreach([
                    'Application' => $env['app'],
                    'Environnement' => $env['environment'],
                    'Adresse' => $env['url'],
                    'PHP' => $env['php'],
                    'Laravel' => $env['laravel'],
                    'Fuseau horaire' => $env['timezone'],
                    'Cache' => $env['cache'],
                    'Queue' => $env['queue'],
                    'Sessions' => $env['session'],
                ] as $label => $value)
                    <div class="list-item"><div class="grow"><strong style="display:inline">{{ $label }}</strong> <span class="text-sm text-muted">— {{ $value }}</span></div></div>
                @endforeach
            </div>
        </div>
    </article>

    <article class="card card-flush" style="margin-top:16px">
        <div class="card-body">
            <h3 style="margin-top:0">Actions</h3>
            <div style="display:flex;gap:12px;flex-wrap:wrap">
                <form method="post" action="{{ route('admin.deployment.caches') }}" data-confirm="Les caches de configuration, de routes et de vues seront vidés et reconstruits à la demande." data-confirm-title="Vider les caches ?">
                    @csrf
                    <button type="submit" class="btn btn-secondary"><x-admin.icon name="activity" :size="16" /> Vider les caches</button>
                </form>

                @if($maintenance === null)
                    <form method="post" action="{{ route('admin.deployment.maintenance.on') }}" data-confirm="Les visiteurs verront une page de maintenance. Vous gardez l’accès grâce à un cookie de contournement." data-confirm-title="Activer le mode maintenance ?">
                        @csrf
                        <button type="submit" class="btn btn-danger"><x-admin.icon name="flag" :size="16" /> Activer la maintenance</button>
                    </form>
                @else
                    <form method="post" action="{{ route('admin.deployment.maintenance.off') }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-primary"><x-admin.icon name="check" :size="16" /> Remettre le site en ligne</button>
                    </form>
                @endif
            </div>
        </div>
    </article>
@endsection
