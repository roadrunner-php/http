<?php

/**
 * High-performance PHP process supervisor and load balancer written in Go. Http core.
 */

declare(strict_types=1);

namespace Spiral\RoadRunner\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Spiral\RoadRunner\WorkerAwareInterface;

interface PSR7WorkerInterface extends WorkerAwareInterface
{
    /**
     * @return ServerRequestInterface|null
     */
    public function waitRequest(): ?ServerRequestInterface;

    /**
     * Send response to the application server.
     *
     * @param ResponseInterface $response
     */
    public function respond(ResponseInterface $response): void;
}
