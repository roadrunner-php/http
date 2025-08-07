<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Http;

use function time;
use function microtime;

class ConfiguratorServer
{
    /**
     * Returns altered copy of _SERVER variable. Sets ip-address,
     * request-time and other values.
     *
     * @return non-empty-array<array-key|string, mixed|string>
     */
    public function configure(Request $request): array
    {
        $_SERVER['REQUEST_URI'] = $request->uri;
        $_SERVER['REQUEST_TIME'] = time();
        $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true);
        $_SERVER['REMOTE_ADDR'] = $request->getRemoteAddr();
        $_SERVER['REQUEST_METHOD'] = $request->method;
        $_SERVER['HTTP_USER_AGENT'] = '';

        foreach ($request->headers as $key => $value) {
            $key = \strtoupper(\str_replace('-', '_', $key));

            if (\in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'])) {
                $_SERVER[$key] = \implode(', ', $value);

                continue;
            }

            $_SERVER['HTTP_' . $key] = \implode(', ', $value);
        }

        return $_SERVER;
    }
}
