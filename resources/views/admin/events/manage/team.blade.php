@extends('admin.layouts.app')

@section('title', 'Équipe OC — '.$event->title)
@section('section', 'Événements')

@section('content')
    @include('admin.events.manage._header')

    <article class="card card-flush" style="margin-bottom:16px">
        <div class="card-head">
            <div>
                <h2>Équipe d’organisation</h2>
                <div class="sub">Chaque rôle ouvre des droits de gestion précis sur cet événement.</div>
            </div>
        </div>

        @if($members->count())
            <div class="table-wrap">
                <table class="tbl">
                    <thead><tr><th>Membre</th><th>Rôle</th><th class="hide-mobile">Droits</th><th></th></tr></thead>
                    <tbody>
                        @foreach($members as $member)
                            <tr>
                                <td><div class="cell-main">{{ $member->user?->name }}</div><div class="cell-sub">{{ $member->user?->email }}{{ $member->notes ? ' · '.$member->notes : '' }}</div></td>
                                <td>
                                    <form method="post" action="{{ route('admin.events.team.update', [$event, $member]) }}" class="inline-form">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="notes" value="{{ $member->notes }}">
                                        <select name="role" aria-label="Rôle" onchange="this.form.submit()">
                                            @foreach($roles as $code => $label)<option value="{{ $code }}" @selected($member->role === $code)>{{ $label }}</option>@endforeach
                                        </select>
                                        <noscript><button type="submit" class="btn btn-secondary btn-sm">OK</button></noscript>
                                    </form>
                                </td>
                                <td class="hide-mobile text-sm text-muted">{{ collect($roleAbilities[$member->role] ?? [])->map(fn ($ability) => $abilityLabels[$ability] ?? $ability)->implode(' · ') }}</td>
                                <td class="text-right">
                                    <form method="post" action="{{ route('admin.events.team.destroy', [$event, $member]) }}" onsubmit="return confirm('Retirer ce membre de l’équipe ?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-ghost btn-sm"><x-admin.icon name="trash" :size="14" /> Retirer</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-admin.empty icon="users" title="Équipe vide" text="Ajoutez les membres de l’équipe d’organisation et attribuez-leur un rôle." />
        @endif
    </article>

    <article class="card card-flush">
        <div class="card-head"><h2>Ajouter un membre</h2></div>
        <div class="card-body">
            <form method="post" action="{{ route('admin.events.team.store', $event) }}" class="form-grid">
                @csrf
                <div class="field"><label>E-mail du membre <span class="req">*</span></label><input type="email" name="email" value="{{ old('email') }}" required maxlength="255"></div>
                <div class="field">
                    <label>Rôle <span class="req">*</span></label>
                    <select name="role" required>@foreach($roles as $code => $label)<option value="{{ $code }}" @selected(old('role') === $code)>{{ $label }}</option>@endforeach</select>
                </div>
                <div class="field span-2"><label>Note</label><input name="notes" maxlength="255" value="{{ old('notes') }}" placeholder="Mission, contact…"></div>
                <div class="form-actions span-2"><button type="submit" class="btn btn-primary"><x-admin.icon name="plus" :size="16" /> Ajouter à l’équipe</button></div>
            </form>
        </div>
    </article>
@endsection
