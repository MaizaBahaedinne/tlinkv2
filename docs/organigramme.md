# Organigramme de la Fondation We Are Carthage

Page interactive et modifiable : `/organigramme` (connexion requise).
Titre affiché : **Organigramme de la Fondation We Are Carthage**.

## 1. Droits d'accès

| Profil | Droits |
| --- | --- |
| Tout membre connecté | Lecture : navigation, recherche, fiches, exports PNG / SVG / PDF / impression, export CSV. |
| Administrateur global (permission `entity.manage` en périmètre **global**) | Tout : ajouter / modifier / supprimer / déplacer / rattacher, gérer les personnes et leurs photos, enregistrer les positions, importer / exporter en JSON, consulter l'historique. |

Aucune nouvelle permission n'est créée : la page réutilise `entity.manage` (donc aucun `db:seed` à rejouer).
Un administrateur limité à un périmètre reste en lecture seule : l'organigramme est transversal à toute la fondation.
Toute écriture est contrôlée côté serveur (`403` sinon), pas seulement masquée dans l'interface.

Les coordonnées (téléphone, e-mail) d'une personne dont « Coordonnées visibles par tous » est décoché ne sont **jamais envoyées** aux lecteurs.
Les notes internes ne sont visibles que des administrateurs.

## 2. Structure initiale (pré-remplie)

Le premier accès à la page crée automatiquement l'organigramme initial (idempotent, rien n'est recréé ensuite).
Tous les postes sont **vacants** : aucun nom n'est inventé.

- **Sommet : Tunivisions Foundation** (bloc « unité », sans personne, périmètre Fondation).
- **Présidence** (sous la fondation) : Président de la fondation.
  - *Conseillers du président* et *Ancien président* : **rattachements** (trait pointillé gris), sans lien hiérarchique avec le directeur exécutif.
- **Directeur exécutif**.
  - **Bureau national University** : Coordinateur national → Manager Marketing (+ Assistant Marketing), Manager RH (+ Assistant RH), Manager Events, Manager R&D, branche **University Clubs**.
  - **Bureau national Highschool** : même structure, branche **High School Clubs**.
  - **Bureau We Are Carthage** : Chef de projet, Managers, Équipe opérationnelle, **Réseau des clubs régionaux We Are**.
  - **Projet We Are Campers** : Chef de projet, Managers, Équipe opérationnelle.
  - **Réseau Alumni** : Coordinateur national, Managers, Équipe opérationnelle.
- **24 clubs régionaux** « We Are <gouvernorat> », chacun avec une équipe type modifiable : Président du club régional, VP Marketing, VP RH, VP Events, VP R&D, Assistants et membres.
- **University Clubs** et **High School Clubs** : branches distinctes, sans limite de nombre. Chaque club créé reçoit (au choix) l'équipe type, **identique pour toutes les unités** : un **Président** et **4 départements** (Marketing, RH, Events, R&D), chacun avec **1 VP, 1 Assistant et des Membres** (plusieurs personnes), soit 13 postes. La structure des clubs régionaux We Are est différente et sera précisée séparément.

Chaque club régional / université / lycée est un **bloc « club »** de l'organigramme : il porte lui-même ses informations (statut, e-mail, Facebook, date de fondation, charte, établissement). Il n'existe plus de table `clubs`.

### Relations confirmées ou « à valider »

Les relations déduites du descriptif et non explicitement confirmées sont marquées **À valider** : trait **orange pointillé**, cadre pointillé et pastille « ? ».
Ce sont notamment :

- le rattachement des bureaux nationaux et des projets au directeur exécutif ;
- l'emplacement des branches University Clubs / High School Clubs sous leur coordinateur ;
- les équipes « si nécessaire » (Équipe opérationnelle) et les blocs « Assistants et membres » / « Autres membres » des équipes types.

Pour confirmer une relation : sélectionner le bloc → **Modifier le bloc** → *Statut du lien* = Confirmé (la note de validation peut être vidée).
Le filtre « Mettre en évidence les relations à valider » (panneau de gauche) estompe tout le reste.

## 3. Utilisation

### Navigation (tous)

- **Zoom** : molette, pincement tactile, boutons − / + ; **⤢** ajuste tout le diagramme à l'écran.
- **Déplacement** : glisser le fond (souris ou doigt) ; flèches du clavier quand le diagramme est focalisé.
- **Replier / déplier** : pastille sous (ou à droite de) chaque bloc ; « Tout déplier » / « Tout replier » ; l'état est mémorisé dans le navigateur.
- **Recherche** : personne, poste, club, gouvernorat. Cliquer un résultat déplie la branche, centre et sélectionne le bloc.
- **Fiche** (panneau de droite) : catégorie, rattachement (fil d'Ariane cliquable), personne(s) avec photo, fonction, téléphone et e-mail professionnels, description, sous-blocs.
- **Réinitialiser la vue** : recentre, ajuste le zoom et rétablit le pliage par défaut.
- **Légende** : couleurs par catégorie (Présidence, Direction exécutive, Bureaux nationaux, Projets & réseaux, Clubs régionaux, University Clubs, High School Clubs) et types de traits.

### Édition (administrateurs globaux)

**Types de blocs** (l'organigramme est la seule base de la structure de la fondation) :

| Type | Rôle |
| --- | --- |
| Poste | Seul type qui accueille des personnes (avatar affiché). |
| Unité / branche | Regroupement (ex. branche des clubs) : sans avatar ni personne. |
| Département de bureau national | Marketing, RH, Events, R&D d'un bureau national ; ne peut pas se trouver dans un club. |
| Club | Porte ses informations (périmètre, statut, établissement, contacts, charte, gouvernorat) ; sans avatar. |
| Département de club | Marketing, RH, Events, R&D d'un club ; uniquement dans un club. |

**Capacité d'un poste** : *Un seul titulaire* (défaut) ou *Plusieurs personnes* (assistants, membres…). Vérifications côté serveur : un poste à titulaire unique refuse une deuxième personne (affectation, import), un poste ne peut pas repasser en titulaire unique s'il a déjà plusieurs personnes, et un bloc qui a des personnes ne peut pas changer pour un type autre que poste.

Les bureaux nationaux et les équipes types de club sont rangés sous des départements. `php artisan orgchart:sync` convertit une ancienne structure plate (managers / vice-présidents directement sous leur coordinateur / président) ; l'opération est idempotente.

| Action | Comment |
| --- | --- |
| Ajouter un poste, une unité ou un département | **Ajouter ▾ → Poste, unité ou département**, ou **Ajouter un sous-poste** dans la fiche. Voir « Types de blocs » ci-dessous. |
| Ajouter un club | **Ajouter ▾ → Club** : type (régional, University, High School), nom, gouvernorat, équipe type. |
| Affecter un utilisateur | Champ **« Affecter un utilisateur »** dans la fiche : recherche par nom ou e-mail dans les comptes actifs. **Une personne de l'organigramme est un utilisateur** : nom, photo, e-mail et téléphone sont lus dans le compte (rien n'est copié, ils se mettent à jour seuls) ; la fiche ne porte que la fonction, la visibilité des coordonnées et des notes internes. Les e-mails de remplacement des comptes importés (`@legacy.invalid`) ne sont pas affichés. Un même utilisateur sur plusieurs postes n'a qu'une seule fiche. |
| Retirer | Bouton ⊖ dans la fiche (retire du poste) ; « Retirer de l'organigramme » dans la fiche personne (retire de tous ses postes, le compte n'est pas supprimé). |
| Affecter quelqu'un déjà dans l'organigramme | Liste « Ou quelqu'un déjà dans l'organigramme » dans la fiche. |
| Modifier un bloc | **Modifier le bloc** ou double-clic sur le bloc : intitulé, catégorie (couleur), rattachement, nature du lien, statut, disposition, repli par défaut… |
| Déplacer un poste vers un autre manager | Dans **Modifier le bloc**, champ *Rattaché à* ; ou en mode **Modifier**, **Maj + glisser-déposer** sur le nouveau parent (confirmation demandée). |
| Réordonner des frères | **Monter / Descendre** dans la fiche. |
| Déplacer un bloc librement | **Modifier** (mode édition) puis glisser le bloc : son sous-arbre le suit. **Enregistrer** valide les positions. « Position automatique » (fiche) rétablit la mise en page calculée. |
| Supprimer | **Supprimer** dans la fiche. Un bloc ayant des sous-blocs exige de cocher « Supprimer aussi les sous-blocs ». La racine ne peut pas être supprimée. |
| Équipe type d'un club vide | **Appliquer l'équipe type** dans la fiche du club. |
| Historique | **Historique** (toolbar) ou **Historique du bloc** (fiche) : qui, quand, quoi (ancienne → nouvelle valeur). |

Un message avertit si l'on quitte la page avec des positions non enregistrées.

### Validations (serveur)

- une seule racine ;
- aucun cycle (un bloc ne peut pas devenir descendant de lui-même) ;
- un rattachement (trait pointillé) ne peut pas servir de parent, et un bloc qui a des enfants ne peut pas devenir rattachement ;
- suppression d'un bloc avec enfants refusée sans cascade explicite ;
- téléphone : chiffres, espaces, `+ - ( )` uniquement ; e-mail valide ; photo : image 2 Mo max ;
- chaque modification est tracée dans le journal d'audit (`orgchart.*`).

### Gestion par tableaux et fiches (back-office)

Le diagramme reste la vue d'ensemble ; le quotidien se fait dans **Admin > Organisation** (lecture pour qui a `entity.manage`, écriture réservée aux administrateurs globaux).

| Écran | Rôle |
| --- | --- |
| **Postes et droits** (`/admin/structure/postes`) | Tableau de tous les blocs : poste, club, type, périmètre (propre ou hérité), niveau d'accès, titulaires (ou « Vacant »). Filtres : recherche (poste, club, titulaire), type, catégorie, périmètre, niveau d'accès, pourvus / vacants. **Action groupée** : cocher des postes (par exemple tous ceux d'un département, après filtrage) puis appliquer un niveau d'accès. Rappel des droits de chaque niveau en haut de page. |
| **Fiche d'un bloc** (`/admin/structure/postes/{id}/modifier`) | Intitulé, type, rattachement, périmètre, capacité, **niveau d'accès** (avec la liste des droits qu'il donne), champs du club, **titulaires** (recherche d'un compte puis « Affecter » / « Retirer »), sous-blocs et ajout d'un sous-bloc, suppression. |
| **Clubs** (`/admin/structure/clubs`) | Tableau des clubs : gouvernorat, statut, président, postes pourvus / total. Création d'un club (équipe standard incluse), **Compléter les équipes** (rattrape les clubs University / High School sans équipe ou incomplets). Chaque ligne ouvre la page du club. **Statut** : compteurs par statut en haut, changement du statut des clubs cochés ou de tous les clubs du filtre en une action. Seuls les clubs **Actifs** apparaissent sur le site public, dans l'API et dans les statistiques. Le drapeau legacy `is_Actif` ne sert qu'à la création du club à l'import : un ré-import ne change plus un statut déjà fixé. |
| **Page d'un club** (`/admin/structure/clubs/{id}`) | Organigramme du club (Président, départements, VP / Assistant / Membres) avec les titulaires ou « Vacant », tableau des postes, adhésions par statut, publications et événements du club (`node_id`), activité récente (journal d'audit des blocs du club). |
| **Droits des clubs** (`/admin/structure/roles`) | Niveau d'accès de chacun des 13 postes de l'équipe standard, **identique dans tous les clubs University / High School**. L'enregistrement s'applique à tous les clubs existants ; les clubs créés ensuite le reprennent. Niveaux permis : aucun, manager, président de club, modérateur (pas de droits globaux ni d'entité sur un poste de club). Stocké en base (`settings`, groupe `org`, clé `club_role_access`). |

**Chaque poste est un rôle.** Le niveau d'accès du poste (`AccessLevels`) donne ses permissions à la personne qui l'occupe, **dans le périmètre du poste** (University, High School, Alumni, Fondation), propre ou hérité du bloc parent. Seul l'administrateur global agit sur toute la plateforme. Pour donner les mêmes droits à tout un département, filtrer le tableau puis utiliser l'action groupée.

Garde-fou : une modification (niveau d'accès, retrait d'un titulaire, suppression) qui retirerait à son auteur ses propres droits d'administrateur global est annulée.

L'alignement des clubs existants est aussi lancé par `php artisan migrate` (migrations `2026_10_25_200000_align_club_teams_with_template` et `2026_10_26_100000_add_role_key_to_org_nodes`) et par `php artisan orgchart:sync`. Il est idempotent : un club University / High School **sans aucun poste** (par exemple importé de l'ancienne base) reçoit l'équipe standard, assistants placés sous le VP remontés au niveau du département, assistants et membres manquants créés, ancien bloc vide « Autres membres et responsables » retiré. Les clubs importés depuis le legacy reçoivent l'équipe dès l'import. Les personnes déjà affectées ne sont jamais déplacées ni retirées.

**Rôle d'un poste de club.** Chaque bloc de l'équipe standard porte un `role_key` (`president`, `dept-marketing`, `vp-marketing`, `assistant-marketing`, `members-marketing`, etc.). Le niveau d'accès d'un poste à rôle vient de l'écran « Droits des clubs » : il est en lecture seule sur la fiche et ignoré par l'action groupée. Les clubs régionaux We Are gardent leur structure propre (sans `role_key`).

## 4. Exports, impression, import

| Format | Contenu |
| --- | --- |
| **PNG** | Image haute résolution (×2, bornée à la limite du navigateur), titre, date, légende, photos intégrées. |
| **SVG** | Vectoriel autonome (photos intégrées), éditable dans Illustrator / Inkscape / Figma. |
| **PDF (une page)** | Diagramme complet ajusté sur une page A3. |
| **PDF (plusieurs pages A3)** | Échelle lisible, découpage en tuiles numérotées (« ligne x, colonne y »), jusqu'à 18 pages. Orientation portrait ou paysage selon la forme du diagramme. |
| **Imprimer** | Génère le PDF multi-pages et l'ouvre dans un onglet pour impression (autoriser les fenêtres contextuelles). |
| **CSV** | Une ligne par bloc : chemin hiérarchique, catégorie, nature du lien, statut, club, gouvernorat, personnes, fonction, téléphone, e-mail (point-virgule, UTF-8 avec BOM, formules neutralisées). Coordonnées masquées pour les lecteurs si non publiques. |
| **JSON** (admin) | Sauvegarde complète versionnée (blocs, personnes, affectations). |
| **Import JSON** (admin) | **Remplace entièrement** l'organigramme après confirmation explicite, dans une transaction (tout ou rien). Validation complète : identifiants uniques, parents existants, cycles, racine unique, types, limites (3000 blocs). Les photos ne sont pas incluses dans le JSON. |

Les exports reflètent **ce qui est affiché** (blocs repliés = non exportés) : dépliez avant d'exporter pour un document complet.

## 5. Périmètres et clubs

L'organigramme est l'unique source de la structure : les anciennes tables `entities`, `departments`, `institutions` et `clubs` ont été supprimées.

**Périmètre.** Chaque bloc appartient à un périmètre : `foundation` (Fondation), `university` (University), `high-school` (High School) ou `alumni` (Alumni), défini dans `App\Support\OrgScopes`. Les adhésions, publications et événements portent ce périmètre (`scope`) et, le cas échéant, le club concerné (`node_id`, bloc de type club).

| Bloc | Périmètre |
| --- | --- |
| Blocs système (présidence, bureaux nationaux University / High School, Alumni) | explicite (`scope`) |
| Sous-blocs (managers, postes…) | **hérité** du plus proche bloc parent (calculé à la lecture, affiché « hérité ») |
| Bloc « club » | son propre périmètre, utilisé pour le rangement par branche et pour les droits |

- Droits : un poste à accès limité (voir `AccessLevels`) agit sur son périmètre, propre ou hérité. `OrgAccess::grants()` renvoie, par permission, la liste des périmètres (`null` = global). L'API expose `managed_scopes`.
- Club : dans « Modifier le bloc », la section *Club* regroupe le statut (actif, inactif, archivé, brouillon), la date de fondation, l'établissement (nom et type), l'e-mail, la page Facebook et la charte signée. Ces champs sont ignorés pour les autres types de blocs.
- Chaque club d'université ou de lycée (donc les clubs importés de l'ancienne plateforme) est rangé par gouvernorat : *University Clubs › Tunis › ESPRIT GCEM*. Un club rangé à la main hors d'un groupe de gouvernorat n'est jamais déplacé. Les clubs régionaux « We Are » restent directement sous le réseau régional.
- **Synchroniser** (menu *Ajouter* › « Réaligner les clubs et périmètres », ou `php artisan orgchart:sync`) : pose le périmètre des blocs système et range les clubs dans leur groupe de gouvernorat. Idempotent.
- Les exports JSON / CSV incluent périmètre, statut, établissement et identifiant legacy ; l'import les restaure. L'import reconstruit les blocs mais conserve les liens des adhésions, publications et événements avec les clubs (par identifiant legacy, clé système, sinon titre et gouvernorat).
- API : `GET /api/v1/entities` renvoie les périmètres, `GET /api/v1/clubs` les clubs (blocs de type club). Une adhésion se demande avec `scope` (et `node_id` optionnel).

### Migration depuis les anciennes tables

La migration `2026_10_23_100000_fold_structure_into_org_chart` reprend les données puis supprime les tables. **Elle est irréversible** : une sauvegarde JSON complète (tables supprimées et liens des adhésions / publications / événements) est écrite dans `storage/app/backups/structure-*.json`. Faites une sauvegarde de la base avant de la lancer.

## 6. Architecture

| Élément | Emplacement |
| --- | --- |
| Migration | `database/migrations/2026_10_19_100000_create_org_chart_tables.php` (`org_nodes`, `org_people`, `org_assignments`) |
| Modèles | `app/Models/OrgNode.php`, `OrgPerson.php`, `OrgAssignment.php` |
| Gabarit initial | `app/Support/OrgChart/OrgChartInstaller.php` (clés `system_key` stables, idempotent) |
| Règles (cycles, suppression) | `app/Support/OrgChart/OrgChartRules.php` |
| Import / export | `app/Support/OrgChart/OrgChartTransfer.php` |
| Liens plateforme | `app/Support/OrgChart/OrgChartLinker.php`, `app/Observers/ClubObserver.php`, migration `2026_10_21_100000_link_org_nodes_to_structure.php` |
| Contrôleur / routes | `app/Http/Controllers/OrgChartController.php`, groupe `orgchart.*` de `routes/web.php` |
| Page | `resources/views/orgchart/index.blade.php` |
| Interface | `public/backoffice/orgchart.css`, `orgchart.js` (application), `orgchart-layout.js` (disposition pure), `orgchart-export.js` (SVG / PNG / PDF) |
| Tests | `tests/Feature/OrgChartTest.php`, `tests/Feature/OrgChartLinksTest.php` |

Choix techniques :

- aucune dépendance front supplémentaire ni étape de build : moteur SVG en JavaScript natif, servi comme les assets du back-office ;
- le dossier d'assets s'appelle `backoffice` (jamais `organigramme`, qui entrerait en collision avec la route du même nom) ;
- le contenu dynamique est inséré via `textContent` : pas d'injection HTML ;
- relations : `parent_id` + `relation_type` (`hierarchy` / `attachment`) ; chaque bloc a un identifiant unique et un `system_key` optionnel pour retrouver les blocs du gabarit ;
- dispositions : `horizontal` (arbre classique) ou `vertical` (liste arborescente compacte, héritée par les descendants — utilisée sous les coordinateurs, les chefs de projet et les réseaux de clubs pour garder un diagramme lisible avec 24+ clubs).

## 7. Déploiement

```bash
git pull origin main
php artisan migrate --force
php artisan orgchart:sync     # périmètres des blocs système et rangement des clubs (après un import legacy notamment)
php artisan storage:link      # une seule fois : photos des personnes (disque public)
php artisan optimize:clear
```

Aucun seeder à lancer : l'organigramme initial est créé à la première ouverture de `/organigramme`
(les gouvernorats manquants sont créés si besoin).

Les photos sont stockées dans `storage/app/public/orgchart/people`.

## 8. Dépannage

- **Page blanche / « Impossible de charger »** : vérifier `php artisan migrate`, puis `storage/logs/laravel.log`.
- **Photos en 404** : `php artisan storage:link` (si `rm public/storage` est nécessaire sur le serveur, utiliser `rm -f`).
- **Les modifications ne s'affichent pas après un déploiement** : les assets sont versionnés par date de modification ; faire un rechargement forcé si un CDN ou un cache intermédiaire est actif.
- **Impression bloquée** : autoriser les fenêtres contextuelles pour le site, ou utiliser « PDF (plusieurs pages A3) » puis imprimer le fichier.
- **Restaurer l'état d'origine** : exporter d'abord en JSON ; pour repartir du gabarit, vider les tables `org_assignments`, `org_people`, `org_nodes` puis rouvrir la page.
