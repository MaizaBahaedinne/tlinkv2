<?php

namespace App\Http\Controllers\Admin;

use App\Models\AppModule;
use App\Support\ModuleCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Catalogue des modules et sous-modules de T.Link et des actions CRUD que chacun supporte
 * (base des habilitations par poste). Réservé à l'administration globale.
 */
class AppModuleController extends AdminController
{
    public function index(Request $request): View
    {
        $this->authorizeGlobal($request);

        $modules = AppModule::query()->whereNull('parent_id')->with('children')->orderBy('sort_order')->orderBy('id')->get();

        return view('admin.modules.index', [
            'modules' => $modules,
            'total' => AppModule::query()->count(),
            'actions' => AppModule::ACTIONS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeGlobal($request);

        $data = $request->validate([
            'parent_id' => ['nullable', 'integer', Rule::exists('app_modules', 'id')->whereNull('parent_id')],
            'key' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+([._-][a-z0-9]+)*$/', 'unique:app_modules,key'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'actions' => ['nullable', 'array'],
            'actions.*' => [Rule::in(array_keys(AppModule::ACTIONS))],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'key.regex' => 'La clé ne contient que des minuscules, chiffres et séparateurs (. _ -), par exemple « feed.polls ».',
            'parent_id.exists' => 'Le module parent doit être un module principal (deux niveaux maximum).',
        ]);

        $parentId = $data['parent_id'] ?? null;

        $module = AppModule::query()->create([
            'parent_id' => $parentId,
            'key' => $data['key'],
            'name' => trim($data['name']),
            'description' => $data['description'] ?? null,
            'actions' => array_values($data['actions'] ?? []),
            'is_active' => $request->boolean('is_active', true),
            'is_system' => false,
            'sort_order' => ((int) AppModule::query()->where('parent_id', $parentId)->max('sort_order')) + 1,
        ]);

        $this->audit($request, 'admin.module.created', $module, ['key' => $module->key, 'name' => $module->name]);

        return back()->with('status', $parentId ? 'Sous-module ajouté.' : 'Module ajouté.');
    }

    public function update(Request $request, AppModule $module): RedirectResponse
    {
        $this->authorizeGlobal($request);

        $data = $request->validate([
            'parent_id' => ['nullable', 'integer', Rule::exists('app_modules', 'id')->whereNull('parent_id')],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'actions' => ['nullable', 'array'],
            'actions.*' => [Rule::in(array_keys(AppModule::ACTIONS))],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ], ['parent_id.exists' => 'Le module parent doit être un module principal (deux niveaux maximum).']);

        $parentId = $data['parent_id'] ?? null;

        if ($parentId !== null && ((int) $parentId === $module->id || $module->children()->exists())) {
            throw ValidationException::withMessages(['parent_id' => 'Ce module a des sous-modules : il ne peut pas devenir lui-même un sous-module.']);
        }

        $module->update([
            'parent_id' => $module->is_system ? $module->parent_id : $parentId,
            'name' => trim($data['name']),
            'description' => $data['description'] ?? null,
            'actions' => array_values($data['actions'] ?? []),
            'sort_order' => (int) ($data['sort_order'] ?? $module->sort_order),
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->audit($request, 'admin.module.updated', $module, ['key' => $module->key, 'active' => $module->is_active, 'actions' => $module->actions]);

        return back()->with('status', 'Module modifié.');
    }

    public function destroy(Request $request, AppModule $module): RedirectResponse
    {
        $this->authorizeGlobal($request);

        if ($module->is_system) {
            throw ValidationException::withMessages(['module' => 'Un module du catalogue d’origine ne peut pas être supprimé : désactivez-le.']);
        }

        if ($module->children()->exists()) {
            throw ValidationException::withMessages(['module' => 'Supprimez d’abord les sous-modules de « '.$module->name.' ».']);
        }

        $this->audit($request, 'admin.module.deleted', $module, ['key' => $module->key, 'name' => $module->name]);
        $module->delete();

        return back()->with('status', 'Module supprimé.');
    }

    /** Réajoute les modules du catalogue d'origine qui auraient été créés après l'installation. */
    public function sync(Request $request): RedirectResponse
    {
        $this->authorizeGlobal($request);

        $added = ModuleCatalog::sync();

        return back()->with('status', $added > 0 ? $added.' module(s) du catalogue ajouté(s).' : 'Le catalogue est déjà complet.');
    }

    private function authorizeGlobal(Request $request): void
    {
        abort_unless($this->context($request)->isGlobal('entity.manage'), 403, 'Réservé à l’administration globale.');
    }
}
