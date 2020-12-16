<?php

/**
 * High-performance PHP process supervisor and load balancer written in Go. Http core.
 */

declare(strict_types=1);

namespace Spiral\RoadRunner\Http;

final class Request
{
    public string   $remoteAddr;
    public string   $protocol;
    public string   $method;
    public string   $uri;
    public array    $headers;
    public array    $cookies;
    public array    $uploads;
    public array    $attributes;
    public array    $query;
    public ?string  $body;
    public bool     $parsed;

    /**
     * @return string
     */
    public function getRemoteAddr(): string
    {
        return $this->attributes['ipAddress'] ?? $this->remoteAddr ?? '127.0.0.1';
    }

    /**
     * @return array|null
     */
    public function getParsedBody(): ?array
    {
        if ($this->parsed) {
            return json_decode($this->body, true);
        }

        return null;
    }
}
