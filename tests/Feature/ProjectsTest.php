<?php

namespace Tests\Feature;

use App\Models\SiteProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class ProjectsTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    private function makeProject(array $overrides = []): SiteProject
    {
        return SiteProject::query()->create($overrides + [
            'slug' => 'we-are-carthage',
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

    public function test_project_page_shows_presentation_and_figures_in_each_locale(): void
    {
        $this->makeProject();

        $this->get('/fr/projets/we-are-carthage')
            ->assertOk()
            ->assertSee('We Are Carthage')
            ->assertSee('Résumé du projet.')
            ->assertSee('1 200')
            ->assertSee('Jeunes formés')
            ->assertSee('Second paragraphe.')
            ->assertDontSee('Ligne incomplète');

        $this->get('/en/projets/we-are-carthage')->assertOk()->assertSee('We Are Carthage EN')->assertSee('Young people trained')->assertSee('Clubs engagés'); // repli français
        $this->get('/ar/projets/we-are-carthage')->assertOk()->assertSee('We Are Carthage'); // repli français
    }

    public function test_menu_always_links_to_projects_in_each_locale(): void
    {
        foreach (['fr', 'en', 'ar'] as $locale) {
            $this->get("/{$locale}")->assertOk()->assertSee("/{$locale}/projets", false)->assertSee(__('site.nav.projects', [], $locale));
        }
    }

    public function test_list_shows_only_published_projects(): void
    {
        $this->get('/fr/projets')->assertOk()->assertSee(__('site.projects.empty'));

        $this->makeProject();
        $this->makeProject(['slug' => 'brouillon', 'name' => ['fr' => 'Projet Brouillon'], 'is_published' => false]);

        $this->get('/fr/projets')->assertOk()->assertSee('We Are Carthage')->assertDontSee('Projet Brouillon');
        $this->get('/fr/projets/brouillon')->assertNotFound();
        $this->get('/fr/projets/inconnu')->assertNotFound();
    }

    public function test_unsafe_project_link_is_not_rendered(): void
    {
        $this->makeProject(['website_url' => 'javascript:alert(1)']);

        $this->get('/fr/projets/we-are-carthage')->assertOk()->assertDontSee('javascript:alert', false);
    }

    public function test_admin_can_create_update_and_delete_a_project(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();
        $this->grantAccess($admin, 'global_admin');

        $this->actingAs($admin)->get('/admin/projets')->assertOk();
        $this->actingAs($admin)->get('/admin/projets/nouveau')->assertOk();

        $this->actingAs($admin)->post('/admin/projets', [
            'name' => ['fr' => 'We Are Campers', 'en' => '', 'ar' => ''],
            'summary' => ['fr' => 'Camps d\'été.'],
            'status' => 'ongoing',
            'is_published' => '1',
            'cover' => UploadedFile::fake()->image('cover.jpg', 800, 400),
            'stats' => [
                ['value' => '500', 'label' => ['fr' => 'Campeurs', 'en' => 'Campers', 'ar' => '']],
                ['value' => '', 'label' => ['fr' => '', 'en' => '', 'ar' => '']],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $project = SiteProject::query()->where('slug', 'we-are-campers')->firstOrFail();
        $this->assertSame([['value' => '500', 'label' => ['fr' => 'Campeurs', 'en' => 'Campers', 'ar' => '']]], $project->stats);
        $this->assertNotEmpty(Storage::disk('public')->allFiles('projects/covers'));
        $this->get('/fr/projets/we-are-campers')->assertOk()->assertSee('500');

        $this->actingAs($admin)->get("/admin/projets/{$project->id}/modifier")->assertOk()->assertSee('We Are Campers');
        $this->actingAs($admin)->put("/admin/projets/{$project->id}", [
            'name' => ['fr' => 'We Are Campers 2'],
            'status' => 'completed',
            'cover_remove' => '1',
            'sort_order' => 3,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $project->refresh();
        $this->assertFalse($project->is_published);
        $this->assertNull($project->cover_path);
        $this->assertSame([], Storage::disk('public')->allFiles('projects/covers'));

        $this->actingAs($admin)->delete("/admin/projets/{$project->id}")->assertRedirect(route('admin.projects.index'));
        $this->assertDatabaseMissing('site_projects', ['id' => $project->id]);
    }

    public function test_figure_label_without_value_and_duplicate_slug_are_rejected(): void
    {
        $admin = User::factory()->create();
        $this->grantAccess($admin, 'global_admin');
        $this->makeProject();

        $this->actingAs($admin)->post('/admin/projets', [
            'name' => ['fr' => 'Autre'], 'status' => 'ongoing',
            'stats' => [['value' => '', 'label' => ['fr' => 'Sans chiffre']]],
        ])->assertSessionHasErrors('stats.0.value');

        $this->actingAs($admin)->post('/admin/projets', [
            'name' => ['fr' => 'Autre'], 'status' => 'ongoing', 'slug' => 'we-are-carthage',
        ])->assertSessionHasErrors('slug');

        $this->actingAs($admin)->post('/admin/projets', ['name' => ['fr' => 'We Are Carthage'], 'status' => 'ongoing'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('site_projects', ['slug' => 'we-are-carthage-2']);
    }

    public function test_scoped_admin_and_guests_cannot_manage_projects(): void
    {
        $this->get('/admin/projets')->assertRedirect(route('login'));

        $admin = User::factory()->create();
        $this->grantAccess($admin, 'entity_admin', 'university');

        $this->actingAs($admin)->get('/admin/projets')->assertForbidden();
        $this->actingAs($admin)->post('/admin/projets', ['name' => ['fr' => 'X'], 'status' => 'ongoing'])->assertForbidden();
    }
}
