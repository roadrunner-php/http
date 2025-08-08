<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Tests\Http\Unit;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Spiral\Goridge\Frame;
use Spiral\RoadRunner\Http\HttpWorker;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Tests\Http\Unit\Stub\TestRelay;
use Spiral\RoadRunner\Worker;

final class PSR7WorkerTest extends TestCase
{
    /**
     * @param array $headers
     *
     * @dataProvider testStateLeakDataProvider
     */
    public function testStateLeak(array $headers): void
    {
        $psrFactory = new Psr17Factory();
        $relay      = new TestRelay();

        $body = [
            'headers' => $headers,
            'rawQuery' => '',
            'remoteAddr' => '127.0.0.1',
            'protocol' => 'HTTP/1.1',
            'method' => 'GET',
            'uri' => 'http://localhost',
            'parsed' => false,
        ];

        $head = (string)\json_encode($body, \JSON_THROW_ON_ERROR);
        $frame = new Frame($head .'test', [\strlen($head)]);

        $relay->addFrames($frame);

        $psrWorker = new PSR7Worker(
            new Worker($relay),
            $psrFactory,
            $psrFactory,
            $psrFactory,
        );

        $psrWorker->waitRequest();


        var_dump($_SERVER);
    }


    public static function testStateLeakDataProvider(): iterable
    {
        yield [['Content-Type' => ['application/json'], 'Accept' => ['application/html']]];
        yield [['Content-Type' => ['application/json']]];
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
