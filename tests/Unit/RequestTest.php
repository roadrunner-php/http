<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Tests\Http\Unit;

use Spiral\RoadRunner\Http\Request;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Test;

#[Covers(Request::class)]
#[Test]
final class RequestTest
{
    public function testGetRemoteAddrReturnsRemoteAddr(): void
    {
        $request = new Request(remoteAddr: '10.0.0.1');

        Assert::same($request->getRemoteAddr(), '10.0.0.1');
    }

    public function testGetRemoteAddrPrefersIpAddressAttribute(): void
    {
        $request = new Request(remoteAddr: '10.0.0.1', attributes: ['ipAddress' => '192.168.0.1']);

        Assert::same($request->getRemoteAddr(), '192.168.0.1');
    }

    public function testGetParsedBodyReturnsNullWhenBodyIsNotParsed(): void
    {
        $request = new Request(body: '{"foo":"bar"}', parsed: false);

        Assert::null($request->getParsedBody());
    }

    public function testGetParsedBodyDecodesJsonBody(): void
    {
        $request = new Request(body: '{"foo":"bar","list":[1,2]}', parsed: true);

        Assert::same($request->getParsedBody(), ['foo' => 'bar', 'list' => [1, 2]]);
    }

    public function testGetParsedBodyThrowsOnMalformedJson(): never
    {
        $request = new Request(body: '{"foo":', parsed: true);

        Expect::exception(\JsonException::class);

        $request->getParsedBody();
    }
}
