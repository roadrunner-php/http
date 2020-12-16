<?php

/**
 * High-performance PHP process supervisor and load balancer written in Go. Http core.
 */

declare(strict_types=1);

namespace Spiral\RoadRunner\Http;

use Spiral\RoadRunner\WorkerInterface;

interface WorkerAwareInterface
{
    /**
     * Returns underlying binary worker.
     *
     * @return WorkerInterface
     */
    public function getWorker(): WorkerInterface;
}
