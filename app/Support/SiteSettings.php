<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Textes editables du site public (Admin > Site public). Stockes en base (table settings, groupe `site`).
 * Un champ localise retombe sur le francais puis sur le texte par defaut des fichiers de langue.
 */
class SiteSettings
{
    public const GROUP = 'site';

    public const KEY = 'content';

    /** Champs traduisibles : cle => type de saisie. */
    public const LOCALIZED = [
        'hero_title' => 'text',
        'hero_subtitle' => 'textarea',
        'about_body' => 'textarea',
    ];

    /** Champs communs a toutes les langues : cle => type de saisie. */
    public const PLAIN = [
        'contact_email' => 'email',
        'contact_phone' => 'text',
        'contact_address' => 'text',
        'facebook_url' => 'url',
        'instagram_url' => 'url',
        'linkedin_url' => 'url',
        'youtube_url' => 'url',
        'hero_bg_main' => 'text',
        'hero_bg_mark_a' => 'text',
        'hero_bg_mark_b' => 'text',
    ];

    /** @var array<string, mixed>|null */
    private ?array $data = null;

    /**
     * @return array<string, mixed>
     */
    public function raw(): array
    {
        if ($this->data === null) {
            $value = Setting::query()
                ->where('group', self::GROUP)
                ->where('key', self::KEY)
                ->value('value');

            $decoded = $value ? json_decode((string) $value, true) : null;
            $this->data = is_array($decoded) ? $decoded : [];
        }

        return $this->data;
    }

    /**
     * Valeur stockee, sans texte par defaut (pour pre-remplir le formulaire admin).
     */
    public function stored(string $key, ?string $locale = null): string
    {
        $value = $locale === null ? ($this->raw()[$key] ?? '') : ($this->raw()[$key][$locale] ?? '');

        return is_string($value) ? trim($value) : '';
    }

    /**
     * Valeur affichee sur le site : langue courante, puis francais, puis texte par defaut.
     */
    public function get(string $key, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        if (isset(self::LOCALIZED[$key])) {
            return $this->stored($key, $locale)
                ?: $this->stored($key, config('site.default_locale'))
                ?: (string) __("site.defaults.{$key}", [], $locale);
        }

        return $this->stored($key);
    }

    /**
     * URL de reseau social uniquement si elle est en http(s) (evite les schemas dangereux type javascript:).
     */
    public function link(string $key): ?string
    {
        $url = $this->stored($key);

        return preg_match('#^https?://#i', $url) === 1 ? $url : null;
    }

    /**
     * URL absolue d'une image Hero envoyee depuis l'admin (stockee sur le disque public),
     * ou URL de secours si aucune image n'est configuree.
     */
    public function heroImage(string $key, string $fallback): string
    {
        $path = ltrim($this->stored($key), '/');

        return $path !== '' ? asset('storage/'.$path) : $fallback;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function save(array $values): void
    {
        Setting::query()->updateOrCreate(
            ['group' => self::GROUP, 'key' => self::KEY],
            ['value' => json_encode($values, JSON_UNESCAPED_UNICODE)],
        );

        $this->data = $values;
    }
}
