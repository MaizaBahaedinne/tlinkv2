@extends('admin.layouts.app')

@section('title', 'Sponsors & partenaires — '.$event->title)
@section('section', 'Événements')

@section('content')
    @include('admin.events.manage._header')

    <article class="card card-flush" style="margin-bottom:16px">
        <div class="card-head">
            <div>
                <h2>Niveaux</h2>
                <div class="sub">Définissez vos niveaux (Platinum, Gold, Partenaire média…). Le rang 1 est affiché en premier.</div>
            </div>
        </div>
        <div class="card-body">
            @foreach($levels as $level)
                <details style="margin-bottom:8px">
                    <summary><strong>{{ $level->name }}</strong> — {{ $level->kindLabel() }}, rang {{ $level->rank }} ({{ $level->sponsors->count() }})</summary>
                    <div style="padding:10px 0">
                        <form method="post" action="{{ route('admin.events.sponsors.levels.update', [$event, $level]) }}" class="form-grid">
                            @csrf @method('PUT')
                            <div class="field"><label>Nom</label><input name="name" value="{{ $level->name }}" required maxlength="120"></div>
                            <div class="field"><label>Type</label>
                                <select name="kind">@foreach(\App\Models\EventSponsorLevel::KINDS as $code => $label)<option value="{{ $code }}" @selected($level->kind === $code)>{{ $label }}</option>@endforeach</select>
                            </div>
                            <div class="field"><label>Rang</label><input type="number" name="rank" min="1" max="99" value="{{ $level->rank }}" required></div>
                            <div class="form-actions span-2"><button type="submit" class="btn btn-primary btn-sm">Enregistrer</button></div>
                        </form>
                        <form method="post" action="{{ route('admin.events.sponsors.levels.destroy', [$event, $level]) }}" onsubmit="return confirm('Supprimer ce niveau ?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-ghost btn-sm"><x-admin.icon name="trash" :size="14" /> Supprimer le niveau</button>
                        </form>
                    </div>
                </details>
            @endforeach

            <form method="post" action="{{ route('admin.events.sponsors.levels.store', $event) }}" class="form-grid" style="margin-top:12px">
                @csrf
                <div class="field"><label>Nouveau niveau <span class="req">*</span></label><input name="name" required maxlength="120" placeholder="Platinum"></div>
                <div class="field"><label>Type</label>
                    <select name="kind">@foreach(\App\Models\EventSponsorLevel::KINDS as $code => $label)<option value="{{ $code }}">{{ $label }}</option>@endforeach</select>
                </div>
                <div class="field"><label>Rang</label><input type="number" name="rank" min="1" max="99" value="{{ $levels->count() + 1 }}" required></div>
                <div class="form-actions span-2"><button type="submit" class="btn btn-primary"><x-admin.icon name="plus" :size="16" /> Ajouter le niveau</button></div>
            </form>
        </div>
    </article>

    @foreach($levels as $level)
        <article class="card card-flush" style="margin-bottom:16px">
            <div class="card-head"><h2>{{ $level->name }}</h2><span class="badge badge-info">{{ $level->kindLabel() }}</span></div>
            @if($level->sponsors->count())
                <div class="table-wrap">
                    <table class="tbl">
                        <tbody>
                            @foreach($level->sponsors as $sponsor)
                                <tr>
                                    <td style="width:70px">@if($sponsor->logoUrl())<img src="{{ $sponsor->logoUrl() }}" alt="" style="max-width:60px;max-height:40px;object-fit:contain">@endif</td>
                                    <td><div class="cell-main">{{ $sponsor->name }}</div><div class="cell-sub">{{ $sponsor->website_url }}</div></td>
                                    <td class="text-right nowrap">
                                        <details>
                                            <summary class="btn btn-ghost btn-sm">Modifier</summary>
                                            <div style="padding:12px 0;text-align:left">
                                                @include('admin.events.manage._sponsor_form', ['sponsor' => $sponsor, 'action' => route('admin.events.sponsors.update', [$event, $sponsor])])
                                                <form method="post" action="{{ route('admin.events.sponsors.destroy', [$event, $sponsor]) }}" onsubmit="return confirm('Supprimer ?')">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="btn btn-ghost btn-sm"><x-admin.icon name="trash" :size="14" /> Supprimer</button>
                                                </form>
                                            </div>
                                        </details>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="card-body text-muted">Aucun sponsor ni partenaire dans ce niveau.</div>
            @endif
        </article>
    @endforeach

    @if($levels->count())
        <article class="card card-flush">
            <div class="card-head"><h2>Nouveau sponsor / partenaire</h2></div>
            <div class="card-body">
                @include('admin.events.manage._sponsor_form', ['sponsor' => new \App\Models\EventSponsor(), 'action' => route('admin.events.sponsors.store', $event)])
            </div>
        </article>
    @endif
@endsection
