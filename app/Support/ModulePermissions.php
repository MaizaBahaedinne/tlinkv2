<?php

namespace App\Support;

use App\Models\AppModule;
use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Models\User;

/**
 * Habilitations CRUD par module : la matrice se règle sur chaque poste (org_nodes.module_permissions),
 * et une personne cumule les matrices des postes qu'elle occupe, chacune limitée au périmètre de son poste.
 */
final class ModulePermissions
{
    /**
     * Permission d'administration que porte chaque module : la matrice CRUD est la seule source des droits d'un poste,
     * les permissions (org_nodes.permissions / access_level) en sont déduites et ne se saisissent plus ailleurs.
     * Les modules absents de cette table (fil, notifications, profils) sont côté membre et ne donnent aucun accès à l'administration.
     */
    public const MODULE_PERMISSIONS = [
        'organization.chart' => 'entity.manage',
        'organization.positions' => 'entity.manage',
        'organization.applications' => 'entity.manage',
        'organization.clubs' => 'entity.manage',
        'organization.mandates' => 'entity.manage',
        'organization.scopes' => 'entity.manage',
        'members.info_session' => 'entity.manage',
        'website.content' => 'entity.manage',
        'website.partners' => 'entity.manage',
        'website.projects' => 'entity.manage',
        'website.news' => 'entity.manage',
        'website.messages' => 'entity.manage',
        'website.newsletter' => 'entity.manage',
        'website.launch' => 'entity.manage',
        'governance.audit' => 'entity.manage',
        'governance.roadmap' => 'entity.manage',
        'governance.modules' => 'entity.manage',
        'members.users' => 'membership.manage',
        'members.memberships' => 'membership.manage',
        'members.entity_members' => 'membership.manage',
        'governance.approvals' => 'approval.review',
        'governance.moderation' => 'post.moderate',
        'feed.public_posts' => 'post.publish_public',
        'events.events' => 'event.manage',
        'events.registrations' => 'event.manage',
    ];

    /**
     * Permissions d'administration déduites d'une matrice (dans l'ordre de AccessLevels::PERMISSIONS).
     *
     * @param array<string, array<int, string>> $matrix
     * @return array<int, string>
     */
    public static function derivePermissions(array $matrix): array
    {
        $found = [];

        foreach (array_keys($matrix) as $key) {
            if (isset(self::MODULE_PERMISSIONS[$key])) {
                $found[self::MODULE_PERMISSIONS[$key]] = true;
            }
        }

        return array_values(array_intersect(array_keys(AccessLevels::PERMISSIONS), array_keys($found)));
    }

    /**
     * Matrice complète (toutes les actions supportées) correspondant à des permissions : sert à reprendre
     * les droits d'un poste créé avec un niveau d'accès, et de préréglage.
     *
     * @param array<int, string> $permissions
     * @return array<string, array<int, string>>
     */
    public static function fromPermissions(array $permissions): array
    {
        $keys = array_keys(array_filter(self::MODULE_PERMISSIONS, fn (string $permission): bool => in_array($permission, $permissions, true)));

        // L'annuaire des comptes reste visible pour qui pilote l'organisation comme pour qui gère les adhésions.
        if (array_intersect(['entity.manage', 'membership.manage'], $permissions) !== []) {
            $keys[] = 'members.users';
        }

        $input = [];

        foreach (AppModule::query()->where('is_active', true)->whereIn('key', array_unique($keys))->get(['key', 'actions']) as $module) {
            $input[$module->key] = $module->supportedActions();
        }

        return self::normalize($input);
    }

    /**
     * Enregistre les droits d'un poste : la matrice, et les permissions qui en découlent.
     *
     * @param array<string, mixed> $matrix
     */
    public static function apply(OrgNode $node, array $matrix, bool $everywhere): void
    {
        $matrix = self::normalize($matrix);
        [$level, $stored] = AccessLevels::fromChecked(self::derivePermissions($matrix), $everywhere);

        $node->update([
            'module_permissions' => $matrix === [] ? null : $matrix,
            'access_level' => $level,
            'permissions' => $stored,
        ]);
    }

    /**
     * Nettoie une matrice saisie : modules actifs connus, actions supportées, « consulter » toujours incluse
     * dès qu'une autre action est accordée (on ne modifie pas ce qu'on ne peut pas voir).
     *
     * @param array<string, mixed> $input clé de module => liste d'actions
     * @return array<string, array<int, string>>
     */
    public static function normalize(array $input): array
    {
        $result = [];

        foreach (AppModule::query()->where('is_active', true)->get(['key', 'actions']) as $module) {
            $supported = $module->supportedActions();
            $asked = array_values(array_intersect($supported, array_map('strval', (array) ($input[$module->key] ?? []))));

            if ($asked === []) {
                continue;
            }

            if (in_array('read', $supported, true) && ! in_array('read', $asked, true)) {
                $asked[] = 'read';
            }

            $result[$module->key] = array_values(array_intersect(array_keys(AppModule::ACTIONS), $asked));
        }

        return $result;
    }

    /**
     * Droits d'un utilisateur : clé de module => action => périmètres (null = toute la plateforme).
     * Un poste sans périmètre (explicite ou hérité) n'accorde rien, sauf s'il agit sur toute la plateforme.
     *
     * @return array<string, array<string, array<int, string|null>>>
     */
    public static function grants(User $user): array
    {
        $today = now()->toDateString();

        $assignments = OrgAssignment::query()
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', $today))
            ->whereHas('person', fn ($query) => $query->where('user_id', $user->id))
            ->whereHas('node', fn ($query) => $query->where('kind', 'position')->whereNotNull('module_permissions'))
            ->with('node')
            ->get();

        $active = AppModule::query()->where('is_active', true)->pluck('key')->flip();
        $grants = [];

        foreach ($assignments as $assignment) {
            /** @var OrgNode $node */
            $node = $assignment->node;
            $matrix = (array) $node->module_permissions;

            if ($matrix === []) {
                continue;
            }

            $everywhere = AccessLevels::reachesEverywhere((string) $node->access_level, $node->permissions);
            $scope = $everywhere ? null : OrgAccess::scopeOf($node);

            if (! $everywhere && $scope === null) {
                continue;
            }

            foreach ($matrix as $key => $actions) {
                if (! isset($active[$key])) {
                    continue;
                }

                foreach ((array) $actions as $action) {
                    $grants[$key][$action][] = $scope;
                }
            }
        }

        return $grants;
    }

    /**
     * L'utilisateur peut-il faire cette action sur ce module (dans le périmètre donné, ou n'importe lequel si null) ?
     */
    public static function can(User $user, string $moduleKey, string $action, ?string $scope = null): bool
    {
        $scopes = self::grants($user)[$moduleKey][$action] ?? [];

        if ($scopes === []) {
            return false;
        }

        if ($scope === null || in_array(null, $scopes, true)) {
            return true;
        }

        foreach ($scopes as $granted) {
            if (OrgScopes::covers((string) $granted, $scope)) {
                return true;
            }
        }

        return false;
    }
}
