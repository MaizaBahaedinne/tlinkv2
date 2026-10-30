@extends('admin.layouts.app')

@section('title', $user->name)
@section('section', 'Utilisateurs')

@push('head')
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/admin-icons.css'])
    @endif
@endpush

@section('content')
    @php
        $genders = ['male' => 'Homme', 'female' => 'Femme'];
        $levels = \App\Support\AccessLevels::LEVELS;
        $dash = fn ($date) => $date?->format('d/m/Y');
        $socials = array_filter(['Facebook' => $profile?->facebook_url, 'Instagram' => $profile?->instagram_url, 'LinkedIn' => $profile?->linkedin_url]);
        $membershipCount = $journey->sum(fn ($group) => $group['memberships']->count());
    @endphp

    <x-admin.page-header title="Fiche utilisateur" :subtitle="'#'.$user->id.' · inscrit le '.($user->created_at?->format('d/m/Y') ?? '—')">
        <x-slot:actions>
            <a href="{{ route('admin.users.index') }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Retour à la liste</a>
            @if($canEdit)
                <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-primary"><x-admin.icon name="edit" :size="16" /> Modifier</a>
            @endif
            @if($canImpersonate)
                <form method="post" action="{{ route('admin.users.impersonate', $user) }}" style="display:inline" onsubmit="return confirm('Vous allez vous connecter en tant que {{ e(addslashes($user->name)) }}. Vos actions seront faites avec son compte. Continuer ?')">
                    @csrf
                    <button type="submit" class="btn btn-secondary"><x-admin.icon name="user" :size="16" /> Se connecter en tant que</button>
                </form>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    {{-- En-tête : cover, photo, identité et poste du mandat actuel --}}
    <article class="card uhero">
        <div class="uhero-cover">
            @if($coverUrl)<img src="{{ $coverUrl }}" alt="">@endif
        </div>
        <div class="uhero-body">
            <div class="uhero-avatar">
                <x-admin.avatar :name="$user->name" :size="112" :src="$avatarUrl" />
            </div>
            <div class="uhero-id">
                <h2>{{ $user->name }}</h2>
                <p class="headline">{{ $profile?->headline ?: 'Aucun titre renseigné' }}</p>
                <div class="uhero-tags">
                    <span class="badge {{ $user->is_active === false ? 'badge-danger' : 'badge-success' }}">{{ $user->is_active === false ? 'Compte désactivé' : 'Compte actif' }}</span>
                    <span class="badge {{ $user->email_verified_at ? 'badge-success' : 'badge-warning' }}">{{ $user->email_verified_at ? 'E-mail vérifié' : 'E-mail non vérifié' }}</span>
                    @foreach($currentPositions as $row)
                        <span class="mandate-chip"><x-admin.icon name="award" :size="14" /> {{ $row['assignment']->node->title }}</span>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="ustats">
            <div><b>{{ $currentPositions->count() }}</b><span>Poste(s) {{ $currentMandate ? 'du mandat '.$currentMandate->label : 'actuel(s)' }}</span></div>
            <div><b>{{ $membershipCount }}</b><span>Adhésion(s)</span></div>
            <div><b>{{ $badgesEarned }}<small class="text-muted" style="font-size:14px"> / {{ $badges->count() }}</small></b><span>Badges obtenus</span></div>
            <div><b>{{ $postsCount }}</b><span>Publication(s)</span></div>
        </div>
    </article>

    <div class="grid-3-1">
        <div style="display:grid;gap:22px;align-content:start;min-width:0">

            {{-- Poste du mandat actuel, supérieurs et subordonnés --}}
            <article class="card card-flush">
                <div class="card-head">
                    <div>
                        <h2>Poste du mandat actuel</h2>
                        @if($currentMandate)<div class="sub">Mandat {{ $currentMandate->label }}{{ filled($currentMandate->bureau_name) ? ' · '.$currentMandate->bureau_name : '' }}</div>@endif
                    </div>
                    @if($currentMandate?->is_current)<span class="badge badge-success">Mandat en cours</span>@endif
                </div>

                @if($currentPositions->isNotEmpty())
                    <div class="post-main">
                        @foreach($currentPositions as $row)
                            @php
                                $assignment = $row['assignment'];
                            @endphp
                            <div class="post-card">
                                <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start">
                                    <div style="min-width:0">
                                        <h3>{{ $assignment->node->title }}</h3>
                                        <div class="meta">
                                            @if(filled($assignment->node->subtitle))<span>{{ $assignment->node->subtitle }}</span>@endif
                                            @if(filled($assignment->person?->job_title))<span>{{ $assignment->person->job_title }}</span>@endif
                                            <span class="badge badge-info">{{ $levels[$assignment->node->access_level] ?? $assignment->node->access_level }}</span>
                                            @if($assignment->starts_at)<span>Depuis le {{ $dash($assignment->starts_at) }}</span>@endif
                                            @if($assignment->ends_at)<span>Jusqu’au {{ $dash($assignment->ends_at) }}</span>@endif
                                        </div>
                                    </div>
                                    <a href="{{ route('orgchart.index', ['bloc' => $assignment->node_id]) }}" class="btn btn-secondary btn-icon btn-sm" title="Voir dans l’organigramme" aria-label="Voir dans l’organigramme"><x-admin.icon name="network" :size="15" /></a>
                                </div>

                                <div class="rel-grid">
                                    @foreach(['superiors' => 'Supérieurs', 'subordinates' => 'Subordonnés'] as $key => $label)
                                        <div>
                                            <div class="rel-title">{{ $label }}</div>
                                            @if($row[$key]->isNotEmpty())
                                                <ul class="rel-list">
                                                    @foreach($row[$key] as $person)
                                                        <li>
                                                            <x-admin.avatar :name="$person['name']" :size="34" :src="$person['photo']" />
                                                            <div class="who">
                                                                @if($canLinkPeople && $person['user_id'])
                                                                    <strong><a href="{{ route('admin.users.show', $person['user_id']) }}" class="link-strong">{{ $person['name'] }}</a></strong>
                                                                @else
                                                                    <strong>{{ $person['name'] }}</strong>
                                                                @endif
                                                                <span>{{ collect([$person['node_title'], $person['job_title']])->filter()->unique()->implode(' · ') }}</span>
                                                            </div>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @else
                                                <p class="rel-none">{{ $key === 'superiors' ? 'Aucun supérieur identifié' : 'Aucun subordonné' }}</p>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <p class="card-body text-sm text-muted" style="padding-top:0">Les droits d’administration viennent uniquement des postes de l’organigramme. Pour les modifier, changez l’affectation ou le niveau d’accès du poste.</p>
                @else
                    <x-admin.empty icon="network" title="Aucun poste dans le mandat actuel" text="Sans poste, l’utilisateur a les droits de membre de base, sans accès à l’administration." />
                @endif
            </article>

            {{-- Parcours Tunivisions : postes et adhésions mandat par mandat --}}
            <article class="card card-flush">
                <div class="card-head">
                    <div>
                        <h2>Parcours Tunivisions</h2>
                        <div class="sub">Adhésions et postes, mandat par mandat</div>
                    </div>
                </div>

                @if($journey->isNotEmpty())
                    <div class="journey">
                        @foreach($journey as $group)
                            @php
                                $mandate = $group['mandate'];
                            @endphp
                            <section class="journey-item {{ $mandate && $currentMandate && $mandate->id === $currentMandate->id ? 'is-current' : '' }}">
                                <div class="journey-head">
                                    <h3>{{ $mandate ? 'Mandat '.$mandate->label : 'Hors mandat' }}</h3>
                                    @if($mandate && filled($mandate->bureau_name))<span class="sub">{{ $mandate->bureau_name }}</span>@endif
                                    @if($mandate?->is_current)<span class="badge badge-success">En cours</span>@endif
                                </div>

                                @if($group['positions']->isNotEmpty())
                                    <div class="journey-kind">Postes</div>
                                    <div class="journey-rows">
                                        @foreach($group['positions'] as $assignment)
                                            @php
                                                $ongoing = $assignment->ends_at === null || $assignment->ends_at->toDateString() >= now()->toDateString();
                                            @endphp
                                            <div class="journey-row">
                                                <div class="what">
                                                    <strong>{{ $assignment->node?->title ?? 'Poste supprimé' }}</strong>
                                                    <span>{{ collect([$assignment->node?->subtitle, $assignment->person?->job_title])->filter()->unique()->implode(' · ') ?: '—' }}</span>
                                                </div>
                                                <div class="when">
                                                    <span>{{ $assignment->starts_at ? $dash($assignment->starts_at) : '—' }} → {{ $assignment->ends_at ? $dash($assignment->ends_at) : 'aujourd’hui' }}</span>
                                                    <span class="badge {{ $ongoing ? 'badge-success' : 'badge-neutral' }}">{{ $ongoing ? 'En poste' : 'Terminé' }}</span>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                @if($group['participations']->isNotEmpty())
                                    <div class="journey-kind">Participations déclarées</div>
                                    <div class="journey-rows">
                                        @foreach($group['participations'] as $participation)
                                            <div class="journey-row">
                                                <div class="what">
                                                    <strong>{{ $participation->position_title ?? 'Membre' }}</strong>
                                                    <span>{{ collect([$participation->node?->title, \App\Support\OrgScopes::label($participation->scope)])->filter()->unique()->implode(' · ') ?: '—' }}</span>
                                                </div>
                                                <div class="when">
                                                    <span>Déclarée le {{ $participation->created_at->format('d/m/Y') }}</span>
                                                    <span class="badge badge-success">Acceptée</span>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                @if($group['memberships']->isNotEmpty())
                                    <div class="journey-kind">Adhésions</div>
                                    <div class="journey-rows">
                                        @foreach($group['memberships'] as $membership)
                                            <div class="journey-row">
                                                <div class="what">
                                                    <strong>{{ $membership->node?->title ?? (\App\Support\OrgScopes::label($membership->scope) ?? '—') }}</strong>
                                                    <span>{{ collect([\App\Support\OrgScopes::label($membership->scope), $membership->membership_type])->filter()->unique()->implode(' · ') ?: '—' }}</span>
                                                </div>
                                                <div class="when">
                                                    <span>{{ $dash($membership->starts_at ?? $membership->created_at) ?? '—' }}{{ $membership->ends_at ? ' → '.$dash($membership->ends_at) : '' }}</span>
                                                    <x-admin.badge :status="$membership->status" />
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </section>
                        @endforeach
                    </div>
                @else
                    <x-admin.empty icon="user-check" title="Aucun parcours" text="Cet utilisateur n’a encore ni adhésion ni poste dans la Fondation." />
                @endif
            </article>

            {{-- Badges : allumés quand obtenus, éteints sinon --}}
            <article class="card card-flush">
                <div class="card-head">
                    <div>
                        <h2>Badges</h2>
                        <div class="sub">{{ $badgesEarned }} obtenu(s) sur {{ $badges->count() }}</div>
                    </div>
                </div>
                @if($badges->isNotEmpty())
                    <div class="badge-grid">
                        @foreach($badges as $badge)
                            @php
                                $earnedAt = $badge->awarded_at;
                            @endphp
                            <div class="badge-tile {{ $earnedAt ? 'is-earned' : 'is-locked' }}" title="{{ $badge->description }}">
                                <span class="medal tier-{{ $badge->tier }}"><i class="{{ $badge->iconClass() }}" aria-hidden="true"></i></span>
                                <strong>{{ $badge->name }}</strong>
                                <small>{{ $badge->tierLabel() }}</small>
                                @if($earnedAt)
                                    <small class="date">Obtenu le {{ \Illuminate\Support\Carbon::parse($earnedAt)->format('d/m/Y') }}</small>
                                @else
                                    <small>Non obtenu</small>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <x-admin.empty icon="award" title="Aucun badge" text="Aucun badge n’est encore configuré." />
                @endif
            </article>

            <article class="card card-flush">
                <div class="card-head"><h2>Dernières publications</h2></div>
                @if($recentPosts->count())
                    <div class="list">
                        @foreach($recentPosts as $post)
                            <div class="list-item">
                                <div class="grow">
                                    <strong>{{ \Illuminate\Support\Str::limit(strip_tags((string) $post->content), 60) ?: '(sans texte)' }}</strong>
                                    <span class="text-sm text-muted">{{ $post->created_at?->format('d/m/Y H:i') }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <x-admin.empty icon="file-text" title="Aucune publication" />
                @endif
            </article>
        </div>

        <aside style="display:grid;gap:22px;align-content:start;min-width:0">
            <article class="card card-flush">
                <div class="card-head"><h2>Informations</h2></div>
                <div class="card-body">
                    <dl class="uinfo">
                        <div><dt>E-mail</dt><dd>{{ $user->email }}</dd></div>
                        <div><dt>Téléphone</dt><dd class="{{ $profile?->phone ? '' : 'is-empty' }}">{{ $profile?->phone ?: 'Non renseigné' }}</dd></div>
                        @if(filled($profile?->phone_secondary))<div><dt>Téléphone 2</dt><dd>{{ $profile->phone_secondary }}</dd></div>@endif
                        <div><dt>Genre</dt><dd class="{{ isset($genders[$profile?->gender]) ? '' : 'is-empty' }}">{{ $genders[$profile?->gender] ?? 'Non renseigné' }}</dd></div>
                        <div><dt>Naissance</dt><dd class="{{ $profile?->birth_date ? '' : 'is-empty' }}">{{ $profile?->birth_date ? $dash($profile->birth_date) : 'Non renseignée' }}</dd></div>
                        <div><dt>Gouvernorat</dt><dd class="{{ $governorate ? '' : 'is-empty' }}">{{ collect([$governorate, $profile?->delegation])->filter()->implode(' · ') ?: 'Non renseigné' }}</dd></div>
                        <div><dt>Adresse</dt><dd class="{{ filled($profile?->address) ? '' : 'is-empty' }}">{{ filled($profile?->address) ? $profile->address : 'Non renseignée' }}</dd></div>
                        <div><dt>Inscrit le</dt><dd>{{ $user->created_at?->format('d/m/Y') }}</dd></div>
                    </dl>
                </div>
            </article>

            @if(filled($profile?->bio))
                <article class="card card-flush">
                    <div class="card-head"><h2>À propos</h2></div>
                    <div class="card-body ubio">{{ $profile->bio }}</div>
                </article>
            @endif

            @if($skills->isNotEmpty() || $languages->isNotEmpty())
                <article class="card card-flush">
                    <div class="card-head"><h2>Compétences et langues</h2></div>
                    <div class="card-body" style="display:grid;gap:14px">
                        @if($skills->isNotEmpty())
                            <div>
                                <div class="rel-title">Compétences</div>
                                <div class="chips">@foreach($skills as $skill)<span class="chip">{{ $skill }}</span>@endforeach</div>
                            </div>
                        @endif
                        @if($languages->isNotEmpty())
                            <div>
                                <div class="rel-title">Langues</div>
                                <div class="chips">@foreach($languages as $language)<span class="chip">{{ $language }}</span>@endforeach</div>
                            </div>
                        @endif
                    </div>
                </article>
            @endif

            @if($socials !== [])
                <article class="card card-flush">
                    <div class="card-head"><h2>Réseaux</h2></div>
                    <div class="card-body chips">
                        @foreach($socials as $label => $url)
                            @if(preg_match('#^https?://#i', (string) $url))
                                <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="chip link-strong">{{ $label }} <x-admin.icon name="external" :size="12" /></a>
                            @endif
                        @endforeach
                    </div>
                </article>
            @endif
        </aside>
    </div>
@endsection
