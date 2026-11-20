<?php

use App\Events\ReadingReceived;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastFactory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Joelfrens\HomeAutomation\Mqtt\Events\MessageReceived;

test('a valid sensor message is broadcast as a reading', function () {
    Event::fake([ReadingReceived::class]);
    $this->travelTo('2026-11-20 18:30:05.123');

    MessageReceived::dispatch('home/kitchen/bme280', '{"temperature":21.4,"humidity":45,"pressure":1003.1,"battery":80}');

    Event::assertDispatched(ReadingReceived::class, function (ReadingReceived $e) {
        return $e->id === 'kitchen|bme280|2026-11-20T18:30:05.123'
            && $e->room === 'kitchen'
            && $e->sensor === 'bme280'
            && $e->recordedAt === '2026-11-20T18:30:05+00:00'
            && $e->values === ['temperature' => 21.4, 'humidity' => 45.0, 'pressure' => 1003.1];
    });
});

test('invalid messages are not broadcast', function (string $topic, string $payload) {
    Event::fake([ReadingReceived::class]);

    MessageReceived::dispatch($topic, $payload);

    Event::assertNotDispatched(ReadingReceived::class);
})->with([
    'unknown sensor type' => ['home/kitchen/water-level', '{"level":12}'],
    'out of range' => ['home/kitchen/bme280', '{"temperature":21,"humidity":150,"pressure":1000}'],
    'not JSON' => ['home/kitchen/bme280', 'hello'],
    'not a sensor topic' => ['home/kitchen/bme280/cmd', '{"on":true}'],
]);

test('the reading goes to the public readings channel', function () {
    $event = new ReadingReceived('id', 'kitchen', 'bme280', '2026-11-20T18:30:05+00:00', ['temperature' => 21.4]);

    expect($event->broadcastOn())->toEqual(new Channel('readings'));
});

test('if Reverb is down the listener logs instead of crashing the mqtt listener', function () {
    $broadcaster = Mockery::mock(BroadcastFactory::class);
    $broadcaster->shouldReceive('queue')->once()->andThrow(new BroadcastException('connection refused'));
    $this->app->instance(BroadcastFactory::class, $broadcaster);

    Log::spy();

    MessageReceived::dispatch('home/kitchen/bme280', '{"temperature":21,"humidity":40,"pressure":1000}');

    Log::shouldHaveReceived('warning')->once()->withArgs(
        fn (string $message) => str_contains($message, 'connection refused'),
    );
    expect(true)->toBeTrue(); // reached: no exception escaped the listener
});
