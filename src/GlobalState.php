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
     * @return non-empty-array<array-key|string, mixed|string>
     */
    public static function populateServer(Request $request): array
    {
        static $server = [];

        if ([] == $server) {
            $server = $_SERVER;
        }

        $server['REQUEST_URI'] = $request->uri;
        $server['REQUEST_TIME'] = time();
        $server['REQUEST_TIME_FLOAT'] = microtime(true);
        $server['REMOTE_ADDR'] = $request->getRemoteAddr();
        $server['REQUEST_METHOD'] = $request->method;
        $server['HTTP_USER_AGENT'] = '';

        foreach ($request->headers as $key => $value) {
            $key = strtoupper(str_replace('-', '_', $key));

            if ($key == 'CONTENT_TYPE' || $key == 'CONTENT_LENGTH') {
                $server[$key] = implode(', ', $value);

                continue;
            }

            $server['HTTP_' . $key] = implode(', ', $value);
        }

        return $server;
    }
}
