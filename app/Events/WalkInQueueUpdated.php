<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired whenever a walk-in entry is added, has its status changed, or is
 * removed. Carries no patient data — just a "something changed" ping — so
 * every other front-desk screen watching the queue knows to pull the fresh
 * state from the app's own authenticated endpoint (walkins.partial) rather
 * than having names/phone numbers pass through the third-party Pusher relay.
 *
 * Broadcasts synchronously (ShouldBroadcastNow, not the queued
 * ShouldBroadcast) since this app has no queue worker running.
 */
class WalkInQueueUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $clinicId)
    {
    }

    /** @return array<int, \Illuminate\Broadcasting\Channel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("clinic.{$this->clinicId}.walkins")];
    }

    public function broadcastAs(): string
    {
        return 'walkin.updated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['at' => now()->toIso8601String()];
    }
}
