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
                    ['key' => 'organization.mandates', 'name' => 'Mandats', 'description' => 'Mandats et bureaux successifs.', 'actions' => self::CRUD],
                    ['key' => 'organization.scopes', 'name' => 'Périmètres', 'description' => 'Hiérarchie des périmètres (espaces) de la Fondation.', 'actions' => self::CRUD],
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
                    ['key' => 'website.messages', 'name' => 'Messages de contact', 'description' => 'Messages reçus via le formulaire de contact.', 'actions' => ['read', 'update', 'delete']],
                    ['key' => 'website.launch', 'name' => 'Lancement du site', 'description' => 'Compte à rebours et ouverture du site.', 'actions' => ['read', 'update']],
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
     * Ajoute les modules manquants (par clé) ; ne modifie jamais un module existant.
     *
     * @return int nombre de modules ajoutés
     */
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
