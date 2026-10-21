<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\EventRegistration;
use App\Models\FoundationEvent;
use App\Models\Membership;
use App\Models\ModerationReport;
use App\Models\OrgNode;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class AdminBackofficeTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    private const ALL_PERMISSIONS = [
        'entity.manage',
        'role.manage',
        'membership.manage',
        'approval.review',
        'post.moderate',
        'event.manage',
    ];

    /**
     * Les droits viennent du niveau d'accès du poste : global, périmètre (si un périmètre est donné) ou modérateur seul.
     *
     * @param array<int, string> $permissions
     */
    private function adminWith(array $permissions, ?string $scope = null): User
    {
        $user = User::factory()->create();
        $level = $scope ? 'entity_admin' : (in_array('entity.manage', $permissions, true) ? 'global_admin' : 'moderator');

        $this->grantAccess($user, $level, $scope);

        return $user;
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
    }

    public function test_user_without_admin_permission_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    }

    public function test_global_admin_can_open_every_section(): void
    {
        $admin = $this->adminWith(self::ALL_PERMISSIONS);
        $event = FoundationEvent::query()->create(['title' => 'Assemblée', 'status' => 'published', 'scope' => 'foundation']);

        $urls = [
            '/admin', '/admin/feuille-de-route', '/admin/journal',
            '/admin/utilisateurs', "/admin/utilisateurs/{$admin->id}",
            '/admin/adhesions',
            '/admin/publications', '/admin/moderation', '/admin/approbations',
            '/admin/evenements', '/admin/evenements/creer', "/admin/evenements/{$event->id}", "/admin/evenements/{$event->id}/modifier",
        ];

        foreach ($urls as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_moderator_only_sees_content_sections(): void
    {
        $moderator = $this->adminWith(['post.moderate']);

        $this->actingAs($moderator)->get('/admin')->assertOk();
        $this->actingAs($moderator)->get('/admin/publications')->assertOk();
        $this->actingAs($moderator)->get('/admin/journal')->assertForbidden();
        $this->actingAs($moderator)->get('/admin/evenements')->assertForbidden();
    }

    public function test_structure_screens_are_replaced_by_the_org_chart(): void
    {
        $admin = $this->adminWith(self::ALL_PERMISSIONS);

        foreach (['entites', 'departements', 'institutions', 'clubs'] as $section) {
            $this->actingAs($admin)->get("/admin/{$section}")->assertNotFound();
        }
    }

    public function test_scoped_admin_is_limited_to_their_scope(): void
    {
        $admin = $this->adminWith(['event.manage'], 'university');
        $own = FoundationEvent::query()->create(['title' => 'Événement fac', 'status' => 'published', 'scope' => 'university']);
        $other = FoundationEvent::query()->create(['title' => 'Événement lycée', 'status' => 'published', 'scope' => 'high-school']);

        $this->actingAs($admin)->get('/admin/evenements')->assertOk()->assertSee('Événement fac')->assertDontSee('Événement lycée');
        $this->actingAs($admin)->get("/admin/evenements/{$own->id}/modifier")->assertOk();
        $this->actingAs($admin)->get("/admin/evenements/{$other->id}/modifier")->assertForbidden();

        $this->actingAs($admin)->post('/admin/evenements', [
            'title' => 'Intrus', 'scope' => 'high-school', 'approval_required' => '0', 'status' => 'draft',
        ])->assertSessionHasErrors('scope');
    }

    public function test_club_nodes_are_counted_in_the_organisation_overview(): void
    {
        $admin = $this->adminWith(self::ALL_PERMISSIONS);
        $parent = OrgNode::query()->create(['relation_type' => 'hierarchy', 'kind' => 'unit', 'category' => 'education', 'title' => 'Branche', 'sort_order' => 0]);
        OrgNode::query()->create(['parent_id' => $parent->id, 'relation_type' => 'hierarchy', 'kind' => 'club', 'category' => 'education', 'title' => 'Club Robotique', 'scope' => 'university', 'sort_order' => 0]);

        $this->actingAs($admin)->get('/admin')->assertOk();
    }

    public function test_roles_screen_and_manual_role_routes_no_longer_exist(): void
    {
        $admin = $this->adminWith(self::ALL_PERMISSIONS);
        $member = User::factory()->create();

        $this->actingAs($admin)->get('/admin/roles')->assertNotFound();
        $this->actingAs($admin)->post("/admin/utilisateurs/{$member->id}/roles", ['role_id' => 1])->assertNotFound();
    }

    public function test_user_page_shows_positions_and_their_access_level(): void
    {
        $admin = $this->adminWith(self::ALL_PERMISSIONS);
        $member = User::factory()->create();
        $this->grantAccess($member, 'moderator');

        $this->actingAs($admin)->get("/admin/utilisateurs/{$member->id}")
            ->assertOk()
            ->assertSee('Poste de test moderator')
            ->assertSee('Modérateur');
        $this->actingAs($admin)->get('/admin/utilisateurs?access=moderator')->assertOk()->assertSee($member->email);
    }

    public function test_membership_status_transitions_are_enforced(): void
    {
        $admin = $this->adminWith(self::ALL_PERMISSIONS);
        $approved = Membership::query()->create(['user_id' => User::factory()->create()->id, 'scope' => 'foundation', 'status' => 'approved']);
        $pending = Membership::query()->create(['user_id' => User::factory()->create()->id, 'scope' => 'foundation', 'status' => 'pending']);

        $this->actingAs($admin)->patch("/admin/adhesions/{$approved->id}", ['status' => 'suspended'])->assertSessionHas('status');
        $this->assertSame('suspended', $approved->fresh()->status);

        $this->actingAs($admin)->patch("/admin/adhesions/{$pending->id}", ['status' => 'approved'])->assertSessionHas('error');
        $this->assertSame('pending', $pending->fresh()->status);
    }

    public function test_approval_can_be_decided_from_the_back_office(): void
    {
        $admin = $this->adminWith(self::ALL_PERMISSIONS);
        $requester = User::factory()->create();
        $membership = Membership::query()->create(['user_id' => $requester->id, 'scope' => 'foundation', 'status' => 'pending']);
        $approval = ApprovalRequest::query()->create([
            'request_type' => 'membership', 'requester_id' => $requester->id,
            'approvable_type' => Membership::class, 'approvable_id' => $membership->id,
            'status' => 'pending', 'submitted_at' => now(),
        ]);

        $this->actingAs($admin)->get('/admin/approbations')->assertOk()->assertSee($requester->name);

        $this->actingAs($admin)->post("/admin/approbations/{$approval->id}", ['decision' => 'approved'])->assertSessionHas('status');

        $this->assertSame('approved', $approval->fresh()->status);
        $this->assertSame('approved', $membership->fresh()->status);
    }

    public function test_posts_can_be_moderated(): void
    {
        $admin = $this->adminWith(self::ALL_PERMISSIONS);
        $author = User::factory()->create();
        $post = Post::query()->create([
            'user_id' => $author->id, 'type' => 'text', 'visibility' => 'public', 'content' => 'Contenu douteux',
            'comments_enabled' => true, 'published_at' => now(),
        ]);
        $report = ModerationReport::query()->create([
            'reported_by' => User::factory()->create()->id, 'reportable_type' => Post::class,
            'reportable_id' => $post->id, 'reason' => 'Spam', 'status' => 'pending',
        ]);

        $this->actingAs($admin)->get('/admin/moderation')->assertOk()->assertSee('Spam');

        $this->actingAs($admin)->patch("/admin/publications/{$post->id}/commentaires")->assertSessionHas('status');
        $this->assertFalse($post->fresh()->comments_enabled);

        $this->actingAs($admin)->patch("/admin/moderation/{$report->id}", ['status' => 'reviewed'])->assertSessionHas('status');
        $this->assertSame('reviewed', $report->fresh()->status);
        $this->assertSame($admin->id, $report->fresh()->reviewed_by);

        $this->actingAs($admin)->delete("/admin/publications/{$post->id}")->assertSessionHas('status');
        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }

    public function test_events_can_be_created_and_registrations_updated(): void
    {
        $admin = $this->adminWith(self::ALL_PERMISSIONS);

        $this->actingAs($admin)->post('/admin/evenements', [
            'title' => 'Journée portes ouvertes', 'scope' => 'foundation', 'starts_at' => '2030-05-10T09:00',
            'capacity' => 50, 'approval_required' => '0', 'status' => 'published',
        ])->assertSessionHasNoErrors();

        $event = FoundationEvent::query()->where('title', 'Journée portes ouvertes')->firstOrFail();
        $this->assertSame($admin->id, $event->organizer_id);

        $registration = EventRegistration::query()->create([
            'event_id' => $event->id, 'user_id' => User::factory()->create()->id, 'status' => 'pending',
        ]);

        $this->actingAs($admin)->get("/admin/evenements/{$event->id}")->assertOk();

        $this->actingAs($admin)->patch("/admin/evenements/{$event->id}/inscriptions/{$registration->id}", ['status' => 'confirmed'])
            ->assertSessionHas('status');
        $this->assertSame('confirmed', $registration->fresh()->status);

        // Evenement avec inscriptions : suppression refusee
        $this->actingAs($admin)->delete("/admin/evenements/{$event->id}")->assertSessionHas('error');
        $this->assertDatabaseHas('events', ['id' => $event->id]);
    }

    public function test_event_requires_end_after_start(): void
    {
        $admin = $this->adminWith(self::ALL_PERMISSIONS);

        $this->actingAs($admin)->post('/admin/evenements', [
            'title' => 'Incohérent', 'starts_at' => '2030-05-10T10:00', 'ends_at' => '2030-05-10T08:00',
            'approval_required' => '0', 'status' => 'draft',
        ])->assertSessionHasErrors('ends_at');
    }
}
