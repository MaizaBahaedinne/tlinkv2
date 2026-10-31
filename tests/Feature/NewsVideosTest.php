<?php

namespace Tests\Feature;

use App\Models\SiteNews;
use App\Models\User;
use App\Support\VideoEmbed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class NewsVideosTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    /** @return array<string, array{0: string, 1: string, 2: ?string}> */
    public static function supportedLinks(): array
    {
        return [
            'youtube watch' => ['https://www.youtube.com/watch?v=QwrBWK07RnU', 'youtube', 'https://www.youtube-nocookie.com/embed/QwrBWK07RnU'],
            'youtube court' => ['https://youtu.be/QwrBWK07RnU?t=3', 'youtube', 'https://www.youtube-nocookie.com/embed/QwrBWK07RnU'],
            'youtube shorts' => ['https://youtube.com/shorts/QwrBWK07RnU', 'youtube', 'https://www.youtube-nocookie.com/embed/QwrBWK07RnU'],
            'vimeo' => ['https://vimeo.com/123456789', 'vimeo', 'https://player.vimeo.com/video/123456789'],
            'dailymotion' => ['https://www.dailymotion.com/video/x8abcde', 'dailymotion', 'https://www.dailymotion.com/embed/video/x8abcde'],
            'facebook video' => ['https://www.facebook.com/tunivisions/videos/123456789012345/', 'facebook', null],
            'facebook reel' => ['https://www.facebook.com/reel/123456789012345', 'facebook', null],
            'facebook watch court' => ['https://fb.watch/abcDEF123/', 'facebook', null],
            'instagram reel' => ['https://www.instagram.com/reel/C1a2B3c4D5e/?igsh=abc', 'instagram', 'https://www.instagram.com/reel/C1a2B3c4D5e/embed/'],
            'instagram post' => ['https://www.instagram.com/p/C1a2B3c4D5e/', 'instagram', 'https://www.instagram.com/p/C1a2B3c4D5e/embed/'],
            'tiktok' => ['https://www.tiktok.com/@tunivisions/video/7345678901234567890', 'tiktok', 'https://www.tiktok.com/embed/v2/7345678901234567890'],
            'tiktok lien court' => ['https://vm.tiktok.com/ZMabc123/', 'tiktok', null],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('supportedLinks')]
    public function test_supported_links_are_recognised(string $url, string $platform, ?string $embed): void
    {
        $parsed = VideoEmbed::parse($url);

        $this->assertNotNull($parsed);
        $this->assertSame($platform, $parsed['platform']);

        if ($embed !== null) {
            $this->assertSame($embed, $parsed['embed']);
        } elseif ($platform === 'facebook') {
            $this->assertStringStartsWith('https://www.facebook.com/plugins/video.php?href='.rawurlencode($url), (string) $parsed['embed']);
        } else {
            $this->assertNull($parsed['embed'], 'un lien court TikTok ne s\'intègre pas, il s\'ouvre sur la plateforme');
        }
    }

    /** @return array<string, array{0: string}> */
    public static function rejectedLinks(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'http simple' => ['http://www.youtube.com/watch?v=QwrBWK07RnU'],
            'autre domaine' => ['https://evil.example.com/watch?v=QwrBWK07RnU'],
            'domaine piège' => ['https://youtube.com.evil.example/watch?v=QwrBWK07RnU'],
            'identifiant invalide' => ['https://www.youtube.com/watch?v=<script>'],
            'identifiants dans l\'url' => ['https://user:pass@www.youtube.com/watch?v=QwrBWK07RnU'],
            'guillemet' => ['https://www.youtube.com/watch?v=QwrBWK07RnU"onload="x'],
            'page facebook' => ['https://www.facebook.com/tunivisions'],
            'profil instagram' => ['https://www.instagram.com/tunivisions/'],
            'profil tiktok' => ['https://www.tiktok.com/@tunivisions'],
            'vide' => [''],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('rejectedLinks')]
    public function test_unsafe_or_unknown_links_are_rejected(string $url): void
    {
        $this->assertNull(VideoEmbed::parse($url));
    }

    public function test_admin_saves_videos_and_public_page_embeds_them(): void
    {
        $admin = User::factory()->create();
        $this->grantPlatformAdmin($admin);
        $this->actingAs($admin);

        $this->post('/admin/actualites', [
            'title' => ['fr' => 'Reportage'],
            'status' => 'published',
            'videos' => [
                ['url' => 'https://www.youtube.com/watch?v=QwrBWK07RnU', 'title' => 'Le résumé'],
                ['url' => '', 'title' => ''],
                ['url' => 'https://www.instagram.com/reel/C1a2B3c4D5e/', 'title' => ''],
                ['url' => 'https://www.tiktok.com/@tunivisions/video/7345678901234567890', 'title' => ''],
                ['url' => 'https://www.facebook.com/tunivisions/videos/123456789012345/', 'title' => ''],
            ],
        ])->assertSessionHasNoErrors();

        $news = SiteNews::query()->firstOrFail();
        $this->assertCount(4, $news->videos, 'les lignes vides sont ignorées');

        $this->get('/fr/actualites/'.$news->id)
            ->assertOk()
            ->assertSee('https://www.youtube-nocookie.com/embed/QwrBWK07RnU', false)
            ->assertSee('Le résumé')
            ->assertSee('https://www.instagram.com/reel/C1a2B3c4D5e/embed/', false)
            ->assertSee('https://www.tiktok.com/embed/v2/7345678901234567890', false)
            ->assertSee('https://www.facebook.com/plugins/video.php?href=', false)
            ->assertSee('Voir sur TikTok');

        $this->get('/en/actualites/'.$news->id)->assertOk()->assertSee('Watch on YouTube');
    }

    public function test_admin_rejects_unknown_video_links(): void
    {
        $admin = User::factory()->create();
        $this->grantPlatformAdmin($admin);
        $this->actingAs($admin);

        $this->post('/admin/actualites', [
            'title' => ['fr' => 'Reportage'],
            'status' => 'published',
            'videos' => [['url' => 'https://evil.example.com/video.mp4', 'title' => '']],
        ])->assertSessionHasErrors('videos');

        $this->assertSame(0, SiteNews::query()->count());
    }

    public function test_unsafe_urls_already_stored_are_never_rendered(): void
    {
        $news = SiteNews::query()->create([
            'title' => ['fr' => 'Ancien'],
            'status' => 'published',
            'published_at' => now()->subDay(),
            'videos' => [['url' => 'javascript:alert(1)', 'title' => ''], ['url' => 'https://evil.example.com/x', 'title' => '']],
        ]);

        $this->get('/fr/actualites/'.$news->id)
            ->assertOk()
            ->assertDontSee('javascript:alert', false)
            ->assertDontSee('evil.example.com', false)
            ->assertDontSee('<iframe', false);
    }

    public function test_admin_form_lists_saved_videos(): void
    {
        $admin = User::factory()->create();
        $this->grantPlatformAdmin($admin);
        $this->actingAs($admin);
        $news = SiteNews::query()->create(['title' => ['fr' => 'A'], 'status' => 'draft', 'videos' => [['url' => 'https://youtu.be/QwrBWK07RnU', 'title' => 'Teaser']]]);

        $this->get('/admin/actualites/'.$news->id.'/modifier')->assertOk()
            ->assertSee('https://youtu.be/QwrBWK07RnU', false)->assertSee('Teaser')->assertSee('add-video', false);
        $this->get('/admin/actualites/nouvelle')->assertOk()->assertSee('Ajouter une vidéo');
    }

    public function test_article_without_video_shows_no_video_section(): void
    {
        $news = SiteNews::query()->create(['title' => ['fr' => 'Sans vidéo'], 'status' => 'published', 'published_at' => now()->subDay()]);

        $this->get('/fr/actualites/'.$news->id)->assertOk()->assertDontSee('videos-title', false);
    }
}
