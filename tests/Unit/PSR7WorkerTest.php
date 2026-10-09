<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Tests\Http\Unit;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Spiral\Goridge\Frame;
use Spiral\RoadRunner\Http\GlobalState;
use Spiral\RoadRunner\Http\HttpWorker;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Http\Request;
use Spiral\RoadRunner\Payload;
use Spiral\RoadRunner\Tests\Http\Unit\Stub\TestRelay;
use Spiral\RoadRunner\Worker;
use Spiral\RoadRunner\WorkerInterface;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataSet;
use Testo\Lifecycle\AfterTest;
use Testo\Test;

#[Covers(PSR7Worker::class)]
#[Covers(GlobalState::class)]
#[Test]
final class PSR7WorkerTest
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

            Assert::equals($_SERVER, $expectedServer);
        }
    }

    public function testGetWorkerReturnsWrappedWorker(): void
    {
        $worker = \Mockery::mock(WorkerInterface::class);

        $psrWorker = self::createPsrWorker($worker);

        Assert::same($psrWorker->getWorker(), $worker);
        Assert::same($psrWorker->getHttpWorker()->getWorker(), $worker);
    }

    public function testWaitRequestReturnsNullOnTermination(): void
    {
        $worker = \Mockery::mock(WorkerInterface::class);
        $worker->shouldReceive('waitPayload')->once()->andReturn(null);

        Assert::null(self::createPsrWorker($worker)->waitRequest());
    }

    public function testWaitRequestMapsRequest(): void
    {
        $worker = self::createWorkerWithRequest([
            'method' => 'POST',
            'uri' => 'http://localhost/path?foo=bar',
            'rawQuery' => 'foo=bar&list[]=1&list[]=2',
            'headers' => ['Content-Type' => ['text/plain'], 'X-Multi' => ['a', 'b']],
            'cookies' => ['theme' => 'dark'],
            'attributes' => ['route' => 'home'],
        ], 'raw body');

        $request = self::createPsrWorker($worker)->waitRequest(populateServer: false);

        Assert::same($request->getMethod(), 'POST');
        Assert::same((string) $request->getUri(), 'http://localhost/path?foo=bar');
        Assert::same($request->getProtocolVersion(), '1.1');
        Assert::same($request->getQueryParams(), ['foo' => 'bar', 'list' => ['1', '2']]);
        Assert::same($request->getCookieParams(), ['theme' => 'dark']);
        Assert::same($request->getHeader('Content-Type'), ['text/plain']);
        Assert::same($request->getHeader('X-Multi'), ['a', 'b']);
        Assert::same($request->getAttribute('route'), 'home');
        Assert::false($request->getAttribute(Request::PARSED_BODY_ATTRIBUTE_NAME));
        Assert::null($request->getParsedBody());
        Assert::same((string) $request->getBody(), 'raw body');
        Assert::same($request->getServerParams()['REQUEST_METHOD'], 'POST');
    }

    public function testWaitRequestDecodesParsedBody(): void
    {
        $worker = self::createWorkerWithRequest(['parsed' => true], '{"name":"value","list":[1,2]}');

        $request = self::createPsrWorker($worker)->waitRequest(populateServer: false);

        Assert::same($request->getParsedBody(), ['name' => 'value', 'list' => [1, 2]]);
        Assert::true($request->getAttribute(Request::PARSED_BODY_ATTRIBUTE_NAME));
    }

    public function testWaitRequestWithoutBodyHasEmptyBody(): void
    {
        $worker = self::createWorkerWithRequest([], '');

        $request = self::createPsrWorker($worker)->waitRequest(populateServer: false);

        Assert::same((string) $request->getBody(), '');
    }

    public function testWaitRequestPopulatesServerGlobalByDefault(): void
    {
        $worker = self::createWorkerWithRequest(['method' => 'PUT'], '');
        $_SERVER = ['FOO' => 'bar'];

        $request = self::createPsrWorker($worker)->waitRequest();

        Assert::same($_SERVER, $request->getServerParams());
        Assert::same($_SERVER['REQUEST_METHOD'], 'PUT');
        Assert::false(\array_key_exists('FOO', $_SERVER));
    }

    public function testWaitRequestKeepsServerGlobalWhenPopulationIsDisabled(): void
    {
        $worker = self::createWorkerWithRequest(['method' => 'PUT'], '');
        $_SERVER = ['FOO' => 'bar'];

        self::createPsrWorker($worker)->waitRequest(populateServer: false);

        Assert::same($_SERVER, ['FOO' => 'bar']);
    }

    #[DataSet(['HTTP/1.0', '1.0'])]
    #[DataSet(['HTTP/1.1', '1.1'])]
    #[DataSet(['HTTP/2', '2'])]
    #[DataSet(['HTTP/2.0', '2'])]
    #[DataSet(['HTTP/3', '1.1'], 'unsupported version falls back to 1.1')]
    #[DataSet(['', '1.1'], 'empty protocol falls back to 1.1')]
    public function testWaitRequestNormalizesProtocolVersion(string $protocol, string $expected): void
    {
        $worker = self::createWorkerWithRequest(['protocol' => $protocol], '');

        $request = self::createPsrWorker($worker)->waitRequest(populateServer: false);

        Assert::same($request->getProtocolVersion(), $expected);
    }

    public function testWaitRequestWrapsUploadedFiles(): void
    {
        $tmpFile = (string) \tempnam(\sys_get_temp_dir(), 'rr-upload');
        \file_put_contents($tmpFile, 'file content');
        $file = static fn(string $name, int $error): array => [
            'name' => $name,
            'mime' => 'text/plain',
            'size' => 12,
            'error' => $error,
            'tmpName' => $tmpFile,
        ];
        $worker = self::createWorkerWithRequest([
            'uploads' => [
                'single' => $file('single.txt', \UPLOAD_ERR_OK),
                'failed' => $file('failed.txt', \UPLOAD_ERR_PARTIAL),
                'list' => [$file('first.txt', \UPLOAD_ERR_OK), $file('second.txt', \UPLOAD_ERR_OK)],
                'nested' => ['deep' => ['file' => $file('deep.txt', \UPLOAD_ERR_OK)]],
            ],
        ], '');

        try {
            $uploads = self::createPsrWorker($worker)->waitRequest(populateServer: false)->getUploadedFiles();

            $single = $uploads['single'];
            Assert::instanceOf($single, UploadedFileInterface::class);
            Assert::same($single->getClientFilename(), 'single.txt');
            Assert::same($single->getClientMediaType(), 'text/plain');
            Assert::same($single->getSize(), 12);
            Assert::same($single->getError(), \UPLOAD_ERR_OK);
            Assert::same((string) $single->getStream(), 'file content');

            Assert::same($uploads['failed']->getClientFilename(), 'failed.txt');
            Assert::same($uploads['failed']->getError(), \UPLOAD_ERR_PARTIAL);

            Assert::same($uploads['list'][0]->getClientFilename(), 'first.txt');
            Assert::same($uploads['list'][1]->getClientFilename(), 'second.txt');
            Assert::same($uploads['nested']['deep']['file']->getClientFilename(), 'deep.txt');
        } finally {
            \unlink($tmpFile);
        }
    }

    public function testRespondSendsWholeBodyWhenStreamingIsOff(): void
    {
        $sent = [];
        $psrWorker = self::createPsrWorker(self::createRespondingWorker($sent));

        $psrWorker->respond(new Response(201, ['X-Foo' => 'bar'], 'Hello, World!'));

        Assert::count($sent, 1);
        Assert::same($sent[0]->body, 'Hello, World!');
        Assert::true($sent[0]->eos);
        Assert::same(
            \json_decode($sent[0]->header, true),
            ['status' => 201, 'headers' => ['X-Foo' => ['bar']]],
        );
    }

    #[DataSet([1024, 'small body', ['small body']], 'body shorter than a chunk')]
    #[DataSet([4, 'Hello, World!', ['Hell', 'o, W', 'orld', '!']], 'last chunk is partial')]
    #[DataSet([4, 'abcdefgh', ['abcd', 'efgh']], 'body is a multiple of the chunk size')]
    public function testRespondStreamsBodyInChunks(int $chunkSize, string $body, array $expectedChunks): void
    {
        $sent = [];
        $psrWorker = self::createPsrWorker(self::createRespondingWorker($sent));
        $psrWorker->chunkSize = $chunkSize;

        $psrWorker->respond(new Response(200, [], $body));

        Assert::same(\array_map(static fn(Payload $p): string => $p->body, $sent), $expectedChunks);
        Assert::same(
            \array_map(static fn(Payload $p): bool => $p->eos, $sent),
            [...\array_fill(0, \count($expectedChunks) - 1, false), true],
        );
    }

    public function testRespondStreamsBodyOfUnknownSize(): void
    {
        $sent = [];
        $psrWorker = self::createPsrWorker(self::createRespondingWorker($sent));
        $psrWorker->chunkSize = 4;
        $stream = \Mockery::mock(StreamInterface::class);
        $stream->shouldReceive('rewind')->once();
        $stream->shouldReceive('getSize')->andReturn(null);
        $stream->shouldReceive('eof')->andReturn(false, false, true);
        $stream->shouldReceive('read')->with(4)->andReturn('abcd', 'ef');

        $psrWorker->respond((new Response(200))->withBody($stream));

        Assert::same(\array_map(static fn(Payload $p): string => $p->body, $sent), ['abcd', 'ef', '']);
        Assert::same(\array_map(static fn(Payload $p): bool => $p->eos, $sent), [false, false, true]);
    }

    #[AfterTest]
    protected function tearDown(): void
    {
        (new \ReflectionProperty(HttpWorker::class, 'codec'))->setValue(null);

        // Clean all extra output buffers
        $level = \ob_get_level();
        while (--$level > 0) {
            \ob_end_clean();
        }
    }

    private static function createPsrWorker(WorkerInterface $worker): PSR7Worker
    {
        $factory = new Psr17Factory();
        return new PSR7Worker($worker, $factory, $factory, $factory);
    }

    private static function createWorkerWithRequest(array $context, string $body): WorkerInterface
    {
        $context += [
            'remoteAddr' => '127.0.0.1',
            'protocol' => 'HTTP/1.1',
            'method' => 'GET',
            'uri' => 'http://localhost',
            'rawQuery' => '',
            'parsed' => false,
        ];

        $worker = \Mockery::mock(WorkerInterface::class);
        $worker->shouldReceive('waitPayload')->once()
            ->andReturn(new Payload($body, \json_encode($context, \JSON_THROW_ON_ERROR)));

        return $worker;
    }

    /**
     * @param list<Payload> $sent Receives every payload passed to {@see WorkerInterface::respond()}.
     */
    private static function createRespondingWorker(array &$sent): WorkerInterface
    {
        $worker = \Mockery::mock(WorkerInterface::class);
        $worker->shouldReceive('getPayload')->andReturn(null);
        $worker->shouldReceive('respond')->andReturnUsing(static function (Payload $payload) use (&$sent): void {
            $sent[] = $payload;
        });

        return $worker;
    }
}
