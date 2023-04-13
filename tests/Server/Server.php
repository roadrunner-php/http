<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Tests\Http\Server;

use Fiber;
use RuntimeException;
use Socket;

class Server
{
    /** @var false|resource|Socket */
    private $socket;

    /** @var Client[] */
    private array $clients = [];

    /** @var Fiber[] */
    private array $fibers = [];

    public function __construct(
        int $port,
    ) {
        $this->socket = \socket_create_listen($port);
        if ($this->socket === false) {
            throw new \RuntimeException('Socket create failed.');
        }
        \socket_set_nonblock($this->socket);

        echo "Server started\n";
    }

    public function __destruct()
    {
        \socket_close($this->socket);
    }

    public static function init(int $port = 6002): self
    {
        return new self($port);
    }

    public function process(): void
    {
        $client = \socket_accept($this->socket);
        if ($client !== false) {
            $key = \array_key_last($this->clients) + 1;
            try {
                $this->clients[$key] = Client::init($client);
                $this->fibers[$key] = new Fiber($this->clients[$key]->process(...));
            } catch (\Throwable) {
                unset($this->clients[$key], $this->fibers[$key]);
            }
        }

        foreach ($this->fibers as $key => $fiber) {
            try {
                $fiber->isStarted() ? $fiber->resume() : $fiber->start();

                if ($fiber->isTerminated()) {
                    throw new RuntimeException('Client terminated.');
                }
            } catch (\Throwable) {
                unset($this->clients[$key], $this->fibers[$key]);
            }
        }
    }
}
