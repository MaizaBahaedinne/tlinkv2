<?php

/*
|--------------------------------------------------------------------------
| Applications mobiles (Capacitor)
|--------------------------------------------------------------------------
| Identifiant d'application => section du site et page d'arrivée après une connexion par le navigateur du téléphone.
| L'identifiant sert aussi de schéma d'adresse (com.tunivisions.tlink://) pour revenir dans l'application ; il doit rester
| identique à capacitor.config.ts, au manifeste Android et à Info.plist.
*/
return [
    'apps' => [
        'com.tunivisions.tlink' => ['section' => 'app', 'path' => '/feed'],
        'com.tunivisions.stores' => ['section' => 'store', 'path' => '/boutique'],
        'com.tunivisions.academy' => ['section' => 'academy', 'path' => '/academy'],
        'com.tunivisions.messenger' => ['section' => 'messenger', 'path' => '/messagerie'],
    ],
];
