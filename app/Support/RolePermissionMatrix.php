<?php

namespace App\Support;

class RolePermissionMatrix
{
    /**
     * @return array<string, string>
     */
    public static function roles(): array
    {
        return [
            'Foundation President' => 'global',
            'Former President' => 'global',
            'National Bureau Administrator' => 'global',
            'Entity Administrator' => 'entity',
            'Department Manager' => 'department',
            'Institution Manager' => 'institution',
            'Governorate Manager' => 'governorate',
            'Club President' => 'club',
            'Club Officer' => 'club',
            'Project Manager' => 'project',
            'Member' => 'club',
            'Public Visitor' => 'public',
        ];
    }

    /**
     * @return array<int, array{name: string, slug: string, description: string}>
     */
    public static function permissions(): array
    {
        return [
            ['name' => 'View Feed', 'slug' => 'feed.view', 'description' => 'Consulter le fil des publications.'],
            ['name' => 'Create Post', 'slug' => 'post.create', 'description' => 'Publier un contenu dans le fil.'],
            ['name' => 'Moderate Post', 'slug' => 'post.moderate', 'description' => 'Traiter les signalements et moderer les contenus.'],
            ['name' => 'Manage Memberships', 'slug' => 'membership.manage', 'description' => 'Gerer les demandes et statuts d adhesion.'],
            ['name' => 'Approve Requests', 'slug' => 'approval.review', 'description' => 'Valider ou rejeter les demandes d approbation.'],
            ['name' => 'Manage Roles', 'slug' => 'role.manage', 'description' => 'Attribuer et gerer les roles.'],
            ['name' => 'Manage Entities', 'slug' => 'entity.manage', 'description' => 'Acceder au pilotage admin d entite.'],
            ['name' => 'Manage Events', 'slug' => 'event.manage', 'description' => 'Creer et administrer les evenements.'],
            ['name' => 'Register Events', 'slug' => 'event.register', 'description' => 'S inscrire a un evenement.'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function roleToPermissions(): array
    {
        return [
            'foundation-president' => self::allPermissionSlugs(),
            'former-president' => ['feed.view'],
            'national-bureau-administrator' => self::allPermissionSlugs(),
            'entity-administrator' => [
                'feed.view',
                'post.create',
                'post.moderate',
                'membership.manage',
                'approval.review',
                'event.manage',
                'event.register',
                'entity.manage',
            ],
            'department-manager' => [
                'feed.view',
                'post.create',
                'post.moderate',
                'membership.manage',
                'approval.review',
                'event.manage',
                'event.register',
            ],
            'institution-manager' => [
                'feed.view',
                'post.create',
                'post.moderate',
                'membership.manage',
                'approval.review',
                'event.manage',
                'event.register',
            ],
            'governorate-manager' => [
                'feed.view',
                'post.create',
                'post.moderate',
                'membership.manage',
                'approval.review',
                'event.manage',
                'event.register',
            ],
            'club-president' => [
                'feed.view',
                'post.create',
                'membership.manage',
                'approval.review',
                'event.manage',
                'event.register',
            ],
            'club-officer' => [
                'feed.view',
                'post.create',
                'event.register',
            ],
            'project-manager' => [
                'feed.view',
                'post.create',
                'event.register',
            ],
            'member' => [
                'feed.view',
                'post.create',
                'event.register',
            ],
            'public-visitor' => [
                'feed.view',
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function allPermissionSlugs(): array
    {
        return array_values(array_map(
            static fn (array $permission): string => $permission['slug'],
            self::permissions()
        ));
    }
}
