<?php

namespace App\Http\Controllers\Admin;

use App\Models\Partner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Logos des partenaires affichés sur le site vitrine.
 */
class PartnerController extends AdminController
{
    public function index(Request $request): View
    {
        return view('admin.partners.index', [
            'partners' => Partner::query()->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'website_url' => ['nullable', 'url:http,https', 'max:255'],
            'logo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'is_active' => ['nullable', 'boolean'],
        ], ['logo.required' => 'Le logo est obligatoire.']);

        $partner = Partner::query()->create([
            'name' => trim($data['name']),
            'website_url' => $data['website_url'] ?? null,
            'logo_path' => $request->file('logo')->store('partners/logos', 'public'),
            'sort_order' => ((int) Partner::query()->max('sort_order')) + 1,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->audit($request, 'admin.partner.created', $partner, ['name' => $partner->name]);

        return back()->with('status', 'Partenaire ajouté.');
    }

    public function update(Request $request, Partner $partner): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'website_url' => ['nullable', 'url:http,https', 'max:255'],
            'logo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $values = [
            'name' => trim($data['name']),
            'website_url' => $data['website_url'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? $partner->sort_order),
            'is_active' => $request->boolean('is_active'),
        ];

        $oldLogo = null;

        if ($request->hasFile('logo')) {
            $oldLogo = $partner->logo_path;
            $values['logo_path'] = $request->file('logo')->store('partners/logos', 'public');
        }

        $partner->update($values);
        $this->deleteLogo($oldLogo);

        $this->audit($request, 'admin.partner.updated', $partner, ['name' => $partner->name, 'active' => $partner->is_active]);

        return back()->with('status', 'Partenaire modifié.');
    }

    public function destroy(Request $request, Partner $partner): RedirectResponse
    {
        $this->audit($request, 'admin.partner.deleted', $partner, ['name' => $partner->name]);

        $logo = $partner->logo_path;
        $partner->delete();
        $this->deleteLogo($logo);

        return back()->with('status', 'Partenaire supprimé.');
    }

    private function deleteLogo(?string $path): void
    {
        if ($path && str_starts_with($path, 'partners/logos/')) {
            Storage::disk('public')->delete($path);
        }
    }
}
