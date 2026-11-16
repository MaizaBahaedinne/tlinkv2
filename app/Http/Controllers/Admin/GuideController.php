<?php

namespace App\Http\Controllers\Admin;

use App\Models\Guide;
use App\Models\GuideStep;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Guides d'utilisation : rédaction des guides et de leurs étapes (texte, notice, capture ou GIF, repère animé).
 */
class GuideController extends AdminController
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(array_keys(Guide::STATUSES))],
            'category' => ['nullable', 'string', 'max:60'],
        ]);

        $guides = Guide::query()
            ->withCount(['steps', 'progress as completed_count' => fn ($query) => $query->whereNotNull('completed_at')])
            ->when($filters['q'] ?? null, function ($query, string $term): void {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $query->where(fn ($inner) => $inner->where('title', 'like', $like)->orWhere('summary', 'like', $like));
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['category'] ?? null, fn ($query, string $category) => $query->where('category', $category))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.guides.index', [
            'guides' => $guides,
            'filters' => $filters,
            'categories' => Guide::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
        ]);
    }

    public function create(): View
    {
        return view('admin.guides.form', [
            'guide' => new Guide(['audience' => 'all', 'icon' => 'book-open', 'accent' => 'rose', 'status' => 'draft', 'sort_order' => ((int) Guide::query()->max('sort_order')) + 10]),
            'categories' => $this->categories(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->guideData($request);

        $guide = Guide::query()->create($data + [
            'slug' => Guide::makeSlug($data['title']),
            'status' => 'draft', // publié seulement après l'ajout des étapes
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        $this->audit($request, 'admin.guide.created', $guide, ['title' => $guide->title]);

        return redirect()->route('admin.guides.edit', $guide)->with('status', 'Guide créé : ajoutez maintenant ses étapes, puis publiez-le.');
    }

    public function edit(Guide $guide): View
    {
        $guide->load('steps');

        return view('admin.guides.form', ['guide' => $guide, 'categories' => $this->categories()]);
    }

    public function update(Request $request, Guide $guide): RedirectResponse
    {
        $data = $this->guideData($request) + $request->validate(['status' => ['required', Rule::in(array_keys(Guide::STATUSES))]]);

        if ($data['status'] === 'published' && ! $guide->steps()->exists()) {
            return back()->withInput()->withErrors(['status' => 'Ajoutez au moins une étape avant de publier le guide.']);
        }

        $guide->update($data + [
            'published_at' => $data['status'] === 'published' ? ($guide->published_at ?? now()) : null,
            'updated_by' => $request->user()->id,
        ]);

        $this->audit($request, 'admin.guide.updated', $guide, ['title' => $guide->title]);

        return back()->with('status', 'Guide enregistré.');
    }

    public function toggle(Request $request, Guide $guide): RedirectResponse
    {
        if (! $guide->isPublished() && ! $guide->steps()->exists()) {
            return back()->withErrors(['status' => 'Ajoutez au moins une étape avant de publier le guide.']);
        }

        $publish = ! $guide->isPublished();
        $guide->update([
            'status' => $publish ? 'published' : 'draft',
            'published_at' => $publish ? ($guide->published_at ?? now()) : null,
            'updated_by' => $request->user()->id,
        ]);

        $this->audit($request, $publish ? 'admin.guide.published' : 'admin.guide.unpublished', $guide, ['title' => $guide->title]);

        return back()->with('status', $publish ? 'Guide publié : les membres peuvent le consulter.' : 'Guide repassé en brouillon.');
    }

    public function destroy(Request $request, Guide $guide): RedirectResponse
    {
        $this->audit($request, 'admin.guide.deleted', $guide, ['title' => $guide->title]);

        $images = $guide->steps()->whereNotNull('image_path')->pluck('image_path');
        $guide->delete();
        $images->each(fn (string $path) => $this->deleteImage($path));

        return redirect()->route('admin.guides.index')->with('status', 'Guide supprimé.');
    }

    public function storeStep(Request $request, Guide $guide): RedirectResponse
    {
        $data = $this->stepData($request);

        $image = $request->hasFile('image') ? $request->file('image')->store('guides', 'public') : null;

        $step = $guide->steps()->create($data + [
            'position' => ((int) $guide->steps()->max('position')) + 1,
            'image_path' => $image,
        ]);

        $this->audit($request, 'admin.guide.step.created', $step, ['guide' => $guide->title, 'title' => $step->title]);

        return redirect()->to(route('admin.guides.edit', $guide).'#etape-'.$step->id)->with('status', 'Étape ajoutée.');
    }

    public function updateStep(Request $request, Guide $guide, GuideStep $step): RedirectResponse
    {
        abort_unless($step->guide_id === $guide->id, 404);

        $data = $this->stepData($request);
        $oldImage = null;

        if ($request->hasFile('image')) {
            $oldImage = $step->image_path;
            $data['image_path'] = $request->file('image')->store('guides', 'public');
        } elseif ($request->boolean('remove_image')) {
            $oldImage = $step->image_path;
            $data['image_path'] = null;
        }

        if (array_key_exists('image_path', $data) ? $data['image_path'] === null : $step->image_path === null) {
            $data['hotspot_x'] = $data['hotspot_y'] = null; // pas d'image, pas de repère
        }

        $step->update($data);
        $this->deleteImage($oldImage);

        $this->audit($request, 'admin.guide.step.updated', $step, ['guide' => $guide->title, 'title' => $step->title]);

        return redirect()->to(route('admin.guides.edit', $guide).'#etape-'.$step->id)->with('status', 'Étape enregistrée.');
    }

    public function moveStep(Request $request, Guide $guide, GuideStep $step): RedirectResponse
    {
        abort_unless($step->guide_id === $guide->id, 404);

        $direction = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'];

        $ids = $guide->steps()->pluck('id')->all();
        $index = array_search($step->id, $ids, true);
        $target = $direction === 'up' ? $index - 1 : $index + 1;

        if ($index !== false && isset($ids[$target])) {
            [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];
            $this->reindex($guide, $ids);
            $this->audit($request, 'admin.guide.step.moved', $step, ['guide' => $guide->title, 'title' => $step->title]);
        }

        return redirect()->to(route('admin.guides.edit', $guide).'#etape-'.$step->id);
    }

    public function destroyStep(Request $request, Guide $guide, GuideStep $step): RedirectResponse
    {
        abort_unless($step->guide_id === $guide->id, 404);

        $this->audit($request, 'admin.guide.step.deleted', $step, ['guide' => $guide->title, 'title' => $step->title]);

        $image = $step->image_path;
        $step->delete();
        $this->deleteImage($image);
        $this->reindex($guide, $guide->steps()->pluck('id')->all());

        // Un guide publié ne peut pas rester sans étape.
        if (! $guide->steps()->exists() && $guide->isPublished()) {
            $guide->update(['status' => 'draft', 'published_at' => null]);
        }

        return redirect()->to(route('admin.guides.edit', $guide).'#etapes')->with('status', 'Étape supprimée.');
    }

    /** @return array<string, mixed> */
    private function guideData(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'summary' => ['nullable', 'string', 'max:500'],
            'category' => ['nullable', 'string', 'max:60'],
            'audience' => ['required', Rule::in(array_keys(Guide::AUDIENCES))],
            'icon' => ['required', Rule::in(array_keys(Guide::ICONS))],
            'accent' => ['required', Rule::in(array_keys(Guide::ACCENTS))],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:99999'],
        ], [
            'title.required' => 'Le titre du guide est obligatoire.',
        ]);

        return [
            'title' => trim($data['title']),
            'summary' => filled($data['summary'] ?? null) ? trim($data['summary']) : null,
            'category' => filled($data['category'] ?? null) ? trim($data['category']) : null,
            'audience' => $data['audience'],
            'icon' => $data['icon'],
            'accent' => $data['accent'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }

    /** @return array<string, mixed> */
    private function stepData(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['nullable', 'string', 'max:3000'],
            'notice_type' => ['nullable', Rule::in(array_keys(GuideStep::NOTICES))],
            'notice' => ['nullable', 'string', 'max:1000'],
            'icon' => ['nullable', Rule::in(array_keys(Guide::ICONS))],
            'image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
            'hotspot_x' => ['nullable', 'integer', 'between:0,100'],
            'hotspot_y' => ['nullable', 'integer', 'between:0,100'],
            'duration' => ['nullable', 'integer', 'between:3,60'],
        ], [
            'title.required' => 'Le titre de l’étape est obligatoire.',
            'image.uploaded' => 'Le fichier n’a pas pu être envoyé : il dépasse la limite du serveur PHP ('.ini_get('upload_max_filesize').' par fichier).',
            'image.max' => 'Le fichier est trop lourd (5 Mo max).',
            'image.image' => 'Le fichier doit être une image ou un GIF.',
            'image.mimes' => 'Formats acceptés : jpg, png, webp ou gif.',
            'hotspot_x.between' => 'Le repère doit rester dans l’image (0 à 100 %).',
            'hotspot_y.between' => 'Le repère doit rester dans l’image (0 à 100 %).',
            'duration.between' => 'La durée d’une étape va de 3 à 60 secondes.',
        ]);

        $notice = filled($data['notice'] ?? null) ? trim($data['notice']) : null;

        return [
            'title' => trim($data['title']),
            'body' => filled($data['body'] ?? null) ? trim($data['body']) : null,
            'notice' => $notice,
            'notice_type' => $notice ? ($data['notice_type'] ?? 'info') : null,
            'icon' => $data['icon'] ?? null,
            'hotspot_x' => isset($data['hotspot_x']) ? (int) $data['hotspot_x'] : null,
            'hotspot_y' => isset($data['hotspot_y']) ? (int) $data['hotspot_y'] : null,
            'duration' => (int) ($data['duration'] ?? GuideStep::DEFAULT_DURATION),
        ];
    }

    /** @param  list<int>  $orderedIds */
    private function reindex(Guide $guide, array $orderedIds): void
    {
        foreach (array_values($orderedIds) as $position => $id) {
            GuideStep::query()->where('guide_id', $guide->id)->whereKey($id)->update(['position' => $position + 1]);
        }
    }

    /** @return \Illuminate\Support\Collection<int, string> */
    private function categories()
    {
        return Guide::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category');
    }

    private function deleteImage(?string $path): void
    {
        if ($path && str_starts_with($path, 'guides/')) {
            Storage::disk('public')->delete($path);
        }
    }
}
