<?php

namespace App\Events;

use App\Models\ConversationMessage;
use App\Models\ConversationMessageAttachment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationMessageSent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public ConversationMessage $message)
    {
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('chat.conversation.' . $this->message->conversation_id);
    }

    public function broadcastAs(): string
    {
        return 'chat.message.sent';
    }

    public function broadcastWith(): array
    {
        $this->message->loadMissing(['user:id,name,nickname,avatar_path,is_admin', 'attachments']);

        return [
            'id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'body' => (string) ($this->message->body ?? ''),
            'date' => $this->message->date,
            'created_at' => $this->message->created_at?->toIso8601String(),
            'user' => [
                'id' => $this->message->user->id,
                'name' => $this->message->user->name,
                'display_name' => $this->message->user->display_name,
                'nickname' => $this->message->user->nickname,
                'avatar_url' => $this->message->user->avatar_url,
                'is_admin' => (bool) $this->message->user->is_admin,
            ],
            'attachments' => $this->message->attachments->map(fn (ConversationMessageAttachment $attachment) => [
                'id' => $attachment->id,
                'type' => $attachment->type,
                'mime_type' => $attachment->mime_type,
                'size' => (int) ($attachment->size ?? 0),
                'original_name' => $attachment->original_name,
                'url' => $attachment->url,
            ])->values(),
        ];
    }
}
