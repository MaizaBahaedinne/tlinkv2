<?php

namespace Tests\Feature;

use App\Models\Story;
use App\Models\StoryView;
use App\Models\User;
use App\Support\Stories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class StoriesTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function publish(User $user, array $attributes = []): Story
    {
        Storage::disk('local')->put('stories/'.$user->id.'/a.jpg', 'x');

        return Story::query()->create($attributes + [
            'user_id' => $user->id,
            'kind' => 'image',
            'path' => 'stories/'.$user->id.'/a.jpg',
            'mime_type' => 'image/jpeg',
            'expires_at' => now()->addHours(24),
        ]);
    }

    public function test_a_member_publishes_a_photo_story_valid_for_24_hours(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/stories', ['media' => UploadedFile::fake()->image('a.jpg'), 'caption' => 'Bonjour'])
            ->assertCreated();

        $story = Story::query()->firstOrFail();
        $this->assertSame('image', $story->kind);
        $this->assertSame('Bonjour', $story->caption);
        $this->assertEqualsWithDelta(now()->addHours(24)->timestamp, $story->expires_at->timestamp, 5);
        Storage::disk('local')->assertExists($story->path);
    }

    public function test_a_member_publishes_a_video_story_and_rejects_other_files(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/stories', ['media' => UploadedFile::fake()->create('v.mp4', 500, 'video/mp4')])
            ->assertCreated();
        $this->assertSame('video', Story::query()->firstOrFail()->kind);

        $this->actingAs($user)
            ->postJson('/stories', ['media' => UploadedFile::fake()->create('doc.pdf', 50, 'application/pdf')])
            ->assertUnprocessable();
    }

    public function test_the_number_of_active_stories_is_limited(): void
    {
        $user = User::factory()->create();
        foreach (range(1, Stories::MAX_ACTIVE_PER_USER) as $i) {
            $this->publish($user);
        }

        $this->actingAs($user)
            ->postJson('/stories', ['media' => UploadedFile::fake()->image('b.jpg')])
            ->assertUnprocessable();
    }

    public function test_guests_cannot_publish_or_read_stories(): void
    {
        $story = $this->publish(User::factory()->create());

        $this->postJson('/stories', [])->assertUnauthorized();
        $this->get(route('stories.media', $story))->assertRedirect();
    }

    public function test_the_feed_lists_stories_with_my_own_first_and_unseen_state(): void
    {
        $me = User::factory()->create();
        $author = User::factory()->create();
        $this->publish($author);
        $this->publish($me);

        $groups = $this->actingAs($me)->get('/feed')->assertOk()->viewData('stories');

        $this->assertSame([$me->id, $author->id], collect($groups)->pluck('user.id')->all());
        $this->assertFalse($groups[1]['items'][0]['seen']);
    }

    public function test_viewing_a_story_is_recorded_once_and_the_owner_is_not_counted(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $story = $this->publish($author);

        $this->actingAs($viewer)->postJson(route('stories.view', $story))->assertOk();
        $this->actingAs($viewer)->postJson(route('stories.view', $story))->assertOk();
        $this->actingAs($author)->postJson(route('stories.view', $story))->assertOk();

        $this->assertSame(1, StoryView::query()->count());

        $groups = $this->actingAs($viewer)->get('/feed')->viewData('stories');
        $this->assertTrue($groups[0]['items'][0]['seen']);
    }

    public function test_only_the_owner_can_delete_a_story(): void
    {
        $author = User::factory()->create();
        $story = $this->publish($author);

        $this->actingAs(User::factory()->create())->deleteJson(route('stories.destroy', $story))->assertForbidden();
        $this->assertSame(1, Story::query()->count());

        $this->actingAs($author)->deleteJson(route('stories.destroy', $story))->assertOk();
        $this->assertSame(0, Story::query()->count());
        Storage::disk('local')->assertMissing('stories/'.$author->id.'/a.jpg');
    }

    public function test_expired_stories_are_hidden_and_purged(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $story = $this->publish($author, ['expires_at' => now()->subMinute()]);

        $this->actingAs($viewer)->get(route('stories.media', $story))->assertNotFound();
        $this->assertSame([], $this->actingAs($viewer)->get('/feed')->viewData('stories'));

        $this->artisan('stories:purge')->assertSuccessful();
        $this->assertSame(0, Story::query()->count());
        Storage::disk('local')->assertMissing('stories/'.$author->id.'/a.jpg');
    }

    public function test_the_media_of_an_active_story_is_served_to_members(): void
    {
        $story = $this->publish(User::factory()->create());

        $this->actingAs(User::factory()->create())->get(route('stories.media', $story))->assertOk();
    }

    public function test_the_module_can_be_switched_off(): void
    {
        \App\Models\AppModule::query()->where('key', Stories::MODULE)->update(['is_active' => false]);
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/stories', ['media' => UploadedFile::fake()->image('a.jpg')])->assertNotFound();
        $this->assertFalse($this->actingAs($user)->get('/feed')->viewData('storiesEnabled'));
    }

    public function test_the_sidebar_menu_replaces_mission_and_lists_the_spaces(): void
    {
        $member = User::factory()->create();
        $keys = collect($this->actingAs($member)->get('/feed')->assertOk()->viewData('menu'));

        $this->assertContains('challenges', $keys->pluck('key')->all());
        $this->assertContains('academy', $keys->pluck('key')->all());
        $this->assertNotContains('admin', $keys->pluck('key')->all());
        $this->assertNotContains('Mission', $keys->pluck('label')->all());

        $admin = User::factory()->create();
        $this->grantPlatformAdmin($admin);
        $adminKeys = collect($this->actingAs($admin)->get('/feed')->viewData('menu'))->pluck('key')->all();
        $this->assertContains('admin', $adminKeys);
    }
}
