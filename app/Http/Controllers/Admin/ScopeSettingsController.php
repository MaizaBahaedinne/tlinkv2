<?php

namespace App\Http\Controllers\Admin;

use App\Models\Setting;
use App\Support\OrgScopes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScopeSettingsController extends AdminController
{
    private const GROUP = 'org';
    private const KEY = 'scope_hierarchy';

    public function edit(): View
    {
        return view('admin/structure/scopes', [
            'scopes' => OrgScopes::ALL,
            'hierarchy' => OrgScopes::hierarchy(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [];
        $all = array_keys(OrgScopes::ALL);

        foreach ($all as $slug) {
            $rules['parent.'.$slug] = ['nullable', 'in:'.implode(',', $all)];
        }

        $data = $request->validate($rules);
        $parent = $data['parent'] ?? [];

        $hierarchy = [];

        foreach ($all as $slug) {
            $p = $parent[$slug] ?? null;
            $hierarchy[$slug] = $p !== '' ? $p : null;

            if ($hierarchy[$slug] === $slug) {
                return back()->withErrors(['parent.'.$slug => 'Un périmètre ne peut pas s’hériter de lui-même.'])->withInput();
            }
        }

        if (!OrgScopes::isAcyclic($hierarchy)) {
            return back()->withErrors(['parent' => 'La hiérarchie contient une boucle.'])->withInput();
        }

        Setting::query()->updateOrCreate(
            ['group' => self::GROUP, 'key' => self::KEY],
            ['value' => json_encode($hierarchy, JSON_UNESCAPED_UNICODE)]
        );

        OrgScopes::flushHierarchyCache();

        $setting = Setting::query()->where('group', self::GROUP)->where('key', self::KEY)->first();
        $this->audit($request, 'admin.scopes.updated', $setting, ['hierarchy' => $hierarchy]);

        return back()->with('status', 'Hiérarchie des périmètres mise à jour.');
    }
}
