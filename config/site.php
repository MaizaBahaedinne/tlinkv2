<?php

/*
|--------------------------------------------------------------------------
| Site public de la Fondation Tunivisions
|--------------------------------------------------------------------------
| Le site lit les donnees de T.Link (publications publiques, evenements, organigramme, clubs).
| Les textes editables (accroche, contacts, reseaux) sont en base : Admin > Site public.
|
| Le site est servi a la racine : `/` redirige vers `/fr`, `/ar` ou `/en`.
| Le fil d'actualite de l'application T.Link est sur `/feed`.
*/
return [
    'locales' => [
        'fr' => ['name' => 'Français', 'dir' => 'ltr'],
        'ar' => ['name' => 'العربية', 'dir' => 'rtl'],
        'en' => ['name' => 'English', 'dir' => 'ltr'],
    ],

    'default_locale' => 'fr',
];
