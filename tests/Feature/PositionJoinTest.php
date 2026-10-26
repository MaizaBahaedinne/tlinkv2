<?php

namespace Tests\Feature;

use App\Models\Governorate;
use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Models\OrgPerson;
use App\Models\PositionApplication;
use App\Models\User;
use Database\Seeders\GovernorateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class PositionJoinTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    private function position(string $capacity = 'single'): OrgNode
    {
        return OrgNode::query()->create([
            'parent_id' => OrgNode::query()->where('system_key', 'university-office')->value('id'),
            'relation_type' => 'hierarchy',
            'kind' => 'position',
            'capacity' => $capacity,
            'access_level' => 'none',
            'category' => 'national_office',
            'title' => 'Responsable communication',
            'sort_order' => 0,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $override = []): array
    {
        $this->seed(GovernorateSeeder::class);

        return array_merge([
            'first_name' => 'Amel',
            'last_name' => 'Ben Salah',
            'email' => 'amel@example.com',
            'password' => 'Motdepasse-Solide-2026',
            'password_confirmation' => 'Motdepasse-Solide-2026',
            'phone' => '+216 20 123 456',
            'gender' => 'female',
            'birth_date' => '2000-05-12',
            'governorate_id' => Governorate::query()->first()->id,
            'message' => 'Je veux contribuer à la communication de la Fondation.',
            'avatar' => UploadedFile::fake()->image('avatar.jpg', 300, 300),
        ], $override);
    }

    public function test_every_position_gets_a_join_link_and_other_blocks_do_not(): void
    {
        $position = $this->position();

        $this->assertSame(40, strlen($position->join_token));
        $this->assertSame(route('position.join', $position->join_token), $position->joinUrl());
        $this->assertNull(OrgNode::query()->where('kind', '!=', 'position')->first()?->joinUrl());
    }

    public function test_unknown_token_is_a_404(): void
    {
        $this->get('/rejoindre/'.str_repeat('a', 40))->assertNotFound();
    }

    public function test_guest_sees_the_complete_form(): void
    {
        $position = $this->position();

        $this->get('/rejoindre/'.$position->join_token)
            ->assertOk()
            ->assertSee('Responsable communication')
            ->assertSee('name="avatar"', false)
            ->assertSee('name="phone"', false)
            ->assertSee('name="birth_date"', false);
    }

    public function test_guest_registers_with_avatar_and_creates_a_pending_application_without_any_assignment(): void
    {
        Storage::fake('public');
        $position = $this->position();

        $this->post('/rejoindre/'.$position->join_token, $this->payload())->assertRedirect(route('home'));

        $user = User::query()->where('email', 'amel@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('+216 20 123 456', $user->profile->phone);
        $this->assertNotNull($user->profile->photo_path);
        Storage::disk('public')->assertExists($user->profile->photo_path);

        $this->assertDatabaseHas('position_applications', ['node_id' => $position->id, 'user_id' => $user->id, 'status' => 'pending']);
        $this->assertSame(0, OrgAssignment::query()->count());
    }

    public function test_avatar_is_required(): void
    {
        Storage::fake('public');
        $position = $this->position();
        $payload = $this->payload();
        unset($payload['avatar']);

        $this->post('/rejoindre/'.$position->join_token, $payload)->assertSessionHasErrors('avatar');
        $this->assertDatabaseCount('position_applications', 0);
        $this->assertSame(0, User::query()->count());
    }

    public function test_taken_single_position_is_closed(): void
    {
        Storage::fake('public');
        $position = $this->position();
        $holder = User::factory()->create();
        $person = OrgPerson::query()->create(['user_id' => $holder->id, 'full_name' => $holder->name, 'contact_visible' => true]);
        OrgAssignment::query()->create(['node_id' => $position->id, 'person_id' => $person->id]);

        $this->get('/rejoindre/'.$position->join_token)->assertOk()->assertSee('déjà pourvu');
        $this->post('/rejoindre/'.$position->join_token, $this->payload())->assertSessionHasErrors('message');
        $this->assertDatabaseCount('position_applications', 0);
    }

    public function test_logged_in_user_applies_with_a_message_only_and_cannot_apply_twice(): void
    {
        $position = $this->position();
        $user = User::factory()->create();

        $this->actingAs($user)->post('/rejoindre/'.$position->join_token, ['message' => 'Motivé.'])->assertRedirect(route('home'));
        $this->assertDatabaseHas('position_applications', ['node_id' => $position->id, 'user_id' => $user->id, 'status' => 'pending']);

        $this->actingAs($user)->post('/rejoindre/'.$position->join_token, ['message' => 'Encore.'])->assertSessionHasErrors('message');
        $this->assertSame(1, PositionApplication::query()->count());
    }

    public function test_admin_approval_assigns_the_person_and_rejection_does_not(): void
    {
        $position = $this->position('multiple');
        $admin = User::factory()->create();
        $this->grantPlatformAdmin($admin);

        $accepted = PositionApplication::query()->create(['node_id' => $position->id, 'user_id' => User::factory()->create()->id, 'message' => 'A']);
        $refused = PositionApplication::query()->create(['node_id' => $position->id, 'user_id' => User::factory()->create()->id, 'message' => 'B']);

        $this->actingAs($admin)->get('/admin/structure/candidatures')->assertOk()->assertSee('Responsable communication');

        $this->actingAs($admin)->post('/admin/structure/candidatures/'.$accepted->id, ['decision' => 'approved'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post('/admin/structure/candidatures/'.$refused->id, ['decision' => 'rejected', 'note' => 'Poste complet'])->assertSessionHasNoErrors();

        $this->assertSame('approved', $accepted->fresh()->status);
        $this->assertSame('rejected', $refused->fresh()->status);
        $this->assertSame(1, OrgAssignment::query()->where('node_id', $position->id)->count());
        $this->assertDatabaseHas('org_people', ['user_id' => $accepted->user_id]);
        $this->assertDatabaseMissing('org_people', ['user_id' => $refused->user_id]);
    }

    public function test_approval_is_refused_when_a_single_position_is_already_taken(): void
    {
        $position = $this->position();
        $admin = User::factory()->create();
        $this->grantPlatformAdmin($admin);

        $first = PositionApplication::query()->create(['node_id' => $position->id, 'user_id' => User::factory()->create()->id]);
        $second = PositionApplication::query()->create(['node_id' => $position->id, 'user_id' => User::factory()->create()->id]);

        $this->actingAs($admin)->post('/admin/structure/candidatures/'.$first->id, ['decision' => 'approved']);
        $this->actingAs($admin)->post('/admin/structure/candidatures/'.$second->id, ['decision' => 'approved'])->assertSessionHasErrors();

        $this->assertSame('pending', $second->fresh()->status);
        $this->assertSame(1, OrgAssignment::query()->where('node_id', $position->id)->count());
    }

    public function test_non_admin_cannot_review_applications(): void
    {
        $position = $this->position();
        $application = PositionApplication::query()->create(['node_id' => $position->id, 'user_id' => User::factory()->create()->id]);

        $this->actingAs(User::factory()->create())->post('/admin/structure/candidatures/'.$application->id, ['decision' => 'approved'])->assertForbidden();
        $this->assertSame('pending', $application->fresh()->status);
    }

    public function test_only_editors_get_the_link_and_regenerating_it_invalidates_the_old_one(): void
    {
        $position = $this->position();
        $old = $position->join_token;
        $admin = User::factory()->create();
        $this->grantPlatformAdmin($admin);
        $member = User::factory()->create();

        $find = fn (array $json): ?array => collect($json['nodes'])->firstWhere('id', $position->id);

        $this->assertNull($find($this->actingAs($member)->getJson('/organigramme/donnees')->json())['join_url'] ?? null);
        $this->assertNotNull($find($this->actingAs($admin)->getJson('/organigramme/donnees')->json())['join_url']);

        $this->actingAs($member)->postJson('/organigramme/blocs/'.$position->id.'/lien-inscription')->assertForbidden();
        $this->actingAs($admin)->postJson('/organigramme/blocs/'.$position->id.'/lien-inscription')->assertOk();

        $this->assertNotSame($old, $position->fresh()->join_token);
        auth()->logout();
        $this->get('/rejoindre/'.$old)->assertNotFound();
    }
}
