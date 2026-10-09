<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Tests\Http\Unit;

use Spiral\RoadRunner\Http\GlobalState;
use Spiral\RoadRunner\Http\Request;
use Testo\Assert;
use Testo\Data\DataProvider;
use Testo\Test;

#[Test]
final class GlobalStateTest
{
    /**
     * @return iterable<array{0: Request, 1: array<array-key, mixed>}>
     */
    public static function enrichServerVarsDataProvider(): iterable
    {
        yield [
            new Request(),
            [
                'REQUEST_URI'        => 'http://localhost',
                'REMOTE_ADDR'        => '127.0.0.1',
                'REQUEST_METHOD'     => 'GET',
                'HTTP_USER_AGENT'    => '',
                'HTTP_HOST'          => 'localhost',
            ],
        ];

        yield [
            new Request(uri: 'https://roadrunner.dev'),
            [
                'REQUEST_URI'        => 'https://roadrunner.dev',
                'REMOTE_ADDR'        => '127.0.0.1',
                'REQUEST_METHOD'     => 'GET',
                'HTTP_USER_AGENT'    => '',
                'HTTP_HOST'          => 'roadrunner.dev',
            ],
        ];

        yield [
            new Request(uri: 'https://roadrunner.dev:8080'),
            [
                'REQUEST_URI'        => 'https://roadrunner.dev:8080',
                'REMOTE_ADDR'        => '127.0.0.1',
                'REQUEST_METHOD'     => 'GET',
                'HTTP_USER_AGENT'    => '',
                'HTTP_HOST'          => 'roadrunner.dev:8080',
            ],
        ];
    }

    /**
     * @param array<array-key, mixed> $expected
     */
    #[DataProvider('enrichServerVarsDataProvider')]
    public function testEnrichServerVars(Request $request, array $expected): void
    {
        $_SERVER = [];

        $server = GlobalState::enrichServerVars($request);

        Assert::array($server)->hasKeys('REQUEST_TIME', 'REQUEST_TIME_FLOAT');

        unset($server['REQUEST_TIME']);
        unset($server['REQUEST_TIME_FLOAT']);

        Assert::equals($server, $expected);
    }
}
