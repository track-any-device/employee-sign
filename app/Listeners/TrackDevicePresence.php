<?php

namespace App\Listeners;

use App\Services\Devices\DevicePresence;
use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Reverb\Events\ChannelRemoved;
use Laravel\Reverb\Events\MessageReceived;
use Throwable;

/**
 * Runs inside the Reverb server process.
 *
 * MessageReceived is only dispatched after Reverb handled the message
 * without error, so a pusher:subscribe for private-device.{id} seen here
 * already passed the channel signature check.
 */
class TrackDevicePresence
{
    /** @var array<string, string> socket id => device id */
    private static array $sockets = [];

    /** @var array<string, int> device id => last touch timestamp */
    private static array $lastTouch = [];

    public function __construct(private DevicePresence $presence) {}

    public function onMessage(MessageReceived $event): void
    {
        try {
            $message = json_decode($event->message, true);
            $name = $message['event'] ?? null;
            $data = is_string($message['data'] ?? null) ? json_decode($message['data'], true) : ($message['data'] ?? []);
            $socket = $event->connection->id();

            if ($name === 'pusher:subscribe' && ($id = DevicePresence::deviceIdFromChannel($data['channel'] ?? null))) {
                self::$sockets[$socket] = $id;
                $this->presence->online($id);
            } elseif ($name === 'pusher:unsubscribe' && ($id = DevicePresence::deviceIdFromChannel($data['channel'] ?? null))) {
                unset(self::$sockets[$socket]);
                $this->presence->offline($id, 'unsubscribed');
            } elseif ($name === 'pusher:ping' && isset(self::$sockets[$socket])) {
                $id = self::$sockets[$socket];
                // Throttle DB writes: at most every 30 s per device.
                if ((self::$lastTouch[$id] ?? 0) < time() - 30) {
                    self::$lastTouch[$id] = time();
                    $this->presence->touch($id);
                }
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Last subscriber left (disconnect, or Reverb pruned a silent connection). */
    public function onChannelRemoved(ChannelRemoved $event): void
    {
        try {
            if ($id = DevicePresence::deviceIdFromChannel($event->channel->name())) {
                self::$sockets = array_filter(self::$sockets, fn ($d) => $d !== $id);
                $this->presence->offline($id);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(MessageReceived::class, [self::class, 'onMessage']);
        $events->listen(ChannelRemoved::class, [self::class, 'onChannelRemoved']);
    }
}
