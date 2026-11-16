<?php

namespace App\Http\Controllers;

use App\Models\Guide;
use App\Models\GuideProgress;
use App\Support\AdminContext;
use App\Support\MyOffice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Bibliothèque des guides d'utilisation : liste, lecteur animé pas à pas et suivi de la progression du membre.
 */
class GuideController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'categorie' => ['nullable', 'string', 'max:60'],
        ]);
        $user = $request->user();

        $base = Guide::query()->visibleTo($user);
        $categories = (clone $base)->whereNotNull('category')->distinct()->orderBy('category')->pluck('category');

        $guides = $base
            ->withCount('steps')
            ->withSum('steps', 'duration')
            ->when($filters['q'] ?? null, function ($query, string $term): void {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $query->where(fn ($inner) => $inner->where('title', 'like', $like)->orWhere('summary', 'like', $like)->orWhere('category', 'like', $like));
            })
            ->when($filters['categorie'] ?? null, fn ($query, string $category) => $query->where('category', $category))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (Guide $guide) => $guide->steps_count > 0);

        return view('guides.index', [
            'guides' => $guides,
            'grouped' => $guides->groupBy(fn (Guide $guide) => $guide->category ?? 'Autres guides'),
            'categories' => $categories,
            'filters' => $filters,
            'progress' => GuideProgress::query()->where('user_id', $user->id)->get()->keyBy('guide_id'),
            'completedCount' => GuideProgress::query()->where('user_id', $user->id)->whereNotNull('completed_at')->whereIn('guide_id', $guides->pluck('id'))->count(),
        ]);
    }

    public function show(Request $request, string $slug): View
    {
        $guide = $this->findGuide($request, $slug);
        $guide->load('steps');

        abort_if($guide->steps->isEmpty() && ! $this->canPreviewDrafts($request), 404);

        return view('guides.show', [
            'guide' => $guide,
            'steps' => $guide->steps,
            'progress' => GuideProgress::query()->where('guide_id', $guide->id)->where('user_id', $request->user()->id)->first(),
            'related' => Guide::query()->visibleTo($request->user())->where('id', '!=', $guide->id)->has('steps')
                ->when($guide->category, fn ($query, string $category) => $query->where('category', $category))
                ->orderBy('sort_order')->limit(3)->get(),
        ]);
    }

    public function progress(Request $request, string $slug): JsonResponse
    {
        $guide = $this->findGuide($request, $slug);
        $count = $guide->steps()->count();

        $data = $request->validate([
            'step' => ['required', 'integer', 'min:0', 'max:'.max(0, $count - 1)],
            'completed' => ['nullable', 'boolean'],
        ]);

        $progress = GuideProgress::query()->firstOrNew(['guide_id' => $guide->id, 'user_id' => $request->user()->id]);
        $progress->last_step = (int) $data['step'];

        if ($request->boolean('completed') && $progress->completed_at === null) {
            $progress->completed_at = now();
        }

        $progress->save();

        return response()->json(['saved' => true, 'completed' => $progress->completed_at !== null]);
    }

    /** Un guide est ouvert à tous quand il est publié ; en brouillon, seule l'administration le consulte (aperçu). */
    private function findGuide(Request $request, string $slug): Guide
    {
        $guide = Guide::query()->where('slug', $slug)->firstOrFail();

        if ($this->canPreviewDrafts($request)) {
            return $guide;
        }

        abort_unless($guide->isPublished(), 404);
        abort_if($guide->audience === 'holders' && ! MyOffice::holdsPosition($request->user()), 404);

        return $guide;
    }

    private function canPreviewDrafts(Request $request): bool
    {
        return AdminContext::of($request)->allowsModule(['entity.manage'], 'help.guides', ['read']);
    }
}
