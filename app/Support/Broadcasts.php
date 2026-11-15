<?php

namespace App\Support;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Laravel\Reverb\Contracts\ApplicationProvider;
use Laravel\Reverb\Protocols\Pusher\EventDispatcher;
use Throwable;

/**
 * Sends broadcast events without ever failing the caller.
 *
 * Inside the Reverb server process (presence listeners) a normal broadcast
 * would make Reverb call its own HTTP API from inside its event loop and
 * block, so there we hand the message to Reverb's dispatcher directly.
 */
class Broadcasts
{
    public static function send(ShouldBroadcast $event): void
    {
        try {
            if (app()->runningConsoleCommand('reverb:start')) {
                self::dispatchInsideReverb($event);

                return;
            }

            broadcast($event);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private static function dispatchInsideReverb(ShouldBroadcast $event): void
    {
        $app = app(ApplicationProvider::class)->findById((string) config('broadcasting.connections.reverb.app_id'));
        $name = method_exists($event, 'broadcastAs') ? $event->broadcastAs() : $event::class;
        $data = method_exists($event, 'broadcastWith') ? $event->broadcastWith() : [];

        foreach ((array) $event->broadcastOn() as $channel) {
            EventDispatcher::dispatch($app, [
                'event' => $name,
                'channel' => $channel instanceof Channel ? $channel->name : (string) $channel,
                'data' => json_encode($data),
            ]);
        }
    }
}
