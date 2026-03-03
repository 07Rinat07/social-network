<?php

namespace App\Events;

use App\Models\FeedbackMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AdminFeedbackCreated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public FeedbackMessage $feedback)
    {
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('admin.feedback');
    }

    public function broadcastAs(): string
    {
        return 'feedback.created';
    }

    public function broadcastWith(): array
    {
        $this->feedback->loadMissing('user:id,name,email,is_admin');

        return [
            'id' => (int) $this->feedback->id,
            'user_id' => $this->feedback->user_id ? (int) $this->feedback->user_id : null,
            'name' => (string) $this->feedback->name,
            'email' => (string) $this->feedback->email,
            'message' => (string) $this->feedback->message,
            'status' => (string) $this->feedback->status,
            'admin_note' => $this->feedback->admin_note,
            'created_at' => $this->feedback->created_at?->toIso8601String(),
            'updated_at' => $this->feedback->updated_at?->toIso8601String(),
            'user' => $this->feedback->user ? [
                'id' => (int) $this->feedback->user->id,
                'name' => (string) $this->feedback->user->name,
                'email' => (string) $this->feedback->user->email,
                'is_admin' => (bool) $this->feedback->user->is_admin,
            ] : null,
        ];
    }
}
