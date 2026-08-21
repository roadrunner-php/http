<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Http;

use JetBrains\PhpStorm\Immutable;

/**
 * @psalm-immutable
 *
 * @psalm-type UploadedFile = array{
 *      name:       non-empty-string,
 *      error:      int<0, max>,
 *      tmpName:    non-empty-string,
 *      size:       int<0, max>,
 *      mime:       string
 * }
 *
 * Header names are keyed by `array-key`, not `non-empty-string`: a header
 * name made up entirely of digits (e.g. "123") is a valid RFC 9110 token,
 * but PHP always coerces such a key into an `int` when it's used as an
 * array key, so it cannot be represented as a string here.
 * @psalm-type HeadersList = array<array-key, array<array-key, string>>
 * @psalm-type AttributesList = array<string, mixed>
 * @psalm-type QueryArgumentsList = array
 * @psalm-type CookiesList = array<string, string>
 * @psalm-type UploadedFilesList = array<array-key, UploadedFile>
 *
 * @psalm-immutable
 */
#[Immutable]
final class Request
{
    public const PARSED_BODY_ATTRIBUTE_NAME = 'rr_parsed_body';

    /**
     * @param HeadersList $headers
     * @param CookiesList $cookies
     * @param UploadedFilesList $uploads
     * @param AttributesList $attributes
     * @param QueryArgumentsList $query
     */
    public function __construct(
        public readonly string $remoteAddr = '127.0.0.1',
        public readonly string $protocol = 'HTTP/1.0',
        public readonly string $method = 'GET',
        public readonly string $uri = 'http://localhost',
        public readonly array $headers = [],
        public readonly array $cookies = [],
        public readonly array $uploads = [],
        public readonly array $attributes = [],
        public readonly array $query = [],
        public readonly string $body = '',
        public readonly bool $parsed = false,
    ) {}

    public function getRemoteAddr(): string
    {
        return (string) ($this->attributes['ipAddress'] ?? $this->remoteAddr);
    }

    /**
     * @throws \JsonException
     */
    public function getParsedBody(): ?array
    {
        if ($this->parsed) {
            return (array) \json_decode($this->body, true, 512, \JSON_THROW_ON_ERROR);
        }

        return null;
    }
}
