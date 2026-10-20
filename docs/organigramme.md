# Organigramme de la Fondation We Are Carthage

Page interactive et modifiable : `/organigramme` (connexion requise).
Titre affiché : **Organigramme de la Fondation We Are Carthage**.

## 1. Droits d'accès

| Profil | Droits |
| --- | --- |
| Tout membre connecté | Lecture : navigation, recherche, fiches, exports PNG / SVG / PDF / impression, export CSV. |
| Administrateur global (permission `entity.manage` en périmètre **global**) | Tout : ajouter / modifier / supprimer / déplacer / rattacher, gérer les personnes et leurs photos, enregistrer les positions, importer / exporter en JSON, consulter l'historique. |

Aucune nouvelle permission n'est créée : la page réutilise `entity.manage` (donc aucun `db:seed` à rejouer).
Un administrateur limité à une entité reste en lecture seule : l'organigramme est transversal à toute la fondation.
Toute écriture est contrôlée côté serveur (`403` sinon), pas seulement masquée dans l'interface.

Les coordonnées (téléphone, e-mail) d'une personne dont « Coordonnées visibles par tous » est décoché ne sont **jamais envoyées** aux lecteurs.
Les notes internes ne sont visibles que des administrateurs.

## 2. Structure initiale (pré-remplie)

Le premier accès à la page crée automatiquement l'organigramme initial (idempotent, rien n'est recréé ensuite).
Tous les postes sont **vacants** : aucun nom n'est inventé.

- **Présidence** : Président de la fondation.
  - *Conseillers du président* et *Ancien président* : **rattachements** (trait pointillé gris), sans lien hiérarchique avec le directeur exécutif.
- **Directeur exécutif**.
  - **Bureau national University** : Coordinateur national → Manager Marketing (+ Assistant Marketing), Manager RH (+ Assistant RH), Manager Events, Manager R&D, branche **University Clubs**.
  - **Bureau national Highschool** : même structure, branche **High School Clubs**.
  - **Bureau We Are Carthage** : Chef de projet, Managers, Équipe opérationnelle, **Réseau des clubs régionaux We Are**.
  - **Projet We Are Campers** : Chef de projet, Managers, Équipe opérationnelle.
  - **Réseau Alumni** : Coordinateur national, Managers, Équipe opérationnelle.
- **24 clubs régionaux** « We Are <gouvernorat> », chacun avec une équipe type modifiable : Président du club régional, VP Marketing, VP RH, VP Events, VP R&D, Assistants et membres.
- **University Clubs** et **High School Clubs** : branches distinctes, sans limite de nombre. Chaque club créé reçoit (au choix) l'équipe type : Président, VP Marketing / RH / Events / R&D, Assistant Marketing, Assistant RH, autres membres.

Chaque club régional / université / lycée crée aussi (ou réutilise) une ligne de la table `clubs`, ce qui le relie au reste de la plateforme (rôles, publications, périmètres).

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

| Action | Comment |
| --- | --- |
| Ajouter un poste / une unité | **Ajouter ▾ → Poste ou unité**, ou **Ajouter un sous-poste** dans la fiche. |
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

## 5. Liens avec les entités, départements, institutions et clubs

L'organigramme n'est pas un silo : ses blocs sont rattachés à la structure de la plateforme.

| Bloc | Liens |
| --- | --- |
| Blocs système (présidence, bureaux nationaux University / High School, Alumni) | `entity_id` + `department_id` explicites (Fondation / Bureau national, University / Bureau universitaire, High School / Bureau lycée, Alumni / Bureau alumni) |
| Sous-blocs (managers, postes…) | **héritent** de l'entité et du département du plus proche bloc parent (calculé à la lecture, affiché « hérité ») |
| Bloc « club » | suit son **club** : entité, département, institution, gouvernorat, nom. Le club reste la source de vérité |

- Chaque club d'université ou de lycée (donc les clubs importés de l'ancienne plateforme) a son bloc, rangé par gouvernorat : *University Clubs › Tunis › ESPRIT GCEM*. Un club créé, renommé, déplacé de gouvernorat ou d'entité, ou dont l'institution change (admin, import, organigramme) met à jour son bloc automatiquement (`App\Observers\ClubObserver`). Les clubs régionaux « We Are » restent directement sous le réseau régional.
- Depuis la fiche d'un bloc : section **Liens avec la plateforme** (entité, département, institution, club, gouvernorat, nombre d'adhérents actifs — administrateurs uniquement — et liens vers les pages d'administration). Dans « Modifier le bloc », les listes Entité / Département / Institution sont filtrées par entité ; pour un bloc club, elles modifient le club.
- Depuis l'administration : l'icône « Voir dans l'organigramme » des listes Entités, Départements, Institutions et Clubs ouvre l'organigramme sur le bloc lié (`/organigramme/lien/{entity|department|institution|club}/{id}` → `/organigramme?bloc=ID`).
- **Synchroniser** (menu *Ajouter* › « Synchroniser avec les clubs », ou `php artisan orgchart:sync`) : relie les blocs système et crée les blocs des clubs qui n'en ont pas. Idempotent.
- Les exports JSON / CSV incluent entité, département et institution (par *slug*) ; l'import les restaure.

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
php artisan orgchart:sync     # relie l'organigramme aux entités / clubs (après un import legacy notamment)
php artisan storage:link      # une seule fois : photos des personnes (disque public)
php artisan optimize:clear
```

Aucun seeder à lancer : l'organigramme initial est créé à la première ouverture de `/organigramme`
(les gouvernorats manquants sont créés si besoin ; les clubs « We Are … » déjà présents dans `clubs` sont réutilisés).

Les photos sont stockées dans `storage/app/public/orgchart/people`.

## 8. Dépannage

- **Page blanche / « Impossible de charger »** : vérifier `php artisan migrate`, puis `storage/logs/laravel.log`.
- **Photos en 404** : `php artisan storage:link` (si `rm public/storage` est nécessaire sur le serveur, utiliser `rm -f`).
- **Les modifications ne s'affichent pas après un déploiement** : les assets sont versionnés par date de modification ; faire un rechargement forcé si un CDN ou un cache intermédiaire est actif.
- **Impression bloquée** : autoriser les fenêtres contextuelles pour le site, ou utiliser « PDF (plusieurs pages A3) » puis imprimer le fichier.
- **Restaurer l'état d'origine** : exporter d'abord en JSON ; pour repartir du gabarit, vider les tables `org_assignments`, `org_people`, `org_nodes` puis rouvrir la page.
