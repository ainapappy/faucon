<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class DebugPing implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public string $message = 'ping') {}

    /**
     * The public channel the event broadcasts on.
     *
     * `REVERB_APP_CHANNEL` names the application's public channel.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel(config('broadcasting.debug_channel'))];
    }

    /**
     * The data broadcast to clients.
     *
     * @return array{message: string, sentAt: string}
     */
    public function broadcastWith(): array
    {
        return [
            'message' => $this->message,
            'sentAt' => now()->toIso8601String(),
        ];
    }
}
