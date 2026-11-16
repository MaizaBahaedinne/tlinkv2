<?php

namespace Tests\Feature;

use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Models\OrgPerson;
use App\Models\PositionInvitation;
use App\Models\User;
use App\Notifications\PositionInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SponsorshipTest extends TestCase
{
    use RefreshDatabase;

    private OrgNode $top;

    private OrgNode $middle;

    private OrgNode $low;

    protected function setUp(): void
    {
        parent::setUp();

        $this->top = $this->node('Directeur du bureau', null);
        $this->middle = $this->node('Chef de pôle', $this->top);
        $this->low = $this->node('Assistant de pôle', $this->middle, 'single');
    }

    private function node(string $title, ?OrgNode $parent, string $capacity = 'multiple', string $relation = 'hierarchy'): OrgNode
    {
        return OrgNode::query()->create([
            'parent_id' => $parent?->id,
            'relation_type' => $relation,
            'kind' => 'position',
            'capacity' => $capacity,
            'access_level' => 'none',
            'category' => 'direction',
            'title' => $title,
            'sort_order' => 0,
        ]);
    }

    private function place(OrgNode $node, string $name = 'Titulaire'): User
    {
        $user = User::factory()->create(['name' => $name]);
        $person = OrgPerson::query()->create(['user_id' => $user->id, 'full_name' => $name, 'contact_visible' => true]);
        OrgAssignment::query()->create(['node_id' => $node->id, 'person_id' => $person->id]);

        return $user;
    }

    /** @return array<string, string> */
    private function invitee(string $email = 'amel@example.com'): array
    {
        return ['first_name' => 'Amel', 'last_name' => 'Ben Salah', 'email' => $email];
    }

    public function test_the_page_offers_the_positions_below_mine_with_link_and_qr_code(): void
    {
        $chief = $this->place($this->middle, 'Sonia Cheffe');
        $this->node('Conseiller rattaché', $this->middle, 'multiple', 'attachment');

        $this->actingAs($chief)->get('/parrainage')->assertOk()
            ->assertSee('Sous mon poste : Chef de pôle')
            ->assertSee('Assistant de pôle')
            ->assertSee(route('position.join', $this->low->join_token))
            ->assertSee('data-sponsor-qr', false)
            ->assertSee('wa.me', false)
            ->assertSee('Inviter par e-mail')
            ->assertDontSee('Directeur du bureau')
            ->assertDontSee('Conseiller rattaché')
            ->assertDontSee($this->middle->join_token);
    }

    public function test_the_user_menu_entry_only_exists_for_position_holders(): void
    {
        $holder = $this->place($this->middle);
        $other = User::factory()->create();

        $this->actingAs($holder)->get('/parrainage')->assertOk()->assertSee('href="'.route('sponsorship.index').'"', false);

        $this->actingAs($other)->get('/parrainage')->assertOk()
            ->assertSee('réservé aux membres qui occupent un poste')
            ->assertDontSee('href="'.route('sponsorship.index').'"', false);
    }

    public function test_a_filled_single_position_is_closed_to_sharing(): void
    {
        $chief = $this->place($this->middle);
        $this->place($this->low, 'Yassine Assistant');

        $this->actingAs($chief)->get('/parrainage')->assertOk()
            ->assertSee('Candidatures fermées')
            ->assertSee('Yassine Assistant')
            ->assertDontSee(route('position.join', $this->low->join_token));
    }

    public function test_an_invitation_by_email_is_sent_and_attributed_to_the_sponsor(): void
    {
        Notification::fake();
        $chief = $this->place($this->middle);

        $this->actingAs($chief)->post('/parrainage/postes/'.$this->low->id.'/invitation', $this->invitee())
            ->assertRedirect()
            ->assertSessionHas('status');

        $invitation = PositionInvitation::query()->firstOrFail();
        $this->assertSame($chief->id, $invitation->invited_by);
        $this->assertSame($this->low->id, $invitation->node_id);
        $this->assertSame('amel@example.com', $invitation->email);
        Notification::assertSentOnDemand(PositionInvitationNotification::class);
        $this->assertDatabaseHas('audit_logs', ['action' => 'sponsorship.invitation.sent']);
    }

    public function test_a_position_above_or_elsewhere_cannot_be_used(): void
    {
        Notification::fake();
        $chief = $this->place($this->middle);
        $elsewhere = $this->node('Poste voisin', $this->top);

        $this->actingAs($chief)->post('/parrainage/postes/'.$this->top->id.'/invitation', $this->invitee())->assertForbidden();
        $this->actingAs($chief)->post('/parrainage/postes/'.$elsewhere->id.'/invitation', $this->invitee())->assertForbidden();
        $this->actingAs(User::factory()->create())->post('/parrainage/postes/'.$this->low->id.'/invitation', $this->invitee())->assertForbidden();

        $this->assertSame(0, PositionInvitation::query()->count());
        Notification::assertNothingSent();
    }

    public function test_a_filled_single_position_refuses_new_invitations(): void
    {
        Notification::fake();
        $chief = $this->place($this->middle);
        $this->place($this->low);

        $this->actingAs($chief)->post('/parrainage/postes/'.$this->low->id.'/invitation', $this->invitee())->assertSessionHasErrors('email');

        $this->assertSame(0, PositionInvitation::query()->count());
    }

    public function test_the_invitation_form_is_validated(): void
    {
        $chief = $this->place($this->middle);

        $this->actingAs($chief)->post('/parrainage/postes/'.$this->low->id.'/invitation', ['first_name' => '', 'last_name' => 'X', 'email' => 'pas-un-mail'])
            ->assertSessionHasErrors(['first_name', 'email']);
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/parrainage')->assertRedirect();
        $this->post('/parrainage/postes/'.$this->low->id.'/invitation', $this->invitee())->assertRedirect();
    }
}
