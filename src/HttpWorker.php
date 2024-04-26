<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Http;

use Generator;
use RoadRunner\HTTP\DTO\V1\HeaderValue;
use RoadRunner\HTTP\DTO\V1\Request as RequestProto;
use RoadRunner\HTTP\DTO\V1\Response;
use Spiral\Goridge\Frame;
use Spiral\RoadRunner\Http\Exception\StreamStoppedException;
use Spiral\RoadRunner\Message\Command\StreamStop;
use Spiral\RoadRunner\Payload;
use Spiral\RoadRunner\StreamWorkerInterface;
use Spiral\RoadRunner\WorkerInterface;

/**
 * @psalm-import-type HeadersList from Request
 * @psalm-import-type AttributesList from Request
 * @psalm-import-type UploadedFilesList from Request
 * @psalm-import-type CookiesList from Request
 *
 * @psalm-type RequestContext = array{
 *      remoteAddr: non-empty-string,
 *      protocol:   non-empty-string,
 *      method:     non-empty-string,
 *      uri:        string,
 *      attributes: AttributesList,
 *      headers:    HeadersList,
 *      cookies:    CookiesList,
 *      uploads:    UploadedFilesList|null,
 *      rawQuery:   string,
 *      parsed:     bool
 * }
 *
 * @see Request
 */
class HttpWorker implements HttpWorkerInterface
{
    private static ?int $codec = null;

    public function __construct(
        private readonly WorkerInterface $worker,
    ) {
    }

    public function getWorker(): WorkerInterface
    {
        return $this->worker;
    }

    /**
     * @throws \JsonException
     */
    public function waitRequest(): ?Request
    {
        $payload = $this->worker->waitPayload();

        // Termination request
        if ($payload === null || (!$payload->body && !$payload->header)) {
            return null;
        }

        if (static::$codec === null) {
            static::$codec = \json_validate($payload->header) ? Frame::CODEC_JSON : Frame::CODEC_PROTO;
        }

        if (static::$codec === Frame::CODEC_PROTO) {
            $message = new RequestProto();
            $message->mergeFromString($payload->header);

            return $this->requestFromProto($payload->body, $message);
        }

        /** @var RequestContext $context */
        $context = \json_decode($payload->header, true, 512, \JSON_THROW_ON_ERROR);

        return $this->arrayToRequest($payload->body, $context);
    }

    /**
     * @param array<array-key, array<array-key, string>> $headers
     * @throws \JsonException
     */
    public function respond(int $status, string|Generator $body = '', array $headers = [], bool $endOfStream = true): void
    {
        if ($status < 200 && $status >= 100 && $body !== '') {
            throw new \InvalidArgumentException('Unable to send a body with informational status code.');
        }

        if ($body instanceof Generator) {
            $this->respondStream($status, $body, $headers, $endOfStream);
            return;
        }

        /** @psalm-suppress TooManyArguments */
        $this->worker->respond($this->createRespondPayload($status, $body, $headers, $endOfStream), static::$codec);
    }

    /**
     * @param array<array-key, array<array-key, string>> $headers
     */
    private function respondStream(int $status, Generator $body, array $headers = [], bool $endOfStream = true): void
    {
        $worker = $this->worker instanceof StreamWorkerInterface
            ? $this->worker->withStreamMode()
            : $this->worker;

        do {
            if (!$body->valid()) {
                // End of generator
                $content = (string)$body->getReturn();
                if ($endOfStream === false && $content === '') {
                    // We don't need to send an empty frame if the stream is not ended
                    return;
                }
                /** @psalm-suppress TooManyArguments */
                $worker->respond(
                    $this->createRespondPayload($status, $content, $headers, $endOfStream),
                    static::$codec
                );
                break;
            }

            $content = (string)$body->current();
            if ($worker->getPayload(StreamStop::class) !== null) {
                $body->throw(new StreamStoppedException());

                // RoadRunner is waiting for a Stream Stop Frame to confirm that the stream is closed
                // and the worker doesn't hang
                $worker->respond(new Payload(''));
                return;
            }

            /**
             * Send a chunk of data
             * @psalm-suppress TooManyArguments
             */
            $worker->respond($this->createRespondPayload($status, $content, $headers, false), static::$codec);

            try {
                $body->next();
            } catch (\Throwable) {
                // Stop the stream if an exception is thrown from the generator
                $worker->respond(new Payload(''));
                return;
            }
        } while (true);
    }

    /**
     * @param RequestContext $context
     */
    private function arrayToRequest(string $body, array $context): Request
    {
        \parse_str($context['rawQuery'], $query);
        return new Request(
            remoteAddr: $context['remoteAddr'],
            protocol: $context['protocol'],
            method: $context['method'],
            uri: $context['uri'],
            headers: $this->filterHeaders((array)($context['headers'] ?? [])),
            cookies: (array)($context['cookies'] ?? []),
            uploads: (array)($context['uploads'] ?? []),
            attributes: [
                Request::PARSED_BODY_ATTRIBUTE_NAME => $context['parsed'],
            ] + (array)($context['attributes'] ?? []),
            query: $query,
            body: $body,
            parsed: $context['parsed'],
        );
    }

    private function requestFromProto(string $body, RequestProto $message): Request
    {
        /** @var UploadedFilesList $uploads */
        $uploads = \json_decode($message->getUploads(), true) ?? [];
        $headers = $this->headerValueToArray($message->getHeader());

        \parse_str($message->getRawQuery(), $query);
        /** @psalm-suppress ArgumentTypeCoercion, MixedArgumentTypeCoercion */
        return new Request(
            remoteAddr: $message->getRemoteAddr(),
            protocol: $message->getProtocol(),
            method: $message->getMethod(),
            uri: $message->getUri(),
            headers: $this->filterHeaders($headers),
            cookies: \array_map(
                static fn(array $values) => \implode(',', $values),
                $this->headerValueToArray($message->getCookies()),
            ),
            uploads: $uploads,
            attributes: [
                Request::PARSED_BODY_ATTRIBUTE_NAME => $message->getParsed(),
            ] + \array_map(
                static fn(array $values) => \array_shift($values),
                $this->headerValueToArray($message->getAttributes()),
            ),
            query: $query,
            body: $message->getParsed() && $body === '' ? '{}' : $body,
            parsed: $message->getParsed(),
        );
    }

    /**
     * Remove all non-string and empty-string keys
     *
     * @param array<array-key, array<array-key, string>> $headers
     * @return HeadersList
     */
    private function filterHeaders(array $headers): array
    {
        foreach ($headers as $key => $_) {
            if (!\is_string($key) || $key === '') {
                // ignore invalid header names or values (otherwise, the worker might be crashed)
                // @see: <https://git.io/JzjgJ>
                unset($headers[$key]);
            }
        }

        /** @var HeadersList $headers */
        return $headers;
    }

    /**
     * @param \Traversable<non-empty-string, HeaderValue> $message
     */
    private function headerValueToArray(\Traversable $message): array
    {
        $result = [];
        /**
         * @var non-empty-string $key
         * @var HeaderValue $value
         */
        foreach ($message as $key => $value) {
            $result[$key] = \iterator_to_array($value->getValue());
        }

        return $result;
    }

    /**
     * @param array<array-key, array<array-key, string>> $headers
     * @return array<non-empty-string, HeaderValue>
     */
    private function arrayToHeaderValue(array $headers = []): array
    {
        $result = [];
        /**
         * @var non-empty-string $key
         * @var array<array-key, string> $value
         */
        foreach ($headers as $key => $value) {
            /** @psalm-suppress DocblockTypeContradiction */
            $value = \array_filter(\is_array($value) ? $value : [$value], static fn (mixed $v): bool => \is_string($v));
            if ($value !== []) {
                $result[$key] = new HeaderValue(['value' => $value]);
            }
        }

        return $result;
    }

    /**
     * @param array<array-key, array<array-key, string>> $headers
     */
    private function createRespondPayload(int $status, string $body, array $headers = [], bool $eos = true): Payload
    {
        $head = static::$codec === Frame::CODEC_PROTO
            ? (new Response(['status' => $status, 'headers' => $this->arrayToHeaderValue($headers)]))
                ->serializeToString()
            : \json_encode(['status' => $status, 'headers' => $headers ?: (object)[]], \JSON_THROW_ON_ERROR);

        return new Payload(body: $body, header: $head, eos: $eos);
    }
}
