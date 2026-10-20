<?php

namespace Tests\Concerns;

use App\Models\Entity;
use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Models\OrgPerson;
use App\Models\User;
use App\Support\OrgChart\OrgChartInstaller;

/**
 * Les droits viennent des postes de l'organigramme : ces aides placent un utilisateur sur un poste de test.
 */
trait GrantsAccess
{
    /**
     * Place l'utilisateur sur un poste isole qui porte le niveau d'acces voulu.
     * Hors administrateur global, le poste est rattache a une entite (celle donnee, sinon une entite de test).
     */
    protected function grantAccess(User $user, string $level = 'global_admin', ?Entity $entity = null): OrgNode
    {
        if ($level !== 'global_admin' && $entity === null) {
            $entity = Entity::query()->firstOrCreate(['slug' => 'test-entity'], ['name' => 'Entité de test', 'category' => 'test']);
        }

        $node = OrgNode::query()->create([
            'relation_type' => 'hierarchy',
            'kind' => 'position',
            'capacity' => 'multiple',
            'access_level' => $level,
            'category' => 'direction',
            'title' => 'Poste de test '.$level,
            'entity_id' => $level === 'global_admin' ? null : $entity?->id,
            'sort_order' => 0,
        ]);

        $this->placeOn($node, $user);

        return $node;
    }

    /**
     * Installe l'organigramme si besoin et place l'utilisateur sur le poste des administrateurs de la plateforme.
     */
    protected function grantPlatformAdmin(User $user): void
    {
        $installer = app(OrgChartInstaller::class);

        if (! $installer->isInstalled()) {
            $installer->install();
        }

        $this->placeOn(OrgNode::query()->where('system_key', 'national-administrators')->firstOrFail(), $user);
    }

    private function placeOn(OrgNode $node, User $user): void
    {
        $person = OrgPerson::query()->firstOrCreate(['user_id' => $user->id], ['full_name' => $user->name, 'contact_visible' => true]);

        OrgAssignment::query()->firstOrCreate(['node_id' => $node->id, 'person_id' => $person->id]);
    }
}
