<?php

namespace App\Http\Controllers\Admin;

use App\Models\AcademyPlan;
use App\Support\Academy\AcademySettings;
use App\Support\Academy\Payments\PaymentGateways;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Réglages d'Academy (vente, prestataire de paiement, consignes) et formules d'abonnement.
 */
class AcademySettingsController extends AdminController
{
    public function edit(): View
    {
        return view('admin.academy.settings', [
            'settings' => AcademySettings::all(),
            'certificateFiles' => [
                'logo_path' => AcademySettings::fileUrl('logo_path'),
                'manager_signature_path' => AcademySettings::fileUrl('manager_signature_path'),
                'president_signature_path' => AcademySettings::fileUrl('president_signature_path'),
            ],
            'gateways' => PaymentGateways::all(),
            'plans' => AcademyPlan::query()->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'gateway' => ['required', Rule::in(array_keys(PaymentGateways::all()))],
            'manual_instructions' => ['nullable', 'string', 'max:2000'],
            'contact_email' => ['nullable', 'email', 'max:255'],
        ]);

        AcademySettings::save([
            'sales_enabled' => $request->boolean('sales_enabled'),
            'gateway' => $data['gateway'],
            'manual_instructions' => (string) ($data['manual_instructions'] ?? ''),
            'contact_email' => (string) ($data['contact_email'] ?? ''),
        ]);

        $this->audit($request, 'admin.academy.settings.updated', $request->user(), ['gateway' => $data['gateway']]);

        return back()->with('status', 'Réglages enregistrés.');
    }

    public function updateCertificate(Request $request): RedirectResponse
    {
        $image = ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'];
        $data = $request->validate([
            'logo' => $image,
            'manager_signature' => $image,
            'president_signature' => $image,
            'manager_name' => ['nullable', 'string', 'max:120'],
            'manager_title' => ['nullable', 'string', 'max:160'],
            'president_name' => ['nullable', 'string', 'max:120'],
            'president_title' => ['nullable', 'string', 'max:160'],
        ]);

        $current = AcademySettings::all();
        $values = [];

        foreach (['manager_name', 'manager_title', 'president_name', 'president_title'] as $field) {
            $values[$field] = trim((string) ($data[$field] ?? ''));
        }

        // Image envoyée : elle remplace l'ancienne ; case « Retirer » cochée : on la supprime.
        foreach (['logo' => 'logo_path', 'manager_signature' => 'manager_signature_path', 'president_signature' => 'president_signature_path'] as $input => $key) {
            if ($request->hasFile($input)) {
                $values[$key] = $request->file($input)->store(AcademySettings::FILES_DIR, 'public');
            } elseif ($request->boolean('remove_'.$input)) {
                $values[$key] = '';
            } else {
                continue;
            }

            $this->deleteFile((string) $current[$key]);
        }

        AcademySettings::save($values);
        $this->audit($request, 'admin.academy.settings.updated', $request->user(), ['certificate' => true]);

        return back()->with('status', 'Certificat enregistré.');
    }

    private function deleteFile(string $path): void
    {
        if (str_starts_with($path, AcademySettings::FILES_DIR.'/')) {
            Storage::disk('public')->delete($path);
        }
    }

    public function storePlan(Request $request): RedirectResponse
    {
        $data = $this->validatedPlan($request);
        $plan = AcademyPlan::query()->create($data + [
            'slug' => $this->uniqueSlug($data['name']),
            'sort_order' => ((int) AcademyPlan::query()->max('sort_order')) + 1,
        ]);

        $this->audit($request, 'admin.academy.plan.saved', $plan, ['name' => $plan->name]);

        return back()->with('status', 'Abonnement ajouté.');
    }

    public function updatePlan(Request $request, AcademyPlan $plan): RedirectResponse
    {
        $plan->update($this->validatedPlan($request));
        $this->audit($request, 'admin.academy.plan.saved', $plan, ['name' => $plan->name]);

        return back()->with('status', 'Abonnement enregistré.');
    }

    public function destroyPlan(Request $request, AcademyPlan $plan): RedirectResponse
    {
        $this->audit($request, 'admin.academy.plan.deleted', $plan, ['name' => $plan->name]);
        $plan->delete();

        return back()->with('status', 'Abonnement supprimé : les abonnés actuels gardent leur accès jusqu’à la fin de leur période.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPlan(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'min:0.01', 'max:99999'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:3650'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'abonnement';
        $slug = $base;

        for ($i = 2; AcademyPlan::query()->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
