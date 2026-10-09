<?php

namespace App\Http\Controllers\Conversations;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ConversationsController extends Controller
{
    /**
     * The chat page with no conversation open.
     */
    public function index(Request $request)
    {
        return inertia('Conversations/Index', [
            'conversations' => $this->conversationList($request->user()),
            'conversation' => null,
            'users' => $this->otherUsers($request->user()),
        ]);
    }

    /**
     * The chat page with one conversation open. Opening it marks it as read.
     */
    public function show(Request $request, Conversation $conversation)
    {
        $user = $request->user();
        abort_unless($conversation->hasParticipant($user), 403);

        $conversation->markAsReadBy($user);
        $conversation->load(['users', 'messages' => fn ($messages) => $messages->with('user')->oldest()->orderBy('id')]);

        return inertia('Conversations/Index', [
            'conversations' => $this->conversationList($user),
            'conversation' => [
                'uuid' => $conversation->uuid,
                'title' => $conversation->titleFor($user),
                'users' => $conversation->users->map(fn (User $member) => [
                    'id' => $member->id,
                    'name' => $member->name,
                ])->values(),
                'messages' => $conversation->messages->map(fn ($message) => [
                    'id' => $message->id,
                    'body' => $message->body,
                    'user_name' => $message->user->name,
                    'is_own' => $message->user_id === $user->id,
                    'created_at' => $message->created_at,
                ])->values(),
            ],
            'users' => $this->otherUsers($user),
        ]);
    }

    /**
     * Start a conversation with one or more people. A chat between two people is reused
     * if it already exists.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id'), Rule::notIn([$user->id])],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $memberIds = collect($data['user_ids'])->push($user->id)->unique()->values();
        $name = filled($data['name'] ?? null) ? $data['name'] : null;

        if ($memberIds->count() === 2 && $name === null) {
            $existing = Conversation::query()
                ->whereNull('name')
                ->has('users', '=', 2)
                ->whereHas('users', fn ($users) => $users->whereKey($memberIds[0]))
                ->whereHas('users', fn ($users) => $users->whereKey($memberIds[1]))
                ->first();

            if ($existing) {
                return redirect()->route('conversation.show', $existing);
            }
        }

        $conversation = DB::transaction(function () use ($user, $memberIds, $name) {
            $conversation = Conversation::create([
                'name' => $name,
                'user_id' => $user->id,
            ]);
            $conversation->users()->attach($memberIds->all());
            $conversation->markAsReadBy($user);

            return $conversation;
        });

        return redirect()->route('conversation.show', $conversation);
    }

    /**
     * The signed-in user's conversations, most recently active first.
     */
    private function conversationList(User $user)
    {
        return Conversation::query()
            ->select('conversations.*')
            ->forUser($user)
            ->withUnreadCountFor($user)
            ->with(['users', 'latestMessage.user'])
            ->orderByDesc('last_massage_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Conversation $conversation) => [
                'uuid' => $conversation->uuid,
                'title' => $conversation->titleFor($user),
                'is_group' => $conversation->users->count() > 2,
                'unread_count' => (int) $conversation->unread_count,
                'last_message' => $conversation->latestMessage ? [
                    'body' => $conversation->latestMessage->body,
                    'user_name' => $conversation->latestMessage->user_id === $user->id
                        ? 'You'
                        : $conversation->latestMessage->user->name,
                    'created_at' => $conversation->latestMessage->created_at,
                ] : null,
            ])
            ->values();
    }

    /**
     * People the user can start a conversation with.
     */
    private function otherUsers(User $user)
    {
        return User::query()
            ->whereKeyNot($user->id)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $other) => ['id' => $other->id, 'name' => $other->name])
            ->values();
    }
}
