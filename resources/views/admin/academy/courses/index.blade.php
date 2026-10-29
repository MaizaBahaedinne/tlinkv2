@extends('admin.layouts.app')

@section('title', 'Cours Academy')
@section('section', 'Tunivisions Academy')

@section('content')
    <x-admin.page-header title="Cours" subtitle="Les cours de Tunivisions Academy : modules, leçons, badges et points attribués aux apprenants.">
        <x-slot:actions>
            <a href="{{ route('admin.academy.courses.create') }}" class="btn btn-primary"><x-admin.icon name="plus" :size="16" /> Nouveau cours</a>
        </x-slot:actions>
    </x-admin.page-header>

    @error('course')<p class="error" style="margin-bottom:12px">{{ $message }}</p>@enderror

    <article class="card card-flush">
        <div class="toolbar">
            <form method="get" action="{{ route('admin.academy.courses.index') }}">
                <div class="search">
                    <x-admin.icon name="search" :size="16" />
                    <input type="search" name="q" value="{{ $term }}" placeholder="Rechercher un cours…">
                </div>
                <select name="status" aria-label="Statut">
                    <option value="">Tous les statuts</option>
                    @foreach(\App\Models\AcademyCourse::STATUSES as $value => $label)
                        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button class="btn btn-primary btn-sm" type="submit">Filtrer</button>
                @if($term !== '' || $status !== '')
                    <a href="{{ route('admin.academy.courses.index') }}" class="btn btn-ghost btn-sm">Réinitialiser</a>
                @endif
            </form>
        </div>

        @if($courses->count())
            <div class="list">
                @foreach($courses as $course)
                    <div class="list-item">
                        <div class="grow">
                            <a href="{{ route('admin.academy.courses.edit', $course) }}"><strong style="display:inline">{{ $course->title }}</strong></a>
                            <x-admin.badge :status="$course->status" :label="\App\Models\AcademyCourse::STATUSES[$course->status] ?? $course->status" />
                            <div class="text-sm text-muted">
                                {{ $course->levelLabel() }} · {{ $course->priceLabel() }} ·
                                {{ $course->modules_count }} module(s), {{ $course->lessons_count }} leçon(s) · {{ $course->enrollments_count }} inscrit(s)
                            </div>
                        </div>
                        <a href="{{ route('admin.academy.courses.edit', $course) }}" class="btn btn-secondary btn-sm"><x-admin.icon name="edit" :size="15" /> Modifier</a>
                    </div>
                @endforeach
            </div>
            {{ $courses->links('admin.partials.pagination') }}
        @else
            <x-admin.empty icon="graduation" title="Aucun cours" text="Créez un premier cours, puis ajoutez ses modules et ses leçons." />
        @endif
    </article>
@endsection
