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
        'pillar_1_title' => 'text',
        'pillar_1_text' => 'textarea',
        'pillar_2_title' => 'text',
        'pillar_2_text' => 'textarea',
        'pillar_3_title' => 'text',
        'pillar_3_text' => 'textarea',
        'about_body' => 'textarea',
        'president_role' => 'text',
        'president_message' => 'textarea',
        'mission' => 'textarea',
        'vision' => 'textarea',
        'value_1_title' => 'text',
        'value_1_text' => 'textarea',
        'value_2_title' => 'text',
        'value_2_text' => 'textarea',
        'value_3_title' => 'text',
        'value_3_text' => 'textarea',
        'value_4_title' => 'text',
        'value_4_text' => 'textarea',
    ];

    /** Libelles admin des champs traduisibles. */
    public const LABELS = [
        'hero_title' => 'Titre d’accueil',
        'hero_subtitle' => 'Sous-titre d’accueil',
        'pillar_1_title' => 'Accueil, pilier 1 : titre',
        'pillar_1_text' => 'Accueil, pilier 1 : texte',
        'pillar_2_title' => 'Accueil, pilier 2 : titre',
        'pillar_2_text' => 'Accueil, pilier 2 : texte',
        'pillar_3_title' => 'Accueil, pilier 3 : titre',
        'pillar_3_text' => 'Accueil, pilier 3 : texte',
        'about_body' => 'Texte « À propos »',
        'president_role' => 'Fonction du président (sous son nom)',
        'president_message' => 'Mot du président',
        'mission' => 'Mission',
        'vision' => 'Vision',
        'value_1_title' => 'Valeur 1 : titre',
        'value_1_text' => 'Valeur 1 : texte',
        'value_2_title' => 'Valeur 2 : titre',
        'value_2_text' => 'Valeur 2 : texte',
        'value_3_title' => 'Valeur 3 : titre',
        'value_3_text' => 'Valeur 3 : texte',
        'value_4_title' => 'Valeur 4 : titre',
        'value_4_text' => 'Valeur 4 : texte',
    ];

    /** Titre de section affiché dans l'admin avant le champ indiqué. */
    public const SECTIONS = [
        'hero_title' => 'Page d’accueil',
        'about_body' => 'Page « À propos » : présentation',
        'president_role' => 'Page « À propos » : mot du président (le nom et la photo se règlent plus bas)',
        'mission' => 'Page « À propos » : mission, vision et valeurs',
    ];

    /** Images envoyées depuis l'admin : clé => dossier du disque public. */
    public const IMAGES = [
        'hero_bg_main' => 'site/hero',
        'hero_bg_mark_a' => 'site/hero',
        'hero_bg_mark_b' => 'site/hero',
        'president_photo' => 'site/president',
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
        'president_name' => 'text',
        'president_photo' => 'text',
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
     * Texte saisi en admin, sans texte par défaut : langue courante, puis français, sinon vide.
     */
    public function optional(string $key, ?string $locale = null): string
    {
        return $this->stored($key, $locale ?? app()->getLocale()) ?: $this->stored($key, config('site.default_locale'));
    }

    /**
     * URL absolue d'une image envoyée depuis l'admin, ou null si aucune.
     */
    public function imageUrl(string $key): ?string
    {
        $path = ltrim($this->stored($key), '/');

        return $path !== '' ? asset('storage/'.$path) : null;
    }

    /**
     * URL de réseau social uniquement si elle est en http(s) (evite les schemas dangereux type javascript:).
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
