<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Les actions du fil (réaction, commentaire, publication…) se font sans recharger la page : réponses JSON et actualisation en direct. */
class FeedRealtimeTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(User $author, string $content = 'Bonjour'): Post
    {
        return Post::query()->create([
            'user_id' => $author->id, 'type' => 'text', 'visibility' => 'authenticated', 'sentiment' => 'neutral',
            'content' => $content, 'published_at' => now(),
        ]);
    }

    public function test_a_reaction_returns_the_updated_post_and_can_be_toggled_off(): void
    {
        $author = User::factory()->create();
        $member = User::factory()->create();
        $post = $this->makePost($author);

        $this->actingAs($member)->postJson("/posts/{$post->id}/react", ['reaction_type' => 'like'])
            ->assertOk()
            ->assertJsonPath('post.my_reaction', 'like')
            ->assertJsonPath('post.counts.reactions', 1)
            ->assertJsonPath('post.reactions.like', 1);

        $this->actingAs($member)->postJson("/posts/{$post->id}/react", ['reaction_type' => 'celebrate'])
            ->assertJsonPath('post.my_reaction', 'celebrate')
            ->assertJsonPath('post.counts.reactions', 1)
            ->assertJsonMissingPath('post.reactions.like');

        $this->actingAs($member)->postJson("/posts/{$post->id}/react", ['reaction_type' => 'celebrate'])
            ->assertJsonPath('post.my_reaction', null)
            ->assertJsonPath('post.counts.reactions', 0);
    }

    public function test_a_comment_and_a_reply_appear_in_the_returned_post(): void
    {
        $author = User::factory()->create();
        $member = User::factory()->create();
        $post = $this->makePost($author);

        $first = $this->actingAs($member)->postJson("/posts/{$post->id}/comment", ['content' => 'Premier !'])
            ->assertCreated()
            ->assertJsonPath('post.counts.comments', 1)
            ->assertJsonPath('post.comments_preview.0.content', 'Premier !');

        $parent = $first->json('post.comments_preview.0.id');

        $this->actingAs($author)->postJson("/posts/{$post->id}/comment", ['content' => 'Merci', 'parent_id' => $parent])
            ->assertCreated()
            ->assertJsonPath('post.comments_preview.0.replies.0.content', 'Merci');
    }

    public function test_the_latest_comments_stay_visible_beyond_five(): void
    {
        $author = User::factory()->create();
        $post = $this->makePost($author);

        foreach (range(1, 7) as $number) {
            $this->actingAs($author)->postJson("/posts/{$post->id}/comment", ['content' => "Commentaire {$number}"])->assertCreated();
        }

        $this->actingAs($author)->getJson('/feed/posts')->assertOk()
            ->assertJsonCount(5, 'posts.0.comments_preview')
            ->assertJsonPath('posts.0.comments_preview.4.content', 'Commentaire 7');
    }

    public function test_comments_on_a_closed_post_are_refused_in_json(): void
    {
        $author = User::factory()->create();
        $post = $this->makePost($author);
        $post->update(['comments_enabled' => false]);

        $this->actingAs($author)->postJson("/posts/{$post->id}/comment", ['content' => 'Test'])->assertStatus(422);
    }

    public function test_publishing_returns_the_new_post_and_the_form_still_works_without_json(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)->postJson('/posts', ['content' => 'Nouvelle publication', 'visibility' => 'authenticated'])
            ->assertCreated()
            ->assertJsonPath('post.content', 'Nouvelle publication')
            ->assertJsonPath('post.can_manage', true);

        $this->actingAs($member)->postJson('/posts', ['content' => '', 'visibility' => 'authenticated'])->assertStatus(422);

        $this->actingAs($member)->post('/posts', ['content' => 'Sans JavaScript', 'visibility' => 'authenticated'])->assertRedirect();
    }

    public function test_editing_and_deleting_a_post_answer_in_json(): void
    {
        $author = User::factory()->create();
        $post = $this->makePost($author);

        $this->actingAs($author)->patchJson("/posts/{$post->id}", ['content' => 'Modifié'])->assertOk()->assertJsonPath('post.content', 'Modifié');
        $this->actingAs($author)->patchJson("/posts/{$post->id}/comments-state")->assertOk()->assertJsonPath('post.comments_enabled', false);
        $this->actingAs(User::factory()->create())->deleteJson("/posts/{$post->id}")->assertForbidden();
        $this->actingAs($author)->deleteJson("/posts/{$post->id}")->assertOk()->assertJsonPath('deleted', $post->id);

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }

    public function test_sharing_and_viewing_update_the_counters(): void
    {
        $author = User::factory()->create();
        $member = User::factory()->create();
        $post = $this->makePost($author);

        $this->actingAs($member)->postJson("/posts/{$post->id}/share", ['channel' => 'internal'])->assertOk()->assertJsonPath('post.counts.shares', 1);
        $this->actingAs($member)->postJson("/posts/{$post->id}/view")->assertOk()->assertJsonPath('post.counts.views', 1);
    }

    public function test_the_live_feed_returns_the_latest_posts_with_my_reaction(): void
    {
        $author = User::factory()->create();
        $member = User::factory()->create();
        $old = $this->makePost($author, 'Ancien');
        $old->update(['published_at' => now()->subDay()]);
        $new = $this->makePost($author, 'Récent');

        $this->actingAs($member)->postJson("/posts/{$new->id}/react", ['reaction_type' => 'support'])->assertOk();

        $this->actingAs($member)->getJson('/feed/posts')->assertOk()
            ->assertJsonPath('posts.0.content', 'Récent')
            ->assertJsonPath('posts.0.my_reaction', 'support')
            ->assertJsonPath('posts.1.content', 'Ancien')
            ->assertJsonPath('posts.1.my_reaction', null);
    }

    public function test_the_live_feed_requires_a_signed_in_member(): void
    {
        $this->getJson('/feed/posts')->assertUnauthorized();
    }

    public function test_the_feed_page_embeds_my_reaction_and_the_messenger_fills_the_screen(): void
    {
        $author = User::factory()->create();
        $post = $this->makePost($author);
        $this->actingAs($author)->postJson("/posts/{$post->id}/react", ['reaction_type' => 'like'])->assertOk();

        $this->actingAs($author)->get('/feed')->assertOk()->assertSee('"my_reaction":"like"', false);
        $this->actingAs($author)->get('/messagerie')->assertOk()->assertSee('mg-shell', false);
    }
}
