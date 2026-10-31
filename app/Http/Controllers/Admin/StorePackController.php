<?php

namespace App\Http\Controllers\Admin;

use App\Models\StorePack;
use App\Models\StorePackItem;
use App\Models\StoreProduct;
use App\Models\StoreVariant;
use App\Support\Store\StoreCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StorePackController extends AdminController
{
    private const IMAGE_DIR = 'store/packs';

    public function index(): View
    {
        return view('admin.store.packs.index', [
            'packs' => StorePack::query()->with(['items.product.variants', 'items.variant'])->orderByDesc('id')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.store.packs.form', $this->formData(new StorePack(['is_active' => true])));
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $items] = $this->validated($request);
        $data['slug'] = StoreCatalog::uniqueSlug(StorePack::class, $data['name']);
        $data['image_path'] = $request->hasFile('image') ? $request->file('image')->store(self::IMAGE_DIR, 'public') : null;

        $pack = DB::transaction(function () use ($data, $items): StorePack {
            $pack = StorePack::query()->create($data);
            $this->syncItems($pack, $items);

            return $pack;
        });

        $this->audit($request, 'admin.store.pack.created', $pack, ['name' => $pack->name]);

        return redirect()->route('admin.store.packs.edit', $pack)->with('status', 'Pack créé.');
    }

    public function edit(StorePack $pack): View
    {
        return view('admin.store.packs.form', $this->formData($pack));
    }

    public function update(Request $request, StorePack $pack): RedirectResponse
    {
        [$data, $items] = $this->validated($request);
        $data['slug'] = StoreCatalog::uniqueSlug(StorePack::class, $data['name'], $pack->id);
        $old = $pack->image_path;

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store(self::IMAGE_DIR, 'public');
        } elseif ($request->boolean('remove_image')) {
            $data['image_path'] = null;
        }

        DB::transaction(function () use ($pack, $data, $items): void {
            $pack->update($data);
            $this->syncItems($pack, $items);
        });

        if (array_key_exists('image_path', $data) && $old && str_starts_with($old, self::IMAGE_DIR.'/')) {
            Storage::disk('public')->delete($old);
        }

        $this->audit($request, 'admin.store.pack.updated', $pack, ['name' => $pack->name]);

        return back()->with('status', 'Pack enregistré.');
    }

    public function destroy(Request $request, StorePack $pack): RedirectResponse
    {
        $image = $pack->image_path;
        $name = $pack->name;
        $pack->delete();

        if ($image && str_starts_with($image, self::IMAGE_DIR.'/')) {
            Storage::disk('public')->delete($image);
        }

        $this->audit($request, 'admin.store.pack.deleted', $pack, ['name' => $name]);

        return redirect()->route('admin.store.packs.index')->with('status', 'Pack supprimé (les commandes passées restent inchangées).');
    }

    /**
     * Lignes du formulaire : `p12` = article 12 (l'acheteur choisit couleur et taille), `v34` = variante 34 imposée.
     *
     * @return array{0: array<string, mixed>, 1: array<int, array{product_id: int, variant_id: int|null, quantity: int}>}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'summary' => ['nullable', 'string', 'max:300'],
            'description' => ['nullable', 'string', 'max:10000'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999'],
            'is_active' => ['nullable', 'boolean'],
            'image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_image' => ['nullable', 'boolean'],
            'items' => ['nullable', 'array', 'max:20'],
            'items.*.ref' => ['nullable', 'string', 'max:20'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $items = [];

        foreach ((array) ($data['items'] ?? []) as $row) {
            $ref = (string) ($row['ref'] ?? '');

            if ($ref === '') {
                continue;
            }

            $id = (int) substr($ref, 1);
            $quantity = (int) ($row['quantity'] ?? 1) ?: 1;

            if (str_starts_with($ref, 'v')) {
                $variant = StoreVariant::query()->find($id);
                $item = $variant ? ['product_id' => $variant->product_id, 'variant_id' => $variant->id, 'quantity' => $quantity] : null;
            } else {
                $item = StoreProduct::query()->whereKey($id)->exists() ? ['product_id' => $id, 'variant_id' => null, 'quantity' => $quantity] : null;
            }

            if ($item === null) {
                throw ValidationException::withMessages(['items' => 'Un article du pack est introuvable.']);
            }

            $items[] = $item;
        }

        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'Ajoutez au moins un article au pack.']);
        }

        unset($data['image'], $data['remove_image'], $data['items']);
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        return [$data, $items];
    }

    /** @param array<int, array{product_id: int, variant_id: int|null, quantity: int}> $items */
    private function syncItems(StorePack $pack, array $items): void
    {
        $pack->items()->delete();

        foreach ($items as $item) {
            StorePackItem::query()->create($item + ['pack_id' => $pack->id]);
        }
    }

    /** @return array<string, mixed> */
    private function formData(StorePack $pack): array
    {
        $pack->loadMissing('items');

        return [
            'pack' => $pack,
            'products' => StoreProduct::query()->with(['variants.color', 'variants.size'])->orderBy('name')->get(),
        ];
    }
}
