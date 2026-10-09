<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Tests\Http\Unit;

use Spiral\RoadRunner\Http\HttpWorker;
use Spiral\RoadRunner\Payload;
use Spiral\RoadRunner\Tests\Http\Unit\Stub\TestRelay;
use Spiral\RoadRunner\Worker;
use Testo\Assert;
use Testo\Lifecycle\AfterTest;
use Testo\Test;

#[Test]
final class StreamResponseTest
{
    private TestRelay $relay;
    private Worker $worker;

    /**
     * Regular case
     */
    public function testRegularCase(): void
    {
        $worker = $this->getWorker();
        $this->getRelay()
            ->addFrame(status: 200, body: 'Hello, World!', headers: ['Content-Type' => 'text/plain'], stream: true);

        Assert::true($worker->hasPayload());
        Assert::instanceOf($payload = $worker->waitPayload(), Payload::class);
        Assert::same($payload->body, 'Hello, World!');
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

        Assert::false($this->worker->hasPayload());
        Assert::same($this->getRelay()->getReceivedBody(), 'Hello, World!');
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

        Assert::same($this->getRelay()->getReceivedBody(), 'Hello,');
    }

    #[AfterTest]
    protected function tearDown(): void
    {
        unset($this->relay, $this->worker);
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
