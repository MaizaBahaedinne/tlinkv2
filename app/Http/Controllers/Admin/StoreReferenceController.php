<?php

namespace App\Http\Controllers\Admin;

use App\Models\StoreCategory;
use App\Models\StoreColor;
use App\Models\StoreSize;
use App\Models\StoreType;
use App\Support\Store\StoreCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Référentiels du catalogue : catégories, types d'article, couleurs et tailles. */
class StoreReferenceController extends AdminController
{
    private const KINDS = [
        'categories' => [StoreCategory::class, 'Catégorie'],
        'types' => [StoreType::class, 'Type'],
        'colors' => [StoreColor::class, 'Couleur'],
        'sizes' => [StoreSize::class, 'Taille'],
    ];

    public function index(): View
    {
        return view('admin.store.refs', [
            'categories' => StoreCategory::query()->withCount('products')->orderBy('sort_order')->orderBy('name')->get(),
            'types' => StoreType::query()->withCount('products')->orderBy('sort_order')->orderBy('name')->get(),
            'colors' => StoreColor::query()->orderBy('sort_order')->orderBy('name')->get(),
            'sizes' => StoreSize::query()->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request, string $kind): RedirectResponse
    {
        [$class, $label] = self::KINDS[$kind];
        $data = $this->validated($request, $kind);

        $record = $class::query()->create($this->payload($class, $kind, $data));
        $this->audit($request, 'admin.store.reference.created', $record, ['kind' => $kind, 'name' => $data['name']]);

        return back()->with('status', $label.' ajoutée.');
    }

    public function update(Request $request, string $kind, int $id): RedirectResponse
    {
        [$class, $label] = self::KINDS[$kind];
        $record = $class::query()->findOrFail($id);
        $data = $this->validated($request, $kind);

        $record->update($this->payload($class, $kind, $data, $record->id));
        $this->audit($request, 'admin.store.reference.updated', $record, ['kind' => $kind, 'name' => $data['name']]);

        return back()->with('status', $label.' mise à jour.');
    }

    public function destroy(Request $request, string $kind, int $id): RedirectResponse
    {
        [$class] = self::KINDS[$kind];
        $record = $class::query()->findOrFail($id);
        $name = $record->name;

        $record->delete();
        $this->audit($request, 'admin.store.reference.deleted', $record, ['kind' => $kind, 'name' => $name]);

        return back()->with('status', 'Élément supprimé : les articles qui l’utilisaient n’y sont plus rattachés.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, string $kind): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:'.($kind === 'sizes' ? 30 : 80)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];

        if ($kind === 'colors') {
            $rules['hex'] = ['required', 'regex:/^#[0-9a-fA-F]{6}$/'];
        }

        if ($kind === 'categories') {
            $rules['is_active'] = ['nullable', 'boolean'];
        }

        return $request->validate($rules);
    }

    /**
     * @param class-string<\Illuminate\Database\Eloquent\Model> $class
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function payload(string $class, string $kind, array $data, ?int $ignoreId = null): array
    {
        $payload = ['name' => $data['name'], 'sort_order' => (int) ($data['sort_order'] ?? 0)];

        if ($kind === 'colors') {
            $payload['hex'] = strtoupper($data['hex']);
        }

        if (in_array($kind, ['categories', 'types'], true)) {
            $payload['slug'] = StoreCatalog::uniqueSlug($class, $data['name'], $ignoreId);
        }

        if ($kind === 'categories') {
            $payload['is_active'] = (bool) ($data['is_active'] ?? false);
        }

        return $payload;
    }
}
