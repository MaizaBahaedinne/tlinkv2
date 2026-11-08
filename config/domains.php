<?php

/*
|--------------------------------------------------------------------------
| Sous-domaines (une seule base de code, une seule base de données)
|--------------------------------------------------------------------------
| Chaque hôte est facultatif : s'il est vide, la section correspondante reste servie par l'hôte principal, sous son
| chemin habituel (/boutique, /academy, /fr…). Exemple en production :
|   DOMAIN_SITE=tunivisionsfoundation.org  DOMAIN_APP=link.tunivisionsfoundation.org
|   DOMAIN_STORE=store.tunivisionsfoundation.org  DOMAIN_ACADEMY=academy.tunivisionsfoundation.org
| Pour partager la session entre les sous-domaines : SESSION_DOMAIN=.tunivisionsfoundation.org.
*/
return [
    'hosts' => [
        // Site vitrine de la Fondation (/, /fr, /ar, /en…).
        'site' => env('DOMAIN_SITE'),
        // T.Link : fil, profil, administration, connexion, organigramme, messagerie…
        'app' => env('DOMAIN_APP'),
        // Tunivisions Stores.
        'store' => env('DOMAIN_STORE'),
        // Tunivisions Academy (et certificats publics).
        'academy' => env('DOMAIN_ACADEMY'),
        // T.Link Messenger : application de messagerie (mêmes routes /messagerie, racine redirigée vers la messagerie).
        'messenger' => env('DOMAIN_MESSENGER'),
    ],

    // Routes de T.Link restant utilisables depuis les sous-domaines Store et Academy (barre de navigation, messagerie…) :
    // elles s'exécutent sur l'hôte courant, la session étant partagée. Les autres adresses de T.Link sont redirigées vers l'hôte « app ».
    'shared_routes' => [
        'app.root',
        'messenger.*',
        'notifications.*',
        'search.*',
        'media.*',
        'devices.*',
        'broadcasting.auth',
        'logout',
    ],
];
