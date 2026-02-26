<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Tests\Http\Unit;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunClassInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Spiral\Goridge\Frame;
use Spiral\RoadRunner\Http\GlobalState;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Tests\Http\Unit\Stub\TestRelay;
use Spiral\RoadRunner\Worker;

#[CoversClass(PSR7Worker::class)]
#[CoversClass(GlobalState::class)]
#[RunClassInSeparateProcess]
final class PSR7WorkerTest extends TestCase
{
    public function testStateServerLeak(): void
    {
        $psrFactory = new Psr17Factory();
        $relay      = new TestRelay();
        $psrWorker  = new PSR7Worker(
            new Worker($relay),
            $psrFactory,
            $psrFactory,
            $psrFactory,
        );

        //dataProvider is always random and we need to keep the order
        $fixtures = [
            [
                [
                    'Content-Type' => ['application/html'],
                    'Connection' => ['keep-alive'],
                ],
                [
                    'REQUEST_URI' => 'http://localhost',
                    'REMOTE_ADDR' => '127.0.0.1',
                    'REQUEST_METHOD' => 'GET',
                    'HTTP_USER_AGENT' => '',
                    'CONTENT_TYPE' => 'application/html',
                    'HTTP_CONNECTION' => 'keep-alive',
                    'HTTP_HOST' =>   'localhost',
                ],
            ],
            [
                [
                    'Content-Type' => ['application/json'],
                ],
                [
                    'REQUEST_URI' => 'http://localhost',
                    'REMOTE_ADDR' => '127.0.0.1',
                    'REQUEST_METHOD' => 'GET',
                    'HTTP_USER_AGENT' => '',
                    'CONTENT_TYPE' => 'application/json',
                    'HTTP_HOST' =>   'localhost',
                ],
            ],
        ];

        $_SERVER = [];
        foreach ($fixtures as [$headers, $expectedServer]) {
            $body = [
                'headers' => $headers,
                'rawQuery' => '',
                'remoteAddr' => '127.0.0.1',
                'protocol' => 'HTTP/1.1',
                'method' => 'GET',
                'uri' => 'http://localhost',
                'parsed' => false,
            ];

            $head = (string) \json_encode($body, \JSON_THROW_ON_ERROR);
            $frame = new Frame($head . 'test', [\strlen($head)]);

            $relay->addFrames($frame);

            $psrWorker->waitRequest();

            unset($_SERVER['REQUEST_TIME']);
            unset($_SERVER['REQUEST_TIME_FLOAT']);

            self::assertEquals($expectedServer, $_SERVER);
        }
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
