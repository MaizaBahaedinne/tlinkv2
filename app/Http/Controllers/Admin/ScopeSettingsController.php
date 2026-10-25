<?php

namespace App\Http\Controllers\Admin;

use App\Models\Setting;
use App\Support\OrgScopes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ScopeSettingsController extends AdminController
{
    private const GROUP = 'org';
    private const CATALOG_KEY = 'scopes_catalog';
    private const HIERARCHY_KEY = 'scope_hierarchy';

    public function edit(): View
    {
        return view('admin/structure/scopes', [
            'scopes' => OrgScopes::all(),
            'hierarchy' => OrgScopes::hierarchy(),
            'coreScopes' => OrgScopes::CORE,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $scopes = OrgScopes::all();
        $slugs = array_keys($scopes);
        $allowedParents = implode(',', $slugs);
        $rules = [];

        foreach ($slugs as $slug) {
            $rules['label.'.$slug] = ['required', 'string', 'max:60'];
            $rules['parent.'.$slug] = ['nullable', 'in:'.$allowedParents];
            $rules['delete.'.$slug] = ['nullable', 'boolean'];
        }

        $rules['new_slug'] = ['nullable', 'string', 'max:50', 'regex:/^[a-z0-9][a-z0-9\-]{1,48}[a-z0-9]$/'];
        $rules['new_label'] = ['nullable', 'string', 'max:60'];
        $rules['new_parent'] = ['nullable', 'in:'.$allowedParents];

        $data = $request->validate($rules);
        $labels = $data['label'] ?? [];
        $deletes = array_keys(array_filter($data['delete'] ?? []));

        foreach ($deletes as $slug) {
            if (in_array($slug, OrgScopes::CORE, true)) {
                return back()->withErrors(['delete.'.$slug => 'Ce périmètre système ne peut pas être supprimé.'])->withInput();
            }

            $count = $this->usageCount($slug);
            if ($count > 0) {
                return back()->withErrors(['delete.'.$slug => 'Suppression impossible: ce périmètre est utilisé ('.$count.' référence(s)).'])->withInput();
            }
        }

        $catalog = [];

        foreach ($scopes as $slug => $currentLabel) {
            if (in_array($slug, $deletes, true)) {
                continue;
            }

            $catalog[$slug] = trim((string) ($labels[$slug] ?? $currentLabel));
        }

        $newSlug = trim((string) ($data['new_slug'] ?? ''));
        $newLabel = trim((string) ($data['new_label'] ?? ''));

        if ($newSlug !== '' || $newLabel !== '') {
            if ($newSlug === '' || $newLabel === '') {
                return back()->withErrors(['new_slug' => 'Pour ajouter un périmètre, renseignez le slug et le libellé.'])->withInput();
            }

            if (isset($catalog[$newSlug])) {
                return back()->withErrors(['new_slug' => 'Ce slug existe déjà.'])->withInput();
            }

            $catalog[$newSlug] = $newLabel;
            $slugs[] = $newSlug;
        }

        $parent = $data['parent'] ?? [];
        $hierarchy = [];

        foreach (array_keys($catalog) as $slug) {
            $p = $parent[$slug] ?? null;
            if ($newSlug !== '' && $slug === $newSlug) {
                $p = $data['new_parent'] ?? null;
            }

            $hierarchy[$slug] = ($p !== '' && isset($catalog[$p])) ? $p : null;

            if ($hierarchy[$slug] === $slug) {
                return back()->withErrors(['parent.'.$slug => 'Un périmètre ne peut pas s’hériter de lui-même.'])->withInput();
            }
        }

        if (!OrgScopes::isAcyclic($hierarchy)) {
            return back()->withErrors(['parent' => 'La hiérarchie contient une boucle.'])->withInput();
        }

        Setting::query()->updateOrCreate(
            ['group' => self::GROUP, 'key' => self::CATALOG_KEY],
            ['value' => json_encode($catalog, JSON_UNESCAPED_UNICODE)]
        );

        Setting::query()->updateOrCreate(
            ['group' => self::GROUP, 'key' => self::HIERARCHY_KEY],
            ['value' => json_encode($hierarchy, JSON_UNESCAPED_UNICODE)]
        );

        OrgScopes::flushCache();

        $setting = Setting::query()->where('group', self::GROUP)->where('key', self::CATALOG_KEY)->first();
        $this->audit($request, 'admin.scopes.updated', $setting, ['catalog' => $catalog, 'hierarchy' => $hierarchy]);

        return back()->with('status', 'Périmètres et héritages mis à jour.');
    }

    private function usageCount(string $slug): int
    {
        $targets = [
            ['org_nodes', 'scope'],
            ['memberships', 'scope'],
            ['posts', 'scope'],
            ['foundation_events', 'scope'],
        ];

        $count = 0;

        foreach ($targets as [$table, $column]) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                $count += DB::table($table)->where($column, $slug)->count();
            }
        }

        return $count;
    }
}
