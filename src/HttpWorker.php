<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Http;

use Generator;
use Spiral\RoadRunner\Message\Command\StreamStop;
use Spiral\RoadRunner\Payload;
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

        /** @var RequestContext $context */
        $context = \json_decode($payload->header, true, 512, \JSON_THROW_ON_ERROR);

        return $this->createRequest($payload->body, $context);
    }

    /**
     * @throws \JsonException
     */
    public function respond(int $status, string|Generator $body, array $headers = []): void
    {
        if ($body instanceof Generator) {
            $this->respondStream($status, $body, $headers);
            return;
        }

        $head = \json_encode([
            'status'  => $status,
            'headers' => $headers ?: (object)[],
        ], \JSON_THROW_ON_ERROR);

        $this->worker->respond(new Payload($body, $head));
    }

    private function respondStream(int $status, Generator $body, array $headers = []): void
    {
        $head = \json_encode([
            'status'  => $status,
            'headers' => $headers ?: (object)[],
        ], \JSON_THROW_ON_ERROR);

        do {
            if (!$body->valid()) {
                $content = (string)$body->getReturn();
                $this->worker->respond(new Payload($content, $head, true));
                break;
            }
            $content = (string)$body->current();
            if ($this->worker->getPayload(StreamStop::class) !== null) {
                $body->throw(new \RuntimeException('Stream has been stopped by the client.'));
                return;
            }
            $this->worker->respond(new Payload($content, $head, false));
            $body->next();
            $head = null;
        } while (true);
    }

    /**
     * @param RequestContext $context
     */
    private function createRequest(string $body, array $context): Request
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
                // ignore invalid header names or values (otherwise, the worker will be crashed)
                // @see: <https://git.io/JzjgJ>
                unset($headers[$key]);
            }
        }

        /** @var HeadersList $headers */
        return $headers;
    }
}
