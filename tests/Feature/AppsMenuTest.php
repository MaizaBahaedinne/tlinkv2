<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

/** Menu des applications de l'en-tête T.Link : remplace le bouton Messenger, chaque application s'ouvre dans une nouvelle page. */
class AppsMenuTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    public function test_the_header_offers_an_apps_menu_with_each_application_opening_in_a_new_page(): void
    {
        $html = $this->actingAs(User::factory()->create())->get('/feed')->assertOk()
            ->assertSee('id="nav-apps"', false)
            ->assertSee('Applications T.Link')
            ->assertSee('id="nav-messages"', false)
            ->assertSee(route('messenger.index', [], false), false)
            ->assertSee('Messenger')->assertSee('Academy')->assertSee('Agenda')
            ->getContent();

        preg_match_all('#<a href="[^"]+"[^>]*data-app-link[^>]*>#', $html, $links);

        $this->assertNotEmpty($links[0]);

        foreach ($links[0] as $link) {
            $this->assertStringContainsString('target="_blank"', $link);
            $this->assertStringContainsString('rel="noopener"', $link);
        }
    }

    public function test_webmail_and_administration_only_show_to_those_who_can_use_them(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)->get('/feed')->assertOk()->assertDontSee('>Administration<', false)->assertDontSee('>Webmail<', false);
    }

    public function test_the_messenger_button_is_gone_from_the_header_bar_and_the_badge_moves_into_the_menu(): void
    {
        $html = $this->actingAs(User::factory()->create())->get('/feed')->assertOk()->getContent();

        $this->assertStringNotContainsString('fa-regular fa-comments" aria-hidden="true"></i>
                    <span id="nav-messages-badge"', $html);
        $this->assertStringContainsString('id="nav-apps-dot"', $html);
        $this->assertSame(1, substr_count($html, 'id="nav-messages-badge"'));
    }
}
