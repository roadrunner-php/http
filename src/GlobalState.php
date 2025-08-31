<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Http;

final class GlobalState
{
    /**
     * @var array<array-key, mixed> Cached state of the $_SERVER superglobal.
     */
    private static array $cachedServer = [];

    /**
     * Cache superglobal $_SERVER to avoid state leaks between requests.
     */
    public static function cacheServerVars(): void
    {
        self::$cachedServer = $_SERVER;
    }

    /**
     * Enrich cached $_SERVER with data from the request.
     *
     * @return non-empty-array<array-key, mixed> Cached $_SERVER data enriched with request data.
     */
    public static function enrichServerVars(Request $request): array
    {
        $server = self::$cachedServer;

        $server['REQUEST_URI'] = $request->uri;
        $server['REQUEST_TIME'] = \time();
        $server['REQUEST_TIME_FLOAT'] = \microtime(true);
        $server['REMOTE_ADDR'] = $request->getRemoteAddr();
        $server['REQUEST_METHOD'] = $request->method;
        $server['HTTP_USER_AGENT'] = '';

        foreach ($request->headers as $key => $value) {
            $key = \strtoupper(\str_replace('-', '_', $key));

            if ($key == 'CONTENT_TYPE' || $key == 'CONTENT_LENGTH') {
                $server[$key] = \implode(', ', $value);

                continue;
            }

            $server['HTTP_' . $key] = \implode(', ', $value);
        }

        return $server;
    }
}

GlobalState::cacheServerVars();
