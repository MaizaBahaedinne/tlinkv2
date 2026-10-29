<?php

namespace App\Http\Controllers\Admin;

use App\Models\AcademyOrder;
use App\Support\Academy\AcademyOrders;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Commandes Academy : l'administration valide les paiements hors ligne (l'accès s'ouvre à la validation).
 */
class AcademyOrderController extends AdminController
{
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');
        $term = trim((string) $request->query('q', ''));

        $orders = AcademyOrder::query()
            ->with('user:id,name,email')
            ->when(array_key_exists($status, AcademyOrder::STATUSES), fn ($query) => $query->where('status', $status))
            ->when($term !== '', function ($query) use ($term): void {
                $like = '%'.addcslashes($term, '\\%_').'%';
                $query->where(fn ($inner) => $inner->where('reference', 'like', $like)->orWhere('label', 'like', $like)
                    ->orWhereHas('user', fn ($user) => $user->where('name', 'like', $like)->orWhere('email', 'like', $like)));
            })
            ->latest('id')
            ->paginate(30)->withQueryString();

        return view('admin.academy.orders.index', [
            'orders' => $orders,
            'status' => $status,
            'term' => $term,
            'pending' => AcademyOrder::query()->where('status', 'pending')->count(),
        ]);
    }

    public function pay(Request $request, AcademyOrder $order): RedirectResponse
    {
        $data = $request->validate(['provider_reference' => ['nullable', 'string', 'max:190']]);

        if (! AcademyOrders::markPaid($order, $data['provider_reference'] ?? null)) {
            return back()->withErrors(['order' => 'Cette commande ne peut plus être validée.']);
        }

        $this->audit($request, 'admin.academy.order.paid', $order, ['reference' => $order->reference]);

        return back()->with('status', 'Paiement validé : l’accès du membre est ouvert.');
    }

    public function cancel(Request $request, AcademyOrder $order): RedirectResponse
    {
        if (! AcademyOrders::cancel($order)) {
            return back()->withErrors(['order' => 'Seule une commande en attente peut être annulée.']);
        }

        $this->audit($request, 'admin.academy.order.cancelled', $order, ['reference' => $order->reference]);

        return back()->with('status', 'Commande annulée.');
    }

    public function refund(Request $request, AcademyOrder $order): RedirectResponse
    {
        if (! AcademyOrders::refund($order)) {
            return back()->withErrors(['order' => 'Seule une commande payée peut être remboursée.']);
        }

        $this->audit($request, 'admin.academy.order.refunded', $order, ['reference' => $order->reference]);

        return back()->with('status', 'Commande remboursée : l’accès ouvert par cet achat est retiré.');
    }
}
