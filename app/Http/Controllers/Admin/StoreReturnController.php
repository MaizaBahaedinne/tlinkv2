<?php

namespace App\Http\Controllers\Admin;

use App\Models\StoreReturn;
use App\Support\Store\StoreReturns;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StoreReturnController extends AdminController
{
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');

        return view('admin.store.returns.index', [
            'returns' => StoreReturn::query()
                ->with(['order:id,reference,total', 'user:id,name,email'])
                ->when(array_key_exists($status, StoreReturn::STATUSES), fn ($query) => $query->where('status', $status))
                ->latest('id')->paginate(25)->withQueryString(),
            'status' => $status,
            'requested' => StoreReturn::query()->where('status', 'requested')->count(),
        ]);
    }

    public function show(StoreReturn $storeReturn): View
    {
        $storeReturn->load(['order.governorate', 'user:id,name,email', 'items.orderItem']);

        return view('admin.store.returns.show', ['return' => $storeReturn]);
    }

    public function decide(Request $request, StoreReturn $storeReturn): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $done = $data['decision'] === 'approved'
            ? StoreReturns::approve($storeReturn, $data['admin_note'] ?? null)
            : StoreReturns::reject($storeReturn, $data['admin_note'] ?? null);

        if (! $done) {
            return back()->withErrors(['return' => 'Cette demande a déjà été traitée.']);
        }

        $this->audit($request, 'admin.store.return.decided', $storeReturn, ['reference' => $storeReturn->reference, 'decision' => $data['decision']]);

        return back()->with('status', $data['decision'] === 'approved' ? 'Retour accepté.' : 'Retour refusé.');
    }

    public function receive(Request $request, StoreReturn $storeReturn): RedirectResponse
    {
        if (! StoreReturns::receive($storeReturn, $request->boolean('restock'), $request->user())) {
            return back()->withErrors(['return' => 'Seul un retour accepté peut être réceptionné.']);
        }

        $this->audit($request, 'admin.store.return.received', $storeReturn, ['reference' => $storeReturn->reference, 'restock' => $request->boolean('restock')]);

        return back()->with('status', $request->boolean('restock') ? 'Retour reçu : les articles sont remis en stock.' : 'Retour reçu (sans remise en stock).');
    }

    public function complete(Request $request, StoreReturn $storeReturn): RedirectResponse
    {
        $data = $request->validate([
            'refund_amount' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! StoreReturns::complete($storeReturn, isset($data['refund_amount']) ? (float) $data['refund_amount'] : null, $data['admin_note'] ?? null)) {
            return back()->withErrors(['return' => 'Le retour doit d’abord être réceptionné.']);
        }

        $this->audit($request, 'admin.store.return.completed', $storeReturn, ['reference' => $storeReturn->reference, 'refund' => (float) $storeReturn->fresh()->refund_amount]);

        return back()->with('status', 'Retour clôturé.');
    }
}
