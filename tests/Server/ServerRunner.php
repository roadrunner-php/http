<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Tests\Http\Server;


use RuntimeException;
use Symfony\Component\Process\PhpProcess;
use Symfony\Component\Process\Process;

class ServerRunner
{
    private static ?Process $process = null;
    private static string $output = '';

    public static function start(int $timeout = 5): void
    {
        self::$process = new Process(['php', 'run_server.php'], __DIR__);
        $run = false;
        self::$process->setTimeout($timeout);
        self::$process->start(static  function (string $type, string $output) use (&$run) {
            if (!$run && $type === Process::OUT && \str_contains($output, 'Server started')) {
                $run = true;
            }
            if ($type === Process::OUT) {
                self::$output .= $output;
            }
            // echo $output;
        });

        if (!self::$process->isRunning()) {
            throw new RuntimeException('Error starting Server: ' . self::$process->getErrorOutput());
        }

        // wait for roadrunner to start
        $ticks = $timeout * 10;
        while (!$run && $ticks > 0) {
            self::$process->getStatus();
            \usleep(100000);
            --$ticks;
        }

        if (!$run) {
            throw new RuntimeException('Error starting Server: timeout');
        }
    }

    public static function stop(): void
    {
        self::$process?->stop(0, 0);
    }

    public static function getBuffer(): string
    {
        self::$process->getStatus();
        $result = self::$output;
        self::$output = '';
        return $result;
    }
}
