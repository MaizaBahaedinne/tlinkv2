<?php

namespace App\Http\Controllers\Admin;

use App\Models\AcademyCourse;
use App\Models\AcademyLesson;
use App\Models\AcademyModule;
use App\Models\Badge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Cours de Tunivisions Academy : fiche du cours, modules et leçons, badges et points associés.
 */
class AcademyCourseController extends AdminController
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');

        $courses = AcademyCourse::query()
            ->withCount(['modules', 'lessons', 'enrollments'])
            ->when($term !== '', fn ($query) => $query->where('title', 'like', '%'.addcslashes($term, '\\%_').'%'))
            ->when(array_key_exists($status, AcademyCourse::STATUSES), fn ($query) => $query->where('status', $status))
            ->orderBy('sort_order')->orderByDesc('id')
            ->paginate(30)->withQueryString();

        return view('admin.academy.courses.index', ['courses' => $courses, 'term' => $term, 'status' => $status]);
    }

    public function create(): View
    {
        return view('admin.academy.courses.form', ['course' => new AcademyCourse(['status' => 'draft', 'level' => 'beginner', 'included_in_plans' => true, 'completion_points' => 50]), 'badges' => $this->badges()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $course = AcademyCourse::query()->create($data + [
            'slug' => $this->uniqueSlug($data['title']),
            'cover_path' => $request->hasFile('cover') ? $request->file('cover')->store('academy/covers', 'public') : null,
            'sort_order' => ((int) AcademyCourse::query()->max('sort_order')) + 1,
        ]);

        $this->audit($request, 'admin.academy.course.created', $course, ['title' => $course->title]);

        return redirect()->route('admin.academy.courses.edit', $course)->with('status', 'Cours créé : ajoutez maintenant ses modules et ses leçons.');
    }

    public function edit(AcademyCourse $course): View
    {
        $course->load('modules.lessons');

        return view('admin.academy.courses.form', ['course' => $course, 'badges' => $this->badges()]);
    }

    public function update(Request $request, AcademyCourse $course): RedirectResponse
    {
        $data = $this->validated($request);
        $oldCover = null;

        if ($request->hasFile('cover')) {
            $oldCover = $course->cover_path;
            $data['cover_path'] = $request->file('cover')->store('academy/covers', 'public');
        } elseif ($request->boolean('remove_cover')) {
            $oldCover = $course->cover_path;
            $data['cover_path'] = null;
        }

        $course->update($data);
        $this->deleteCover($oldCover);
        $this->audit($request, 'admin.academy.course.updated', $course, ['title' => $course->title, 'status' => $course->status]);

        return back()->with('status', 'Cours enregistré.');
    }

    public function destroy(Request $request, AcademyCourse $course): RedirectResponse
    {
        if ($course->enrollments()->whereNotNull('completed_at')->exists()) {
            return back()->withErrors(['course' => 'Des membres ont terminé ce cours (certificats délivrés) : archivez-le plutôt que de le supprimer.']);
        }

        $this->audit($request, 'admin.academy.course.deleted', $course, ['title' => $course->title]);
        $cover = $course->cover_path;
        $course->delete();
        $this->deleteCover($cover);

        return redirect()->route('admin.academy.courses.index')->with('status', 'Cours supprimé.');
    }

    public function storeModule(Request $request, AcademyCourse $course): RedirectResponse
    {
        $data = $this->validatedModule($request);
        $data['sort_order'] ??= ((int) $course->modules()->max('sort_order')) + 1;
        $module = $course->modules()->create($data);

        $this->audit($request, 'admin.academy.module.saved', $module, ['title' => $module->title]);

        return back()->with('status', 'Module ajouté.');
    }

    public function updateModule(Request $request, AcademyModule $module): RedirectResponse
    {
        $module->update($this->validatedModule($request, $module->sort_order));
        $this->audit($request, 'admin.academy.module.saved', $module, ['title' => $module->title]);

        return back()->with('status', 'Module enregistré.');
    }

    public function destroyModule(Request $request, AcademyModule $module): RedirectResponse
    {
        $this->audit($request, 'admin.academy.module.deleted', $module, ['title' => $module->title]);
        $module->delete();

        return back()->with('status', 'Module supprimé avec ses leçons.');
    }

    public function storeLesson(Request $request, AcademyModule $module): RedirectResponse
    {
        $data = $this->validatedLesson($request);
        $data['sort_order'] ??= ((int) $module->lessons()->max('sort_order')) + 1;
        $lesson = $module->lessons()->create($data);

        $this->audit($request, 'admin.academy.lesson.saved', $lesson, ['title' => $lesson->title]);

        return back()->with('status', 'Leçon ajoutée.');
    }

    public function updateLesson(Request $request, AcademyLesson $lesson): RedirectResponse
    {
        $lesson->update($this->validatedLesson($request, $lesson->sort_order));
        $this->audit($request, 'admin.academy.lesson.saved', $lesson, ['title' => $lesson->title]);

        return back()->with('status', 'Leçon enregistrée.');
    }

    public function destroyLesson(Request $request, AcademyLesson $lesson): RedirectResponse
    {
        $this->audit($request, 'admin.academy.lesson.deleted', $lesson, ['title' => $lesson->title]);
        $lesson->delete();

        return back()->with('status', 'Leçon supprimée.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'summary' => ['nullable', 'string', 'max:300'],
            'description' => ['nullable', 'string', 'max:10000'],
            'level' => ['required', Rule::in(array_keys(AcademyCourse::LEVELS))],
            'instructor_name' => ['nullable', 'string', 'max:120'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'included_in_plans' => ['nullable', 'boolean'],
            'badge_id' => ['nullable', 'integer', 'exists:badges,id'],
            'completion_points' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'status' => ['required', Rule::in(array_keys(AcademyCourse::STATUSES))],
            'cover' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_cover' => ['nullable', 'boolean'],
        ]);

        unset($data['cover'], $data['remove_cover']);
        $data['included_in_plans'] = $request->boolean('included_in_plans');
        $data['completion_points'] = (int) ($data['completion_points'] ?? 0);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedModule(Request $request, ?int $currentSort = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'badge_id' => ['nullable', 'integer', 'exists:badges,id'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        if ($currentSort !== null && ! isset($data['sort_order'])) {
            $data['sort_order'] = $currentSort;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedLesson(Request $request, ?int $currentSort = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'kind' => ['required', Rule::in(array_keys(AcademyLesson::KINDS))],
            'video_url' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string', 'max:20000'],
            'duration_minutes' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'points' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'is_preview' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ], [
            'content.max' => 'Le contenu est trop long (20 000 caractères maximum).',
        ]);

        if ($data['kind'] === 'video') {
            if (blank($data['video_url'] ?? null) || (new AcademyLesson(['video_url' => $data['video_url']]))->embedUrl() === null) {
                throw ValidationException::withMessages(['video_url' => 'Indiquez un lien YouTube ou Vimeo en https.']);
            }
        } else {
            $data['video_url'] = null;
        }

        $data['points'] = (int) ($data['points'] ?? 0);
        $data['is_preview'] = $request->boolean('is_preview');

        if ($currentSort !== null && ! isset($data['sort_order'])) {
            $data['sort_order'] = $currentSort;
        }

        return $data;
    }

    /**
     * @return array<int, string>
     */
    private function badges(): array
    {
        return Badge::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->pluck('name', 'id')->all();
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'cours';
        $slug = $base;

        for ($i = 2; AcademyCourse::query()->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    private function deleteCover(?string $path): void
    {
        if ($path && str_starts_with($path, 'academy/covers/')) {
            Storage::disk('public')->delete($path);
        }
    }
}
