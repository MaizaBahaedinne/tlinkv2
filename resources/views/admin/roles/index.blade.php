@extends('admin.layouts.app')

@section('title', 'Rôles & permissions')
@section('section', 'Rôles & permissions')

@section('content')
    <x-admin.page-header title="Rôles & permissions" subtitle="Matrice des droits accordés à chaque rôle. Lecture seule : la matrice est définie par le système." />

    <article class="card card-flush">
        <div class="table-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Rôle</th>
                        <th>Portée</th>
                        <th>Actifs</th>
                        @foreach($permissions as $permission)
                            <th title="{{ $permission->description }}" style="text-align:center">{{ $permission->name }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($roles as $role)
                        @php($granted = $role->permissions->pluck('slug')->all())
                        <tr>
                            <td>
                                <div class="cell-main">{{ $role->name }}</div>
                                @unless($role->is_active)<div class="cell-sub">Désactivé</div>@endunless
                            </td>
                            <td class="text-sm">{{ $role->scope_type }}</td>
                            <td>{{ $role->active_assignments_count }}</td>
                            @foreach($permissions as $permission)
                                <td style="text-align:center">
                                    @if(in_array($permission->slug, $granted, true))
                                        <span style="color:var(--success)" title="Accordé"><x-admin.icon name="check" :size="16" /></span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </article>

    <article class="card card-flush" style="margin-top:22px">
        <div class="card-head"><h2>Légende des permissions</h2></div>
        <div class="list">
            @foreach($permissions as $permission)
                <div class="list-item">
                    <div class="grow">
                        <strong>{{ $permission->name }}</strong>
                        <span class="text-sm text-muted">{{ $permission->description }}</span>
                    </div>
                    <code class="text-sm text-muted">{{ $permission->slug }}</code>
                </div>
            @endforeach
        </div>
    </article>
@endsection
