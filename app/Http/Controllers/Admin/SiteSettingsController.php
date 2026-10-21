<?php

namespace App\Http\Controllers\Admin;

use App\Support\SiteSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Textes et coordonnees du site public de la Fondation (reserve a l'administration globale).
 */
class SiteSettingsController extends AdminController
{
    public function edit(Request $request): View
    {
        $this->authorizeGlobal($request);

        return view('admin.site.edit', [
            'site' => new SiteSettings(),
            'locales' => config('site.locales'),
            'localized' => SiteSettings::LOCALIZED,
            'plain' => SiteSettings::PLAIN,
            'publicUrl' => route('site.root'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeGlobal($request);

        $rules = [];
        foreach (SiteSettings::LOCALIZED as $key => $type) {
            $rules[$key] = ['nullable', 'array'];
            foreach (array_keys(config('site.locales')) as $locale) {
                $rules["{$key}.{$locale}"] = ['nullable', 'string', 'max:'.($type === 'textarea' ? 3000 : 200)];
            }
        }
        $rules += [
            'contact_email' => ['nullable', 'email:rfc', 'max:190'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'contact_address' => ['nullable', 'string', 'max:255'],
            'facebook_url' => ['nullable', 'url:http,https', 'max:255'],
            'instagram_url' => ['nullable', 'url:http,https', 'max:255'],
            'linkedin_url' => ['nullable', 'url:http,https', 'max:255'],
            'youtube_url' => ['nullable', 'url:http,https', 'max:255'],
        ];

        $data = $request->validate($rules);

        $values = [];
        foreach (array_keys(SiteSettings::LOCALIZED) as $key) {
            $values[$key] = collect($data[$key] ?? [])
                ->map(fn ($text) => trim((string) $text))
                ->filter(fn (string $text) => $text !== '')
                ->all();
        }
        foreach (array_keys(SiteSettings::PLAIN) as $key) {
            $values[$key] = trim((string) ($data[$key] ?? ''));
        }

        $site = new SiteSettings();
        $site->save($values);

        $setting = \App\Models\Setting::query()->where('group', SiteSettings::GROUP)->where('key', SiteSettings::KEY)->first();
        $this->audit($request, 'admin.site.updated', $setting, ['fields' => array_keys($values)]);

        return back()->with('status', 'Site public mis à jour.');
    }

    private function authorizeGlobal(Request $request): void
    {
        abort_unless($this->context($request)->scope('entity.manage') === [], 403, 'Réservé à l\'administration globale.');
    }
}
