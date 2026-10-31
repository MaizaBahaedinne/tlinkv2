<?php

namespace App\Http\Controllers;

use App\Models\StoreCategory;
use App\Models\StorePack;
use App\Models\StoreProduct;
use App\Models\StoreReview;
use App\Models\StoreType;
use App\Support\Store\StoreSettings;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Vitrine de Tunivisions Stores : catalogue, fiche article et fiche pack. */
class StoreController extends Controller
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));
        $category = (string) $request->query('categorie', '');
        $type = (string) $request->query('type', '');

        $products = StoreProduct::query()
            ->where('is_active', true)
            ->with(['category:id,name,slug', 'type:id,name,slug', 'variants' => fn ($query) => $query->where('is_active', true)->with('color:id,name,hex')])
            ->when($term !== '', fn ($query) => $query->where('name', 'like', '%'.addcslashes($term, '\\%_').'%'))
            ->when($category !== '', fn ($query) => $query->whereHas('category', fn ($inner) => $inner->where('slug', $category)))
            ->when($type !== '', fn ($query) => $query->whereHas('type', fn ($inner) => $inner->where('slug', $type)))
            ->orderByDesc('is_featured')->orderByDesc('id')
            ->paginate(12)->withQueryString();

        $filtering = $term !== '' || $category !== '' || $type !== '';

        return view('store.index', [
            'products' => $products,
            'packs' => $filtering ? collect() : StorePack::query()->where('is_active', true)->with(['items.product.variants', 'items.variant'])->latest('id')->get(),
            'categories' => StoreCategory::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'slug']),
            'types' => StoreType::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'slug']),
            'term' => $term,
            'category' => $category,
            'type' => $type,
            'settings' => StoreSettings::all(),
        ]);
    }

    public function showProduct(Request $request, StoreProduct $product): View
    {
        abort_unless($product->is_active, 404);

        $product->load([
            'category:id,name,slug',
            'type:id,name',
            'images',
            'variants' => fn ($query) => $query->where('is_active', true)->with(['color', 'size'])->orderBy('id'),
            'reviews' => fn ($query) => $query->published()->with('user:id,name')->latest('id'),
        ]);

        $user = $request->user();

        return view('store.product', [
            'product' => $product,
            'settings' => StoreSettings::all(),
            'rating' => $product->ratingSummary(),
            'canReview' => StoreReview::canReview($user, $product),
            'myReview' => $user ? StoreReview::query()->where('product_id', $product->id)->where('user_id', $user->id)->first() : null,
        ]);
    }

    public function showPack(StorePack $pack): View
    {
        abort_unless($pack->is_active, 404);

        $pack->load(['items.product.variants' => fn ($query) => $query->where('is_active', true)->with(['color', 'size'])->orderBy('id'), 'items.variant.color', 'items.variant.size']);

        return view('store.pack', ['pack' => $pack, 'settings' => StoreSettings::all()]);
    }
}
