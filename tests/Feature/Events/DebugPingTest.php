<?php

use App\Events\DebugPing;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

test('DebugPing broadcasts on the configured public debug channel', function () {
    config(['broadcasting.debug_channel' => 'faucon-test-channel']);

    $event = new DebugPing('Reverb est branché');

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(Channel::class)
        ->and($channels[0]->name)->toBe('faucon-test-channel')
        ->and($event)->toBeInstanceOf(ShouldBroadcastNow::class);
});

test('DebugPing exposes its message and send time to clients', function () {
    $event = new DebugPing('Reverb est branché');

    $payload = $event->broadcastWith();

    expect($payload['message'])->toBe('Reverb est branché')
        ->and($payload['sentAt'])->not->toBeEmpty();
});
