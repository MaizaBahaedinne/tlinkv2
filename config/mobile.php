<?php

/*
|--------------------------------------------------------------------------
| Applications mobiles (Capacitor)
|--------------------------------------------------------------------------
| Identifiant d'application => section du site, page d'arrivée, nom et jeu d'icônes (public/app-icons/<jeu>).
| L'identifiant sert aussi de schéma d'adresse (com.tunivisions.tlink://) pour revenir dans l'application ; il doit rester
| identique à capacitor.config.ts, au manifeste Android et à Info.plist. Chaque sous-domaine publie son propre manifeste
| installable (/manifest.webmanifest) avec le nom et le logo de son application.
*/
return [
    'apps' => [
        'com.tunivisions.tlink' => ['section' => 'app', 'path' => '/feed', 'name' => 'T.Link', 'icons' => 'link', 'description' => 'Le réseau de la Fondation Tunivisions'],
        'com.tunivisions.stores' => ['section' => 'store', 'path' => '/boutique', 'name' => 'Tunivisions Stores', 'short_name' => 'Stores', 'icons' => 'store', 'description' => 'La boutique de la Fondation Tunivisions'],
        'com.tunivisions.academy' => ['section' => 'academy', 'path' => '/academy', 'name' => 'Tunivisions Academy', 'short_name' => 'Academy', 'icons' => 'academy', 'description' => 'Les formations de la Fondation Tunivisions'],
        'com.tunivisions.messenger' => ['section' => 'messenger', 'path' => '/messagerie', 'name' => 'T.Link Messenger', 'short_name' => 'Messenger', 'icons' => 'messenger', 'description' => 'La messagerie de T.Link'],
    ],

    // Empreintes SHA-256 du certificat de signature des APK (séparées par des virgules, format AA:BB:…) : elles permettent à Android
    // d'ouvrir directement l'application installée quand on touche un lien du site (App Links, /.well-known/assetlinks.json).
    // Elles s'affichent à la fin du workflow « Mobile Android ».
    'android_fingerprints' => array_values(array_filter(array_map(fn (string $value): string => strtoupper(trim($value)), explode(',', (string) env('ANDROID_CERT_FINGERPRINTS', ''))))),
];
