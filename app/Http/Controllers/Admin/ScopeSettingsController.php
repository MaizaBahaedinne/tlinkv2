<?php

namespace App\Http\Controllers\Admin;

use App\Models\Setting;
use App\Support\OrgScopes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ScopeSettingsController extends AdminController
{
    private const GROUP = 'org';
    private const CATALOG_KEY = 'scopes_catalog';
    private const HIERARCHY_KEY = 'scope_hierarchy';
    private const DESCRIPTIONS_KEY = 'scopes_descriptions';
    private const LOGOS_KEY = 'scopes_logos';
    private const TYPES_KEY = 'scopes_types';
    private const LOGO_RULES = ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'];

    public function edit(): View
    {
        return view('admin/structure/scopes', [
            'scopes' => OrgScopes::all(),
            'hierarchy' => OrgScopes::hierarchy(),
            'descriptions' => OrgScopes::descriptions(),
            'logos' => collect(OrgScopes::logos())
                ->filter(fn (string $path) => Storage::disk('public')->exists($path))
                ->map(fn (string $path) => Storage::disk('public')->url($path))
                ->all(),
            'coreScopes' => OrgScopes::CORE,
            'types' => OrgScopes::types(),
            'typeLabels' => OrgScopes::TYPES,
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
            $rules['description.'.$slug] = ['nullable', 'string', 'max:300'];
            $rules['parent.'.$slug] = ['nullable', 'in:'.$allowedParents];
            $rules['type.'.$slug] = ['nullable', Rule::in(array_keys(OrgScopes::TYPES))];
            $rules['delete.'.$slug] = ['nullable', 'boolean'];
            $rules['logo.'.$slug] = self::LOGO_RULES;
            $rules['remove_logo.'.$slug] = ['nullable', 'boolean'];
        }

        $rules['new_logo'] = self::LOGO_RULES;

        $rules['new_slug'] = ['nullable', 'string', 'max:50', 'regex:/^[a-z0-9][a-z0-9\-]{1,48}[a-z0-9]$/'];
        $rules['new_label'] = ['nullable', 'string', 'max:60'];
        $rules['new_description'] = ['nullable', 'string', 'max:300'];
        $rules['new_parent'] = ['nullable', 'in:'.$allowedParents];
        $rules['new_type'] = ['nullable', Rule::in(array_keys(OrgScopes::TYPES))];

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
        $descriptions = [];
        $descriptionsInput = $data['description'] ?? [];

        foreach ($scopes as $slug => $currentLabel) {
            if (in_array($slug, $deletes, true)) {
                continue;
            }

            $catalog[$slug] = trim((string) ($labels[$slug] ?? $currentLabel));
            $desc = trim((string) ($descriptionsInput[$slug] ?? ''));
            if ($desc !== '') {
                $descriptions[$slug] = $desc;
            }
        }

        $newSlug = trim((string) ($data['new_slug'] ?? ''));
        $newLabel = trim((string) ($data['new_label'] ?? ''));
        $newDescription = trim((string) ($data['new_description'] ?? ''));

        if ($newSlug !== '' || $newLabel !== '') {
            if ($newSlug === '' || $newLabel === '') {
                return back()->withErrors(['new_slug' => 'Pour ajouter un périmètre, renseignez le slug et le libellé.'])->withInput();
            }

            if (isset($catalog[$newSlug])) {
                return back()->withErrors(['new_slug' => 'Ce slug existe déjà.'])->withInput();
            }

            $catalog[$newSlug] = $newLabel;
            if ($newDescription !== '') {
                $descriptions[$newSlug] = $newDescription;
            }
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

        $logos = $this->syncLogos($request, array_keys($catalog), $newSlug, $data['remove_logo'] ?? []);

        // Type : valeur saisie, sinon type actuel (un champ absent ne change rien).
        $currentTypes = OrgScopes::types();
        $types = [];

        foreach (array_keys($catalog) as $slug) {
            $input = $slug === $newSlug ? ($data['new_type'] ?? null) : ($data['type'][$slug] ?? null);
            $types[$slug] = $input ?: ($currentTypes[$slug] ?? 'project');
        }

        Setting::query()->updateOrCreate(
            ['group' => self::GROUP, 'key' => self::TYPES_KEY],
            ['value' => json_encode($types, JSON_UNESCAPED_UNICODE)]
        );

        Setting::query()->updateOrCreate(
            ['group' => self::GROUP, 'key' => self::LOGOS_KEY],
            ['value' => json_encode($logos, JSON_UNESCAPED_UNICODE)]
        );

        Setting::query()->updateOrCreate(
            ['group' => self::GROUP, 'key' => self::CATALOG_KEY],
            ['value' => json_encode($catalog, JSON_UNESCAPED_UNICODE)]
        );

        Setting::query()->updateOrCreate(
            ['group' => self::GROUP, 'key' => self::HIERARCHY_KEY],
            ['value' => json_encode($hierarchy, JSON_UNESCAPED_UNICODE)]
        );

        Setting::query()->updateOrCreate(
            ['group' => self::GROUP, 'key' => self::DESCRIPTIONS_KEY],
            ['value' => json_encode($descriptions, JSON_UNESCAPED_UNICODE)]
        );

        OrgScopes::flushCache();

        $setting = Setting::query()->where('group', self::GROUP)->where('key', self::CATALOG_KEY)->first();
        $this->audit($request, 'admin.scopes.updated', $setting, ['catalog' => $catalog, 'hierarchy' => $hierarchy, 'descriptions' => $descriptions, 'types' => $types]);

        return back()->with('status', 'Périmètres et héritages mis à jour.');
    }

    /**
     * @param array<int, string> $keptSlugs
     * @param array<string, mixed> $removeFlags
     * @return array<string, string>
     */
    private function syncLogos(Request $request, array $keptSlugs, string $newSlug, array $removeFlags): array
    {
        $existing = OrgScopes::logos();
        $disk = Storage::disk('public');
        $logos = [];

        foreach ($existing as $slug => $path) {
            if (! in_array($slug, $keptSlugs, true)) {
                $disk->delete($path);
            }
        }

        foreach ($keptSlugs as $slug) {
            $path = $existing[$slug] ?? null;
            $file = $slug === $newSlug ? $request->file('new_logo') : $request->file('logo.'.$slug);

            if ($path && (! empty($removeFlags[$slug]) || $file)) {
                $disk->delete($path);
                $path = null;
            }

            if ($file) {
                $path = $file->store('scopes/logos', 'public');
            }

            if ($path) {
                $logos[$slug] = $path;
            }
        }

        return $logos;
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
