<?php

namespace App\Http\Controllers;

use App\Models\StoreCartItem;
use App\Models\StorePack;
use App\Models\StoreVariant;
use App\Support\Store\StoreCart;
use App\Support\Store\StoreSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StoreCartController extends Controller
{
    public function index(Request $request): View
    {
        return view('store.cart', ['cart' => StoreCart::summary($request->user()), 'settings' => StoreSettings::all()]);
    }

    public function addProduct(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'variant_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.StoreCart::MAX_QUANTITY],
        ]);

        StoreCart::addVariant($request->user(), StoreVariant::query()->findOrFail($data['variant_id']), (int) $data['quantity']);

        return redirect()->route('store.cart.index')->with('status', 'Article ajouté au panier.');
    }

    public function addPack(Request $request, StorePack $pack): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:'.StoreCart::MAX_QUANTITY],
            'choices' => ['nullable', 'array'],
            'choices.*' => ['nullable', 'integer'],
        ]);

        StoreCart::addPack($request->user(), $pack, (int) $data['quantity'], (array) ($data['choices'] ?? []));

        return redirect()->route('store.cart.index')->with('status', 'Pack ajouté au panier.');
    }

    public function update(Request $request, StoreCartItem $item): RedirectResponse
    {
        abort_unless((int) $item->user_id === (int) $request->user()->id, 404);

        $data = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:'.StoreCart::MAX_QUANTITY]]);

        StoreCart::setQuantity($item, (int) $data['quantity']);

        return back()->with('status', 'Panier mis à jour.');
    }

    public function remove(Request $request, StoreCartItem $item): RedirectResponse
    {
        abort_unless((int) $item->user_id === (int) $request->user()->id, 404);

        $item->delete();

        return back()->with('status', 'Article retiré du panier.');
    }
}
