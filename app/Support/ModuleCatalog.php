<?php

namespace App\Support;

use App\Models\AppModule;

/**
 * Catalogue par défaut des modules et sous-modules de T.Link.
 * Il est inséré à l'installation (sans jamais écraser les modifications de l'administrateur)
 * et peut être complété à tout moment depuis l'administration.
 */
final class ModuleCatalog
{
    private const CRUD = ['create', 'read', 'update', 'delete'];

    /**
     * @return array<int, array{key: string, name: string, description: string, children: array<int, array{key: string, name: string, description: string, actions: array<int, string>}>}>
     */
    public static function defaults(): array
    {
        return [
            [
                'key' => 'feed', 'name' => 'Fil d’actualité', 'description' => 'Publications et interactions de la communauté.',
                'children' => [
                    ['key' => 'feed.posts', 'name' => 'Publications', 'description' => 'Publier, modifier et supprimer des publications (privées, entité ou publiques).', 'actions' => self::CRUD],
                    ['key' => 'feed.comments', 'name' => 'Commentaires', 'description' => 'Commenter les publications.', 'actions' => self::CRUD],
                    ['key' => 'feed.reactions', 'name' => 'Réactions', 'description' => 'Réagir aux publications.', 'actions' => ['create', 'read', 'delete']],
                    ['key' => 'feed.shares', 'name' => 'Partages', 'description' => 'Partager une publication.', 'actions' => ['create', 'read', 'delete']],
                    ['key' => 'feed.attachments', 'name' => 'Pièces jointes', 'description' => 'Images, vidéos et fichiers joints aux publications.', 'actions' => ['create', 'read', 'delete']],
                    ['key' => 'feed.public_posts', 'name' => 'Publications publiques', 'description' => 'Publier des contenus visibles publiquement.', 'actions' => ['create']],
                ],
            ],
            [
                'key' => 'events', 'name' => 'Événements', 'description' => 'Événements de la Fondation et inscriptions.',
                'children' => [
                    ['key' => 'events.events', 'name' => 'Événements', 'description' => 'Créer et administrer les événements.', 'actions' => self::CRUD],
                    ['key' => 'events.registrations', 'name' => 'Inscriptions aux événements', 'description' => 'Inscriptions, liste d’attente et présences.', 'actions' => self::CRUD],
                ],
            ],
            [
                'key' => 'members', 'name' => 'Communauté', 'description' => 'Membres, profils et adhésions.',
                'children' => [
                    ['key' => 'members.users', 'name' => 'Comptes utilisateurs', 'description' => 'Liste, fiches et administration des comptes.', 'actions' => self::CRUD],
                    ['key' => 'members.profiles', 'name' => 'Profils', 'description' => 'Profil du membre (photo, coordonnées, présentation).', 'actions' => ['read', 'update']],
                    ['key' => 'members.memberships', 'name' => 'Adhésions', 'description' => 'Demandes d’adhésion à un périmètre ou à un club.', 'actions' => self::CRUD],
                    ['key' => 'members.entity_members', 'name' => 'Membres de mon entité', 'description' => 'Liste, ajout, suspension et retrait des membres de sa propre entité (sans les sous-entités).', 'actions' => ['create', 'read', 'update']],
                    ['key' => 'members.info_session', 'name' => 'Info session et quiz', 'description' => 'Vidéo d’accueil et quiz de validation du compte.', 'actions' => ['read', 'update']],
                    ['key' => 'members.companies', 'name' => 'Entreprises', 'description' => 'Base des entreprises citées dans les profils, avec leur logo.', 'actions' => self::CRUD],
                    ['key' => 'members.verification', 'name' => 'Vérifications d’identité', 'description' => 'Examen des pièces d’identité (CIN et selfie) pour le badge « Profil vérifié ». Données sensibles.', 'actions' => ['read', 'update']],
                ],
            ],
            [
                'key' => 'organization', 'name' => 'Organisation', 'description' => 'Structure de la Fondation : organigramme, postes, clubs et mandats.',
                'children' => [
                    ['key' => 'organization.chart', 'name' => 'Organigramme', 'description' => 'Blocs, personnes et affectations de l’organigramme.', 'actions' => self::CRUD],
                    ['key' => 'organization.my_office', 'name' => 'Mon bureau', 'description' => 'Voir ses supérieurs et son équipe ; un supérieur affecte et retire les titulaires des postes situés sous le sien.', 'actions' => ['read', 'update']],
                    ['key' => 'organization.positions', 'name' => 'Postes et droits', 'description' => 'Postes, titulaires, niveaux d’accès et périmètres.', 'actions' => self::CRUD],
                    ['key' => 'organization.applications', 'name' => 'Candidatures aux postes', 'description' => 'Candidatures déposées via le lien d’inscription d’un poste.', 'actions' => ['create', 'read', 'update']],
                    ['key' => 'organization.clubs', 'name' => 'Clubs', 'description' => 'Clubs régionaux, universitaires et lycées.', 'actions' => self::CRUD],
                    ['key' => 'organization.institutions', 'name' => 'Établissements', 'description' => 'Lycées, universités, facultés et écoles liés aux clubs, avec leur logo.', 'actions' => self::CRUD],
                    ['key' => 'organization.mandates', 'name' => 'Mandats', 'description' => 'Mandats et bureaux successifs.', 'actions' => self::CRUD],
                    ['key' => 'organization.scopes', 'name' => 'Périmètres', 'description' => 'Hiérarchie des périmètres (espaces) de la Fondation.', 'actions' => self::CRUD],
                ],
            ],
            [
                'key' => 'academy', 'name' => 'Tunivisions Academy', 'description' => 'Cours en ligne, abonnements, paiements et calendrier des sessions.',
                'children' => [
                    ['key' => 'academy.courses', 'name' => 'Cours', 'description' => 'Cours, modules, leçons, badges et points associés.', 'actions' => self::CRUD],
                    ['key' => 'academy.sessions', 'name' => 'Calendrier des sessions', 'description' => 'Sessions en direct du calendrier Academy.', 'actions' => self::CRUD],
                    ['key' => 'academy.orders', 'name' => 'Commandes et paiements', 'description' => 'Validation des paiements et accès accordés.', 'actions' => ['read', 'update']],
                    ['key' => 'academy.plans', 'name' => 'Abonnements et réglages', 'description' => 'Formules d’abonnement et réglages du paiement.', 'actions' => self::CRUD],
                ],
            ],
            [
                'key' => 'store', 'name' => 'Tunivisions Stores', 'description' => 'Boutique : articles, stock, packs, commandes, livraison et retours.',
                'children' => [
                    ['key' => 'store.catalog', 'name' => 'Catalogue', 'description' => 'Catégories, types, couleurs, tailles, articles et packs. Désactiver ce module ferme la boutique pour tous.', 'actions' => self::CRUD],
                    ['key' => 'store.stock', 'name' => 'Stock', 'description' => 'Niveaux de stock et réapprovisionnements.', 'actions' => ['read', 'update']],
                    ['key' => 'store.orders', 'name' => 'Commandes', 'description' => 'Suivi, paiement, expédition et annulation des commandes.', 'actions' => ['read', 'update']],
                    ['key' => 'store.returns', 'name' => 'Retours', 'description' => 'Demandes de retour, réception, remboursement ou échange.', 'actions' => ['read', 'update']],
                    ['key' => 'store.settings', 'name' => 'Réglages, livraison et paiement', 'description' => 'Ouverture de la boutique, modes de livraison et de paiement.', 'actions' => ['read', 'update']],
                ],
            ],
            [
                'key' => 'messenger', 'name' => 'T.Link Messenger', 'description' => 'Messagerie instantanée entre membres.',
                'children' => [
                    ['key' => 'messenger.chat', 'name' => 'Messagerie', 'description' => 'Conversations directes et de groupe entre membres, en temps réel. Désactiver ce module ferme la messagerie pour tous.', 'actions' => ['read', 'update']],
                ],
            ],
            [
                'key' => 'mentoring', 'name' => 'Mentorat', 'description' => 'Accompagnement des membres et de leurs collectifs par des mentors.',
                'children' => [
                    ['key' => 'mentoring.tickets', 'name' => 'Tickets de mentorat', 'description' => 'Demandes, incidents et problèmes remontés par les membres ou leur collectif : qualification par le manager mentor, affectation à un mentor, suivi et REX. Désactiver ce module ferme le mentorat pour tous.', 'actions' => ['read', 'update']],
                ],
            ],
            [
                'key' => 'governance', 'name' => 'Gouvernance', 'description' => 'Validation, modération et traçabilité.',
                'children' => [
                    ['key' => 'governance.approvals', 'name' => 'Approbations', 'description' => 'Demandes en attente de validation.', 'actions' => ['read', 'update']],
                    ['key' => 'governance.moderation', 'name' => 'Modération', 'description' => 'Signalements et suppression de contenus.', 'actions' => ['read', 'update', 'delete']],
                    ['key' => 'governance.audit', 'name' => 'Journal d’audit', 'description' => 'Historique des actions sensibles.', 'actions' => ['read']],
                    ['key' => 'governance.roadmap', 'name' => 'Feuille de route', 'description' => 'Avancement des modules du projet.', 'actions' => ['read', 'update']],
                    ['key' => 'governance.modules', 'name' => 'Modules et habilitations', 'description' => 'Catalogue des modules de l’application.', 'actions' => self::CRUD],
                ],
            ],
            [
                'key' => 'website', 'name' => 'Site public', 'description' => 'Site vitrine de la Fondation.',
                'children' => [
                    ['key' => 'website.content', 'name' => 'Contenu et textes', 'description' => 'Textes, visuels et coordonnées du site.', 'actions' => ['read', 'update']],
                    ['key' => 'website.partners', 'name' => 'Partenaires', 'description' => 'Logos des partenaires.', 'actions' => self::CRUD],
                    ['key' => 'website.projects', 'name' => 'Projets', 'description' => 'Pages de présentation des projets de la Fondation et leurs chiffres clés.', 'actions' => self::CRUD],
                    ['key' => 'website.messages', 'name' => 'Messages de contact', 'description' => 'Messages reçus via le formulaire de contact.', 'actions' => ['read', 'update', 'delete']],
                    ['key' => 'website.news', 'name' => 'Actualités du site', 'description' => 'Articles affichés sur le site vitrine, indépendants des publications du fil T.Link.', 'actions' => self::CRUD],
                    ['key' => 'website.newsletter', 'name' => 'Newsletter', 'description' => 'Abonnés à la newsletter du site : liste, export et suppression.', 'actions' => ['read', 'delete']],
                    ['key' => 'website.launch', 'name' => 'Lancement du site', 'description' => 'Compte à rebours et ouverture du site.', 'actions' => ['read', 'update']],
                ],
            ],
            [
                'key' => 'gamification', 'name' => 'Badges et missions', 'description' => 'Missions, niveaux et badges des membres.',
                'children' => [
                    ['key' => 'gamification.badges', 'name' => 'Badges et missions', 'description' => 'Créer les badges (bronze, argent, or, platine), leurs missions et leurs points de contrôle.', 'actions' => self::CRUD],
                ],
            ],
            [
                'key' => 'notifications', 'name' => 'Notifications', 'description' => 'Notifications des membres.',
                'children' => [
                    ['key' => 'notifications.inbox', 'name' => 'Boîte de notifications', 'description' => 'Consulter et marquer comme lues.', 'actions' => ['read', 'update', 'delete']],
                ],
            ],
        ];
    }

    /**
     * Un module est actif s'il est actif lui-même et si son parent l'est ; un module inconnu du catalogue reste actif.
     */
    public static function isActive(string $key): bool
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('app_modules')) {
            return true;
        }

        $module = AppModule::query()->where('key', $key)->first(['id', 'parent_id', 'is_active']);

        if ($module === null) {
            return true;
        }

        $parent = $module->parent_id !== null ? AppModule::query()->find($module->parent_id, ['id', 'is_active']) : null;

        return $module->is_active && ($parent === null || $parent->is_active);
    }

    /**
     * Ajoute les modules manquants (par clé) ; ne modifie jamais un module existant.
     *
     * @return int nombre de modules ajoutés
     */
    public static function sync(): int
    {
        $added = 0;

        foreach (self::defaults() as $parentIndex => $parent) {
            $parentModel = AppModule::query()->firstOrCreate(['key' => $parent['key']], [
                'name' => $parent['name'],
                'description' => $parent['description'],
                'actions' => [],
                'is_system' => true,
                'sort_order' => $parentIndex,
            ]);
            $added += $parentModel->wasRecentlyCreated ? 1 : 0;

            foreach ($parent['children'] as $childIndex => $child) {
                $childModel = AppModule::query()->firstOrCreate(['key' => $child['key']], [
                    'parent_id' => $parentModel->id,
                    'name' => $child['name'],
                    'description' => $child['description'],
                    'actions' => $child['actions'],
                    'is_system' => true,
                    'sort_order' => $childIndex,
                ]);
                $added += $childModel->wasRecentlyCreated ? 1 : 0;
            }
        }

        return $added;
    }
}
