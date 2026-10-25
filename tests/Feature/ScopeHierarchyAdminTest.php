<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AccessScope;
use App\Support\OrgScopes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class ScopeHierarchyAdminTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    public function test_only_entity_admin_can_access_scope_settings_screen(): void
    {
        $this->get('/admin/structure/perimetres')->assertRedirect('/connexion');

        $member = User::factory()->create();
        $this->actingAs($member)->get('/admin/structure/perimetres')->assertForbidden();

        $admin = User::factory()->create();
        $this->grantAccess($admin, 'global_admin');
        $this->actingAs($admin)->get('/admin/structure/perimetres')->assertOk();
    }

    public function test_admin_can_update_scope_hierarchy_and_cycles_are_rejected(): void
    {
        $admin = User::factory()->create();
        $this->grantAccess($admin, 'global_admin');

        $labels = [];
        foreach (OrgScopes::all() as $slug => $label) {
            $labels[$slug] = $label;
        }

        $this->actingAs($admin)->put('/admin/structure/perimetres', [
            'label' => $labels,
            'parent' => [
                'foundation' => '',
                'university' => 'foundation',
                'high-school' => 'foundation',
                'alumni' => 'university',
            ],
        ])->assertSessionHasNoErrors()->assertSessionHas('status');

        OrgScopes::flushCache();
        $hierarchy = OrgScopes::hierarchy();

        $this->assertSame('foundation', $hierarchy['university']);
        $this->assertSame('university', $hierarchy['alumni']);

        $this->actingAs($admin)->put('/admin/structure/perimetres', [
            'label' => $labels,
            'parent' => [
                'foundation' => 'alumni',
                'university' => 'foundation',
                'high-school' => 'foundation',
                'alumni' => 'university',
            ],
        ])->assertSessionHasErrors('parent');
    }

    public function test_scope_inheritance_covers_children_permissions(): void
    {
        $manager = User::factory()->create();
        $this->grantAccess($manager, 'manager', 'foundation');

        OrgScopes::flushCache();

        $this->assertTrue(AccessScope::can($manager, 'membership.manage', 'foundation'));
        $this->assertTrue(AccessScope::can($manager, 'membership.manage', 'university'));
        $this->assertTrue(AccessScope::can($manager, 'approval.review', 'high-school'));
        $this->assertTrue(AccessScope::can($manager, 'event.manage', 'alumni'));
    }
}
