<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Http;

use function time;
use function microtime;
use function strtoupper;
use function str_replace;
use function implode;

final class GlobalState
{
    /**
     * Sets ip-address, request-time and other values.
     *
     * @return  non-empty-array<array-key|string, mixed|string>
     */
    public static function populateServer(Request $request): array
    {
        /** @var non-empty-array<array-key|string, mixed|string>|null $originalServer */
        static $originalServer = null;

        if ($originalServer == null) {
            $originalServer = $_SERVER;
        }

        $newServer = $originalServer;

        $newServer['REQUEST_URI'] = $request->uri;
        $newServer['REQUEST_TIME'] = time();
        $newServer['REQUEST_TIME_FLOAT'] = microtime(true);
        $newServer['REMOTE_ADDR'] = $request->getRemoteAddr();
        $newServer['REQUEST_METHOD'] = $request->method;
        $newServer['HTTP_USER_AGENT'] = '';

        foreach ($request->headers as $key => $value) {
            $key = strtoupper(str_replace('-', '_', $key));

            if ($key == 'CONTENT_TYPE' || $key == 'CONTENT_LENGTH') {
                $newServer[$key] = implode(', ', $value);

                continue;
            }

            $newServer['HTTP_' . $key] = implode(', ', $value);
        }

        return $newServer;
    }
}
