<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Tests\Http\Unit;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Spiral\RoadRunner\Http\HttpWorker;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Worker;

final class PSR7WorkerTest extends TestCase
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

        self::assertInstanceOf(HttpWorker::class, $psrWorker->getHttpWorker());
    }

    protected function tearDown(): void
    {
        // Clean all extra output buffers
        $level = \ob_get_level();
        while (--$level > 0) {
            \ob_end_clean();
        }
    }
}
