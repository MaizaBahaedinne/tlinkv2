@extends('admin.layouts.app')

@section('title', $quiz->title)
@section('section', 'Tunivisions Academy')

@section('content')
    <x-admin.page-header :title="$quiz->title" :subtitle="$quiz->module ? 'Quiz du module « '.$quiz->module->title.' » — cours « '.$quiz->course->title.' ».' : 'Quiz final du cours « '.$quiz->course->title.' ».'">
        <x-slot:actions>
            <a href="{{ route('admin.academy.courses.edit', $quiz->course) }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Retour au cours</a>
        </x-slot:actions>
    </x-admin.page-header>

    @error('question')<p class="error" style="margin-bottom:12px">{{ $message }}</p>@enderror

    <article class="card">
        <h2 style="margin-top:0">Réglages</h2>
        <form method="post" action="{{ route('admin.academy.courses.quiz.update', $quiz) }}" class="grid" style="gap:14px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr))">
            @csrf
            @method('PUT')
            <div style="grid-column:1/-1">
                <label class="text-sm" for="q-title">Titre</label>
                <input id="q-title" type="text" name="title" value="{{ old('title', $quiz->title) }}" maxlength="160" required>
                @error('title')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div style="grid-column:1/-1">
                <label class="text-sm" for="q-intro">Consignes affichées avant le quiz</label>
                <textarea id="q-intro" name="intro" rows="3" maxlength="3000">{{ old('intro', $quiz->intro) }}</textarea>
                @error('intro')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="text-sm" for="q-pass">Score pour réussir (%)</label>
                <input id="q-pass" type="number" name="pass_percent" min="1" max="100" value="{{ old('pass_percent', $quiz->pass_percent) }}" required>
                @error('pass_percent')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="text-sm" for="q-draw">Questions tirées au sort par tentative</label>
                <input id="q-draw" type="number" name="questions_per_attempt" min="1" max="100" value="{{ old('questions_per_attempt', $quiz->questions_per_attempt) }}" placeholder="Toutes ({{ $quiz->questions->count() }})">
                <p class="hint">Vide = toutes les questions, dans un ordre aléatoire. Un nombre plus petit tire un sous-ensemble différent à chaque tentative.</p>
                @error('questions_per_attempt')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div style="align-self:end">
                <label class="check"><input type="checkbox" name="is_required" value="1" @checked(old('is_required', $quiz->is_required) ? true : false)> Obligatoire pour terminer {{ $quiz->module ? 'le module' : 'le cours' }}</label>
            </div>
            <div style="grid-column:1/-1;display:flex;justify-content:flex-end">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </article>

    <article class="card card-flush" style="margin-top:16px">
        <div class="card-head"><h2>Questions ({{ $quiz->questions->count() }})</h2></div>

        @forelse($quiz->questions as $question)
            <div class="list-item" style="display:block;border-top:1px solid var(--line)">
                <div style="display:flex;gap:10px;align-items:flex-start;flex-wrap:wrap">
                    <div class="grow" style="min-width:220px">
                        <strong>{{ $loop->iteration }}. {{ $question->text }}</strong>
                        <div class="text-sm text-muted">
                            {{ \App\Models\AcademyQuizQuestion::TYPES[$question->type] ?? $question->type }} —
                            @foreach($question->options as $option)
                                <span @if($option->is_correct) style="font-weight:700;color:var(--success)" @endif>{{ $option->text }}</span>@if(! $loop->last), @endif
                            @endforeach
                        </div>
                    </div>
                    <form method="post" action="{{ route('admin.academy.courses.quiz.questions.destroy', $question) }}" onsubmit="return confirm('Supprimer cette question ?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm"><x-admin.icon name="trash" :size="15" /> Supprimer</button>
                    </form>
                </div>
                <details style="margin-top:8px" @if(old('_form') === 'qq'.$question->id && $errors->any()) open @endif>
                    <summary class="btn btn-secondary btn-sm" style="cursor:pointer">Modifier la question</summary>
                    <form method="post" action="{{ route('admin.academy.courses.quiz.questions.update', $question) }}" class="grid" style="gap:12px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));margin-top:10px">
                        @csrf
                        @method('PUT')
                        @include('admin.academy.quizzes._question-fields', ['question' => $question, 'prefix' => 'qq'.$question->id])
                        <div style="grid-column:1/-1;display:flex;justify-content:flex-end"><button type="submit" class="btn btn-primary btn-sm">Enregistrer</button></div>
                    </form>
                </details>
            </div>
        @empty
            <x-admin.empty icon="circle" title="Aucune question" text="Ajoutez des questions : tant que le quiz n’en a pas, il n’est pas proposé aux membres." />
        @endforelse

        <div style="padding:16px;border-top:1px solid var(--line)">
            <details @if(old('_form') === 'nq' && $errors->any()) open @endif @if($quiz->questions->isEmpty()) open @endif>
                <summary class="btn btn-primary btn-sm" style="cursor:pointer"><x-admin.icon name="plus" :size="14" /> Ajouter une question</summary>
                <form method="post" action="{{ route('admin.academy.courses.quiz.questions.store', $quiz) }}" class="grid" style="gap:12px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));margin-top:10px">
                    @csrf
                    @include('admin.academy.quizzes._question-fields', ['question' => null, 'prefix' => 'nq'])
                    <div style="grid-column:1/-1;display:flex;justify-content:flex-end"><button type="submit" class="btn btn-primary btn-sm">Ajouter la question</button></div>
                </form>
            </details>
        </div>
    </article>

    <article class="card" style="margin-top:16px">
        <h2 style="margin-top:0">Supprimer le quiz</h2>
        <p class="text-sm text-muted">Les questions et les tentatives des membres sont supprimées avec le quiz.</p>
        <form method="post" action="{{ route('admin.academy.courses.quiz.destroy', $quiz) }}" onsubmit="return confirm('Supprimer ce quiz et toutes ses tentatives ?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger"><x-admin.icon name="trash" :size="15" /> Supprimer le quiz</button>
        </form>
    </article>
@endsection
