@extends('admin.layouts.app')

@section('title', 'Connexion OVH du webmail')
@section('section', 'Webmail')

@section('content')
    <x-admin.page-header title="Connexion OVH du webmail" subtitle="T.Link crée les boîtes des entités chez OVH (MX Plan) et en garde les mots de passe, chiffrés. Ces réglages ne servent qu’à cela : le secret et la clé de consommateur ne sont jamais ré-affichés." />

    @if($errors->has('test'))
        <div class="alert alert-error" role="alert"><x-admin.icon name="x" /> <span>{{ $errors->first('test') }}</span></div>
    @endif

    @unless($ready)
        <div class="alert alert-info" role="status" style="margin-bottom:16px"><x-admin.icon name="flag" /> <span>L’API OVH n’est pas encore configurée : vous pouvez tout de même enregistrer des boîtes déjà créées chez OVH, avec leur mot de passe. Pour que T.Link crée lui-même les boîtes, renseignez les trois clés ci-dessous.</span></div>
    @endunless

    <form method="post" action="{{ route('admin.webmail.settings.update') }}" class="card" autocomplete="off">
        @csrf
        @method('PUT')
        <div class="card-body">
            <div class="form-grid">
                <x-admin.input name="domain" label="Domaine des adresses" :value="$config['domain']" :required="true" maxlength="190" placeholder="fondation-tunivisions.org" hint="Les boîtes ont la forme nom@ce-domaine ; le domaine doit avoir un MX Plan chez OVH." />
                <x-admin.select name="endpoint" label="Région de l’API OVH" :options="$endpoints" :value="$config['endpoint']" :required="true" :placeholder="null" />
                <x-admin.input name="application_key" label="Clé d’application (Application Key)" :value="$config['application_key']" maxlength="100" />
                <x-admin.input name="application_secret" type="password" label="Secret d’application (Application Secret)" maxlength="100" autocomplete="new-password" :hint="$hasSecret ? 'Un secret est enregistré : laisser vide pour le conserver.' : null" />
                <x-admin.input name="consumer_key" type="password" label="Clé de consommateur (Consumer Key)" maxlength="100" autocomplete="new-password" :hint="$hasConsumer ? 'Une clé est enregistrée : laisser vide pour la conserver.' : 'Droits à accorder : GET, POST et DELETE sur /email/domain/*.'" />
                <x-admin.input name="mailbox_size" type="number" label="Taille des nouvelles boîtes (Mo, facultatif)" :value="$config['mailbox_size']" hint="Laisser vide pour la taille par défaut du MX Plan." />
                <x-admin.input name="imap_host" label="Serveur IMAP (lecture)" :value="$config['imap_host']" :required="true" maxlength="190" />
                <x-admin.select name="imap_port" label="Port IMAP" :options="[993 => '993 (SSL, conseillé)', 143 => '143 (STARTTLS)']" :value="$config['imap_port']" :required="true" :placeholder="null" />
                <x-admin.input name="smtp_host" label="Serveur SMTP (envoi)" :value="$config['smtp_host']" :required="true" maxlength="190" />
                <x-admin.select name="smtp_port" label="Port SMTP" :options="[465 => '465 (SSL, conseillé)', 587 => '587 (STARTTLS)']" :value="$config['smtp_port']" :required="true" :placeholder="null" />
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><x-admin.icon name="check" :size="16" /> Enregistrer</button>
        </div>
    </form>

    @if($ready)
        <form method="post" action="{{ route('admin.webmail.settings.test') }}" style="margin-top:16px">
            @csrf
            <button type="submit" class="btn btn-secondary"><x-admin.icon name="shield" :size="16" /> Tester la connexion à l’API OVH</button>
        </form>
    @endif
@endsection
