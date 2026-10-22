@extends('admin.layouts.app')

@section('title', 'Info session et quiz')
@section('section', 'Contenu')

@section('content')
    <x-admin.page-header title="Info session et quiz" subtitle="Un membre regarde la vidéo, puis passe le quiz : le réussir valide son compte. Les questions et les réponses sont mélangées à chaque tentative.">
        <x-slot:actions>
            <a href="{{ route('info-session.show') }}" class="btn btn-secondary" target="_blank" rel="noopener"><x-admin.icon name="eye" :size="16" /> Voir côté membre</a>
        </x-slot:actions>
    </x-admin.page-header>

    @if($errors->any())
        <div class="alert alert-error" role="alert">
            <x-admin.icon name="x" />
            <span>@foreach($errors->all() as $message){{ $message }} @endforeach</span>
        </div>
    @endif

    <section class="stats" style="margin-bottom:22px">
        <x-admin.stat label="Comptes validés" :value="$stats['validated'].' / '.$stats['members']" icon="check" tone="green" />
        <x-admin.stat label="Tentatives" :value="$stats['attempts']" icon="file-text" tone="blue" />
        <x-admin.stat label="Quiz réussis" :value="$stats['passed']" icon="graduation" tone="cyan" :hint="$stats['attempts'] > 0 ? round($stats['passed'] / $stats['attempts'] * 100).' % de réussite' : null" />
    </section>

    <form method="post" action="{{ route('admin.info-session.update') }}" id="info-form">
        @csrf
        @method('PUT')

        <div class="grid-3-1">
            <div style="display:grid;gap:22px;align-content:start;min-width:0">
                <article class="card card-flush">
                    <div class="card-head"><div><h2>Vidéo et texte</h2></div></div>
                    <fieldset @disabled(! $canEdit) style="border:0;margin:0;padding:0;min-width:0">
                        <div class="card-body">
                            <div class="form-grid">
                                <x-admin.input name="title" label="Titre" :value="$settings['title']" required :maxlength="150" />
                                <x-admin.input name="video_url" label="Lien YouTube" type="url" :value="$settings['video_url']" required span hint="youtube.com/watch?v=… ou youtu.be/… La vidéo doit être lisible en intégration." />
                                <x-admin.textarea name="intro" label="Texte sous la vidéo" :value="$settings['intro']" :maxlength="3000" :rows="6" span />
                            </div>
                        </div>
                    </fieldset>
                </article>

                <div id="questions" style="display:grid;gap:22px">
                    @foreach($questions as $i => $question)
                        @include('admin.info-session.partials.question', ['i' => $i, 'q' => $question, 'slots' => 6, 'canEdit' => $canEdit])
                    @endforeach
                </div>

                @if($canEdit)
                    <div>
                        <button type="button" class="btn btn-secondary" id="add-question"><x-admin.icon name="plus" :size="16" /> Ajouter une question</button>
                        <p class="hint" style="margin-top:8px">Pour supprimer une réponse, videz son champ. Une question sans intitulé est ignorée à l’enregistrement.</p>
                    </div>
                @endif
            </div>

            <div style="display:grid;gap:22px;align-content:start">
                <article class="card card-flush">
                    <div class="card-head"><div><h2>Règles du quiz</h2></div></div>
                    <fieldset @disabled(! $canEdit) style="border:0;margin:0;padding:0;min-width:0">
                        <div class="card-body">
                            <div class="form-grid" style="grid-template-columns:1fr">
                                <x-admin.input name="duration_minutes" label="Durée (minutes)" type="number" :value="$settings['duration_minutes']" required min="1" max="60" />
                                <x-admin.input name="pass_percent" label="Réussite à partir de (%)" type="number" :value="$settings['pass_percent']" required min="1" max="100" hint="100 % : toutes les questions doivent être justes. Une question à choix multiple n’est juste que si les bonnes réponses sont toutes cochées, et elles seules." />
                            </div>
                        </div>
                    </fieldset>
                    @if($canEdit)
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary"><x-admin.icon name="check" :size="16" /> Enregistrer</button>
                        </div>
                    @else
                        <div class="card-body"><p class="text-sm text-muted">Seuls les administrateurs globaux peuvent modifier l’info session.</p></div>
                    @endif
                </article>

                <article class="card card-flush">
                    <div class="card-head"><div><h2>Dernières tentatives</h2></div></div>
                    @if($attempts->count())
                        <div class="list">
                            @foreach($attempts as $attempt)
                                <div class="list-item">
                                    <div class="grow">
                                        <strong>{{ $attempt->user?->name ?? '—' }}</strong>
                                        <span class="text-sm text-muted">{{ $attempt->score ?? 0 }} / {{ $attempt->total }} · {{ $attempt->submitted_at?->diffForHumans() }}</span>
                                    </div>
                                    <span class="badge badge-{{ ['passed' => 'success', 'failed' => 'danger', 'expired' => 'warning'][$attempt->status] ?? 'neutral' }}">{{ $statuses[$attempt->status] ?? $attempt->status }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="card-body"><p class="text-sm text-muted">Aucune tentative pour l’instant.</p></div>
                    @endif
                </article>
            </div>
        </div>
    </form>

    @if($canEdit)
        <template id="question-template">
            @include('admin.info-session.partials.question', ['i' => '__Q__', 'q' => ['type' => 'single'], 'slots' => 4, 'canEdit' => true])
        </template>
    @endif
@endsection

@push('scripts')
    <script>
        (function () {
            var list = document.getElementById('questions');
            var template = document.getElementById('question-template');
            var next = list.querySelectorAll('[data-question]').length + 100;

            function renumber() {
                list.querySelectorAll('[data-question]').forEach(function (block, index) {
                    block.querySelector('[data-num]').textContent = index + 1;
                });
            }

            document.getElementById('add-question') && document.getElementById('add-question').addEventListener('click', function () {
                // Gabarit statique du serveur (aucune donnée saisie n'y est injectée).
                list.insertAdjacentHTML('beforeend', template.innerHTML.replace(/__Q__/g, String(next++)));
                renumber();
                var added = list.lastElementChild.querySelector('input[type="text"]');
                added && added.focus();
            });

            list.addEventListener('click', function (event) {
                var button = event.target.closest('[data-remove-question]');
                if (!button) return;
                button.closest('[data-question]').remove();
                renumber();
            });

            renumber();
        })();
    </script>
@endpush
