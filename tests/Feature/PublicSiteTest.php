<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\FoundationEvent;
use App\Models\Membership;
use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Models\OrgPerson;
use App\Models\Post;
use App\Models\PostAttachment;
use App\Models\Setting;
use App\Models\User;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    private function makePost(string $content, string $visibility = 'public'): Post
    {
        return Post::query()->create([
            'user_id' => User::factory()->create()->id,
            'type' => 'text',
            'visibility' => $visibility,
            'content' => $content,
            'published_at' => now()->subHour(),
        ]);
    }

    public function test_root_redirects_to_preferred_locale(): void
    {
        $this->get('/', ['Accept-Language' => 'en'])->assertRedirect('/en');
        $this->get('/', ['Accept-Language' => 'ar-TN,ar;q=0.9'])->assertRedirect('/ar');
        $this->get('/', ['Accept-Language' => 'de'])->assertRedirect('/fr');
    }

    public function test_unknown_locale_is_not_found(): void
    {
        $this->get('/de')->assertNotFound();
    }

    public function test_feed_lives_on_feed_and_logged_in_users_land_there(): void
    {
        $this->get('/feed')->assertRedirect('/connexion');
        $this->assertSame('/feed', route('home', [], false));

        $user = User::factory()->create();
        $this->actingAs($user)->get('/feed')->assertOk();
        $this->actingAs($user)->get('/connexion')->assertRedirect('/feed');
    }

    public function test_every_page_renders_in_each_locale(): void
    {
        $post = $this->makePost('Une publication publique');
        $event = FoundationEvent::query()->create(['title' => 'Assemblée', 'status' => 'published', 'starts_at' => now()->addWeek()]);

        foreach (['fr', 'en', 'ar'] as $locale) {
            foreach (['', '/a-propos', '/actualites', "/actualites/{$post->id}", '/evenements', "/evenements/{$event->id}", '/clubs', '/contact'] as $path) {
                $this->get("/{$locale}{$path}")->assertOk();
            }
        }
    }

    public function test_arabic_pages_are_right_to_left(): void
    {
        $this->get('/ar')->assertOk()->assertSee('dir="rtl"', false)->assertSee('lang="ar"', false);
        $this->get('/fr')->assertSee('dir="ltr"', false);
    }

    public function test_only_public_posts_are_listed(): void
    {
        $public = $this->makePost('Annonce ouverte à tous');
        $private = $this->makePost('Note interne réservée', 'authenticated');

        $this->get('/fr/actualites')
            ->assertSee('Annonce ouverte à tous')
            ->assertDontSee('Note interne réservée');

        $this->get("/fr/actualites/{$public->id}")->assertOk();
        $this->get("/fr/actualites/{$private->id}")->assertNotFound();
    }

    public function test_post_content_is_escaped(): void
    {
        $post = $this->makePost('<script>alert(1)</script>');

        $this->get("/fr/actualites/{$post->id}")
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_media_is_only_served_for_public_posts(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('posts/images/a.jpg', 'x');
        Storage::disk('public')->put('posts/images/b.jpg', 'x');

        $public = PostAttachment::query()->create(['post_id' => $this->makePost('ouvert')->id, 'disk' => 'public', 'path' => 'posts/images/a.jpg', 'mime_type' => 'image/jpeg']);
        $private = PostAttachment::query()->create(['post_id' => $this->makePost('prive', 'authenticated')->id, 'disk' => 'public', 'path' => 'posts/images/b.jpg', 'mime_type' => 'image/jpeg']);

        $this->get("/medias/{$public->id}")->assertOk();
        $this->get("/medias/{$private->id}")->assertNotFound();
    }

    public function test_only_published_events_are_shown(): void
    {
        FoundationEvent::query()->create(['title' => 'Forum des clubs', 'status' => 'published', 'starts_at' => now()->addDays(3)]);
        $draft = FoundationEvent::query()->create(['title' => 'Brouillon secret', 'status' => 'draft', 'starts_at' => now()->addDays(3)]);

        $this->get('/fr/evenements')
            ->assertSee('Forum des clubs')
            ->assertDontSee('Brouillon secret');
        $this->get("/fr/evenements/{$draft->id}")->assertNotFound();
    }

    public function test_team_lists_assigned_people_but_not_platform_administrators(): void
    {
        $president = OrgNode::query()->create(['kind' => 'position', 'category' => 'presidency', 'title' => 'Présidente', 'access_level' => 'none']);
        $tech = OrgNode::query()->create(['kind' => 'position', 'category' => 'presidency', 'title' => 'Administrateurs', 'access_level' => 'global_admin', 'system_key' => 'national-administrators']);

        foreach ([[$president, 'Amel Ben Salah'], [$tech, 'Technicien Caché']] as [$node, $name]) {
            $person = OrgPerson::query()->create(['full_name' => $name, 'email' => 'secret@example.test', 'phone' => '99999999']);
            OrgAssignment::query()->create(['node_id' => $node->id, 'person_id' => $person->id]);
        }

        $this->get('/fr/a-propos')
            ->assertSee('Amel Ben Salah')
            ->assertDontSee('Technicien Caché')
            ->assertDontSee('secret@example.test')
            ->assertDontSee('99999999');
    }

    public function test_clubs_page_lists_active_clubs_and_filters(): void
    {
        OrgNode::query()->create(['kind' => 'club', 'category' => 'university_club', 'title' => 'Club Alpha', 'scope' => 'university', 'status' => 'active']);
        OrgNode::query()->create(['kind' => 'club', 'category' => 'highschool_club', 'title' => 'Club Beta', 'scope' => 'high-school', 'status' => 'active']);
        OrgNode::query()->create(['kind' => 'club', 'category' => 'university_club', 'title' => 'Club Inactif', 'scope' => 'university', 'status' => 'inactive']);

        $this->get('/fr/clubs')->assertSee('Club Alpha')->assertSee('Club Beta')->assertDontSee('Club Inactif');
        $this->get('/fr/clubs?scope=university')->assertSee('Club Alpha')->assertDontSee('Club Beta');
        $this->get('/fr/clubs?scope=bogus')->assertSessionHasErrors('scope');
    }

    public function test_clubs_page_exposes_governorate_markers_for_the_map(): void
    {
        $tunis = \App\Models\Governorate::query()->create(['name' => 'Tunis', 'slug' => 'tunis', 'is_active' => true]);
        $sfax = \App\Models\Governorate::query()->create(['name' => 'Sfax', 'slug' => 'sfax', 'is_active' => true]);
        $this->assertEqualsWithDelta(36.8065, $tunis->latitude, 0.0001); // coordonnées posées à la création

        foreach ([['A', $tunis], ['B', $tunis], ['C', $sfax]] as [$name, $governorate]) {
            OrgNode::query()->create(['kind' => 'club', 'category' => 'university_club', 'title' => 'Club '.$name, 'scope' => 'university', 'status' => 'active', 'governorate_id' => $governorate->id]);
        }

        $response = $this->get('/fr/clubs')->assertOk()->assertSee('id="clubs-map"', false);
        preg_match('/data-markers="([^"]*)"/', $response->getContent(), $m);
        $markers = json_decode(html_entity_decode($m[1]), true);

        $this->assertCount(2, $markers);
        $this->assertSame(2, collect($markers)->firstWhere('name', 'Tunis')['count']);

        // Filtrer sur un gouvernorat garde tous les marqueurs et met le choisi en évidence.
        $filtered = $this->get('/fr/clubs?gov='.$sfax->id)->assertOk()->assertSee('Club C')->assertDontSee('Club A');
        preg_match('/data-markers="([^"]*)"/', $filtered->getContent(), $m);
        $markers = json_decode(html_entity_decode($m[1]), true);
        $this->assertCount(2, $markers);
        $this->assertTrue(collect($markers)->firstWhere('name', 'Sfax')['active']);
    }

    public function test_team_is_a_section_of_the_about_page_and_not_in_the_menu(): void
    {
        foreach (['fr' => 'Équipe', 'en' => 'Team'] as $locale => $label) {
            $page = $this->get("/{$locale}/a-propos")->assertOk()->assertSee('id="equipe"', false);
            $this->assertStringNotContainsString("/{$locale}/equipe", $page->getContent());
            $this->assertStringNotContainsString('>'.$label.'</a>', $page->getContent());
        }

        // L'ancienne adresse redirige vers la section.
        $this->get('/fr/equipe')->assertStatus(301)->assertRedirect('/fr/a-propos#equipe');
    }

    public function test_language_switcher_is_a_dropdown_list(): void
    {
        $html = $this->get('/fr/clubs?scope=university')->assertOk()->getContent();

        $this->assertStringContainsString('id="lang-switcher"', $html);
        foreach (['Français', 'العربية', 'English'] as $name) {
            $this->assertStringContainsString('>'.$name.'</span>', $html);
        }
        // Le changement de langue conserve la page et les filtres.
        $this->assertStringContainsString('/en/clubs?scope=university', $html);
        $this->assertStringContainsString('/ar/clubs?scope=university', $html);
    }

    public function test_team_is_grouped_by_inherited_scope_in_org_chart_order(): void
    {
        $node = fn (array $attributes) => OrgNode::query()->create($attributes + ['kind' => 'position', 'access_level' => 'none']);
        $person = function (OrgNode $position, string $name): void {
            $person = OrgPerson::query()->create(['full_name' => $name]);
            OrgAssignment::query()->create(['node_id' => $position->id, 'person_id' => $person->id]);
        };

        $root = $node(['category' => 'presidency', 'title' => 'Président', 'scope' => 'foundation', 'sort_order' => 0]);
        $office = $node(['category' => 'national_office', 'title' => 'Coordinateur University', 'parent_id' => $root->id, 'scope' => 'university', 'sort_order' => 2]);
        $manager = $node(['category' => 'national_office', 'title' => 'Manager RH', 'parent_id' => $office->id, 'sort_order' => 0]); // hérite de university
        $highschool = $node(['category' => 'national_office', 'title' => 'Coordinateur Highschool', 'parent_id' => $root->id, 'scope' => 'high-school', 'sort_order' => 1]);
        $clubPresident = $node(['category' => 'university_club', 'title' => 'Président de club', 'parent_id' => $office->id, 'sort_order' => 1]);

        $person($manager, 'Sonia Univ');
        $person($office, 'Coordo Univ');
        $person($root, 'Karim Fondation');
        $person($highschool, 'Leila Lycée');
        $person($clubPresident, 'Membre De Club');

        $html = $this->get('/fr/a-propos')->assertOk()->getContent();

        $this->assertStringNotContainsString('Membre De Club', $html); // les clubs ont leur page

        // Sections dans l'ordre des périmètres : Fondation, University, High School.
        $foundation = strpos($html, 'id="foundation"');
        $university = strpos($html, 'id="university"');
        $highSchool = strpos($html, 'id="high-school"');
        $this->assertIsInt($foundation);
        $this->assertLessThan($university, $foundation);
        $this->assertLessThan($highSchool, $university);

        $this->assertTrue(strpos($html, 'Karim Fondation') > $foundation && strpos($html, 'Karim Fondation') < $university);
        $this->assertTrue(strpos($html, 'Sonia Univ') > $university && strpos($html, 'Sonia Univ') < $highSchool); // héritage du périmètre
        $this->assertTrue(strpos($html, 'Leila Lycée') > $highSchool);

        // Dans un périmètre, l'ordre suit l'arbre : le coordinateur avant son manager.
        $this->assertLessThan(strpos($html, 'Sonia Univ'), strpos($html, 'Coordo Univ'));
    }

    public function test_news_and_events_can_be_filtered_by_scope(): void
    {
        $univ = $this->makePost('Annonce des clubs universitaires');
        $univ->update(['scope' => 'university']);
        $lycee = $this->makePost('Annonce des clubs lycéens');
        $lycee->update(['scope' => 'high-school']);
        $general = $this->makePost('Annonce générale de la fondation');

        $this->get('/fr/actualites?scope=university')->assertSee('Annonce des clubs universitaires')->assertDontSee('Annonce des clubs lycéens')->assertDontSee('Annonce générale');
        $this->get('/fr/actualites?scope=foundation')->assertSee('Annonce générale de la fondation')->assertDontSee('Annonce des clubs universitaires');
        $this->get('/fr/actualites?scope=bogus')->assertSessionHasErrors('scope');

        FoundationEvent::query()->create(['title' => 'Forum University', 'status' => 'published', 'starts_at' => now()->addDays(3), 'scope' => 'university']);
        FoundationEvent::query()->create(['title' => 'Camp High School', 'status' => 'published', 'starts_at' => now()->addDays(4), 'scope' => 'high-school']);

        $this->get('/fr/evenements?scope=high-school')->assertSee('Camp High School')->assertDontSee('Forum University');
        $this->get('/fr/evenements')->assertSee('Forum University')->assertSee('Camp High School');
    }

    public function test_unsafe_club_link_is_not_rendered(): void
    {
        OrgNode::query()->create(['kind' => 'club', 'category' => 'university_club', 'title' => 'Club Piège', 'scope' => 'university', 'status' => 'active', 'facebook_url' => 'javascript:alert(1)']);

        $this->get('/fr/clubs')->assertSee('Club Piège')->assertDontSee('javascript:alert', false);
    }

    public function test_home_shows_aggregate_statistics_only(): void
    {
        $member = User::factory()->create(['email' => 'membre.prive@example.test']);
        Membership::query()->create(['user_id' => $member->id, 'scope' => 'university', 'status' => 'approved']);

        $this->get('/fr')->assertOk()->assertDontSee('membre.prive@example.test');
    }

    public function test_contact_form_stores_message(): void
    {
        $this->post('/fr/contact', [
            'name' => 'Sami', 'email' => 'sami@example.test', 'subject' => 'Partenariat', 'message' => 'Bonjour, nous souhaitons collaborer.',
        ])->assertRedirect('/fr/contact')->assertSessionHas('contact_sent');

        $this->assertDatabaseHas('contact_messages', ['email' => 'sami@example.test', 'locale' => 'fr', 'status' => 'new']);
    }

    public function test_contact_form_validates_and_localizes_errors(): void
    {
        $this->from('/en/contact')->post('/en/contact', ['name' => '', 'email' => 'nope', 'subject' => 'x', 'message' => 'court'])
            ->assertSessionHasErrors(['name', 'email', 'message']);

        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_contact_form_ignores_honeypot_submissions(): void
    {
        $this->post('/fr/contact', [
            'name' => 'Bot', 'email' => 'bot@example.test', 'subject' => 'Spam', 'message' => 'Message de robot automatique.', 'website' => 'http://spam.test',
        ])->assertSessionHasErrors('website');

        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_site_settings_override_defaults_with_locale_fallback(): void
    {
        Setting::query()->create([
            'group' => SiteSettings::GROUP,
            'key' => SiteSettings::KEY,
            'value' => json_encode(['hero_title' => ['fr' => 'Titre français personnalisé'], 'contact_email' => 'contact@tunivisions.test', 'facebook_url' => 'javascript:alert(1)'], JSON_UNESCAPED_UNICODE),
        ]);

        $this->get('/fr')->assertSee('Titre français personnalisé')->assertSee('contact@tunivisions.test');
        $this->get('/en')->assertSee('Titre français personnalisé'); // repli sur le français
        $this->get('/fr')->assertDontSee('javascript:alert', false);
    }

    public function test_admin_can_update_site_settings_and_read_messages(): void
    {
        $admin = User::factory()->create();
        $this->grantAccess($admin, 'global_admin');

        $this->actingAs($admin)->get('/admin/site-public')->assertOk();
        $this->actingAs($admin)->put('/admin/site-public', [
            'hero_title' => ['fr' => 'Bienvenue', 'en' => 'Welcome', 'ar' => ''],
            'contact_email' => 'hello@tunivisions.test',
            'facebook_url' => 'https://facebook.com/tunivisions',
        ])->assertRedirect();

        $this->get('/en')->assertSee('Welcome');
        $this->get('/ar')->assertSee('Bienvenue'); // repli sur le français

        $message = ContactMessage::query()->create(['name' => 'Sami', 'email' => 's@example.test', 'subject' => 'Salut', 'message' => 'Un message assez long.', 'locale' => 'fr']);
        $this->actingAs($admin)->get('/admin/messages')->assertOk()->assertSee('Salut');
        $this->actingAs($admin)->patch("/admin/messages/{$message->id}", ['status' => 'read'])->assertRedirect();
        $this->assertSame('read', $message->fresh()->status);
    }

    public function test_invalid_site_settings_are_rejected(): void
    {
        $admin = User::factory()->create();
        $this->grantAccess($admin, 'global_admin');

        $this->actingAs($admin)->put('/admin/site-public', ['facebook_url' => 'javascript:alert(1)'])->assertSessionHasErrors('facebook_url');
    }

    public function test_scoped_admin_cannot_manage_site_or_messages(): void
    {
        $admin = User::factory()->create();
        $this->grantAccess($admin, 'entity_admin', 'university');

        $this->actingAs($admin)->get('/admin/site-public')->assertForbidden();
        $this->actingAs($admin)->get('/admin/messages')->assertForbidden();
    }

    public function test_site_admin_pages_require_authentication(): void
    {
        $this->get('/admin/site-public')->assertRedirect(route('login'));
        $this->get('/admin/messages')->assertRedirect(route('login'));
    }
}
