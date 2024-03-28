<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Tests\Http\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RoadRunner\HTTP\DTO\V1\HeaderValue;
use Spiral\RoadRunner\Http\HttpWorker;
use Spiral\RoadRunner\Http\Request;
use Spiral\RoadRunner\Payload;
use Spiral\RoadRunner\WorkerInterface;

final class HttpWorkerTest extends TestCase
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
        'body' => 'foo'
    ];

    #[DataProvider('requestDataProvider')]
    public function testWaitRequestFromArray(array $header, array $expected): void
    {
        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects($this->once())
            ->method('waitPayload')
            ->willReturn(new Payload('foo', \json_encode($header)));

        $worker = new HttpWorker($worker);

        $this->assertEquals(new Request(...$expected), $worker->waitRequest());
    }

    #[DataProvider('requestDataProvider')]
    public function testWaitRequestFromProto(array $header, array $expected): void
    {
        $request = self::createProtoRequest($header);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects($this->once())
            ->method('waitPayload')
            ->willReturn(new Payload('foo', $request->serializeToString()));

        $worker = new HttpWorker($worker);

        $this->assertEquals(new Request(...$expected), $worker->waitRequest());
    }

    #[DataProvider('emptyRequestDataProvider')]
    public function testWaitRequestWithEmptyData(?Payload $payload): void
    {
        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects($this->once())
            ->method('waitPayload')
            ->willReturn($payload);

        $worker = new HttpWorker($worker);

        $this->assertEquals(null, $worker->waitRequest());
    }

    public static function requestDataProvider(): \Traversable
    {
        yield [self::REQUIRED_PAYLOAD_DATA, self::REQUIRED_REQUEST_DATA];
        yield [
            \array_merge(self::REQUIRED_PAYLOAD_DATA, ['parsed' => true]),
            \array_merge(
                self::REQUIRED_REQUEST_DATA,
                ['parsed' => true, 'attributes' => [Request::PARSED_BODY_ATTRIBUTE_NAME => true]]
            )
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
            ])
        ];
        yield [
            \array_merge(self::REQUIRED_PAYLOAD_DATA, [
                'cookies' => [
                    'theme' => 'light',
                ],
            ]),
            \array_merge(self::REQUIRED_REQUEST_DATA, [
                'cookies' => ['theme' => 'light'],
            ])
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
                        ]
                    ],
                    'nested' => [
                        'some-key' => [
                            'name' => 'test.png',
                            'mime' => 'image/png',
                            'size' => 123,
                            'error' => 0,
                            'tmpName' => '/tmp/php/php1h4j1o',
                        ],
                    ]
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
                        ]
                    ],
                    'nested' => [
                        'some-key' => [
                            'name' => 'test.png',
                            'mime' => 'image/png',
                            'size' => 123,
                            'error' => 0,
                            'tmpName' => '/tmp/php/php1h4j1o',
                        ],
                    ]
                ],
            ])
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
                    'foo' => 'bar'
                ],
            ])
        ];
    }

    public static function emptyRequestDataProvider(): \Traversable
    {
        yield [null];
        yield [new Payload(null, null)];
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

    protected function tearDown(): void
    {
        (new \ReflectionProperty(HttpWorker::class, 'codec'))->setValue(null);
    }
}
