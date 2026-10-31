<?php

namespace App\Http\Controllers;

use App\Models\Governorate;
use App\Models\StoreDeliveryMethod;
use App\Models\StoreOrder;
use App\Models\StorePaymentMethod;
use App\Support\Store\StoreCart;
use App\Support\Store\StoreOrders;
use App\Support\Store\StoreReturns;
use App\Support\Store\StoreSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Commande du membre : validation du panier, suivi, annulation et demande de retour. */
class StoreOrderController extends Controller
{
    public function checkout(Request $request): View|RedirectResponse
    {
        $cart = StoreCart::summary($request->user());

        if ($cart['lines'] === []) {
            return redirect()->route('store.cart.index');
        }

        if (! $cart['ok']) {
            return redirect()->route('store.cart.index')->withErrors(['cart' => 'Ajustez les articles signalés avant de commander.']);
        }

        return view('store.checkout', [
            'cart' => $cart,
            'deliveries' => StoreDeliveryMethod::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(),
            'payments' => StorePaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(),
            'governorates' => Governorate::query()->orderBy('name')->get(['id', 'name']),
            'settings' => StoreSettings::all(),
        ]);
    }

    public function place(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'delivery_method_id' => ['required', 'integer'],
            'payment_method_id' => ['required', 'integer'],
            'ship_name' => ['required', 'string', 'max:120'],
            'ship_phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\s().-]{6,30}$/'],
            'governorate_id' => ['nullable', Rule::exists('governorates', 'id')],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $order = StoreOrders::place($request->user(), $data);

        return redirect()->route('store.orders.show', $order)->with('status', 'Commande enregistrée. Merci !');
    }

    public function index(Request $request): View
    {
        return view('store.orders', [
            'orders' => StoreOrder::query()->where('user_id', $request->user()->id)->with('returns')->latest('id')->paginate(15),
            'settings' => StoreSettings::all(),
        ]);
    }

    public function show(Request $request, StoreOrder $order): View
    {
        $this->authorizeOrder($request, $order);

        $order->load(['items' => fn ($query) => $query->orderBy('id'), 'governorate:id,name', 'returns.items.orderItem']);

        return view('store.order', [
            'order' => $order,
            'payment' => StorePaymentMethod::query()->find($order->payment_method_id),
            'canReturn' => StoreReturns::canRequest($order),
            'returnable' => StoreReturns::returnable($order),
            'settings' => StoreSettings::all(),
        ]);
    }

    public function cancel(Request $request, StoreOrder $order): RedirectResponse
    {
        $this->authorizeOrder($request, $order);

        // Le membre n'annule que tant que la boutique n'a pas confirmé la commande.
        if ($order->status !== 'pending' || ! StoreOrders::cancel($order, $request->user())) {
            return back()->withErrors(['order' => 'Cette commande est déjà prise en charge : contactez la boutique pour l’annuler.']);
        }

        return back()->with('status', 'Commande annulée.');
    }

    public function requestReturn(Request $request, StoreOrder $order): RedirectResponse
    {
        $this->authorizeOrder($request, $order);

        $data = $request->validate([
            'quantities' => ['required', 'array'],
            'quantities.*' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'reason' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:2000'],
            'resolution' => ['required', Rule::in(['refund', 'exchange'])],
        ]);

        $return = StoreReturns::request($request->user(), $order, $data['quantities'], $data['reason'], $data['details'] ?? null, $data['resolution']);

        return back()->with('status', 'Demande de retour '.$return->reference.' envoyée : nous revenons vers vous rapidement.');
    }

    private function authorizeOrder(Request $request, StoreOrder $order): void
    {
        abort_unless((int) $order->user_id === (int) $request->user()->id, 404);
    }
}
