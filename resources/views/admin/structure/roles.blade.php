@extends('admin.layouts.app')

@section('title', 'Droits des clubs')
@section('section', 'Organisation')

@section('content')
    <x-admin.page-header title="Droits des clubs" subtitle="Les droits sont les mêmes dans tous les clubs University / High School, selon le poste. Réglez-les ici, une seule fois.">
        <x-slot:actions>
            <a href="{{ route('admin.clubs.index') }}" class="btn btn-secondary"><x-admin.icon name="building" :size="16" /> Clubs</a>
        </x-slot:actions>
    </x-admin.page-header>

    @if($errors->any())
        <div class="alert alert-error" role="alert">
            <x-admin.icon name="x" />
            <span>@foreach($errors->all() as $message){{ $message }} @endforeach</span>
        </div>
    @endif

    <div class="grid-3-1">
        <form method="post" action="{{ route('admin.roles.update') }}" class="card card-flush">
            @csrf
            @method('PUT')
            <div class="card-head">
                <div>
                    <h2>Niveau d’accès par poste</h2>
                    <p class="sub">Le niveau s’applique, dans son périmètre, à toute personne qui occupe le poste dans un club.</p>
                </div>
            </div>
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Poste</th>
                            <th class="hide-mobile">Département</th>
                            <th class="hide-mobile">Clubs concernés</th>
                            <th>Niveau d’accès</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($roles as $key => $role)
                            <tr>
                                <td>
                                    <span class="cell-main">{{ $role['title'] }}</span>
                                    <div class="cell-sub">{{ $role['capacity'] === 'multiple' ? 'Plusieurs titulaires' : 'Un titulaire' }}</div>
                                </td>
                                <td class="hide-mobile">{{ $role['department'] ?? '—' }}</td>
                                <td class="hide-mobile">{{ $counts[$key] ?? 0 }}</td>
                                <td>
                                    <select name="levels[{{ $key }}]" aria-label="Niveau d’accès : {{ $role['title'] }}" @disabled(! $canEdit)>
                                        @foreach($levels as $level => $label)
                                            <option value="{{ $level }}" @selected(old('levels.'.$key, $current[$key] ?? 'none') === $level)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($canEdit)
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><x-admin.icon name="check" :size="16" /> Enregistrer pour tous les clubs</button>
                </div>
            @else
                <div class="card-body"><p class="text-sm text-muted">Seuls les administrateurs globaux peuvent modifier ces droits.</p></div>
            @endif
        </form>

        <article class="card card-flush" style="align-self:start">
            <div class="card-head"><div><h2>Ce que permet chaque niveau</h2></div></div>
            <div class="card-body" style="display:grid;gap:14px">
                @foreach($levels as $level => $label)
                    <div>
                        <strong>{{ $label }}</strong>
                        <div class="chips" style="margin-top:6px">
                            @forelse($levelPermissions[$level] ?? [] as $permission)
                                <span class="chip">{{ $permission }}</span>
                            @empty
                                <span class="text-sm text-muted">Aucun droit d’administration.</span>
                            @endforelse
                        </div>
                    </div>
                @endforeach
                <p class="hint">Les droits s’exercent sur le périmètre du club (University ou High School). Les droits globaux et d’entité sont réservés aux postes de direction.</p>
            </div>
        </article>
    </div>
@endsection
