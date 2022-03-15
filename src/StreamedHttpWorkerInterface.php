<?php

/**
 * This file is part of RoadRunner package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\RoadRunner\Http;

use Generator;
use Stringable;

interface StreamedHttpWorkerInterface extends HttpWorkerInterface
{
    /**
     * Send streamed response to the application server.
     *
     * @param int $status Http status code
     * @param Generator<array-key, string|Stringable, void, void> $body Streamed body of response
     * @param array $headers An associative array of the message's headers
     * @param array $trailed An associative array of trailed headers (not supported yet)
     */
    public function respondStream(int $status, Generator $body, array $headers = [], array $trailed = []): void;
}
