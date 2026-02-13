<?php

use App\Models\Conversation;
use App\Models\UserBlock;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('chat.conversation.{conversationId}', function ($user, int $conversationId) {
    $conversation = Conversation::query()->find($conversationId);

    if (!$conversation) {
        return false;
    }

    if ($conversation->type === Conversation::TYPE_DIRECT) {
        $participantIds = $conversation->participants()->pluck('users.id')->values()->all();

        if (count($participantIds) === 2 && UserBlock::isBlockedBetween($participantIds[0], $participantIds[1])) {
            return false;
        }
    }

    return $conversation->isAccessibleBy($user->id);
});

Broadcast::channel('site.online', function ($user) {
    return [
        'id' => (int) $user->id,
        'name' => (string) $user->name,
        'display_name' => (string) $user->display_name,
        'nickname' => $user->nickname,
        'avatar_url' => $user->avatar_url,
        'is_admin' => (bool) $user->is_admin,
    ];
});

Broadcast::channel('chat.presence.{conversationId}', function ($user, int $conversationId) {
    $conversation = Conversation::query()->find($conversationId);

    if (!$conversation) {
        return false;
    }

    if ($conversation->type === Conversation::TYPE_DIRECT) {
        $participantIds = $conversation->participants()->pluck('users.id')->values()->all();

        if (count($participantIds) === 2 && UserBlock::isBlockedBetween($participantIds[0], $participantIds[1])) {
            return false;
        }
    }

    if (!$conversation->isAccessibleBy($user->id)) {
        return false;
    }

    return [
        'id' => (int) $user->id,
        'name' => (string) $user->name,
        'display_name' => (string) $user->display_name,
        'nickname' => $user->nickname,
        'avatar_url' => $user->avatar_url,
        'is_admin' => (bool) $user->is_admin,
    ];
});

Broadcast::channel('feedback.user.{userId}', function ($user, int $userId) {
    return (int) $user->id === $userId;
});
