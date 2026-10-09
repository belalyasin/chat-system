<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Conversation extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'last_massage_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Conversation $conversation) {
            $conversation->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }

    public function users()
    {
        return $this->belongsToMany(User::class)
            ->withPivot('read_at')->withTimestamps();
    }

    public function others()
    {
        return $this->users()->where('user_id', '!=', auth()->id());
    }

    public function messages()
    {
        return $this->hasMany(Massage::class);
    }

    public function latestMessage()
    {
        return $this->hasOne(Massage::class)->latestOfMany();
    }

    public function hasParticipant(User $user): bool
    {
        return $this->users()->whereKey($user->getKey())->exists();
    }

    /**
     * Record that the user has read everything sent so far.
     */
    public function markAsReadBy(User $user): void
    {
        $this->users()->updateExistingPivot($user->getKey(), ['read_at' => now()]);
    }

    /**
     * The name shown to a user: the group name, or the other people in the conversation.
     */
    public function titleFor(User $user): string
    {
        if (filled($this->name)) {
            return $this->name;
        }

        $others = $this->users->where('id', '!=', $user->id)->pluck('name');

        return $others->isEmpty() ? $user->name : $others->join(', ');
    }

    /**
     * Only the conversations the user takes part in.
     */
    public function scopeForUser(Builder $query, User $user): void
    {
        $query->whereHas('users', fn (Builder $users) => $users->whereKey($user->getKey()));
    }

    /**
     * Adds an unread_count column: messages from other people sent after the user last read the conversation.
     */
    public function scopeWithUnreadCountFor(Builder $query, User $user): void
    {
        $lastRead = fn ($pivot) => $pivot->from('conversation_user')
            ->whereColumn('conversation_user.conversation_id', 'conversations.id')
            ->where('conversation_user.user_id', $user->getKey())
            ->whereNotNull('conversation_user.read_at');

        $query->addSelect(['unread_count' => Massage::query()
            ->selectRaw('count(*)')
            ->whereColumn('massages.conversation_id', 'conversations.id')
            ->where('massages.user_id', '!=', $user->getKey())
            ->where(function ($unread) use ($lastRead) {
                $unread
                    ->whereNotExists(fn ($pivot) => $lastRead($pivot)->selectRaw('1'))
                    ->orWhere('massages.created_at', '>', fn ($pivot) => $lastRead($pivot)->select('conversation_user.read_at')->limit(1));
            }),
        ]);
    }
}
