<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\SiteNews;
use App\Models\User;
use App\Support\ModuleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class SiteNewsTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    private function globalAdmin(): User
    {
        $admin = User::factory()->create();
        $this->grantPlatformAdmin($admin);

        return $admin;
    }

    public function test_global_admin_creates_edits_and_deletes_news(): void
    {
        Storage::fake('public');
        $admin = $this->globalAdmin();
        $this->actingAs($admin);

        $this->post('/admin/actualites', [
            'title' => ['fr' => 'Rentrée des clubs', 'en' => 'Clubs back to school'],
            'excerpt' => ['fr' => 'Un résumé court'],
            'body' => ['fr' => "Premier paragraphe.\n\nSecond paragraphe."],
            'cover' => UploadedFile::fake()->image('une.jpg', 800, 400),
            'scope' => 'university',
            'status' => 'published',
        ])->assertSessionHasNoErrors();

        $news = SiteNews::query()->firstOrFail();
        $this->assertSame('Rentrée des clubs', $news->text('title', 'fr'));
        $this->assertSame('Clubs back to school', $news->text('title', 'en'));
        $this->assertSame('Rentrée des clubs', $news->text('title', 'ar'), 'repli sur le français');
        $this->assertSame('published', $news->status);
        $this->assertNotNull($news->published_at, 'une actualité publiée sans date paraît immédiatement');
        $this->assertSame($admin->id, $news->author_id);
        Storage::disk('public')->assertExists($news->cover_path);
        $oldCover = $news->cover_path;

        $this->get('/fr/actualites')->assertOk()->assertSee('Rentrée des clubs');
        $this->get('/en/actualites/'.$news->id)->assertOk()->assertSee('Clubs back to school');

        $this->put('/admin/actualites/'.$news->id, [
            'title' => ['fr' => 'Titre modifié'],
            'status' => 'draft',
            'cover' => UploadedFile::fake()->image('autre.jpg', 800, 400),
        ])->assertSessionHasNoErrors();

        $news->refresh();
        $this->assertSame('Titre modifié', $news->text('title', 'fr'));
        $this->assertSame('draft', $news->status);
        Storage::disk('public')->assertMissing($oldCover);
        Storage::disk('public')->assertExists($news->cover_path);
        $this->get('/fr/actualites')->assertDontSee('Titre modifié');

        $cover = $news->cover_path;
        $this->delete('/admin/actualites/'.$news->id)->assertRedirect('/admin/actualites');
        $this->assertDatabaseCount('site_news', 0);
        Storage::disk('public')->assertMissing($cover);
    }

    public function test_cover_can_be_removed(): void
    {
        Storage::fake('public');
        $this->actingAs($this->globalAdmin());

        $this->post('/admin/actualites', [
            'title' => ['fr' => 'Avec image'],
            'cover' => UploadedFile::fake()->image('une.jpg'),
            'status' => 'draft',
        ])->assertSessionHasNoErrors();

        $news = SiteNews::query()->firstOrFail();
        $cover = $news->cover_path;

        $this->put('/admin/actualites/'.$news->id, ['title' => ['fr' => 'Avec image'], 'status' => 'draft', 'remove_cover' => '1'])->assertSessionHasNoErrors();

        $this->assertNull($news->refresh()->cover_path);
        Storage::disk('public')->assertMissing($cover);
    }

    public function test_future_date_schedules_the_publication(): void
    {
        $this->actingAs($this->globalAdmin());

        $this->post('/admin/actualites', [
            'title' => ['fr' => 'Parution plus tard'],
            'status' => 'published',
            'published_at' => now()->addDays(2)->format('Y-m-d\TH:i'),
        ])->assertSessionHasNoErrors();

        $news = SiteNews::query()->firstOrFail();
        $this->assertFalse($news->isVisible());
        $this->get('/fr/actualites')->assertDontSee('Parution plus tard');
        $this->get('/admin/actualites')->assertOk()->assertSee('Programmée');
    }

    public function test_validation_rules(): void
    {
        $this->actingAs($this->globalAdmin());

        $this->post('/admin/actualites', ['title' => ['en' => 'Only english'], 'status' => 'published'])
            ->assertSessionHasErrors('title.fr');

        $this->post('/admin/actualites', ['title' => ['fr' => 'X'], 'status' => 'bogus'])->assertSessionHasErrors('status');
        $this->post('/admin/actualites', ['title' => ['fr' => 'X'], 'status' => 'draft', 'scope' => 'inconnu'])->assertSessionHasErrors('scope');
        $this->post('/admin/actualites', ['title' => ['fr' => 'X'], 'status' => 'draft', 'cover' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')])->assertSessionHasErrors('cover');

        $this->assertDatabaseCount('site_news', 0);
    }

    public function test_admin_pages_render_and_filter(): void
    {
        $this->actingAs($this->globalAdmin());
        SiteNews::query()->create(['title' => ['fr' => 'Alpha publiée'], 'status' => 'published', 'published_at' => now()->subDay()]);
        $draft = SiteNews::query()->create(['title' => ['fr' => 'Beta brouillon'], 'status' => 'draft']);

        $this->get('/admin/actualites')->assertOk()->assertSee('Alpha publiée')->assertSee('Beta brouillon');
        $this->get('/admin/actualites?status=draft')->assertOk()->assertSee('Beta brouillon')->assertDontSee('Alpha publiée');
        $this->get('/admin/actualites?q=Alpha')->assertOk()->assertSee('Alpha publiée')->assertDontSee('Beta brouillon');
        $this->get('/admin/actualites/nouvelle')->assertOk();
        $this->get('/admin/actualites/'.$draft->id.'/modifier')->assertOk()->assertSee('Beta brouillon');
    }

    public function test_admin_content_is_escaped(): void
    {
        $this->actingAs($this->globalAdmin());
        SiteNews::query()->create(['title' => ['fr' => '<script>alert(1)</script>'], 'status' => 'draft']);

        $this->get('/admin/actualites')->assertOk()->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_scoped_admin_and_guest_cannot_manage_news(): void
    {
        $this->get('/admin/actualites')->assertRedirect();

        $user = User::factory()->create();
        $this->grantAccess($user, 'entity_admin', 'university');
        $this->actingAs($user);

        $this->get('/admin/actualites')->assertForbidden();
        $this->post('/admin/actualites', ['title' => ['fr' => 'Intrus'], 'status' => 'published'])->assertForbidden();
        $this->assertDatabaseCount('site_news', 0);
    }

    public function test_feed_publications_have_no_site_toggle_anymore(): void
    {
        $admin = $this->globalAdmin();
        $this->actingAs($admin);

        $post = Post::query()->create(['user_id' => $admin->id, 'type' => 'text', 'visibility' => 'public', 'content' => 'Publication du fil', 'published_at' => now()]);

        $this->patch('/admin/publications/'.$post->id.'/vitrine')->assertStatus(404);
        $this->get('/admin/publications')->assertOk()->assertSee('Publication du fil')->assertDontSee('Vitrine');
    }

    public function test_migration_imports_existing_site_posts_with_an_independent_cover(): void
    {
        Storage::fake('public');
        $author = User::factory()->create();

        $kept = Post::query()->create(['user_id' => $author->id, 'type' => 'text', 'visibility' => 'public', 'content' => "Titre repris\n\nLe corps.", 'is_site_news' => true, 'scope' => 'university', 'published_at' => now()->subDay()]);
        Post::query()->create(['user_id' => $author->id, 'type' => 'text', 'visibility' => 'public', 'content' => 'Non autorisée', 'is_site_news' => false, 'published_at' => now()->subDay()]);
        Post::query()->create(['user_id' => $author->id, 'type' => 'text', 'visibility' => 'authenticated', 'content' => 'Privée', 'is_site_news' => true, 'published_at' => now()->subDay()]);

        Storage::disk('public')->put('posts/images/a.jpg', 'image');
        \App\Models\PostAttachment::query()->create(['post_id' => $kept->id, 'disk' => 'public', 'path' => 'posts/images/a.jpg', 'mime_type' => 'image/jpeg']);

        \Illuminate\Support\Facades\Schema::dropIfExists('site_news');
        (require database_path('migrations/2026_10_31_100000_create_site_news_table.php'))->up();

        $this->assertDatabaseCount('site_news', 1);
        $news = SiteNews::query()->firstOrFail();
        $this->assertSame('Titre repris', $news->text('title', 'fr'));
        $this->assertSame("Titre repris\n\nLe corps.", $news->text('body', 'fr'));
        $this->assertSame('university', $news->scope);
        $this->assertSame('published', $news->status);
        $this->assertStringStartsWith('news/covers/', $news->cover_path);
        Storage::disk('public')->assertExists($news->cover_path);
        $this->assertNotSame('posts/images/a.jpg', $news->cover_path, 'la couverture est copiée, pas partagée avec le fil');
    }

    public function test_module_is_in_the_catalog_and_the_old_media_route_is_gone(): void
    {
        ModuleCatalog::sync();

        $this->assertDatabaseHas('app_modules', ['key' => 'website.news']);
        $this->get('/medias/1')->assertNotFound();
    }
}
