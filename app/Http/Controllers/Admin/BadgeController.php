<?php

namespace App\Http\Controllers\Admin;

use App\Models\Badge;
use App\Models\Mission;
use App\Models\MissionCheckpoint;
use App\Support\Gamification\ActivityCatalog;
use App\Support\Gamification\GamificationEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Badges (bronze, argent, or, platine), leurs missions et les points de contrôle qui mesurent l'activité des membres.
 */
class BadgeController extends AdminController
{
    public function index(Request $request): View
    {
        return view('admin.badges.index', [
            'badges' => Badge::query()->withCount(['missions', 'userBadges'])->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateBadge($request, true);

        $badge = Badge::query()->create($data + [
            'slug' => $this->uniqueSlug($data['name']),
            'sort_order' => ((int) Badge::query()->max('sort_order')) + 1,
        ]);

        $this->audit($request, 'admin.badge.created', $badge, ['name' => $badge->name, 'tier' => $badge->tier]);

        return redirect()->route('admin.badges.edit', $badge)->with('status', 'Badge créé : ajoutez maintenant ses missions.');
    }

    public function edit(Request $request, Badge $badge): View
    {
        $badge->load(['missions.checkpoints']);

        return view('admin.badges.edit', [
            'badge' => $badge,
            'triggers' => ActivityCatalog::grouped(),
        ]);
    }

    public function update(Request $request, Badge $badge): RedirectResponse
    {
        $data = $this->validateBadge($request);
        $data['sort_order'] = (int) $request->input('sort_order', $badge->sort_order);

        $badge->update($data);

        $this->audit($request, 'admin.badge.updated', $badge, ['name' => $badge->name, 'tier' => $badge->tier, 'active' => $badge->is_active]);

        return back()->with('status', 'Badge modifié.');
    }

    public function destroy(Request $request, Badge $badge): RedirectResponse
    {
        $this->audit($request, 'admin.badge.deleted', $badge, ['name' => $badge->name]);
        $badge->delete();

        return redirect()->route('admin.badges.index')->with('status', 'Badge supprimé.');
    }

    public function storeMission(Request $request, Badge $badge): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $mission = $badge->missions()->create([
            'title' => trim($data['title']),
            'description' => $data['description'] ?? null,
            'sort_order' => ((int) $badge->missions()->max('sort_order')) + 1,
        ]);

        $this->audit($request, 'admin.badge.mission.created', $mission, ['badge' => $badge->name, 'title' => $mission->title]);

        return back()->with('status', 'Mission ajoutée : ajoutez ses points de contrôle.');
    }

    public function updateMission(Request $request, Mission $mission): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $mission->update([
            'title' => trim($data['title']),
            'description' => $data['description'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? $mission->sort_order),
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->audit($request, 'admin.badge.mission.updated', $mission, ['title' => $mission->title, 'active' => $mission->is_active]);

        return back()->with('status', 'Mission modifiée.');
    }

    public function destroyMission(Request $request, Mission $mission): RedirectResponse
    {
        $this->audit($request, 'admin.badge.mission.deleted', $mission, ['title' => $mission->title]);
        $mission->delete();

        return back()->with('status', 'Mission supprimée.');
    }

    public function storeCheckpoint(Request $request, Mission $mission): RedirectResponse
    {
        $data = $this->validateCheckpoint($request);

        $checkpoint = $mission->checkpoints()->create($data + [
            'sort_order' => ((int) $mission->checkpoints()->max('sort_order')) + 1,
        ]);

        $this->audit($request, 'admin.badge.checkpoint.created', $checkpoint, ['mission' => $mission->title, 'trigger' => $checkpoint->trigger_key, 'target' => $checkpoint->target]);

        return back()->with('status', 'Point de contrôle ajouté.');
    }

    public function updateCheckpoint(Request $request, MissionCheckpoint $checkpoint): RedirectResponse
    {
        $data = $this->validateCheckpoint($request);
        $data['sort_order'] = (int) $request->input('sort_order', $checkpoint->sort_order);

        $checkpoint->update($data);

        $this->audit($request, 'admin.badge.checkpoint.updated', $checkpoint, ['trigger' => $checkpoint->trigger_key, 'target' => $checkpoint->target]);

        return back()->with('status', 'Point de contrôle modifié.');
    }

    public function destroyCheckpoint(Request $request, MissionCheckpoint $checkpoint): RedirectResponse
    {
        $this->audit($request, 'admin.badge.checkpoint.deleted', $checkpoint, ['title' => $checkpoint->title]);
        $checkpoint->delete();

        return back()->with('status', 'Point de contrôle supprimé.');
    }

    /** Recalcule tous les membres : à lancer après avoir modifié des missions pour rattraper l'historique. */
    public function sync(Request $request): RedirectResponse
    {
        $result = GamificationEngine::refreshEveryone();

        $this->audit($request, 'admin.badge.synced', $request->user(), $result);

        return back()->with('status', $result['users'].' membre(s) recalculé(s), '.$result['awarded'].' badge(s) décerné(s).'.($result['failed'] > 0 ? ' '.$result['failed'].' membre(s) en erreur : voir storage/logs/laravel.log.' : ''));
    }

    /**
     * @return array{name: string, description: ?string, tier: string, icon: string, is_active: bool}
     */
    private function validateBadge(Request $request, bool $activeByDefault = false): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'tier' => ['required', Rule::in(array_keys(Badge::TIERS))],
            'icon' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9 \-]+$/'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return [
            'name' => trim($data['name']),
            'description' => $data['description'] ?? null,
            'tier' => $data['tier'],
            'icon' => trim($data['icon'] ?? '') !== '' ? trim($data['icon']) : 'fa-solid fa-medal',
            'is_active' => $request->boolean('is_active', $activeByDefault),
        ];
    }

    /**
     * @return array{title: string, trigger_key: string, target: int}
     */
    private function validateCheckpoint(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'trigger_key' => ['required', Rule::in(array_keys(ActivityCatalog::triggers()))],
            'target' => ['required', 'integer', 'min:1', 'max:100000'],
        ]);

        return [
            'title' => trim($data['title']),
            'trigger_key' => $data['trigger_key'],
            'target' => (int) $data['target'],
        ];
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'badge';
        $slug = $base;
        $i = 2;

        while (Badge::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
