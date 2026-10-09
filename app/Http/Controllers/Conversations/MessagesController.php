<?php

namespace App\Http\Controllers\Conversations;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MessagesController extends Controller
{
    /**
     * Send a message to a conversation the user takes part in.
     */
    public function store(Request $request, Conversation $conversation)
    {
        $user = $request->user();
        abort_unless($conversation->hasParticipant($user), 403);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        DB::transaction(function () use ($conversation, $user, $data) {
            $message = $conversation->messages()->create([
                'user_id' => $user->id,
                'body' => $data['body'],
            ]);

            $conversation->update(['last_massage_at' => $message->created_at]);
            $conversation->markAsReadBy($user);
        });

        return redirect()->route('conversation.show', $conversation);
    }
}
