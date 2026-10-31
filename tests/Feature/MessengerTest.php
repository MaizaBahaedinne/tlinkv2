<?php

namespace Tests\Feature;

use App\Models\AppModule;
use App\Models\MessengerConversation;
use App\Models\MessengerMessage;
use App\Models\User;
use App\Support\Messenger\Messenger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MessengerTest extends TestCase
{
    use RefreshDatabase;

    private function directBetween(User $a, User $b): MessengerConversation
    {
        // `is_active` vient de la valeur par défaut en base : on recharge les comptes créés par la fabrique.
        return Messenger::direct($a->fresh(), $b->fresh());
    }

    public function test_guests_are_redirected_and_the_module_can_be_switched_off(): void
    {
        $this->get('/messagerie')->assertRedirect('/connexion');
        $this->get('/messagerie/messages/1/image')->assertRedirect('/connexion');

        $user = User::factory()->create();
        $this->actingAs($user)->get('/messagerie')->assertOk()->assertSee('T.Link Messenger')->assertSee('Nouveau');

        AppModule::query()->where('key', 'messenger.chat')->update(['is_active' => false]);
        $this->actingAs($user)->get('/messagerie')->assertNotFound();
        $this->actingAs($user)->get('/messagerie/non-lus')->assertOk()->assertJson(['unread' => 0]);
    }

    public function test_direct_conversation_is_unique_per_pair(): void
    {
        [$alice, $bob] = User::factory()->count(2)->create();

        $this->actingAs($alice)->post(route('messenger.direct', $bob))->assertRedirect();
        $this->actingAs($bob)->post(route('messenger.direct', $alice))->assertRedirect();

        $this->assertSame(1, MessengerConversation::query()->count());
        $conversation = MessengerConversation::query()->firstOrFail();
        $this->assertSame(2, $conversation->participants()->count());
        $this->actingAs($alice)->post(route('messenger.direct', $alice))->assertRedirect(route('messenger.index'));
        $this->assertSame(1, MessengerConversation::query()->count());
    }

    public function test_messages_flow_in_real_time_with_unread_counts_and_read_receipts(): void
    {
        [$alice, $bob] = User::factory()->count(2)->create();
        $conversation = $this->directBetween($alice, $bob);

        $this->actingAs($alice)->postJson(route('messenger.send', $conversation), ['body' => '  Bonjour Bob  '])
            ->assertCreated()->assertJsonPath('message.body', 'Bonjour Bob')->assertJsonPath('message.mine', true);
        $firstId = MessengerMessage::query()->firstOrFail()->id;

        // Bob : pastille, liste et message non lus.
        $this->actingAs($bob)->getJson(route('messenger.unread'))->assertJson(['unread' => 1]);
        $list = $this->actingAs($bob)->getJson(route('messenger.conversations'))->assertOk()->json('conversations');
        $this->assertSame($alice->name, $list[0]['title']);
        $this->assertSame(1, $list[0]['unread']);
        $this->assertSame('Bonjour Bob', $list[0]['preview']);

        $initial = $this->actingAs($bob)->getJson(route('messenger.messages', $conversation))->assertOk()->json();
        $this->assertCount(1, $initial['messages']);
        $this->assertFalse($initial['messages'][0]['mine']);
        $this->assertSame($alice->name, $initial['conversation']['title']);

        // Alice ne voit pas encore de lecture.
        $this->actingAs($alice)->getJson(route('messenger.messages', ['conversation' => $conversation->id, 'after' => $firstId]))
            ->assertJsonCount(0, 'messages')->assertJsonPath('reads.'.$bob->id, 0);

        // Bob marque comme lu : pastille à zéro, accusé de lecture côté Alice.
        $this->actingAs($bob)->postJson(route('messenger.read', $conversation), ['up_to' => $firstId])->assertJson(['unread' => 0]);
        $this->actingAs($alice)->getJson(route('messenger.messages', ['conversation' => $conversation->id, 'after' => $firstId]))
            ->assertJsonPath('reads.'.$bob->id, $firstId);

        // Réponse de Bob : seuls les nouveaux messages sont renvoyés.
        $this->actingAs($bob)->postJson(route('messenger.send', $conversation), ['body' => 'Salut Alice'])->assertCreated();
        $poll = $this->actingAs($alice)->getJson(route('messenger.messages', ['conversation' => $conversation->id, 'after' => $firstId]))->json();
        $this->assertCount(1, $poll['messages']);
        $this->assertSame('Salut Alice', $poll['messages'][0]['body']);
        $this->assertArrayNotHasKey('conversation', $poll);
    }

    public function test_message_validation(): void
    {
        Storage::fake('local');
        [$alice, $bob] = User::factory()->count(2)->create();
        $conversation = $this->directBetween($alice, $bob);
        $url = route('messenger.send', $conversation);

        $this->actingAs($alice)->postJson($url, ['body' => '   '])->assertStatus(422)->assertJsonValidationErrors('body');
        $this->actingAs($alice)->postJson($url, ['body' => str_repeat('a', Messenger::MAX_BODY + 1)])->assertStatus(422)->assertJsonValidationErrors('body');
        $this->actingAs($alice)->postJson($url, ['image' => UploadedFile::fake()->create('virus.pdf', 10, 'application/pdf')])->assertStatus(422)->assertJsonValidationErrors('image');
        $this->assertSame(0, MessengerMessage::query()->count());
    }

    public function test_outsiders_cannot_read_write_or_fetch_images(): void
    {
        Storage::fake('local');
        [$alice, $bob, $eve] = User::factory()->count(3)->create();
        $conversation = $this->directBetween($alice, $bob);
        $this->actingAs($alice)->postJson(route('messenger.send', $conversation), ['image' => UploadedFile::fake()->image('photo.jpg')])->assertCreated();
        $message = MessengerMessage::query()->firstOrFail();

        $this->actingAs($eve)->get(route('messenger.show', $conversation))->assertForbidden();
        $this->actingAs($eve)->getJson(route('messenger.messages', $conversation))->assertForbidden();
        $this->actingAs($eve)->postJson(route('messenger.send', $conversation), ['body' => 'intrus'])->assertForbidden();
        $this->actingAs($eve)->postJson(route('messenger.read', $conversation), ['up_to' => 1])->assertForbidden();
        $this->actingAs($eve)->get(route('messenger.image', $message))->assertForbidden();
        $this->actingAs($eve)->deleteJson(route('messenger.messages.destroy', $message))->assertForbidden();

        $this->actingAs($bob)->get(route('messenger.image', $message))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_image_attachment_is_stored_privately(): void
    {
        Storage::fake('local');
        [$alice, $bob] = User::factory()->count(2)->create();
        $conversation = $this->directBetween($alice, $bob);

        $response = $this->actingAs($alice)->postJson(route('messenger.send', $conversation), ['body' => 'Regarde', 'image' => UploadedFile::fake()->image('photo.png')])->assertCreated();
        $message = MessengerMessage::query()->firstOrFail();

        Storage::disk('local')->assertExists($message->attachment_path);
        $this->assertStringStartsWith('messenger/'.$conversation->id.'/', $message->attachment_path);
        $this->assertNotNull($response->json('message.image'));
    }

    public function test_author_deletes_own_message_and_peers_see_the_deletion(): void
    {
        Storage::fake('local');
        [$alice, $bob] = User::factory()->count(2)->create();
        $conversation = $this->directBetween($alice, $bob);
        $this->actingAs($alice)->postJson(route('messenger.send', $conversation), ['body' => 'À effacer', 'image' => UploadedFile::fake()->image('p.jpg')]);
        $message = MessengerMessage::query()->firstOrFail();
        $path = $message->attachment_path;

        $this->actingAs($bob)->deleteJson(route('messenger.messages.destroy', $message))->assertForbidden();
        $this->actingAs($alice)->deleteJson(route('messenger.messages.destroy', $message))->assertOk();

        $message->refresh();
        $this->assertNotNull($message->deleted_at);
        $this->assertNull($message->body);
        Storage::disk('local')->assertMissing($path);

        $poll = $this->actingAs($bob)->getJson(route('messenger.messages', ['conversation' => $conversation->id, 'after' => $message->id, 'since' => time() - 5]))->json();
        $this->assertSame([$message->id], $poll['deleted_ids']);

        $initial = $this->actingAs($bob)->getJson(route('messenger.messages', $conversation))->json();
        $this->assertTrue($initial['messages'][0]['deleted']);
        $this->assertNull($initial['messages'][0]['body']);
        $this->assertSame(0, Messenger::unreadCount($bob));
    }

    public function test_history_is_paginated(): void
    {
        [$alice, $bob] = User::factory()->count(2)->create();
        $conversation = $this->directBetween($alice, $bob);
        foreach (range(1, 60) as $n) {
            Messenger::send($conversation, $alice, 'Message '.$n);
        }

        $page = $this->actingAs($bob)->getJson(route('messenger.messages', $conversation))->json();
        $this->assertCount(Messenger::PAGE, $page['messages']);
        $this->assertTrue($page['has_more']);
        $this->assertSame('Message 60', end($page['messages'])['body']);

        $older = $this->actingAs($bob)->getJson(route('messenger.messages', ['conversation' => $conversation->id, 'before' => $page['messages'][0]['id']]))->json();
        $this->assertCount(10, $older['messages']);
        $this->assertFalse($older['has_more']);
        $this->assertSame('Message 1', $older['messages'][0]['body']);
    }

    public function test_typing_indicator_expires_and_is_hidden_from_the_typist(): void
    {
        Cache::flush();
        [$alice, $bob] = User::factory()->count(2)->create();
        $conversation = $this->directBetween($alice, $bob);

        $this->actingAs($alice)->postJson(route('messenger.typing', $conversation))->assertOk();

        $this->actingAs($bob)->getJson(route('messenger.messages', $conversation))->assertJsonPath('typing', [$alice->name]);
        $this->actingAs($alice)->getJson(route('messenger.messages', $conversation))->assertJsonPath('typing', []);

        // L'envoi du message efface l'indicateur.
        $this->actingAs($alice)->postJson(route('messenger.send', $conversation), ['body' => 'ok']);
        $this->actingAs($bob)->getJson(route('messenger.messages', $conversation))->assertJsonPath('typing', []);
    }

    public function test_group_conversations(): void
    {
        [$alice, $bob, $carol, $dave] = User::factory()->count(4)->create();

        $this->actingAs($alice)->post(route('messenger.groups.store'), ['title' => 'Bureau', 'members' => [$bob->id]])->assertRedirect();
        $this->actingAs($alice)->post(route('messenger.groups.store'), ['title' => 'Sans membre', 'members' => []])->assertSessionHasErrors('members');
        $this->actingAs($alice)->post(route('messenger.groups.store'), ['title' => '', 'members' => [$bob->id]])->assertSessionHasErrors('title');
        $this->actingAs($alice)->post(route('messenger.groups.store'), ['title' => 'Seule', 'members' => [$alice->id]])->assertSessionHasErrors('members');

        $this->actingAs($alice)->post(route('messenger.groups.store'), ['title' => 'Équipe', 'members' => [$bob->id, $carol->id, $carol->id]])->assertRedirect();
        $group = MessengerConversation::query()->where('title', 'Équipe')->firstOrFail();
        $this->assertSame(3, $group->participants()->count());

        $this->actingAs($carol)->postJson(route('messenger.send', $group), ['body' => 'Hello tous'])->assertCreated();
        $this->assertSame(1, Messenger::unreadCount($alice));
        $this->assertSame(1, Messenger::unreadCount($bob));
        $this->assertSame(0, Messenger::unreadCount($carol));

        $payload = $this->actingAs($bob)->getJson(route('messenger.messages', $group))->json();
        $this->assertSame('group', $payload['conversation']['type']);
        $this->assertSame('Équipe', $payload['conversation']['title']);
        $this->assertCount(3, $payload['conversation']['members']);

        $this->actingAs($dave)->getJson(route('messenger.messages', $group))->assertForbidden();

        // Quitter le groupe : plus d'accès, plus de messages non lus.
        $this->actingAs($bob)->post(route('messenger.leave', $group))->assertRedirect(route('messenger.index'));
        $this->actingAs($bob)->getJson(route('messenger.messages', $group))->assertForbidden();
        $this->assertSame(0, Messenger::unreadCount($bob));
    }

    public function test_leave_is_reserved_to_groups(): void
    {
        [$alice, $bob] = User::factory()->count(2)->create();
        $conversation = $this->directBetween($alice, $bob);

        $this->actingAs($alice)->post(route('messenger.leave', $conversation))->assertStatus(422);
    }

    public function test_blocking_prevents_new_and_existing_conversations(): void
    {
        [$alice, $bob] = User::factory()->count(2)->create();
        $conversation = $this->directBetween($alice, $bob);

        $this->actingAs($alice)->post(route('messenger.block', $bob))->assertRedirect();

        $this->actingAs($bob)->postJson(route('messenger.send', $conversation), ['body' => 'Hé'])->assertStatus(422);
        $this->actingAs($alice)->postJson(route('messenger.send', $conversation), ['body' => 'Hé'])->assertStatus(422)
            ->assertJsonPath('message', 'Vous avez bloqué ce membre. Débloquez-le pour lui écrire.');
        $this->assertSame(0, MessengerMessage::query()->count());

        $this->actingAs($bob)->getJson(route('messenger.messages', $conversation))->assertJsonPath('conversation.restriction', 'Vous ne pouvez pas écrire à ce membre.');
        $this->assertSame([], Messenger::searchMembers($bob, substr($alice->name, 0, 4)));

        $carol = User::factory()->create();
        $this->actingAs($bob)->post(route('messenger.groups.store'), ['title' => 'G', 'members' => [$alice->id]])->assertSessionHasErrors('members');

        $this->actingAs($alice)->delete(route('messenger.unblock', $bob))->assertRedirect();
        $this->actingAs($bob)->postJson(route('messenger.send', $conversation), ['body' => 'Merci'])->assertCreated();

        // Un blocage empêche aussi d'ouvrir une nouvelle conversation.
        $this->actingAs($carol)->post(route('messenger.block', $alice));
        $this->actingAs($alice)->post(route('messenger.direct', $carol))->assertRedirect(route('messenger.index'));
        $this->assertSame(1, MessengerConversation::query()->count());
    }

    public function test_member_search_hides_contact_details_and_inactive_accounts(): void
    {
        $me = User::factory()->create(['name' => 'Moi Même']);
        User::factory()->create(['name' => 'Sami Trabelsi', 'email' => 'sami@example.com']);
        User::factory()->create(['name' => 'Sami Inactif', 'is_active' => false]);
        User::factory()->create(['name' => 'Sami Ancien', 'email' => 'ancien@legacy.invalid']);

        $json = $this->actingAs($me)->getJson(route('messenger.members', ['q' => 'sami']))->assertOk()->json('members');

        $this->assertCount(1, $json);
        $this->assertSame('Sami Trabelsi', $json[0]['name']);
        $this->assertSame(['id', 'name', 'avatar', 'verified'], array_keys($json[0]));
        $this->actingAs($me)->getJson(route('messenger.members', ['q' => 's']))->assertJson(['members' => []]);
    }

    public function test_empty_conversations_stay_hidden_from_the_recipient(): void
    {
        [$alice, $bob] = User::factory()->count(2)->create();
        $this->directBetween($alice, $bob);

        $this->assertCount(1, $this->actingAs($alice)->getJson(route('messenger.conversations'))->json('conversations'));
        $this->assertCount(0, $this->actingAs($bob)->getJson(route('messenger.conversations'))->json('conversations'));
    }

    public function test_header_badge_and_profile_button(): void
    {
        [$alice, $bob] = User::factory()->count(2)->create();
        $conversation = $this->directBetween($alice, $bob);
        Messenger::send($conversation, $alice, 'Salut');

        $this->actingAs($bob)->get('/notifications')->assertOk()->assertSee('id="nav-messages"', false)->assertSee('nav-messages-badge', false);
        $this->actingAs($alice)->get(route('member.show', $bob))->assertOk()->assertSee('Envoyer un message');

        AppModule::query()->where('key', 'messenger.chat')->update(['is_active' => false]);
        $this->actingAs($bob)->get('/notifications')->assertOk()->assertDontSee('id="nav-messages"', false);
        $this->actingAs($alice)->get(route('member.show', $bob))->assertOk()->assertDontSee('Envoyer un message');
    }

    public function test_floating_dock_is_on_regular_pages_but_not_on_the_messenger_page_or_when_disabled(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/notifications')->assertOk()->assertSee('id="mg-dock"', false)->assertSee('data-url-direct', false);
        $this->actingAs($user)->get('/messagerie')->assertOk()->assertDontSee('id="mg-dock"', false);

        AppModule::query()->where('key', 'messenger.chat')->update(['is_active' => false]);
        $this->actingAs($user)->get('/notifications')->assertOk()->assertDontSee('id="mg-dock"', false);
    }

    public function test_direct_conversation_can_be_opened_as_json_for_the_dock(): void
    {
        [$alice, $bob] = User::factory()->count(2)->create();
        $bob = $bob->fresh();

        $id = $this->actingAs($alice->fresh())->postJson(route('messenger.direct', $bob))->assertOk()->json('conversation');
        $this->assertSame(MessengerConversation::query()->firstOrFail()->id, $id);

        $this->actingAs($alice->fresh())->postJson(route('messenger.direct', $alice))->assertStatus(422);
    }
}
