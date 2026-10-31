<?php

namespace App\Http\Controllers\Admin;

use App\Models\StoreCategory;
use App\Models\StoreColor;
use App\Models\StoreOrderItem;
use App\Models\StoreProduct;
use App\Models\StoreSize;
use App\Models\StoreType;
use App\Models\StoreVariant;
use App\Support\Store\StoreCatalog;
use App\Support\Store\StoreStock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StoreProductController extends AdminController
{
    private const IMAGE_DIR = 'store/products';

    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));
        $category = (int) $request->query('category', 0);
        $state = (string) $request->query('state', '');

        $products = StoreProduct::query()
            ->with(['category:id,name', 'type:id,name', 'variants:id,product_id,stock,is_active,price,low_stock_threshold'])
            ->when($term !== '', fn ($query) => $query->where('name', 'like', '%'.addcslashes($term, '\\%_').'%'))
            ->when($category > 0, fn ($query) => $query->where('category_id', $category))
            ->when($state === 'active', fn ($query) => $query->where('is_active', true))
            ->when($state === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderByDesc('id')
            ->paginate(20)->withQueryString();

        return view('admin.store.products.index', [
            'products' => $products,
            'categories' => StoreCategory::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'term' => $term,
            'category' => $category,
            'state' => $state,
        ]);
    }

    public function create(): View
    {
        return view('admin.store.products.form', $this->formData(new StoreProduct(['is_active' => true])));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = StoreCatalog::uniqueSlug(StoreProduct::class, $data['name']);
        $data['image_path'] = $request->hasFile('image') ? $request->file('image')->store(self::IMAGE_DIR, 'public') : null;

        $product = StoreProduct::query()->create($data);
        $this->audit($request, 'admin.store.product.created', $product, ['name' => $product->name]);

        return redirect()->route('admin.store.products.edit', $product)->with('status', 'Article créé : ajoutez maintenant ses variantes (couleurs et tailles) et son stock.');
    }

    public function edit(StoreProduct $product): View
    {
        return view('admin.store.products.form', $this->formData($product));
    }

    public function update(Request $request, StoreProduct $product): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = StoreCatalog::uniqueSlug(StoreProduct::class, $data['name'], $product->id);
        $old = $product->image_path;

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store(self::IMAGE_DIR, 'public');
        } elseif ($request->boolean('remove_image')) {
            $data['image_path'] = null;
        }

        $product->update($data);

        if (array_key_exists('image_path', $data)) {
            $this->deleteImage($old);
        }

        $this->audit($request, 'admin.store.product.updated', $product, ['name' => $product->name]);

        return back()->with('status', 'Article enregistré.');
    }

    public function destroy(Request $request, StoreProduct $product): RedirectResponse
    {
        $ordered = StoreOrderItem::query()->whereIn('variant_id', $product->variants()->select('id'))->exists();

        if ($ordered) {
            return back()->withErrors(['product' => 'Cet article a déjà été commandé : désactivez-le plutôt que de le supprimer.']);
        }

        $image = $product->image_path;
        $name = $product->name;
        $product->delete();
        $this->deleteImage($image);
        $this->audit($request, 'admin.store.product.deleted', $product, ['name' => $name]);

        return redirect()->route('admin.store.products.index')->with('status', 'Article supprimé.');
    }

    /** Génère toutes les combinaisons couleur × taille choisies (sans doublon) avec un stock initial. */
    public function storeVariants(Request $request, StoreProduct $product): RedirectResponse
    {
        $data = $request->validate([
            'colors' => ['nullable', 'array'],
            'colors.*' => ['integer', Rule::exists('store_colors', 'id')],
            'sizes' => ['nullable', 'array'],
            'sizes.*' => ['integer', Rule::exists('store_sizes', 'id')],
            'stock' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);

        $colors = array_values(array_unique($data['colors'] ?? [])) ?: [null];
        $sizes = array_values(array_unique($data['sizes'] ?? [])) ?: [null];
        $created = 0;

        DB::transaction(function () use ($product, $colors, $sizes, $data, $request, &$created): void {
            foreach ($colors as $colorId) {
                foreach ($sizes as $sizeId) {
                    $exists = StoreVariant::query()->where('product_id', $product->id)->where('color_id', $colorId)->where('size_id', $sizeId)->exists();

                    if ($exists) {
                        continue;
                    }

                    $variant = StoreVariant::query()->create([
                        'product_id' => $product->id,
                        'color_id' => $colorId,
                        'size_id' => $sizeId,
                        'sku' => 'P'.$product->id.'-C'.($colorId ?? 0).'-S'.($sizeId ?? 0),
                        'price' => $data['price'] ?? null,
                        'stock' => 0,
                        'low_stock_threshold' => $data['low_stock_threshold'] ?? 3,
                    ]);

                    if (($data['stock'] ?? 0) > 0) {
                        StoreStock::adjust($variant, (int) $data['stock'], 'initial', $request->user(), 'Stock initial');
                    }

                    $created++;
                }
            }
        });

        $this->audit($request, 'admin.store.variants.created', $product, ['count' => $created]);

        return back()->with($created > 0 ? 'status' : 'error', $created > 0 ? $created.' variante(s) ajoutée(s).' : 'Ces variantes existent déjà.');
    }

    public function updateVariant(Request $request, StoreVariant $variant): RedirectResponse
    {
        $data = $request->validate([
            'sku' => ['nullable', 'string', 'max:60', Rule::unique('store_variants', 'sku')->ignore($variant->id)],
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'low_stock_threshold' => ['required', 'integer', 'min:0', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $variant->update([
            'sku' => $data['sku'] ?? null,
            'price' => $data['price'] ?? null,
            'low_stock_threshold' => $data['low_stock_threshold'],
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        $this->audit($request, 'admin.store.variant.updated', $variant, ['product_id' => $variant->product_id]);

        return back()->with('status', 'Variante enregistrée.');
    }

    public function destroyVariant(Request $request, StoreVariant $variant): RedirectResponse
    {
        if (StoreOrderItem::query()->where('variant_id', $variant->id)->exists()) {
            return back()->withErrors(['variant' => 'Cette variante a déjà été commandée : désactivez-la plutôt que de la supprimer.']);
        }

        $productId = $variant->product_id;
        $variant->delete();
        $this->audit($request, 'admin.store.variant.deleted', $variant, ['product_id' => $productId]);

        return back()->with('status', 'Variante supprimée.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'category_id' => ['nullable', Rule::exists('store_categories', 'id')],
            'type_id' => ['nullable', Rule::exists('store_types', 'id')],
            'summary' => ['nullable', 'string', 'max:300'],
            'description' => ['nullable', 'string', 'max:10000'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999'],
            'is_active' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_image' => ['nullable', 'boolean'],
        ]);

        unset($data['image'], $data['remove_image']);
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $data['is_featured'] = (bool) ($data['is_featured'] ?? false);

        return $data;
    }

    /** @return array<string, mixed> */
    private function formData(StoreProduct $product): array
    {
        $product->loadMissing(['variants.color', 'variants.size']);

        return [
            'product' => $product,
            'categories' => StoreCategory::query()->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all(),
            'types' => StoreType::query()->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all(),
            'colors' => StoreColor::query()->orderBy('sort_order')->orderBy('name')->get(),
            'sizes' => StoreSize::query()->orderBy('sort_order')->orderBy('id')->get(),
        ];
    }

    private function deleteImage(?string $path): void
    {
        if ($path && str_starts_with($path, self::IMAGE_DIR.'/')) {
            Storage::disk('public')->delete($path);
        }
    }
}
