<?php

/*
|--------------------------------------------------------------------------
| Site public de la Fondation Tunivisions
|--------------------------------------------------------------------------
| Le site lit les donnees de T.Link (publications publiques, evenements, organigramme, clubs).
| Les textes editables (accroche, contacts, reseaux) sont en base : Admin > Site public.
|
| - SITE_DOMAIN renseigne : le site est servi a la racine de ce domaine (ex. www.tunivisions.org).
| - SITE_DOMAIN vide : le site est servi sous le prefixe SITE_PATH (ex. /fondation).
*/
return [
    'domain' => env('SITE_DOMAIN') ?: null,

    'path' => trim((string) env('SITE_PATH', 'fondation'), '/'),

    'locales' => [
        'fr' => ['name' => 'Français', 'dir' => 'ltr'],
        'ar' => ['name' => 'العربية', 'dir' => 'rtl'],
        'en' => ['name' => 'English', 'dir' => 'ltr'],
    ],

    'default_locale' => 'fr',
];
