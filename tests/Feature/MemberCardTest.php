<?php

namespace Tests\Feature;

use App\Models\Challenge;
use App\Models\ClubActivity;
use App\Models\Mandate;
use App\Models\MemberCard;
use App\Models\Membership;
use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Models\OrgPerson;
use App\Models\Premises;
use App\Models\User;
use App\Support\Cards\CardCode;
use App\Support\Cards\MemberCards;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class MemberCardTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    private function startYear(): int
    {
        return now()->month >= 9 ? now()->year : now()->year - 1;
    }

    private function mandate(int $offset = 0, string $status = 'current'): Mandate
    {
        $start = $this->startYear() + $offset;

        return Mandate::query()->create(['label' => $start.'-'.($start + 1), 'start_year' => $start, 'end_year' => $start + 1, 'bureau_name' => 'Bureau', 'status' => $status]);
    }

    private function club(string $title = 'Club Robotique', string $scope = 'university'): OrgNode
    {
        return OrgNode::query()->create([
            'relation_type' => 'hierarchy', 'kind' => 'club', 'category' => 'university_club',
            'title' => $title, 'scope' => $scope, 'status' => 'active', 'sort_order' => 0,
        ]);
    }

    private function holdInClub(User $user, OrgNode $club, string $level = 'club_president'): void
    {
        $position = OrgNode::query()->create([
            'parent_id' => $club->id, 'relation_type' => 'hierarchy', 'kind' => 'position', 'capacity' => 'multiple',
            'access_level' => $level, 'category' => 'direction', 'title' => 'Poste '.$level, 'sort_order' => 0,
        ]);
        $person = OrgPerson::query()->firstOrCreate(['user_id' => $user->id], ['full_name' => $user->name, 'contact_visible' => true]);
        OrgAssignment::query()->create(['node_id' => $position->id, 'person_id' => $person->id]);
    }

    private function member(string $name, ?OrgNode $club = null, string $scope = 'university', string $status = 'approved', ?string $endsAt = null): Membership
    {
        return Membership::query()->create([
            'user_id' => User::factory()->create(['name' => $name])->id, 'scope' => $scope, 'node_id' => $club?->id,
            'status' => $status, 'membership_type' => 'member', 'starts_at' => now()->setDate($this->startYear(), 9, 1)->toDateString(), 'ends_at' => $endsAt,
        ]);
    }

    private function activity(OrgNode $club, string $title = 'Atelier Robotique'): ClubActivity
    {
        $challenge = Challenge::query()->create([
            'title' => 'Défi Campus', 'theme' => 'T', 'scope' => 'university', 'status' => 'published',
            'starts_at' => now()->subDays(30), 'ends_at' => now()->addDays(30), 'evaluation_at' => now()->addDays(32), 'results_at' => now()->addDays(34),
        ]);

        return ClubActivity::query()->create([
            'challenge_id' => $challenge->id, 'club_id' => $club->id, 'title' => $title, 'type' => 'event', 'audience' => 'public',
            'in_theme' => true, 'starts_at' => now()->addHours(2), 'ends_at' => now()->addHours(5), 'status' => 'published',
        ]);
    }

    private function scan(User $operator, MemberCard $card, string $target, string $direction = 'auto')
    {
        return $this->actingAs($operator->fresh())->postJson('/admin/pointage', [
            'target' => $target, 'direction' => $direction, 'code' => CardCode::payload($card),
        ]);
    }

    public function test_a_card_is_issued_per_approved_membership_for_the_current_mandate(): void
    {
        $mandate = $this->mandate();
        $approved = $this->member('Approuvé', $this->club());
        $pending = $this->member('En attente', null, 'university', 'pending');

        $card = MemberCard::query()->where('membership_id', $approved->id)->first();

        $this->assertNotNull($card);
        $this->assertSame($mandate->id, $card->mandate_id);
        $this->assertSame('active', $card->status);
        $this->assertMatchesRegularExpression('/^TL-[A-Z2-9]{4}-[A-Z2-9]{4}$/', $card->number);
        $this->assertTrue($card->isValid());
        $this->assertNull(MemberCard::query()->where('membership_id', $pending->id)->first());
        $this->assertSame(0, MemberCards::sync()['issued']);
    }

    public function test_no_card_is_issued_without_a_current_mandate(): void
    {
        $this->mandate(0, 'upcoming');
        $this->member('Membre');

        $this->assertSame(0, MemberCard::query()->count());
    }

    public function test_cards_are_not_renewed_through_carried_memberships_but_follow_the_period_of_the_membership(): void
    {
        $old = $this->mandate();
        $stays = $this->member('Reste');
        $leaves = $this->member('Part', null, 'university', 'approved', now()->setDate($this->startYear() + 1, 6, 30)->toDateString());

        $this->assertSame(2, MemberCard::query()->where('mandate_id', $old->id)->where('status', 'active')->count());

        $old->update(['status' => 'closed']);
        $new = $this->mandate(1);

        $this->assertSame('expired', MemberCard::query()->where('membership_id', $stays->id)->where('mandate_id', $old->id)->value('status'));
        // Adhésion sans fin d'un mandat précédent : pas de carte reconduite sans adhésion validée dans le nouveau mandat.
        $this->assertNull(MemberCard::query()->where('membership_id', $stays->id)->where('mandate_id', $new->id)->first());
        $this->assertSame(0, MemberCard::query()->where('user_id', $stays->user_id)->where('status', 'active')->count());
        $this->assertSame('expired', MemberCard::query()->where('membership_id', $leaves->id)->where('mandate_id', $old->id)->value('status'));
        $this->assertNull(MemberCard::query()->where('membership_id', $leaves->id)->where('mandate_id', $new->id)->first());
    }

    public function test_a_card_expires_when_the_membership_is_no_longer_approved(): void
    {
        $this->mandate();
        $membership = $this->member('Suspendu');

        $membership->update(['status' => 'suspended']);

        $this->assertSame('expired', MemberCard::query()->where('membership_id', $membership->id)->value('status'));
    }

    public function test_member_page_and_rotating_code_belong_to_the_owner(): void
    {
        $this->mandate();
        $membership = $this->member('Sana Ben Ali', $this->club('Club AAC'));
        $card = MemberCard::query()->where('membership_id', $membership->id)->firstOrFail();

        $this->actingAs($membership->user)->get('/ma-carte')->assertOk()->assertSee('Sana Ben Ali')->assertSee($card->number)->assertSee('Club AAC')
            ->assertSee('data-card-role', false)->assertSee('Membre')->assertSee('logoFondation-white.webp', false);

        $membership->update(['membership_type' => 'Trésorier']);
        $this->actingAs($membership->user)->get('/ma-carte')->assertSee('Trésorier');

        $response = $this->actingAs($membership->user)->getJson("/ma-carte/{$card->id}/code")->assertOk()->assertJsonPath('valid', true);
        [$resolved, $error] = CardCode::resolve($response->json('code'));
        $this->assertSame($card->id, $resolved->id);
        $this->assertNull($error);

        $this->actingAs(User::factory()->create())->getJson("/ma-carte/{$card->id}/code")->assertNotFound();
    }

    public function test_codes_are_signed_and_expire(): void
    {
        $this->mandate();
        $card = MemberCard::query()->where('membership_id', $this->member('Membre')->id)->firstOrFail();
        $now = time();

        $this->assertSame($card->id, CardCode::resolve(CardCode::payload($card, $now), $now + 20)[0]->id);
        $this->assertNull(CardCode::resolve(CardCode::payload($card, $now), $now + 600)[0]);
        $this->assertNull(CardCode::resolve(substr(CardCode::payload($card, $now), 0, -2).'xx', $now)[0]);
        $this->assertNull(CardCode::resolve('https://example.com', $now)[0]);
    }

    public function test_club_leader_scans_in_then_out_and_duplicates_are_ignored(): void
    {
        $this->mandate();
        $club = $this->club();
        $leader = User::factory()->create();
        $this->holdInClub($leader, $club);
        $activity = $this->activity($club);
        $card = MemberCard::query()->where('membership_id', $this->member('Participant', $club)->id)->firstOrFail();

        $this->scan($leader, $card, 'activity:'.$activity->id)->assertOk()->assertJsonPath('direction', 'in')->assertJsonPath('present', 1)->assertJsonPath('note', 'Non inscrit à l’activité');

        // Double lecture immédiate : pas de second pointage.
        $this->scan($leader, $card, 'activity:'.$activity->id)->assertOk()->assertJsonPath('duplicate', true);
        $this->assertSame(1, $card->scans()->count());

        $this->travel(1)->minutes();
        $this->scan($leader, $card, 'activity:'.$activity->id)->assertOk()->assertJsonPath('direction', 'out')->assertJsonPath('present', 0);
        $this->assertSame(2, $card->scans()->count());
    }

    public function test_scanning_is_refused_outside_the_operators_perimeter_and_for_unusable_cards(): void
    {
        $this->mandate();
        $mine = $this->club();
        $other = $this->club('Club Théâtre');
        $leader = User::factory()->create();
        $this->holdInClub($leader, $mine);
        $foreignActivity = $this->activity($other, 'Pièce Théâtre');
        $ownActivity = $this->activity($mine);
        $card = MemberCard::query()->where('membership_id', $this->member('Participant', $mine)->id)->firstOrFail();

        $this->scan($leader, $card, 'activity:'.$foreignActivity->id)->assertStatus(422);
        $this->assertSame(0, $card->scans()->count());

        $card->update(['status' => 'suspended']);
        $this->scan($leader, $card, 'activity:'.$ownActivity->id)->assertStatus(422)->assertJsonPath('member.name', 'Participant');

        $card->update(['status' => 'expired']);
        $this->scan($leader, $card, 'activity:'.$ownActivity->id)->assertStatus(422);

        $this->actingAs($leader->fresh())->postJson('/admin/pointage', ['target' => 'activity:'.$ownActivity->id, 'direction' => 'in', 'code' => 'n-importe-quoi'])->assertStatus(422);
        $this->assertSame(0, $card->scans()->count());
    }

    public function test_manual_number_entry_and_premises_scanning(): void
    {
        $this->mandate();
        $admin = User::factory()->create();
        $this->grantAccess($admin, 'entity_admin', 'university');
        $card = MemberCard::query()->where('membership_id', $this->member('Participant')->id)->firstOrFail();

        $this->actingAs($admin->fresh())->post('/admin/locaux', ['name' => 'Siège', 'scope' => 'university'])->assertRedirect();
        $premises = Premises::query()->firstOrFail();
        Premises::query()->create(['name' => 'Local Alumni', 'scope' => 'alumni', 'is_active' => true]);

        $this->actingAs($admin->fresh())->get('/admin/pointage')->assertOk()->assertSee('Siège')->assertDontSee('Local Alumni');

        $this->actingAs($admin->fresh())->postJson('/admin/pointage', ['target' => 'premises:'.$premises->id, 'direction' => 'in', 'number' => strtolower($card->number)])
            ->assertOk()->assertJsonPath('direction', 'in');
        $this->assertSame('manual', $card->scans()->first()->method);

        $this->actingAs($admin->fresh())->postJson('/admin/pointage', ['target' => 'premises:'.Premises::query()->where('scope', 'alumni')->value('id'), 'direction' => 'in', 'number' => $card->number])->assertStatus(422);
    }

    public function test_history_and_card_list_are_limited_to_the_perimeter(): void
    {
        $this->mandate();
        $admin = User::factory()->create();
        $this->grantAccess($admin, 'entity_admin', 'university');
        $global = User::factory()->create();
        $this->grantAccess($global);

        $mine = MemberCard::query()->where('membership_id', $this->member('Membre Campus')->id)->firstOrFail();
        $foreign = MemberCard::query()->where('membership_id', $this->member('Membre Alumni', null, 'alumni')->id)->firstOrFail();

        $campus = Premises::query()->create(['name' => 'Local Campus', 'scope' => 'university', 'is_active' => true]);
        $alumni = Premises::query()->create(['name' => 'Local Alumni', 'scope' => 'alumni', 'is_active' => true]);
        $this->scan($global, $mine, 'premises:'.$campus->id)->assertOk();
        $this->scan($global, $foreign, 'premises:'.$alumni->id)->assertOk();

        $this->actingAs($admin->fresh())->get('/admin/pointage/historique')->assertOk()->assertSee('Membre Campus')->assertDontSee('Membre Alumni');
        $this->actingAs($admin->fresh())->get('/admin/cartes')->assertOk()->assertSee('Membre Campus')->assertDontSee('Membre Alumni');
        $this->actingAs($global->fresh())->get('/admin/pointage/historique')->assertOk()->assertSee('Membre Campus')->assertSee('Membre Alumni');

        $this->actingAs($admin->fresh())->patch("/admin/cartes/{$foreign->id}/suspendre")->assertForbidden();
        $this->actingAs($admin->fresh())->patch("/admin/cartes/{$mine->id}/suspendre")->assertRedirect();
        $this->assertSame('suspended', $mine->fresh()->status);

        $this->actingAs($admin->fresh())->patch("/admin/cartes/{$mine->id}/reactiver")->assertRedirect();
        $this->assertSame('active', $mine->fresh()->status);
    }

    public function test_a_plain_member_cannot_use_the_scanner(): void
    {
        $this->actingAs(User::factory()->create())->postJson('/admin/pointage', ['target' => 'premises:1', 'direction' => 'in', 'number' => 'TL-AAAA-BBBB'])->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/admin/pointage')->assertForbidden();
    }
}
