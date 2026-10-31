<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Models\OrgPerson;
use App\Models\Post;
use App\Models\Profile;
use App\Models\User;
use App\Support\Messenger\Messenger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Le badge « Profil vérifié » suit le membre partout : fil, profil, messagerie, recherche, bureau, menu. */
class VerifiedBadgeEverywhereTest extends TestCase
{
    use RefreshDatabase;

    private function verified(string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        Profile::query()->create(['user_id' => $user->id])->forceFill(['identity_verified_at' => now()])->save();

        return $user;
    }

    public function test_feed_payload_flags_verified_authors_commenters_and_repliers(): void
    {
        $author = $this->verified('Auteure Vérifiée');
        $plain = User::factory()->create(['name' => 'Membre Simple']);
        $post = Post::query()->create(['user_id' => $author->id, 'type' => 'text', 'visibility' => 'authenticated', 'content' => 'Bonjour', 'comments_enabled' => true, 'published_at' => now()]);
        $comment = Comment::query()->create(['post_id' => $post->id, 'user_id' => $plain->id, 'content' => 'Salut']);
        Comment::query()->create(['post_id' => $post->id, 'user_id' => $author->id, 'parent_id' => $comment->id, 'content' => 'Merci']);
        $plainPost = Post::query()->create(['user_id' => $plain->id, 'type' => 'text', 'visibility' => 'authenticated', 'content' => 'Autre', 'comments_enabled' => true, 'published_at' => now()->subMinute()]);

        $feed = collect($this->actingAs($plain)->get('/feed')->assertOk()->viewData('feedPayload'))->keyBy('id');

        $this->assertTrue($feed[$post->id]['author']['verified']);
        $this->assertFalse($feed[$plainPost->id]['author']['verified']);
        $this->assertFalse($feed[$post->id]['comments_preview'][0]['author_verified']);
        $this->assertTrue($feed[$post->id]['comments_preview'][0]['replies'][0]['author_verified']);
    }

    public function test_messenger_flags_verified_members_in_list_thread_search_and_messages(): void
    {
        $me = User::factory()->create(['name' => 'Moi Même']);
        $friend = $this->verified('Amine Vérifié');
        $other = User::factory()->create(['name' => 'Amine Ordinaire']);
        $withFriend = Messenger::direct($me->fresh(), $friend->fresh());
        $withOther = Messenger::direct($me->fresh(), $other->fresh());

        $this->actingAs($friend)->postJson(route('messenger.send', $withFriend), ['body' => 'Coucou'])->assertCreated();
        $this->actingAs($other)->postJson(route('messenger.send', $withOther), ['body' => 'Salut'])->assertCreated();

        $list = collect($this->actingAs($me)->getJson(route('messenger.conversations'))->assertOk()->json('conversations'))->keyBy('title');
        $this->assertTrue($list['Amine Vérifié']['verified']);
        $this->assertFalse($list['Amine Ordinaire']['verified']);

        $thread = $this->actingAs($me)->getJson(route('messenger.messages', $withFriend))->assertOk();
        $thread->assertJsonPath('conversation.verified', true)->assertJsonPath('messages.0.verified', true);
        $this->actingAs($me)->getJson(route('messenger.messages', $withOther))->assertJsonPath('conversation.verified', false)->assertJsonPath('messages.0.verified', false);

        $members = collect(Messenger::searchMembers($me, 'Amine'))->keyBy('name');
        $this->assertTrue($members['Amine Vérifié']['verified']);
        $this->assertFalse($members['Amine Ordinaire']['verified']);
    }

    public function test_search_flags_verified_members(): void
    {
        $member = $this->verified('Sana Vérifiée');
        $unit = OrgNode::query()->create(['relation_type' => 'hierarchy', 'kind' => 'unit', 'category' => 'direction', 'title' => 'Direction', 'sort_order' => 0]);
        $position = OrgNode::query()->create(['parent_id' => $unit->id, 'relation_type' => 'hierarchy', 'kind' => 'position', 'category' => 'direction', 'title' => 'Directrice', 'sort_order' => 0]);
        $person = OrgPerson::query()->create(['user_id' => $member->id, 'full_name' => 'Sana Vérifiée', 'job_title' => 'Directrice']);
        OrgAssignment::query()->create(['node_id' => $position->id, 'person_id' => $person->id]);

        $viewer = User::factory()->create();
        $items = $this->actingAs($viewer)->getJson('/recherche/suggestions?q=sana')->assertOk()->json('groups.0.items');
        $this->assertTrue($items[0]['verified']);

        $this->get('/recherche?q=sana')->assertOk()->assertSee('Profil vérifié');
    }

    public function test_profile_pages_show_the_badge_only_for_verified_members(): void
    {
        $verified = $this->verified('Salma Vérifiée');
        $viewer = User::factory()->create();

        $this->actingAs($viewer)->get(route('member.show', $verified))->assertOk()->assertSee('Profil vérifié');
        $this->actingAs($verified)->get('/profil')->assertOk()->assertSee('title="Profil vérifié"', false);
        $this->actingAs($viewer)->get('/profil')->assertOk()->assertDontSee('title="Profil vérifié"', false);
    }
}
