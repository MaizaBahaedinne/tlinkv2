<?php

namespace App\Http\Controllers\Admin;

use App\Models\StoreStockMovement;
use App\Models\StoreVariant;
use App\Support\Store\StoreStock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StoreStockController extends AdminController
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));
        $filter = (string) $request->query('filter', '');

        $variants = StoreVariant::query()
            ->with(['product:id,name,price', 'color:id,name,hex', 'size:id,name'])
            ->when($term !== '', function ($query) use ($term): void {
                $like = '%'.addcslashes($term, '\\%_').'%';
                $query->where(fn ($inner) => $inner->where('sku', 'like', $like)->orWhereHas('product', fn ($product) => $product->where('name', 'like', $like)));
            })
            ->when($filter === 'out', fn ($query) => $query->where('stock', 0))
            ->when($filter === 'low', fn ($query) => $query->whereColumn('stock', '<=', 'low_stock_threshold'))
            ->orderBy('product_id')->orderBy('id')
            ->paginate(30)->withQueryString();

        return view('admin.store.stock', [
            'variants' => $variants,
            'term' => $term,
            'filter' => $filter,
            'movements' => StoreStockMovement::query()->with(['variant.product:id,name', 'variant.color:id,name', 'variant.size:id,name', 'user:id,name'])->latest('id')->limit(25)->get(),
            'lowCount' => StoreVariant::query()->where('is_active', true)->whereColumn('stock', '<=', 'low_stock_threshold')->count(),
        ]);
    }

    public function adjust(Request $request, StoreVariant $variant): RedirectResponse
    {
        $data = $request->validate([
            'delta' => ['required', 'integer', 'not_in:0', 'between:-100000,100000'],
            'reason' => ['required', Rule::in(['restock', 'adjustment'])],
            'note' => ['nullable', 'string', 'max:250'],
        ]);

        $movement = StoreStock::adjust($variant, (int) $data['delta'], $data['reason'], $request->user(), $data['note'] ?? null);
        $this->audit($request, 'admin.store.stock.adjusted', $variant, ['delta' => $movement->delta, 'balance' => $movement->balance]);

        return back()->with('status', 'Stock mis à jour : '.$movement->balance.' en stock.');
    }
}
