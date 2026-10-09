<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ConversationTest extends TestCase
{
    use RefreshDatabase;

    private function conversationBetween(User ...$users): Conversation
    {
        $conversation = Conversation::create(['user_id' => $users[0]->id]);
        $conversation->users()->attach(collect($users)->pluck('id')->all());

        return $conversation;
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get('/conversation')->assertRedirect('/login');
    }

    public function test_the_chat_page_lists_only_the_users_own_conversations(): void
    {
        [$me, $friend, $stranger] = User::factory(3)->create();
        $mine = $this->conversationBetween($me, $friend);
        $this->conversationBetween($friend, $stranger);

        $this->actingAs($me)->get('/conversation')->assertInertia(fn (Assert $page) => $page
            ->component('Conversations/Index')
            ->has('conversations', 1)
            ->where('conversations.0.uuid', $mine->uuid)
            ->where('conversations.0.title', $friend->name)
            ->where('conversation', null)
            ->has('users', 2));
    }

    public function test_starting_a_chat_with_one_person_creates_a_conversation_and_reuses_it(): void
    {
        [$me, $friend] = User::factory(2)->create();

        $this->actingAs($me)->post('/conversation', ['user_ids' => [$friend->id]])->assertRedirect();
        $conversation = Conversation::firstOrFail();
        $this->assertEqualsCanonicalizing([$me->id, $friend->id], $conversation->users->pluck('id')->all());

        // Asking again, from either side, opens the same conversation
        $this->actingAs($friend)->post('/conversation', ['user_ids' => [$me->id]])
            ->assertRedirect(route('conversation.show', $conversation));
        $this->assertDatabaseCount('conversations', 1);
    }

    public function test_a_named_group_can_be_started_with_several_people(): void
    {
        [$me, $second, $third] = User::factory(3)->create();

        $this->actingAs($me)->post('/conversation', [
            'user_ids' => [$second->id, $third->id],
            'name' => 'Project team',
        ])->assertRedirect();

        $conversation = Conversation::firstOrFail();
        $this->assertSame('Project team', $conversation->name);
        $this->assertCount(3, $conversation->users);
    }

    public function test_a_conversation_needs_at_least_one_other_person(): void
    {
        $me = User::factory()->create();

        $this->actingAs($me)->post('/conversation', ['user_ids' => []])->assertSessionHasErrors('user_ids');
        $this->actingAs($me)->post('/conversation', ['user_ids' => [$me->id]])->assertSessionHasErrors('user_ids.0');
        $this->actingAs($me)->post('/conversation', ['user_ids' => [999]])->assertSessionHasErrors('user_ids.0');

        $this->assertDatabaseCount('conversations', 0);
    }

    public function test_a_participant_can_send_a_message(): void
    {
        [$me, $friend] = User::factory(2)->create();
        $conversation = $this->conversationBetween($me, $friend);

        $this->actingAs($me)
            ->post("/conversation/{$conversation->uuid}/messages", ['body' => 'Hello there'])
            ->assertRedirect(route('conversation.show', $conversation));

        $this->assertDatabaseHas('massages', [
            'conversation_id' => $conversation->id,
            'user_id' => $me->id,
            'body' => 'Hello there',
        ]);
        $this->assertNotNull($conversation->fresh()->last_massage_at);
    }

    public function test_an_empty_message_is_rejected(): void
    {
        [$me, $friend] = User::factory(2)->create();
        $conversation = $this->conversationBetween($me, $friend);

        $this->actingAs($me)
            ->post("/conversation/{$conversation->uuid}/messages", ['body' => ''])
            ->assertSessionHasErrors('body');

        $this->assertDatabaseCount('massages', 0);
    }

    public function test_people_outside_a_conversation_cannot_open_it_or_write_in_it(): void
    {
        [$me, $friend, $stranger] = User::factory(3)->create();
        $conversation = $this->conversationBetween($me, $friend);

        $this->actingAs($stranger)->get("/conversation/{$conversation->uuid}")->assertForbidden();
        $this->actingAs($stranger)
            ->post("/conversation/{$conversation->uuid}/messages", ['body' => 'Let me in'])
            ->assertForbidden();

        $this->assertDatabaseCount('massages', 0);
    }

    public function test_opening_a_conversation_shows_its_messages_in_order_and_marks_it_read(): void
    {
        [$me, $friend] = User::factory(2)->create();
        $conversation = $this->conversationBetween($me, $friend);

        $this->actingAs($friend)->post("/conversation/{$conversation->uuid}/messages", ['body' => 'First']);
        $this->travel(1)->minutes();
        $this->actingAs($friend)->post("/conversation/{$conversation->uuid}/messages", ['body' => 'Second']);
        $this->travel(1)->minutes();

        $this->actingAs($me)->get('/conversation')->assertInertia(fn (Assert $page) => $page
            ->where('conversations.0.unread_count', 2)
            ->where('conversations.0.last_message.body', 'Second')
            ->where('conversations.0.last_message.user_name', $friend->name));

        $this->actingAs($me)->get("/conversation/{$conversation->uuid}")->assertInertia(fn (Assert $page) => $page
            ->component('Conversations/Index')
            ->where('conversation.uuid', $conversation->uuid)
            ->where('conversation.title', $friend->name)
            ->has('conversation.messages', 2)
            ->where('conversation.messages.0.body', 'First')
            ->where('conversation.messages.0.is_own', false)
            ->where('conversation.messages.1.body', 'Second')
            ->where('conversations.0.unread_count', 0));

        // The sender never sees their own messages as unread
        $this->actingAs($friend)->get('/conversation')->assertInertia(fn (Assert $page) => $page
            ->where('conversations.0.unread_count', 0)
            ->where('conversations.0.last_message.user_name', 'You'));
    }

    public function test_the_most_recently_active_conversation_comes_first(): void
    {
        [$me, $first, $second] = User::factory(3)->create();
        $older = $this->conversationBetween($me, $first);
        $newer = $this->conversationBetween($me, $second);

        $this->actingAs($me)->post("/conversation/{$newer->uuid}/messages", ['body' => 'Hi']);
        $this->travel(5)->minutes();
        $this->actingAs($me)->post("/conversation/{$older->uuid}/messages", ['body' => 'Hi again']);

        $this->actingAs($me)->get('/conversation')->assertInertia(fn (Assert $page) => $page
            ->where('conversations.0.uuid', $older->uuid)
            ->where('conversations.1.uuid', $newer->uuid));
    }
}
