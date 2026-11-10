<div class="tabs" style="margin-bottom:16px;padding:0">
    <a href="{{ route('admin.mail.edit') }}" class="tab {{ request()->routeIs('admin.mail.edit') ? 'is-active' : '' }}">Boîtes d’envoi</a>
    <a href="{{ route('admin.mail.templates') }}" class="tab {{ request()->routeIs('admin.mail.templates*') ? 'is-active' : '' }}">Modèles d’e-mails</a>
    <a href="{{ route('admin.mail.logs') }}" class="tab {{ request()->routeIs('admin.mail.logs') ? 'is-active' : '' }}">Journal des envois</a>
</div>
