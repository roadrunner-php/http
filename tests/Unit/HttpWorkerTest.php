<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Tests\Http\Unit;

use RoadRunner\HTTP\DTO\V1\HeaderValue;
use RoadRunner\HTTP\DTO\V1\Response;
use Spiral\Goridge\Frame;
use Spiral\RoadRunner\Http\HttpWorker;
use Spiral\RoadRunner\Http\Request;
use Spiral\RoadRunner\Payload;
use Spiral\RoadRunner\WorkerInterface;
use Testo\Assert;
use Testo\Data\DataProvider;
use Testo\Data\DataSet;
use Testo\Expect;
use Testo\Lifecycle\AfterTest;
use Testo\Test;

#[Test]
final class HttpWorkerTest
{
    private const REQUIRED_PAYLOAD_DATA = [
        'rawQuery' => 'first=value&arr[]=foo+bar&arr[]=baz',
        'remoteAddr' => '127.0.0.1',
        'protocol' => 'HTTP/1.1',
        'method' => 'GET',
        'uri' => 'http://localhost',
        'parsed' => false,
    ];
    private const REQUIRED_REQUEST_DATA = [
        'remoteAddr' => '127.0.0.1',
        'protocol' => 'HTTP/1.1',
        'method' => 'GET',
        'uri' => 'http://localhost',
        'attributes' => [Request::PARSED_BODY_ATTRIBUTE_NAME => false],
        'query' => ['first' => 'value', 'arr' => ['foo bar', 'baz']],
        'parsed' => false,
        'body' => 'foo',
    ];

    public static function requestDataProvider(): \Traversable
    {
        yield [self::REQUIRED_PAYLOAD_DATA, self::REQUIRED_REQUEST_DATA];
        yield [
            \array_merge(self::REQUIRED_PAYLOAD_DATA, ['parsed' => true]),
            \array_merge(
                self::REQUIRED_REQUEST_DATA,
                ['parsed' => true, 'attributes' => [Request::PARSED_BODY_ATTRIBUTE_NAME => true]],
            ),
        ];
        yield [
            \array_merge(self::REQUIRED_PAYLOAD_DATA, [
                'headers' => [
                    'Content-Type' => ['application/x-www-form-urlencoded'],
                    111 => ['invalid-non-string-key'],
                    '' => ['invalid-empty-string-key'],
                ],
            ]),
            \array_merge(self::REQUIRED_REQUEST_DATA, [
                'headers' => ['Content-Type' => ['application/x-www-form-urlencoded']],
            ]),
        ];
        yield [
            \array_merge(self::REQUIRED_PAYLOAD_DATA, [
                'cookies' => [
                    'theme' => 'light',
                ],
            ]),
            \array_merge(self::REQUIRED_REQUEST_DATA, [
                'cookies' => ['theme' => 'light'],
            ]),
        ];
        yield [
            \array_merge(self::REQUIRED_PAYLOAD_DATA, [
                'uploads' => [
                    'single-file' => [
                        'name' => 'test.png',
                        'mime' => 'image/png',
                        'size' => 123,
                        'error' => 0,
                        'tmpName' => '/tmp/php/php1h4j1o',
                    ],
                    'multiple' => [
                        [
                            'name' => 'test.png',
                            'mime' => 'image/png',
                            'size' => 123,
                            'error' => 0,
                            'tmpName' => '/tmp/php/php1h4j1o',
                        ],
                        [
                            'name' => 'test2.jpg',
                            'mime' => 'image/jpeg',
                            'size' => 1235,
                            'error' => 0,
                            'tmpName' => '/tmp/php/php2h4j1o',
                        ],
                    ],
                    'nested' => [
                        'some-key' => [
                            'name' => 'test.png',
                            'mime' => 'image/png',
                            'size' => 123,
                            'error' => 0,
                            'tmpName' => '/tmp/php/php1h4j1o',
                        ],
                    ],
                ],
            ]),
            \array_merge(self::REQUIRED_REQUEST_DATA, [
                'uploads' => [
                    'single-file' => [
                        'name' => 'test.png',
                        'mime' => 'image/png',
                        'size' => 123,
                        'error' => 0,
                        'tmpName' => '/tmp/php/php1h4j1o',
                    ],
                    'multiple' => [
                        [
                            'name' => 'test.png',
                            'mime' => 'image/png',
                            'size' => 123,
                            'error' => 0,
                            'tmpName' => '/tmp/php/php1h4j1o',
                        ],
                        [
                            'name' => 'test2.jpg',
                            'mime' => 'image/jpeg',
                            'size' => 1235,
                            'error' => 0,
                            'tmpName' => '/tmp/php/php2h4j1o',
                        ],
                    ],
                    'nested' => [
                        'some-key' => [
                            'name' => 'test.png',
                            'mime' => 'image/png',
                            'size' => 123,
                            'error' => 0,
                            'tmpName' => '/tmp/php/php1h4j1o',
                        ],
                    ],
                ],
            ]),
        ];
        yield [
            \array_merge(self::REQUIRED_PAYLOAD_DATA, [
                'attributes' => [
                    'foo' => 'bar',
                ],
            ]),
            \array_merge(self::REQUIRED_REQUEST_DATA, [
                'attributes' => [
                    Request::PARSED_BODY_ATTRIBUTE_NAME => false,
                    'foo' => 'bar',
                ],
            ]),
        ];
    }

    public static function emptyRequestDataProvider(): \Traversable
    {
        yield [null];
        yield [new Payload(null, null)];
    }

    public static function headersDataProvider(): \Traversable
    {
        yield [
            ['Content-Type' => ['application/x-www-form-urlencoded']],
            ['Content-Type' => new HeaderValue(['value' => ['application/x-www-form-urlencoded']])],
        ];
        yield [
            ['Content-Type' => ['application/x-www-form-urlencoded'], 'X-Test' => ['foo', 'bar']],
            [
                'Content-Type' => new HeaderValue(['value' => ['application/x-www-form-urlencoded']]),
                'X-Test' => new HeaderValue(['value' => ['foo', 'bar']]),
            ],
        ];
        yield [['Content-Type' => [null]], []];
        yield [['Content-Type' => [1]], []];
        yield [['Content-Type' => [true]], []];
        yield [['Content-Type' => [false]], []];
        yield [['Content-Type' => [new \stdClass()]], []];
        yield [['Content-Type' => [1.5]], []];
        yield [
            ['X-Test' => ['foo', 'bar'], 'X-Test2' => ['foo', null], 'X-Test3' => [null, 1]],
            [
                'X-Test' => new HeaderValue(['value' => ['foo', 'bar']]),
                'X-Test2' => new HeaderValue(['value' => ['foo']]),
            ],
        ];
        yield [
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            ['Content-Type' => new HeaderValue(['value' => ['application/x-www-form-urlencoded']])],
        ];
        yield [['Content-Type' => new \stdClass()], []];
    }

    #[DataProvider('requestDataProvider')]
    public function testWaitRequestFromArray(array $header, array $expected): void
    {
        $worker = \Mockery::mock(WorkerInterface::class)->shouldIgnoreMissing();
        $worker->shouldReceive('waitPayload')->once()->andReturn(new Payload('foo', \json_encode($header)));

        $worker = new HttpWorker($worker);

        Assert::equals($worker->waitRequest(), new Request(...$expected));
    }

    #[DataProvider('requestDataProvider')]
    public function testWaitRequestFromProto(array $header, array $expected): void
    {
        $request = self::createProtoRequest($header);

        $worker = \Mockery::mock(WorkerInterface::class)->shouldIgnoreMissing();
        $worker->shouldReceive('waitPayload')->once()->andReturn(new Payload('foo', $request->serializeToString()));

        $worker = new HttpWorker($worker);

        Assert::equals($worker->waitRequest(), new Request(...$expected));
    }

    #[DataProvider('emptyRequestDataProvider')]
    public function testWaitRequestWithEmptyData(?Payload $payload): void
    {
        $worker = \Mockery::mock(WorkerInterface::class)->shouldIgnoreMissing();
        $worker->shouldReceive('waitPayload')->once()->andReturn($payload);

        $worker = new HttpWorker($worker);

        Assert::equals($worker->waitRequest(), null);
    }

    public function testEmptyBodyShouldBeConvertedIntoEmptyArrayWithParsedTrue(): void
    {
        $request = self::createProtoRequest(\array_merge(self::REQUIRED_PAYLOAD_DATA, ['parsed' => true]));

        $worker = \Mockery::mock(WorkerInterface::class)->shouldIgnoreMissing();
        $worker->shouldReceive('waitPayload')->once()->andReturn(new Payload('', $request->serializeToString()));

        $worker = new HttpWorker($worker);

        $request = $worker->waitRequest();
        Assert::same($request->getParsedBody(), []);
    }

    public function testRespondUnableToSendBodyWithInfoStatusException(): void
    {
        $worker = new HttpWorker(\Mockery::mock(WorkerInterface::class)->shouldIgnoreMissing());

        Expect::exception(\InvalidArgumentException::class)->withMessageContaining('Unable to send a body with informational status code.');
        $worker->respond(100, 'foo');
    }

    public function testRespondWithProtoCodec(): void
    {
        $expectedHeader = new Response([
            'status' => 200,
            'headers' => ['Content-Type' => new HeaderValue(['value' => ['application/x-www-form-urlencoded']])],
        ]);

        $worker = \Mockery::mock(WorkerInterface::class)->shouldIgnoreMissing();
        $worker->shouldReceive('respond')->once()->with(\Mockery::isEqual(new Payload('foo', $expectedHeader->serializeToString())), Frame::CODEC_PROTO, \Mockery::andAnyOtherArgs());

        (new \ReflectionProperty(HttpWorker::class, 'codec'))->setValue(Frame::CODEC_PROTO);
        $worker = new HttpWorker($worker);

        $worker->respond(200, 'foo', ['Content-Type' => ['application/x-www-form-urlencoded']]);
    }

    #[DataProvider('headersDataProvider')]
    public function testRespondWithProtoCodecWithHeaders(array $headers, array $expected): void
    {
        $expectedHeader = new Response(['status' => 200, 'headers' => $expected]);

        $worker = \Mockery::mock(WorkerInterface::class)->shouldIgnoreMissing();
        $worker->shouldReceive('respond')->once()->with(\Mockery::isEqual(new Payload('foo', $expectedHeader->serializeToString())), Frame::CODEC_PROTO, \Mockery::andAnyOtherArgs());

        (new \ReflectionProperty(HttpWorker::class, 'codec'))->setValue(Frame::CODEC_PROTO);
        $worker = new HttpWorker($worker);

        $worker->respond(200, 'foo', $headers);
    }

    public function testRespondWithJsonCodec(): void
    {
        $worker = \Mockery::mock(WorkerInterface::class)->shouldIgnoreMissing();
        $worker->shouldReceive('respond')->once()->with(\Mockery::isEqual(new Payload('foo', \json_encode([
            'status' => 200,
            'headers' => ['Content-Type' => ['application/x-www-form-urlencoded']],
        ]))), Frame::CODEC_JSON, \Mockery::andAnyOtherArgs());

        (new \ReflectionProperty(HttpWorker::class, 'codec'))->setValue(Frame::CODEC_JSON);
        $worker = new HttpWorker($worker);

        $worker->respond(200, 'foo', ['Content-Type' => ['application/x-www-form-urlencoded']]);
    }

    public function testGetWorkerReturnsWrappedWorker(): void
    {
        $worker = \Mockery::mock(WorkerInterface::class);

        Assert::same((new HttpWorker($worker))->getWorker(), $worker);
    }

    #[DataSet([true, ['Hel', 'lo', ''], [false, false, true]], 'ended stream gets a closing frame')]
    #[DataSet([false, ['Hel', 'lo'], [false, false]], 'open stream gets no empty frame')]
    public function testRespondStreamThroughNonStreamWorker(bool $endOfStream, array $bodies, array $eos): void
    {
        $sent = [];
        $worker = \Mockery::mock(WorkerInterface::class);
        $worker->shouldReceive('getPayload')->andReturn(null);
        $worker->shouldReceive('respond')->andReturnUsing(static function (Payload $payload) use (&$sent): void {
            $sent[] = $payload;
        });

        (new HttpWorker($worker))->respond(200, (static function (): \Generator {
            yield 'Hel';
            yield 'lo';
        })(), endOfStream: $endOfStream);

        Assert::same(\array_map(static fn(Payload $p): string => $p->body, $sent), $bodies);
        Assert::same(\array_map(static fn(Payload $p): bool => $p->eos, $sent), $eos);
    }

    #[AfterTest]
    protected function tearDown(): void
    {
        (new \ReflectionProperty(HttpWorker::class, 'codec'))->setValue(null);
    }

    private static function createProtoRequest(array $values): \RoadRunner\HTTP\DTO\V1\Request
    {
        $toHeaderValue = static function (string $key, bool $wrap = true) use (&$values): void {
            if (isset($values[$key])) {
                foreach ($values[$key] as $valueKey => $value) {
                    $values[$key][$valueKey] = new HeaderValue(['value' => $wrap ? [$value] : $value]);
                }
            }
        };

        $toHeaderValue('headers', false);
        $toHeaderValue('attributes');
        $toHeaderValue('cookies');

        return new \RoadRunner\HTTP\DTO\V1\Request([
            'remote_addr' => $values['remoteAddr'],
            'protocol' => $values['protocol'],
            'method' => $values['method'],
            'uri' => $values['uri'],
            'header' => $values['headers'] ?? [],
            'cookies' => $values['cookies'] ?? [],
            'raw_query' => $values['rawQuery'],
            'parsed' => $values['parsed'],
            'uploads' => \json_encode($values['uploads'] ?? []),
            'attributes' => $values['attributes'] ?? [],
        ]);
    }
}
