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
     */
    public static function populateServer(Request $request): void
    {
        $_SERVER['REQUEST_URI'] = $request->uri;
        $_SERVER['REQUEST_TIME'] = time();
        $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true);
        $_SERVER['REMOTE_ADDR'] = $request->getRemoteAddr();
        $_SERVER['REQUEST_METHOD'] = $request->method;
        $_SERVER['HTTP_USER_AGENT'] = '';

        foreach ($request->headers as $key => $value) {
            $key = strtoupper(str_replace('-', '_', $key));

            if ($key == 'CONTENT_TYPE' || $key == 'CONTENT_LENGTH') {
                $_SERVER[$key] = implode(', ', $value);

                continue;
            }

            $_SERVER['HTTP_' . $key] = implode(', ', $value);
        }
    }
}
