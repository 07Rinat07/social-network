<?php

namespace App\Events;

use App\Models\FeedbackMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FeedbackStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public FeedbackMessage $feedback)
    {
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('feedback.user.' . $this->feedback->user_id);
    }

    public function broadcastAs(): string
    {
        return 'feedback.status.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->feedback->id,
            'status' => $this->feedback->status,
            'admin_note' => $this->feedback->admin_note,
            'message' => $this->feedback->message,
            'created_at' => $this->feedback->created_at?->toIso8601String(),
            'updated_at' => $this->feedback->updated_at?->toIso8601String(),
        ];
    }
}
