@extends('admin.layouts.app')

@php
    $editing = $course->exists;
    $mine = old('_form') === 'course';
    $val = fn (string $field, $default = null) => $mine ? old($field, $default) : $default;
    $err = fn (string $field): ?string => $mine ? $errors->first($field) : null;
    $moduleQuizzes = $editing ? $course->quizzes->filter(fn ($quiz) => $quiz->module_id !== null)->keyBy('module_id') : collect();
    $finalQuiz = $editing ? $course->quizzes->first(fn ($quiz) => $quiz->module_id === null) : null;
@endphp

@section('title', $editing ? $course->title : 'Nouveau cours')
@section('section', 'Tunivisions Academy')

@section('content')
    <x-admin.page-header :title="$editing ? $course->title : 'Nouveau cours'" subtitle="Fiche du cours, prix, badge de fin de cours et points. Les modules et leçons s’ajoutent une fois le cours créé.">
        <x-slot:actions>
            <a href="{{ route('admin.academy.courses.index') }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Retour</a>
            @if($editing && $course->isPublished())
                <a href="{{ route('academy.courses.show', $course->slug) }}" class="btn btn-secondary" target="_blank" rel="noopener"><x-admin.icon name="external" :size="16" /> Voir côté membre</a>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    @error('course')<p class="error" style="margin-bottom:12px">{{ $message }}</p>@enderror

    <article class="card">
        <form method="post" enctype="multipart/form-data" action="{{ $editing ? route('admin.academy.courses.update', $course) : route('admin.academy.courses.store') }}" class="grid" style="gap:14px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr))">
            @csrf
            @if($editing) @method('PUT') @endif
            <input type="hidden" name="_form" value="course">

            <div style="grid-column:1/-1">
                <label class="text-sm" for="c-title">Titre</label>
                <input id="c-title" type="text" name="title" value="{{ $val('title', $course->title) }}" maxlength="160" required>
                @if($err('title'))<p class="error">{{ $err('title') }}</p>@endif
            </div>
            <div style="grid-column:1/-1">
                <label class="text-sm" for="c-summary">Résumé (une phrase)</label>
                <input id="c-summary" type="text" name="summary" value="{{ $val('summary', $course->summary) }}" maxlength="300">
                @if($err('summary'))<p class="error">{{ $err('summary') }}</p>@endif
            </div>
            <div style="grid-column:1/-1">
                <label class="text-sm" for="c-description">Présentation détaillée</label>
                <textarea id="c-description" name="description" rows="5" maxlength="10000">{{ $val('description', $course->description) }}</textarea>
                @if($err('description'))<p class="error">{{ $err('description') }}</p>@endif
            </div>
            <div style="grid-column:1/-1">
                <label class="text-sm" for="c-objectives">Objectifs de la formation (un par ligne)</label>
                <textarea id="c-objectives" name="objectives" rows="4" maxlength="3000">{{ $val('objectives', $course->objectives) }}</textarea>
                @if($err('objectives'))<p class="error">{{ $err('objectives') }}</p>@endif
            </div>
            <div>
                <label class="text-sm" for="c-prereq">Prérequis (un par ligne)</label>
                <textarea id="c-prereq" name="prerequisites" rows="3" maxlength="2000">{{ $val('prerequisites', $course->prerequisites) }}</textarea>
                @if($err('prerequisites'))<p class="error">{{ $err('prerequisites') }}</p>@endif
            </div>
            <div>
                <label class="text-sm" for="c-audience">Public visé (un par ligne)</label>
                <textarea id="c-audience" name="audience" rows="3" maxlength="2000">{{ $val('audience', $course->audience) }}</textarea>
                @if($err('audience'))<p class="error">{{ $err('audience') }}</p>@endif
            </div>
            <div>
                <label class="text-sm" for="c-language">Langue</label>
                <input id="c-language" type="text" name="language" value="{{ $val('language', $course->language ?? 'Français') }}" maxlength="40">
                @if($err('language'))<p class="error">{{ $err('language') }}</p>@endif
            </div>
            <div>
                <label class="text-sm" for="c-level">Niveau</label>
                <select id="c-level" name="level" required>
                    @foreach(\App\Models\AcademyCourse::LEVELS as $value => $label)
                        <option value="{{ $value }}" @selected($val('level', $course->level) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @if($err('level'))<p class="error">{{ $err('level') }}</p>@endif
            </div>
            <div>
                <label class="text-sm" for="c-instructor">Formateur</label>
                <input id="c-instructor" type="text" name="instructor_name" value="{{ $val('instructor_name', $course->instructor_name) }}" maxlength="120">
                @if($err('instructor_name'))<p class="error">{{ $err('instructor_name') }}</p>@endif
            </div>
            <div>
                <label class="text-sm" for="c-instructor-title">Fonction du formateur</label>
                <input id="c-instructor-title" type="text" name="instructor_title" value="{{ $val('instructor_title', $course->instructor_title) }}" maxlength="160">
                @if($err('instructor_title'))<p class="error">{{ $err('instructor_title') }}</p>@endif
            </div>
            <div style="grid-column:1/-1">
                <label class="text-sm" for="c-instructor-bio">Biographie du formateur</label>
                <textarea id="c-instructor-bio" name="instructor_bio" rows="3" maxlength="3000">{{ $val('instructor_bio', $course->instructor_bio) }}</textarea>
                @if($err('instructor_bio'))<p class="error">{{ $err('instructor_bio') }}</p>@endif
            </div>
            <div style="grid-column:1/-1">
                <label class="text-sm" for="c-instructor-photo">Photo du formateur</label>
                @if($editing && $course->instructorPhotoUrl())
                    <img src="{{ $course->instructorPhotoUrl() }}" alt="" style="display:block;width:72px;height:72px;object-fit:cover;border-radius:50%;margin:6px 0">
                    <label class="check"><input type="checkbox" name="remove_instructor_photo" value="1"> Retirer la photo</label>
                @endif
                <input id="c-instructor-photo" type="file" name="instructor_photo" accept="image/png,image/jpeg,image/webp">
                @if($err('instructor_photo'))<p class="error">{{ $err('instructor_photo') }}</p>@endif
            </div>
            <div>
                <label class="text-sm" for="c-price">Prix (TND) — vide = gratuit</label>
                <input id="c-price" type="number" step="0.01" min="0" max="99999" name="price" value="{{ $val('price', $course->price !== null && (float) $course->price > 0 ? $course->price : null) }}">
                @if($err('price'))<p class="error">{{ $err('price') }}</p>@endif
            </div>
            <div>
                <label class="text-sm" for="c-status">Statut</label>
                <select id="c-status" name="status" required>
                    @foreach(\App\Models\AcademyCourse::STATUSES as $value => $label)
                        <option value="{{ $value }}" @selected($val('status', $course->status) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @if($err('status'))<p class="error">{{ $err('status') }}</p>@endif
            </div>
            <div>
                <label class="text-sm" for="c-badge">Badge de fin de cours</label>
                <select id="c-badge" name="badge_id">
                    <option value="">Aucun</option>
                    @foreach($badges as $id => $name)
                        <option value="{{ $id }}" @selected((int) $val('badge_id', $course->badge_id) === $id)>{{ $name }}</option>
                    @endforeach
                </select>
                @if($err('badge_id'))<p class="error">{{ $err('badge_id') }}</p>@endif
            </div>
            <div>
                <label class="text-sm" for="c-points">Points à la fin du cours</label>
                <input id="c-points" type="number" min="0" max="10000" name="completion_points" value="{{ $val('completion_points', $course->completion_points) }}">
                @if($err('completion_points'))<p class="error">{{ $err('completion_points') }}</p>@endif
            </div>
            <div style="align-self:end">
                <label class="check"><input type="checkbox" name="included_in_plans" value="1" @checked($val('included_in_plans', $course->included_in_plans))> Inclus dans les abonnements</label>
            </div>
            <div style="grid-column:1/-1">
                <label class="text-sm" for="c-cover">Image de couverture</label>
                @if($editing && $course->coverUrl())
                    <img src="{{ $course->coverUrl() }}" alt="" style="display:block;max-width:240px;border-radius:12px;margin:6px 0">
                    <label class="check"><input type="checkbox" name="remove_cover" value="1"> Retirer l’image</label>
                @endif
                <input id="c-cover" type="file" name="cover" accept="image/png,image/jpeg,image/webp">
                <p class="hint">jpg, png ou webp — 4 Mo maximum.</p>
                @if($err('cover'))<p class="error">{{ $err('cover') }}</p>@endif
            </div>
            <div style="grid-column:1/-1;display:flex;justify-content:flex-end">
                <button type="submit" class="btn btn-primary">{{ $editing ? 'Enregistrer' : 'Créer le cours' }}</button>
            </div>
        </form>
    </article>

    @if($editing)
        <article class="card card-flush" style="margin-top:16px">
            <div class="card-head">
                <h2>Modules et leçons</h2>
            </div>

            @foreach($course->modules as $module)
                <div class="list-item" style="display:block;border-top:1px solid var(--line)">
                    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                        <strong>{{ $module->sort_order }}. {{ $module->title }}</strong>
                        <span class="text-sm text-muted">{{ $module->lessons->count() }} leçon(s)@if($module->badge_id) · badge de fin de module @endif</span>
                        <form method="post" action="{{ route('admin.academy.courses.modules.destroy', $module) }}" style="margin-left:auto" onsubmit="return confirm('Supprimer ce module et ses leçons ?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm"><x-admin.icon name="trash" :size="15" /> Supprimer</button>
                        </form>
                    </div>

                    <details style="margin-top:8px" @if(in_array(old('_form'), ['m'.$module->id], true) && $errors->any()) open @endif>
                        <summary class="btn btn-secondary btn-sm" style="cursor:pointer">Modifier le module</summary>
                        <form method="post" action="{{ route('admin.academy.courses.modules.update', $module) }}" class="grid" style="gap:12px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));margin-top:10px">
                            @csrf
                            @method('PUT')
                            @include('admin.academy.courses._module-fields', ['module' => $module, 'prefix' => 'm'.$module->id])
                            <div style="grid-column:1/-1;display:flex;justify-content:flex-end"><button type="submit" class="btn btn-primary btn-sm">Enregistrer</button></div>
                        </form>
                    </details>

                    <div style="margin:10px 0 0 14px">
                        @foreach($module->lessons as $lesson)
                            <div style="border-top:1px dashed var(--line);padding:8px 0">
                                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                                    <span>{{ $lesson->sort_order }}. {{ $lesson->title }}</span>
                                    <span class="text-sm text-muted">{{ \App\Models\AcademyLesson::KINDS[$lesson->kind] ?? $lesson->kind }} · {{ $lesson->points }} pt(s)@if($lesson->duration_minutes) · {{ $lesson->duration_minutes }} min @endif</span>
                                    @if($lesson->is_preview)<x-admin.badge status="published" label="Aperçu gratuit" />@endif
                                    <form method="post" action="{{ route('admin.academy.courses.lessons.destroy', $lesson) }}" style="margin-left:auto" onsubmit="return confirm('Supprimer cette leçon ?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-ghost btn-sm"><x-admin.icon name="trash" :size="14" /> Supprimer</button>
                                    </form>
                                </div>
                                <details @if(old('_form') === 'l'.$lesson->id && $errors->any()) open @endif>
                                    <summary class="text-sm" style="cursor:pointer">Modifier la leçon</summary>
                                    <form method="post" action="{{ route('admin.academy.courses.lessons.update', $lesson) }}" class="grid" style="gap:12px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));margin-top:10px">
                                        @csrf
                                        @method('PUT')
                                        @include('admin.academy.courses._lesson-fields', ['lesson' => $lesson, 'prefix' => 'l'.$lesson->id])
                                        <div style="grid-column:1/-1;display:flex;justify-content:flex-end"><button type="submit" class="btn btn-primary btn-sm">Enregistrer</button></div>
                                    </form>
                                </details>
                            </div>
                        @endforeach

                        <details @if(old('_form') === 'nl'.$module->id && $errors->any()) open @endif>
                            <summary class="btn btn-ghost btn-sm" style="cursor:pointer"><x-admin.icon name="plus" :size="14" /> Ajouter une leçon</summary>
                            <form method="post" action="{{ route('admin.academy.courses.lessons.store', $module) }}" class="grid" style="gap:12px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));margin-top:10px">
                                @csrf
                                @include('admin.academy.courses._lesson-fields', ['lesson' => null, 'prefix' => 'nl'.$module->id])
                                <div style="grid-column:1/-1;display:flex;justify-content:flex-end"><button type="submit" class="btn btn-primary btn-sm">Ajouter la leçon</button></div>
                            </form>
                        </details>
                        <div style="border-top:1px dashed var(--line);padding:8px 0;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                            <x-admin.icon name="award" :size="15" />
                            @if($moduleQuizzes->has($module->id))
                                @php $moduleQuiz = $moduleQuizzes[$module->id]; @endphp
                                <span>Quiz du module : {{ $moduleQuiz->questions_count }} question(s), seuil {{ $moduleQuiz->pass_percent }} %@if(! $moduleQuiz->is_required) (facultatif)@endif</span>
                                <a href="{{ route('admin.academy.courses.quiz.edit', $moduleQuiz) }}" class="btn btn-secondary btn-sm"><x-admin.icon name="edit" :size="14" /> Gérer le quiz</a>
                            @else
                                <form method="post" action="{{ route('admin.academy.courses.quiz.store', $course) }}">
                                    @csrf
                                    <input type="hidden" name="module_id" value="{{ $module->id }}">
                                    <button type="submit" class="btn btn-ghost btn-sm"><x-admin.icon name="plus" :size="14" /> Ajouter un quiz à ce module</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach

            <div style="padding:16px;border-top:1px solid var(--line)">
                <details @if(old('_form') === 'nm' && $errors->any()) open @endif @if($course->modules->isEmpty()) open @endif>
                    <summary class="btn btn-secondary btn-sm" style="cursor:pointer"><x-admin.icon name="plus" :size="14" /> Ajouter un module</summary>
                    <form method="post" action="{{ route('admin.academy.courses.modules.store', $course) }}" class="grid" style="gap:12px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));margin-top:10px">
                        @csrf
                        @include('admin.academy.courses._module-fields', ['module' => null, 'prefix' => 'nm'])
                        <div style="grid-column:1/-1;display:flex;justify-content:flex-end"><button type="submit" class="btn btn-primary btn-sm">Ajouter le module</button></div>
                    </form>
                </details>
            </div>
        </article>

        <article class="card" style="margin-top:16px">
            <h2 style="margin-top:0">Quiz final du cours</h2>
            <p class="text-sm text-muted">Un quiz pour évaluer le niveau de l’apprenant : questions et réponses en ordre aléatoire, score minimal pour réussir. Obligatoire, il conditionne le certificat et le badge de fin de cours.</p>
            @if($finalQuiz)
                <p>{{ $finalQuiz->questions_count }} question(s), seuil de réussite {{ $finalQuiz->pass_percent }} %@if($finalQuiz->questions_per_attempt), {{ $finalQuiz->questions_per_attempt }} tirée(s) au sort par tentative @endif @if(! $finalQuiz->is_required)(facultatif)@endif</p>
                <a href="{{ route('admin.academy.courses.quiz.edit', $finalQuiz) }}" class="btn btn-primary"><x-admin.icon name="edit" :size="15" /> Gérer le quiz final</a>
            @else
                <form method="post" action="{{ route('admin.academy.courses.quiz.store', $course) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary"><x-admin.icon name="plus" :size="15" /> Ajouter un quiz final</button>
                </form>
            @endif
        </article>

        <article class="card card-flush" style="margin-top:16px">
            <div class="card-head"><h2>Avis des membres ({{ $reviews->count() }})</h2></div>
            @forelse($reviews as $review)
                <div class="list-item" style="display:block;border-top:1px solid var(--line)">
                    <div style="display:flex;gap:10px;align-items:flex-start;flex-wrap:wrap">
                        <div class="grow" style="min-width:220px">
                            <strong>{{ $review->user?->name ?? 'Membre supprimé' }}</strong>
                            <span class="text-sm text-muted">— formation {{ $review->course_rating }}/5 @if($review->instructor_rating), formateur {{ $review->instructor_rating }}/5 @endif · {{ $review->created_at->translatedFormat('d M Y') }}</span>
                            @if($review->is_hidden)<x-admin.badge status="archived" label="Masqué" />@endif
                            @if($review->course_comment)<p class="text-sm" style="margin:4px 0 0">Formation : {{ $review->course_comment }}</p>@endif
                            @if($review->instructor_comment)<p class="text-sm" style="margin:4px 0 0">Formateur : {{ $review->instructor_comment }}</p>@endif
                        </div>
                        <form method="post" action="{{ route('admin.academy.courses.reviews.toggle', $review) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn btn-secondary btn-sm">{{ $review->is_hidden ? 'Afficher' : 'Masquer' }}</button>
                        </form>
                        <form method="post" action="{{ route('admin.academy.courses.reviews.destroy', $review) }}" onsubmit="return confirm('Supprimer cet avis ?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm"><x-admin.icon name="trash" :size="15" /> Supprimer</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="text-sm text-muted" style="padding:16px">Aucun avis pour le moment.</p>
            @endforelse
        </article>

        <article class="card" style="margin-top:16px">
            <h2 style="margin-top:0">Supprimer le cours</h2>
            <p class="text-sm text-muted">Les inscriptions et la progression des membres sont supprimées avec le cours. Un cours déjà terminé par des membres ne peut qu’être archivé.</p>
            <form method="post" action="{{ route('admin.academy.courses.destroy', $course) }}" onsubmit="return confirm('Supprimer définitivement ce cours ?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger"><x-admin.icon name="trash" :size="15" /> Supprimer le cours</button>
            </form>
        </article>
    @endif
@endsection
