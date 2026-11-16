<?php

namespace Tests\Feature;

use App\Models\Guide;
use App\Models\GuideProgress;
use App\Models\GuideStep;
use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Models\OrgPerson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class GuideTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Guide::query()->delete(); // la migration installe les guides de départ
    }

    private function globalAdmin(): User
    {
        $admin = User::factory()->create();
        $this->grantPlatformAdmin($admin);

        return $admin;
    }

    private function holder(): User
    {
        $node = OrgNode::query()->create([
            'parent_id' => null, 'relation_type' => 'hierarchy', 'kind' => 'position', 'capacity' => 'multiple',
            'access_level' => 'none', 'category' => 'direction', 'title' => 'Directeur', 'sort_order' => 0,
        ]);
        $user = User::factory()->create();
        $person = OrgPerson::query()->create(['user_id' => $user->id, 'full_name' => 'Titulaire', 'contact_visible' => true]);
        OrgAssignment::query()->create(['node_id' => $node->id, 'person_id' => $person->id]);

        return $user;
    }

    private function guide(string $title = 'Mon guide', array $attributes = [], int $steps = 2): Guide
    {
        $guide = Guide::query()->create($attributes + [
            'slug' => Guide::makeSlug($title), 'title' => $title, 'summary' => 'Un résumé.', 'category' => 'Prise en main',
            'status' => 'published', 'published_at' => now(),
        ]);

        for ($i = 1; $i <= $steps; $i++) {
            $guide->steps()->create(['position' => $i, 'title' => 'Étape '.$i.' de '.$title, 'body' => 'Texte '.$i, 'duration' => 5]);
        }

        return $guide;
    }

    public function test_starter_guides_are_installed_by_the_migration(): void
    {
        Guide::query()->delete();
        \App\Support\Guides\StarterGuides::install();

        $this->assertSame(6, Guide::query()->published()->count());
        $this->assertTrue(Guide::query()->where('slug', 'bien-demarrer-sur-tlink')->exists());
        $this->assertTrue(Guide::query()->where('audience', 'holders')->exists());

        \App\Support\Guides\StarterGuides::install(); // ne duplique pas
        $this->assertSame(6, Guide::query()->count());
    }

    public function test_library_requires_login(): void
    {
        $this->get('/guides')->assertRedirect();
    }

    public function test_library_lists_published_guides_only(): void
    {
        $this->guide('Guide visible');
        $this->guide('Guide brouillon', ['status' => 'draft', 'published_at' => null]);

        $this->actingAs(User::factory()->create())->get('/guides')->assertOk()
            ->assertSee('Guide visible')
            ->assertSee('Prise en main')
            ->assertDontSee('Guide brouillon');
    }

    public function test_holder_only_guides_are_reserved_to_position_holders(): void
    {
        $guide = $this->guide('Guide des responsables', ['audience' => 'holders']);

        $this->actingAs(User::factory()->create())->get('/guides')->assertOk()->assertDontSee('Guide des responsables');
        $this->get('/guides/'.$guide->slug)->assertNotFound();

        $this->actingAs($this->holder())->get('/guides')->assertOk()->assertSee('Guide des responsables');
        $this->get('/guides/'.$guide->slug)->assertOk();
    }

    public function test_library_search_filters_by_title_and_category(): void
    {
        $this->guide('Carte de membre', ['category' => 'Mon compte']);
        $this->guide('Webmail', ['category' => 'Applications']);

        $this->actingAs(User::factory()->create())->get('/guides?q=carte')->assertOk()->assertSee('Carte de membre')->assertDontSee('Webmail');
        $this->get('/guides?categorie=Applications')->assertOk()->assertSee('Webmail')->assertDontSee('Carte de membre');
    }

    public function test_player_renders_every_step_as_animated_player_and_written_notice(): void
    {
        $guide = $this->guide('Guide animé');
        $guide->steps()->first()->update(['notice_type' => 'warning', 'notice' => 'Gardez la carte ouverte', 'icon' => 'qrcode']);

        $this->actingAs(User::factory()->create())->get('/guides/'.$guide->slug)->assertOk()
            ->assertSee('data-guide-player', false)
            ->assertSee('data-guide-text', false)
            ->assertSee('Étape 1 de Guide animé')
            ->assertSee('Étape 2 de Guide animé')
            ->assertSee('Attention :', false)
            ->assertSee('Gardez la carte ouverte')
            ->assertSee('Notice écrite');
    }

    public function test_step_with_image_and_hotspot_renders_the_animated_pointer(): void
    {
        $guide = $this->guide('Guide image', [], 1);
        $guide->steps()->first()->update(['image_path' => 'guides/capture.png', 'hotspot_x' => 62, 'hotspot_y' => 40]);

        $this->actingAs(User::factory()->create())->get('/guides/'.$guide->slug)->assertOk()
            ->assertSee('guide-pointer', false)
            ->assertSee('--hx: 62%; --hy: 40%', false)
            ->assertSee('guides/capture.png', false);
    }

    public function test_draft_guide_is_hidden_from_members_but_previewable_by_admins(): void
    {
        $guide = $this->guide('Brouillon secret', ['status' => 'draft', 'published_at' => null]);

        $this->actingAs(User::factory()->create())->get('/guides/'.$guide->slug)->assertNotFound();

        $this->actingAs($this->globalAdmin())->get('/guides/'.$guide->slug)->assertOk()->assertSee('Brouillon : ce guide');
    }

    public function test_unknown_guide_is_not_found(): void
    {
        $this->actingAs(User::factory()->create())->get('/guides/introuvable')->assertNotFound();
    }

    public function test_progress_is_saved_and_completion_is_kept(): void
    {
        $guide = $this->guide('Guide suivi', [], 3);
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/guides/'.$guide->slug.'/progression', ['step' => 1])->assertOk()->assertJson(['saved' => true, 'completed' => false]);
        $this->assertSame(1, GuideProgress::query()->where('guide_id', $guide->id)->where('user_id', $user->id)->value('last_step'));

        $this->postJson('/guides/'.$guide->slug.'/progression', ['step' => 2, 'completed' => true])->assertOk()->assertJson(['completed' => true]);
        $this->postJson('/guides/'.$guide->slug.'/progression', ['step' => 0])->assertOk()->assertJson(['completed' => true]);

        $progress = GuideProgress::query()->where('guide_id', $guide->id)->firstOrFail();
        $this->assertNotNull($progress->completed_at);
        $this->assertSame(1, GuideProgress::query()->count());

        $this->get('/guides')->assertSee('Terminé');
    }

    public function test_progress_rejects_an_unknown_step_and_a_draft_guide(): void
    {
        $guide = $this->guide('Guide court', [], 2);
        $draft = $this->guide('Guide caché', ['status' => 'draft', 'published_at' => null]);

        $this->actingAs(User::factory()->create())->postJson('/guides/'.$guide->slug.'/progression', ['step' => 9])->assertStatus(422);
        $this->postJson('/guides/'.$draft->slug.'/progression', ['step' => 0])->assertNotFound();
    }

    public function test_resume_starts_at_the_last_step_read(): void
    {
        $guide = $this->guide('Guide reprise', [], 4);
        $user = User::factory()->create();
        GuideProgress::query()->create(['guide_id' => $guide->id, 'user_id' => $user->id, 'last_step' => 2]);

        $this->actingAs($user)->get('/guides/'.$guide->slug)->assertOk()->assertSee('data-start="2"', false);
        $this->get('/guides')->assertSee('Reprendre');
    }

    public function test_member_cannot_open_the_admin_guides(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/guides')->assertForbidden();
    }

    public function test_admin_creates_a_guide_as_draft_and_cannot_publish_it_without_steps(): void
    {
        $this->actingAs($this->globalAdmin());

        $this->post('/admin/guides', ['title' => 'Mon premier guide', 'summary' => 'Résumé', 'category' => 'Prise en main', 'audience' => 'all', 'icon' => 'compass', 'accent' => 'sky', 'sort_order' => 5])
            ->assertRedirect();

        $guide = Guide::query()->firstOrFail();
        $this->assertSame('mon-premier-guide', $guide->slug);
        $this->assertSame('draft', $guide->status);

        $this->post('/admin/guides/'.$guide->id.'/publication')->assertSessionHasErrors('status');
        $this->assertSame('draft', $guide->fresh()->status);

        $this->get('/admin/guides/'.$guide->id.'/modifier')->assertOk()->assertSee('Ajouter une étape');
    }

    public function test_guide_slugs_stay_unique(): void
    {
        $this->actingAs($this->globalAdmin());
        $data = ['title' => 'Même titre', 'audience' => 'all', 'icon' => 'book-open', 'accent' => 'rose'];

        $this->post('/admin/guides', $data)->assertRedirect();
        $this->post('/admin/guides', $data)->assertRedirect();

        $this->assertEqualsCanonicalizing(['meme-titre', 'meme-titre-2'], Guide::query()->pluck('slug')->all());
    }

    public function test_guide_form_rejects_unknown_values(): void
    {
        $this->actingAs($this->globalAdmin());

        $this->post('/admin/guides', ['title' => '', 'audience' => 'x', 'icon' => 'nope', 'accent' => 'pink'])
            ->assertSessionHasErrors(['title', 'audience', 'icon', 'accent']);
        $this->assertSame(0, Guide::query()->count());
    }

    public function test_admin_adds_a_step_with_an_image_and_a_hotspot(): void
    {
        Storage::fake('public');
        $this->actingAs($this->globalAdmin());
        $guide = $this->guide('Guide admin', [], 0);

        $this->post('/admin/guides/'.$guide->id.'/etapes', [
            'title' => 'Cliquer sur le bouton', 'body' => 'Texte', 'notice_type' => 'tip', 'notice' => 'Astuce utile',
            'image' => UploadedFile::fake()->image('capture.png', 800, 500), 'hotspot_x' => 40, 'hotspot_y' => 55, 'duration' => 9,
        ])->assertRedirect();

        $step = $guide->steps()->firstOrFail();
        $this->assertSame(1, $step->position);
        $this->assertSame('tip', $step->notice_type);
        $this->assertSame(40, $step->hotspot_x);
        $this->assertSame(9, $step->duration);
        Storage::disk('public')->assertExists($step->image_path);
    }

    public function test_step_hotspot_and_duration_are_validated(): void
    {
        $this->actingAs($this->globalAdmin());
        $guide = $this->guide('Guide validation', [], 0);

        $this->post('/admin/guides/'.$guide->id.'/etapes', ['title' => 'Étape', 'hotspot_x' => 150, 'hotspot_y' => 20, 'duration' => 1])
            ->assertSessionHasErrors(['hotspot_x', 'duration']);
        $this->post('/admin/guides/'.$guide->id.'/etapes', ['title' => ''])->assertSessionHasErrors('title');
        $this->assertSame(0, GuideStep::query()->count());
    }

    public function test_step_without_notice_text_has_no_notice_type(): void
    {
        $this->actingAs($this->globalAdmin());
        $guide = $this->guide('Guide notice', [], 0);

        $this->post('/admin/guides/'.$guide->id.'/etapes', ['title' => 'Étape', 'notice_type' => 'warning'])->assertRedirect();

        $this->assertNull($guide->steps()->first()->notice_type);
    }

    public function test_admin_replaces_and_removes_a_step_image(): void
    {
        Storage::fake('public');
        $this->actingAs($this->globalAdmin());
        $guide = $this->guide('Guide image admin', [], 1);
        $step = $guide->steps()->first();

        $this->put('/admin/guides/'.$guide->id.'/etapes/'.$step->id, ['title' => 'Avec image', 'image' => UploadedFile::fake()->image('a.png'), 'hotspot_x' => 10, 'hotspot_y' => 20])->assertRedirect();
        $first = $step->fresh()->image_path;
        Storage::disk('public')->assertExists($first);

        $this->put('/admin/guides/'.$guide->id.'/etapes/'.$step->id, ['title' => 'Image remplacée', 'image' => UploadedFile::fake()->image('b.png'), 'hotspot_x' => 10, 'hotspot_y' => 20])->assertRedirect();
        $second = $step->fresh()->image_path;
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);

        $this->put('/admin/guides/'.$guide->id.'/etapes/'.$step->id, ['title' => 'Sans image', 'remove_image' => 1, 'hotspot_x' => 10, 'hotspot_y' => 20])->assertRedirect();
        $step->refresh();
        $this->assertNull($step->image_path);
        $this->assertNull($step->hotspot_x);
        Storage::disk('public')->assertMissing($second);
    }

    public function test_admin_cannot_edit_a_step_through_another_guide(): void
    {
        $this->actingAs($this->globalAdmin());
        $one = $this->guide('Guide un', [], 1);
        $two = $this->guide('Guide deux', [], 1);

        $this->put('/admin/guides/'.$one->id.'/etapes/'.$two->steps()->first()->id, ['title' => 'Piratage'])->assertNotFound();
        $this->assertNotSame('Piratage', $two->steps()->first()->title);
    }

    public function test_admin_moves_steps_up_and_down(): void
    {
        $this->actingAs($this->globalAdmin());
        $guide = $this->guide('Guide ordre', [], 3);
        [$a, $b, $c] = $guide->steps->all();

        $this->post('/admin/guides/'.$guide->id.'/etapes/'.$c->id.'/deplacer', ['direction' => 'up'])->assertRedirect();
        $this->assertSame([$a->id, $c->id, $b->id], $guide->steps()->pluck('id')->all());

        $this->post('/admin/guides/'.$guide->id.'/etapes/'.$a->id.'/deplacer', ['direction' => 'up'])->assertRedirect(); // déjà première
        $this->assertSame([$a->id, $c->id, $b->id], $guide->steps()->pluck('id')->all());

        $this->post('/admin/guides/'.$guide->id.'/etapes/'.$a->id.'/deplacer', ['direction' => 'down'])->assertRedirect();
        $this->assertSame([$c->id, $a->id, $b->id], $guide->steps()->pluck('id')->all());
        $this->assertSame([1, 2, 3], $guide->steps()->pluck('position')->all());
    }

    public function test_admin_deletes_a_step_and_positions_are_compacted(): void
    {
        Storage::fake('public');
        $this->actingAs($this->globalAdmin());
        $guide = $this->guide('Guide suppression', [], 3);
        $middle = $guide->steps->get(1);
        $middle->update(['image_path' => UploadedFile::fake()->image('m.png')->store('guides', 'public')]);

        $this->delete('/admin/guides/'.$guide->id.'/etapes/'.$middle->id)->assertRedirect();

        Storage::disk('public')->assertMissing($middle->image_path);
        $this->assertSame([1, 2], $guide->steps()->pluck('position')->all());
    }

    public function test_deleting_the_last_step_of_a_published_guide_returns_it_to_draft(): void
    {
        $this->actingAs($this->globalAdmin());
        $guide = $this->guide('Guide unique', [], 1);

        $this->delete('/admin/guides/'.$guide->id.'/etapes/'.$guide->steps()->first()->id)->assertRedirect();

        $this->assertSame('draft', $guide->fresh()->status);
    }

    public function test_admin_publishes_and_unpublishes_a_guide(): void
    {
        $this->actingAs($this->globalAdmin());
        $guide = $this->guide('Guide bascule', ['status' => 'draft', 'published_at' => null]);

        $this->post('/admin/guides/'.$guide->id.'/publication')->assertRedirect();
        $this->assertSame('published', $guide->fresh()->status);
        $this->assertNotNull($guide->fresh()->published_at);

        $this->post('/admin/guides/'.$guide->id.'/publication')->assertRedirect();
        $this->assertSame('draft', $guide->fresh()->status);
    }

    public function test_admin_updates_a_guide_without_changing_its_address(): void
    {
        $this->actingAs($this->globalAdmin());
        $guide = $this->guide('Ancien titre');

        $this->put('/admin/guides/'.$guide->id, ['title' => 'Nouveau titre', 'audience' => 'holders', 'icon' => 'briefcase', 'accent' => 'violet', 'status' => 'published', 'sort_order' => 3])->assertRedirect();

        $guide->refresh();
        $this->assertSame('Nouveau titre', $guide->title);
        $this->assertSame('ancien-titre', $guide->slug);
        $this->assertSame('holders', $guide->audience);
    }

    public function test_admin_deletes_a_guide_with_its_images_and_progress(): void
    {
        Storage::fake('public');
        $this->actingAs($this->globalAdmin());
        $guide = $this->guide('Guide à supprimer', [], 1);
        $step = $guide->steps()->first();
        $step->update(['image_path' => UploadedFile::fake()->image('x.png')->store('guides', 'public')]);
        GuideProgress::query()->create(['guide_id' => $guide->id, 'user_id' => User::factory()->create()->id, 'last_step' => 0]);

        $this->delete('/admin/guides/'.$guide->id)->assertRedirect(route('admin.guides.index'));

        $this->assertSame(0, Guide::query()->count());
        $this->assertSame(0, GuideStep::query()->count());
        $this->assertSame(0, GuideProgress::query()->count());
        Storage::disk('public')->assertMissing($step->image_path);
    }

    public function test_admin_list_filters_and_escapes_titles(): void
    {
        $this->actingAs($this->globalAdmin());
        $this->guide('<script>alert(1)</script>');
        $this->guide('Autre guide', ['status' => 'draft', 'published_at' => null]);

        $this->get('/admin/guides')->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('Autre guide');
        $this->get('/admin/guides?status=draft')->assertOk()->assertSee('Autre guide')->assertDontSee('&lt;script&gt;', false);
    }

    public function test_guides_module_is_registered_and_menu_entry_is_visible_to_admins(): void
    {
        $this->assertTrue(\App\Models\AppModule::query()->where('key', 'help.guides')->exists());

        $this->actingAs($this->globalAdmin())->get('/admin')->assertOk()->assertSee('Guides d’utilisation');
    }

    public function test_user_menu_links_to_the_library(): void
    {
        $this->actingAs(User::factory()->create())->get('/guides')->assertOk()->assertSee(route('guides.index'), false);
    }
}
