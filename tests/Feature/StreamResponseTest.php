<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Tests\Http\Feature;

use Spiral\Goridge\SocketRelay;
use Spiral\RoadRunner\Http\Exception\StreamStoppedException;
use Spiral\RoadRunner\Http\HttpWorker;
use Spiral\RoadRunner\Message\Command\GetProcessId;
use Spiral\RoadRunner\Payload;
use Spiral\RoadRunner\Tests\Http\Server\Command\BaseCommand;
use Spiral\RoadRunner\Tests\Http\Server\Command\StreamStop;
use Spiral\RoadRunner\Tests\Http\Server\ServerRunner;
use Spiral\RoadRunner\Worker;
use Testo\Assert;
use Testo\Expect;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
final class StreamResponseTest
{
    private SocketRelay $relay;
    private Worker $worker;
    private $serverAddress = 'tcp://127.0.0.1:6002';

    /**
     * Regular case
     */
    public function testRegularCase(): void
    {
        $worker = $this->getWorker();
        $worker->respond(new Payload('Hello, World!'));

        \usleep(100_000);
        Assert::same(\trim(ServerRunner::getBuffer()), 'Hello, World!');
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
        Assert::same(\trim(ServerRunner::getBuffer()), \implode("\n", $chunks));
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
                } catch (StreamStoppedException $e) {
                    return;
                }
                yield 'rld';
                yield '!';
            })(),
        );


        \usleep(100_000);
        Assert::same(\trim(ServerRunner::getBuffer()), \implode("\n", ['Hel', 'lo,']));
    }

    public function testSend1xxWithBody(): void
    {
        $httpWorker = $this->makeHttpWorker();

        Expect::exception(\Throwable::class)->withMessageContaining('Unable to send a body with informational status code');

        $httpWorker->respond(
            103,
            (function () {
                yield 'Hel';
                yield 'lo,';
            })(),
        );
    }

    public function testExceptionInGenerator(): void
    {
        $httpWorker = $this->makeHttpWorker();

        // Flush buffer
        ServerRunner::getBuffer();

        $httpWorker->respond(
            200,
            (function () {
                yield 'Hel';
                yield 'lo,';
                throw new \Exception('test');
            })(),
        );


        \usleep(100_000);
        Assert::same(\trim(ServerRunner::getBuffer()), \implode("\n", ['Hel', 'lo,']));
    }

    /**
     * StopStream should be ignored if stream is already ended.
     * Commented because doesn't pass in CI
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

        Assert::false($this->getWorker()->hasPayload(\Spiral\RoadRunner\Message\Command\StreamStop::class));
        $this->sendCommand(new StreamStop());
        \usleep(200_000);
        Assert::same(\trim(ServerRunner::getBuffer()), \implode("\n", ['Hello', 'World!']));
        Assert::true($this->getWorker()->hasPayload(\Spiral\RoadRunner\Message\Command\StreamStop::class));

        $this->getWorker()->getPayload(\Spiral\RoadRunner\Message\Command\StreamStop::class);
        $this->getWorker()->getPayload(GetProcessId::class);

        Assert::false($this->getWorker()->hasPayload());
    }

    #[BeforeTest]
    protected function setUp(): void
    {
        ServerRunner::start();
        ServerRunner::getBuffer();
    }

    #[AfterTest]
    protected function tearDown(): void
    {
        unset($this->relay, $this->worker);
        ServerRunner::stop();
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

    private function sendCommand(BaseCommand $command): void
    {
        $this->getRelay()->send($command->getRequestFrame());
        \usleep(500_000);
    }
}
