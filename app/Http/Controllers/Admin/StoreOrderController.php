<?php

namespace App\Http\Controllers\Admin;

use App\Models\StoreOrder;
use App\Support\Store\StoreOrders;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StoreOrderController extends AdminController
{
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');
        $payment = (string) $request->query('payment', '');
        $term = trim((string) $request->query('q', ''));

        $orders = StoreOrder::query()
            ->with('user:id,name,email')
            ->when(array_key_exists($status, StoreOrder::STATUSES), fn ($query) => $query->where('status', $status))
            ->when(array_key_exists($payment, StoreOrder::PAYMENT_STATUSES), fn ($query) => $query->where('payment_status', $payment))
            ->when($term !== '', function ($query) use ($term): void {
                $like = '%'.addcslashes($term, '\\%_').'%';
                $query->where(fn ($inner) => $inner->where('reference', 'like', $like)->orWhere('ship_name', 'like', $like)->orWhere('ship_phone', 'like', $like)
                    ->orWhereHas('user', fn ($user) => $user->where('name', 'like', $like)->orWhere('email', 'like', $like)));
            })
            ->latest('id')
            ->paginate(25)->withQueryString();

        return view('admin.store.orders.index', [
            'orders' => $orders,
            'status' => $status,
            'payment' => $payment,
            'term' => $term,
            'pending' => StoreOrder::query()->where('status', 'pending')->count(),
        ]);
    }

    public function show(StoreOrder $order): View
    {
        $order->load(['user:id,name,email', 'governorate:id,name', 'items' => fn ($query) => $query->orderBy('id'), 'returns']);

        return view('admin.store.orders.show', ['order' => $order]);
    }

    public function status(Request $request, StoreOrder $order): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(StoreOrder::STATUSES))]]);

        if (! StoreOrders::advance($order, $data['status'])) {
            return back()->withErrors(['order' => 'Ce changement de statut n’est pas possible depuis « '.$order->statusLabel().' ».']);
        }

        $this->audit($request, 'admin.store.order.status_changed', $order, ['reference' => $order->reference, 'status' => $data['status']]);

        return back()->with('status', 'Commande passée à « '.$order->fresh()->statusLabel().' ».');
    }

    public function pay(Request $request, StoreOrder $order): RedirectResponse
    {
        if (! StoreOrders::markPaid($order)) {
            return back()->withErrors(['order' => 'Cette commande ne peut pas être marquée comme payée.']);
        }

        $this->audit($request, 'admin.store.order.paid', $order, ['reference' => $order->reference]);

        return back()->with('status', 'Paiement enregistré.');
    }

    public function cancel(Request $request, StoreOrder $order): RedirectResponse
    {
        if (! StoreOrders::cancel($order, $request->user())) {
            return back()->withErrors(['order' => 'Une commande expédiée ou livrée ne peut plus être annulée : passez par un retour.']);
        }

        $this->audit($request, 'admin.store.order.cancelled', $order, ['reference' => $order->reference]);

        return back()->with('status', 'Commande annulée : le stock a été remis en vente.');
    }

    public function tracking(Request $request, StoreOrder $order): RedirectResponse
    {
        $data = $request->validate([
            'tracking_number' => ['nullable', 'string', 'max:100'],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $order->update(['tracking_number' => $data['tracking_number'] ?? null, 'admin_note' => $data['admin_note'] ?? null]);

        return back()->with('status', 'Suivi enregistré.');
    }
}
