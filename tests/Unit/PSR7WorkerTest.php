<?php

namespace Spiral\RoadRunner\Tests\Http\Unit;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Spiral\RoadRunner\Http\HttpWorker;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Worker;

class PSR7WorkerTest extends TestCase
{
    public function testHttpWorkerIsAvailable(): void
    {
        $psrFactory = new Psr17Factory();

        $psrWorker = new PSR7Worker(
            Worker::create(),
            $psrFactory,
            $psrFactory,
            $psrFactory,
        );

        ob_end_clean();

        self::assertInstanceOf(HttpWorker::class, $psrWorker->getHttpWorker());
    }
}
