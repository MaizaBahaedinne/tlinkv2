<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileJourneyDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function seedJourney(User $user): void
    {
        $this->actingAs($user)->patch('/profil', [
            'section' => 'professional_experiences',
            'items' => [
                ['company' => 'Ancienne Societe', 'role' => 'Stagiaire', 'start_date' => '2018-01-01', 'end_date' => '2018-06-01'],
                ['company' => 'Societe Recente', 'role' => 'Ingenieur', 'start_date' => '2024-01-01'],
            ],
        ])->assertSessionHasNoErrors();
    }

    public function test_a_member_deletes_one_block_of_his_journey_even_when_the_display_is_sorted(): void
    {
        $user = User::factory()->create();
        $this->seedJourney($user);

        // « Societe Recente » est affichee en premier mais enregistree en second.
        $page = $this->get('/profil')->assertOk();
        $page->assertSee(route('profile.journey.destroy', ['professional_experiences', 1]), false);

        $this->delete(route('profile.journey.destroy', ['professional_experiences', 1]))->assertRedirect(route('profile.show').'#parcours');

        $rows = $user->profile()->first()->privacy_settings['professional_experiences'];
        $this->assertCount(1, $rows);
        $this->assertSame('Ancienne Societe', $rows[0]['company']);
    }

    public function test_deleting_an_unknown_block_or_section_is_not_found(): void
    {
        $user = User::factory()->create();
        $this->seedJourney($user);

        $this->delete('/profil/parcours/professional_experiences/9')->assertNotFound();
        $this->delete('/profil/parcours/languages/0')->assertNotFound();
        $this->assertCount(2, $user->profile()->first()->privacy_settings['professional_experiences']);
    }

    public function test_a_member_cannot_delete_the_blocks_of_someone_else(): void
    {
        $owner = User::factory()->create();
        $this->seedJourney($owner);

        $this->actingAs(User::factory()->create())->delete('/profil/parcours/professional_experiences/0')->assertNotFound();
        $this->assertCount(2, $owner->profile()->first()->privacy_settings['professional_experiences']);
    }

    public function test_guests_cannot_delete_blocks(): void
    {
        $this->delete('/profil/parcours/educations/0')->assertRedirect();
    }

    public function test_the_delete_button_is_not_shown_on_the_profile_of_other_members(): void
    {
        $owner = User::factory()->create();
        $this->seedJourney($owner);

        $this->actingAs(User::factory()->create())->get(route('member.show', $owner))->assertOk()->assertDontSee('/profil/parcours/', false);
    }
}
