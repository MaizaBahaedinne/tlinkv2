<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\FoundationEvent;
use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Models\OrgPerson;
use App\Models\SiteNews;
use App\Models\SiteProject;
use App\Models\User;
use App\Support\OrgScopes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class ProjectsTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    /** Catalogue : un projet, un chapitre personnalisé, et les périmètres système (alumni passe en chapitre pour une liste vide). */
    private function defineScopes(): void
    {
        $catalog = ['we-are-carthage' => 'We Are Carthage', 'club-tunis' => 'Club Tunis'] + OrgScopes::DEFAULT;

        Setting::query()->updateOrCreate(['group' => 'org', 'key' => 'scopes_catalog'], ['value' => json_encode($catalog)]);
        Setting::query()->updateOrCreate(['group' => 'org', 'key' => 'scopes_types'], ['value' => json_encode([
            'foundation' => 'management',
            'university' => 'chapter',
            'high-school' => 'chapter',
            'alumni' => 'chapter',
            'club-tunis' => 'chapter',
            'we-are-carthage' => 'project',
        ])]);
        OrgScopes::flushCache();
    }

    private function makePage(array $overrides = []): SiteProject
    {
        return SiteProject::query()->create($overrides + [
            'slug' => 'we-are-carthage',
            'scope' => 'we-are-carthage',
            'name' => ['fr' => 'We Are Carthage', 'en' => 'We Are Carthage EN', 'ar' => ''],
            'summary' => ['fr' => 'Résumé du projet.'],
            'body' => ['fr' => "Premier paragraphe.\n\nSecond paragraphe."],
            'stats' => [
                ['value' => '1 200', 'label' => ['fr' => 'Jeunes formés', 'en' => 'Young people trained']],
                ['value' => '40', 'label' => ['fr' => 'Clubs engagés']],
                ['value' => '', 'label' => ['fr' => 'Ligne incomplète']],
            ],
            'status' => 'ongoing',
            'started_year' => 2019,
            'is_published' => true,
        ]);
    }

    public function test_scope_types_have_defaults_and_can_be_changed_by_admin(): void
    {
        $this->assertSame('management', OrgScopes::type('foundation'));
        $this->assertSame('chapter', OrgScopes::type('university'));
        $this->assertSame('chapter', OrgScopes::type('high-school'));

        $admin = User::factory()->create();
        $this->grantAccess($admin, 'global_admin');
        $this->actingAs($admin)->get('/admin/structure/perimetres')->assertOk()->assertSee('Management')->assertSee('Chapitre')->assertSee('Projet');

        $this->actingAs($admin)->put('/admin/structure/perimetres', [
            'label' => OrgScopes::all(),
            'type' => ['alumni' => 'chapter', 'university' => 'project'],
            'new_slug' => 'we-are-campers',
            'new_label' => 'We Are Campers',
            'new_type' => 'project',
        ])->assertSessionHasNoErrors();

        OrgScopes::flushCache();
        $this->assertSame('chapter', OrgScopes::type('alumni'));
        $this->assertSame('project', OrgScopes::type('university'));
        $this->assertSame('project', OrgScopes::type('we-are-campers'));
        $this->assertSame('management', OrgScopes::type('foundation')); // champ absent : inchangé

        $this->actingAs($admin)->put('/admin/structure/perimetres', [
            'label' => OrgScopes::all(),
            'type' => ['alumni' => 'inconnu'],
        ])->assertSessionHasErrors('type.alumni');
    }

    public function test_menu_always_links_to_projects_in_each_locale(): void
    {
        foreach (['fr', 'en', 'ar'] as $locale) {
            $this->get("/{$locale}")->assertOk()->assertSee("/{$locale}/projets", false)->assertSee(__('site.nav.projects', [], $locale));
        }
    }

    public function test_list_shows_only_scopes_of_type_project(): void
    {
        $this->defineScopes();
        $this->get('/fr/projets')->assertOk()->assertSee('We Are Carthage')->assertDontSee('Club Tunis')->assertDontSee('High School');

        Setting::query()->where('key', 'scopes_types')->update(['value' => json_encode(['we-are-carthage' => 'chapter', 'club-tunis' => 'chapter', 'alumni' => 'chapter'])]);
        OrgScopes::flushCache();
        $this->get('/fr/projets')->assertOk()->assertSee(__('site.projects.empty'));
    }

    public function test_project_scope_without_page_content_uses_scope_name_and_description(): void
    {
        $this->defineScopes();
        Setting::query()->updateOrCreate(['group' => 'org', 'key' => 'scopes_descriptions'], ['value' => json_encode(['we-are-carthage' => 'Description du périmètre.'])]);
        OrgScopes::flushCache();

        $this->get('/fr/projets/we-are-carthage')->assertOk()->assertSee('We Are Carthage')->assertSee('Description du périmètre.');
        $this->get('/fr/projets/club-tunis')->assertNotFound(); // chapitre
        $this->get('/fr/projets/foundation')->assertNotFound(); // management
        $this->get('/fr/projets/inconnu')->assertNotFound();
    }

    public function test_project_page_shows_its_team_events_and_news_only(): void
    {
        $this->defineScopes();
        $this->makePage();

        $position = OrgNode::query()->create(['kind' => 'position', 'category' => 'project', 'scope' => 'we-are-carthage', 'title' => 'Cheffe de projet', 'access_level' => 'none']);
        $other = OrgNode::query()->create(['kind' => 'position', 'category' => 'university', 'scope' => 'university', 'title' => 'Responsable campus', 'access_level' => 'none']);

        foreach ([[$position, 'Leila Carthage'], [$other, 'Karim Campus']] as [$node, $name]) {
            $person = OrgPerson::query()->create(['full_name' => $name, 'email' => 'secret@example.test']);
            OrgAssignment::query()->create(['node_id' => $node->id, 'person_id' => $person->id]);
        }

        FoundationEvent::query()->create(['title' => 'Gala Carthage', 'scope' => 'we-are-carthage', 'status' => 'published', 'starts_at' => now()->addDays(5)]);
        FoundationEvent::query()->create(['title' => 'Atelier Campus', 'scope' => 'university', 'status' => 'published', 'starts_at' => now()->addDays(5)]);
        SiteNews::query()->create(['title' => ['fr' => 'Bilan Carthage'], 'scope' => 'we-are-carthage', 'status' => 'published', 'published_at' => now()->subHour()]);
        SiteNews::query()->create(['title' => ['fr' => 'Actu Campus'], 'scope' => 'university', 'status' => 'published', 'published_at' => now()->subHour()]);

        $this->get('/fr/projets/we-are-carthage')
            ->assertOk()
            ->assertSee(__('site.projects.team', [], 'fr'))
            ->assertSee('Leila Carthage')
            ->assertSee('Cheffe de projet')
            ->assertSee('Gala Carthage')
            ->assertSee('Bilan Carthage')
            ->assertDontSee('secret@example.test')
            ->assertDontSee('Karim Campus')
            ->assertDontSee('Atelier Campus')
            ->assertDontSee('Actu Campus');
    }

    public function test_project_page_hides_empty_team_events_and_news_sections(): void
    {
        $this->defineScopes();
        $this->makePage();

        $this->get('/fr/projets/we-are-carthage')
            ->assertOk()
            ->assertDontSee(__('site.projects.team', [], 'fr'))
            ->assertDontSee(__('site.projects.events', [], 'fr'))
            ->assertDontSee(__('site.projects.news', [], 'fr'));
    }

    public function test_project_page_shows_the_scope_logo(): void
    {
        Storage::fake('public');
        $this->defineScopes();
        Storage::disk('public')->put('scopes/logos/carthage.png', 'x');
        Setting::query()->updateOrCreate(['group' => 'org', 'key' => 'scopes_logos'], ['value' => json_encode(['we-are-carthage' => 'scopes/logos/carthage.png'])]);
        OrgScopes::flushCache();

        $this->get('/fr/projets/we-are-carthage')->assertOk()->assertSee('scopes/logos/carthage.png', false);
    }

    public function test_project_page_shows_presentation_and_figures_in_each_locale(): void
    {
        $this->defineScopes();
        $this->makePage();

        $this->get('/fr/projets/we-are-carthage')
            ->assertOk()
            ->assertSee('Résumé du projet.')
            ->assertSee('1 200')
            ->assertSee('Jeunes formés')
            ->assertSee('Second paragraphe.')
            ->assertDontSee('Ligne incomplète');

        $this->get('/en/projets/we-are-carthage')->assertOk()->assertSee('We Are Carthage EN')->assertSee('Young people trained')->assertSee('Clubs engagés'); // repli français
        $this->get('/ar/projets/we-are-carthage')->assertOk()->assertSee('We Are Carthage'); // repli français
    }

    public function test_unpublished_page_is_hidden(): void
    {
        $this->defineScopes();
        $this->makePage(['is_published' => false]);

        $this->get('/fr/projets/we-are-carthage')->assertNotFound();
        $this->get('/fr/projets')->assertOk()->assertDontSee('We Are Carthage');
    }

    public function test_unsafe_project_link_is_not_rendered(): void
    {
        $this->defineScopes();
        $this->makePage(['website_url' => 'javascript:alert(1)']);

        $this->get('/fr/projets/we-are-carthage')->assertOk()->assertDontSee('javascript:alert', false);
    }

    public function test_admin_lists_project_scopes_and_edits_their_page(): void
    {
        Storage::fake('public');
        $this->defineScopes();
        $admin = User::factory()->create();
        $this->grantAccess($admin, 'global_admin');

        $this->actingAs($admin)->get('/admin/projets')->assertOk()->assertSee('We Are Carthage')->assertDontSee('Club Tunis');
        $this->actingAs($admin)->get('/admin/projets/we-are-carthage/modifier')->assertOk();
        $this->actingAs($admin)->get('/admin/projets/club-tunis/modifier')->assertNotFound();

        $this->actingAs($admin)->put('/admin/projets/we-are-carthage', [
            'summary' => ['fr' => 'Camps de vacances.'],
            'status' => 'ongoing',
            'is_published' => '1',
            'cover' => UploadedFile::fake()->image('cover.jpg', 800, 400),
            'stats' => [
                ['value' => '500', 'label' => ['fr' => 'Campeurs', 'en' => 'Campers', 'ar' => '']],
                ['value' => '', 'label' => ['fr' => '', 'en' => '', 'ar' => '']],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $page = SiteProject::query()->where('scope', 'we-are-carthage')->firstOrFail();
        $this->assertSame([['value' => '500', 'label' => ['fr' => 'Campeurs', 'en' => 'Campers', 'ar' => '']]], $page->stats);
        $this->assertNotEmpty(Storage::disk('public')->allFiles('projects/covers'));
        $this->get('/fr/projets/we-are-carthage')->assertOk()->assertSee('500')->assertSee('Camps de vacances.');

        $this->actingAs($admin)->put('/admin/projets/we-are-carthage', ['status' => 'completed', 'cover_remove' => '1'])->assertSessionHasNoErrors();
        $page->refresh();
        $this->assertFalse($page->is_published);
        $this->assertNull($page->cover_path);
        $this->assertSame([], Storage::disk('public')->allFiles('projects/covers'));

        $this->actingAs($admin)->delete('/admin/projets/we-are-carthage')->assertRedirect(route('admin.projects.index'));
        $this->assertDatabaseMissing('site_projects', ['scope' => 'we-are-carthage']);
        $this->get('/fr/projets/we-are-carthage')->assertOk(); // retour à la page par défaut du périmètre
    }

    public function test_figure_label_without_value_is_rejected(): void
    {
        $this->defineScopes();
        $admin = User::factory()->create();
        $this->grantAccess($admin, 'global_admin');

        $this->actingAs($admin)->put('/admin/projets/we-are-carthage', [
            'status' => 'ongoing',
            'stats' => [['value' => '', 'label' => ['fr' => 'Sans chiffre']]],
        ])->assertSessionHasErrors('stats.0.value');
    }

    public function test_guests_are_redirected_and_scoped_admin_with_module_right_can_open_projects(): void
    {
        $this->defineScopes();
        $this->get('/admin/projets')->assertRedirect(route('login'));

        $admin = User::factory()->create();
        $this->grantAccess($admin, 'entity_admin', 'university');

        $this->actingAs($admin)->get('/admin/projets')->assertOk();
    }
}
