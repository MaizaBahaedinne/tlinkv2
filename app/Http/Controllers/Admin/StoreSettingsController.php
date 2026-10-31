<?php

namespace App\Http\Controllers\Admin;

use App\Models\Governorate;
use App\Models\StoreDeliveryMethod;
use App\Models\StorePaymentMethod;
use App\Support\Store\StoreSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StoreSettingsController extends AdminController
{
    public function edit(): View
    {
        return view('admin.store.settings', [
            'settings' => StoreSettings::all(),
            'deliveries' => StoreDeliveryMethod::query()->orderBy('sort_order')->orderBy('id')->get(),
            'payments' => StorePaymentMethod::query()->orderBy('sort_order')->orderBy('id')->get(),
            'governorates' => Governorate::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'name' => ['required', 'string', 'max:80'],
            'tagline' => ['nullable', 'string', 'max:200'],
            'return_days' => ['required', 'integer', 'min:0', 'max:365'],
            'contact_email' => ['nullable', 'email', 'max:190'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'return_policy' => ['nullable', 'string', 'max:5000'],
        ]);

        $data['enabled'] = (bool) ($data['enabled'] ?? false);
        $data['return_days'] = (string) $data['return_days'];

        StoreSettings::save(array_map(fn ($value) => $value ?? '', $data));
        $this->audit($request, 'admin.store.settings.updated', $request->user(), ['enabled' => $data['enabled']]);

        return back()->with('status', 'Réglages enregistrés.');
    }

    public function storeDelivery(Request $request): RedirectResponse
    {
        $method = StoreDeliveryMethod::query()->create($this->deliveryData($request));
        $this->audit($request, 'admin.store.delivery.created', $method, ['name' => $method->name]);

        return back()->with('status', 'Mode de livraison ajouté.');
    }

    public function updateDelivery(Request $request, StoreDeliveryMethod $method): RedirectResponse
    {
        $method->update($this->deliveryData($request));
        $this->audit($request, 'admin.store.delivery.updated', $method, ['name' => $method->name]);

        return back()->with('status', 'Mode de livraison enregistré.');
    }

    public function destroyDelivery(Request $request, StoreDeliveryMethod $method): RedirectResponse
    {
        $name = $method->name;
        $method->delete();
        $this->audit($request, 'admin.store.delivery.deleted', $method, ['name' => $name]);

        return back()->with('status', 'Mode de livraison supprimé (les commandes passées gardent leurs informations).');
    }

    public function updatePayment(Request $request, StorePaymentMethod $method): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'instructions' => ['nullable', 'string', 'max:3000'],
            'applies_to' => ['required', Rule::in(array_keys(StorePaymentMethod::APPLIES_TO))],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $method->update([
            'name' => $data['name'],
            'instructions' => $data['instructions'] ?? null,
            'applies_to' => $data['applies_to'],
            'is_active' => (bool) ($data['is_active'] ?? false),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        $this->audit($request, 'admin.store.payment.updated', $method, ['key' => $method->key, 'active' => $method->is_active]);

        return back()->with('status', 'Mode de paiement enregistré.');
    }

    /** @return array<string, mixed> */
    private function deliveryData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'kind' => ['required', Rule::in(array_keys(StoreDeliveryMethod::KINDS))],
            'fee' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'free_above' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'estimated_delay' => ['nullable', 'string', 'max:100'],
            'instructions' => ['nullable', 'string', 'max:3000'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'governorate_fees' => ['nullable', 'array'],
            'governorate_fees.*' => ['nullable', 'numeric', 'min:0', 'max:9999'],
        ]);

        $perGovernorate = collect((array) ($data['governorate_fees'] ?? []))->filter(fn ($fee) => $fee !== null && $fee !== '')->all();

        return [
            'name' => $data['name'],
            'kind' => $data['kind'],
            'fee' => $data['kind'] === 'pickup' ? 0 : ($data['fee'] ?? 0),
            'free_above' => $data['kind'] === 'pickup' ? null : ($data['free_above'] ?? null),
            'governorate_fees' => $data['kind'] === 'pickup' || $perGovernorate === [] ? null : $perGovernorate,
            'estimated_delay' => $data['estimated_delay'] ?? null,
            'instructions' => $data['instructions'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }
}
