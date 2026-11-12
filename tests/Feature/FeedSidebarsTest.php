<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class FeedSidebarsTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    public function test_the_feed_exposes_active_partners_with_their_logo_and_the_mission_link(): void
    {
        Partner::query()->create(['name' => 'Partenaire Visible', 'logo_path' => 'partners/logos/a.png', 'website_url' => 'https://exemple.tn', 'is_active' => true]);
        Partner::query()->create(['name' => 'Partenaire Sans Lien', 'logo_path' => 'partners/logos/c.png', 'website_url' => 'javascript:alert(1)', 'is_active' => true]);
        Partner::query()->create(['name' => 'Partenaire Masque', 'logo_path' => 'partners/logos/b.png', 'is_active' => false]);

        $payload = $this->actingAs(User::factory()->create())->get('/feed')->assertOk()->viewData('partners');

        $this->assertSame(['Partenaire Visible', 'Partenaire Sans Lien'], $payload->pluck('name')->all());
        $this->assertStringContainsString('partners/logos/a.png', $payload[0]['logo_url']);
        $this->assertSame('https://exemple.tn', $payload[0]['url']);
        $this->assertNull($payload[1]['url']);

        $this->get('/feed')->assertOk()->assertSee('/a-propos#mission', false);
    }

    public function test_the_composer_receives_the_avatar_of_the_member(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        \Illuminate\Support\Facades\Storage::disk('public')->put('profiles/me.jpg', 'x');

        $user = User::factory()->create();
        \App\Models\Profile::query()->updateOrCreate(['user_id' => $user->id], ['photo_path' => 'profiles/me.jpg']);

        $auth = $this->actingAs($user)->get('/feed')->assertOk()->viewData('authUser');
        $this->assertSame($user->id, $auth['id']);
        $this->assertStringContainsString('profiles/me.jpg', $auth['avatar_url']);

        $other = User::factory()->create();
        $this->assertNull($this->actingAs($other)->get('/feed')->assertOk()->viewData('authUser')['avatar_url']);
    }

    public function test_pending_memberships_are_only_listed_for_a_reviewer(): void
    {
        $applicant = User::factory()->create(['name' => 'Candidat Test']);
        \App\Models\Membership::query()->create([
            'user_id' => $applicant->id, 'scope' => 'university', 'status' => 'pending', 'membership_type' => 'member',
            'starts_at' => now()->toDateString(),
        ]);

        $this->assertNull($this->actingAs(User::factory()->create())->get('/feed')->assertOk()->viewData('pendingMemberships'));

        $reviewer = User::factory()->create();
        $this->grantPlatformAdmin($reviewer);

        $payload = $this->actingAs($reviewer)->get('/feed')->assertOk()->viewData('pendingMemberships');
        $this->assertSame(1, $payload['total']);
        $this->assertSame('Candidat Test', $payload['items'][0]['name']);
        $this->assertNotEmpty($payload['items'][0]['ago']);

        // Sa propre demande n'est pas à valider par soi-même.
        $this->assertNull($this->actingAs($applicant)->get('/feed')->viewData('pendingMemberships'));
    }

    public function test_the_feed_lists_upcoming_activities(): void
    {
        $this->assertIsArray($this->actingAs(User::factory()->create())->get('/feed')->assertOk()->viewData('upcoming'));
    }

    public function test_the_mission_anchor_exists_on_the_about_page(): void
    {
        $this->get('/fr/a-propos')->assertOk()->assertSee('id="mission"', false);
    }
}
