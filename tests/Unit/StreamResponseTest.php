<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Tests\Http\Unit;

use PHPUnit\Framework\TestCase;
use Spiral\RoadRunner\Http\HttpWorker;
use Spiral\RoadRunner\Payload;
use Spiral\RoadRunner\Tests\Http\Unit\Stub\TestRelay;
use Spiral\RoadRunner\Worker;

final class StreamResponseTest extends TestCase
{
    private TestRelay $relay;
    private Worker $worker;

    protected function tearDown(): void
    {
        unset($this->relay, $this->worker);
    }

    /**
     * Regular case
     */
    public function testRegularCase(): void
    {
        $worker = $this->getWorker();
        $this->getRelay()
            ->addFrame(status: 200, body: 'Hello, World!', headers: ['Content-Type' => 'text/plain'], stream: true);

        self::assertTrue($worker->hasPayload());
        self::assertInstanceOf(Payload::class, $payload = $worker->waitPayload());
        self::assertSame('Hello, World!', $payload->body);
    }

    /**
     * Test stream response with multiple frames
     */
    public function testStreamResponseWithMultipleFrames(): void
    {
        $httpWorker = $this->makeHttpWorker();

        $httpWorker->respond(200, (function () {
            yield 'Hel';
            yield 'lo,';
            yield ' Wo';
            yield 'rld';
            yield '!';
        })());

        self::assertFalse($this->worker->hasPayload());
        self::assertSame('Hello, World!', $this->getRelay()->getReceivedBody());
    }

    public function testStopStreamResponse(): void
    {
        $httpWorker = $this->makeHttpWorker();

        $httpWorker->respond(200, (function () {
            yield 'Hel';
            yield 'lo,';
            $this->getRelay()->addStopStreamFrame();
            try {
                yield ' Wo';
            } catch (\Throwable $e) {
                return;
            }
            yield 'rld';
            yield '!';
        })());

        self::assertSame('Hello,', $this->getRelay()->getReceivedBody());
    }

    private function getRelay(): TestRelay
    {
        return $this->relay ??= new TestRelay();
    }

    private function getWorker(): Worker
    {
        return $this->worker ??= new Worker($this->getRelay(), false);
    }

    private function makeHttpWorker(): HttpWorker
    {
        return new HttpWorker($this->getWorker());
    }
}
