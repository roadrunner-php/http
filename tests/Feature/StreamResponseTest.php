<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Tests\Http\Feature;

use PHPUnit\Framework\TestCase;
use Spiral\Goridge\SocketRelay;
use Spiral\RoadRunner\Http\HttpWorker;
use Spiral\RoadRunner\Payload;
use Spiral\RoadRunner\Tests\Http\Server\Command\BaseCommand;
use Spiral\RoadRunner\Tests\Http\Server\Command\StreamStop;
use Spiral\RoadRunner\Tests\Http\Server\ServerRunner;
use Spiral\RoadRunner\Worker;

class StreamResponseTest extends TestCase
{
    private SocketRelay $relay;
    private Worker $worker;
    private $serverAddress = 'tcp://127.0.0.1:6002';

    protected function setUp(): void
    {
        parent::setUp();
        ServerRunner::start();
        ServerRunner::getBuffer();
    }

    protected function tearDown(): void
    {
        unset($this->relay, $this->worker);
        ServerRunner::stop();
        parent::tearDown();
    }

    /**
     * Regular case
     */
    public function testRegularCase(): void
    {
        $worker = $this->getWorker();
        $worker->respond(new Payload('Hello, World!'));

        \usleep(100_000);
        self::assertSame('Hello, World!', \trim(ServerRunner::getBuffer()));
    }

    /**
     * Test stream response with multiple frames
     */
    public function testStreamResponseWithMultipleFrames(): void
    {
        $httpWorker = $this->makeHttpWorker();

        $chunks = ['Hel', 'lo,', ' Wo', 'rld', '!'];
        ServerRunner::getBuffer();
        $httpWorker->respond(
            200,
            (function () use ($chunks) {
                yield from $chunks;
            })(),
        );

        \usleep(100_000);
        self::assertSame(\implode("\n", $chunks), \trim(ServerRunner::getBuffer()));
    }

    public function testStopStreamResponse(): void
    {
        $httpWorker = $this->makeHttpWorker();

        // Flush buffer
        ServerRunner::getBuffer();

        $httpWorker->respond(
            200,
            (function () {
                yield 'Hel';
                yield 'lo,';
                $this->sendCommand(new StreamStop());
                try {
                    yield ' Wo';
                } catch (\Throwable $e) {
                    return;
                }
                yield 'rld';
                yield '!';
            })(),
        );


        \usleep(100_000);
        self::assertSame(\implode("\n", ['Hel', 'lo,']), \trim(ServerRunner::getBuffer()));
    }

    /**
     * StopStream should be ignored if stream is already ended.
     */
    public function testStopStreamAfterStreamEnd(): void
    {
        $httpWorker = $this->makeHttpWorker();

        // Flush buffer
        ServerRunner::getBuffer();

        $httpWorker->respond(
            200,
            (function () {
                yield 'Hello';
                yield 'World!';
            })(),
        );

        $this->assertFalse($this->getWorker()->hasPayload(\Spiral\RoadRunner\Message\Command\StreamStop::class));
        $this->sendCommand(new StreamStop());
        \usleep(200_000);
        self::assertSame(\implode("\n", ['Hello', 'World!']), \trim(ServerRunner::getBuffer()));
        $this->assertTrue($this->getWorker()->hasPayload(\Spiral\RoadRunner\Message\Command\StreamStop::class));
        $this->assertFalse($this->getWorker()->hasPayload());
    }

    private function getRelay(): SocketRelay
    {
        return $this->relay ??= SocketRelay::create($this->serverAddress);
    }

    private function getWorker(): Worker
    {
        return $this->worker ??= new Worker(relay: $this->getRelay(), interceptSideEffects: false);
    }

    private function makeHttpWorker(): HttpWorker
    {
        return new HttpWorker($this->getWorker());
    }

    private function sendCommand(BaseCommand $command)
    {
        $this->getRelay()->send($command->getRequestFrame());
        \usleep(500_000);
    }
}
