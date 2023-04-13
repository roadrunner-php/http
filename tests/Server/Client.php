<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Tests\Http\Server;

use Fiber;
use Spiral\Goridge\Frame;
use Spiral\RoadRunner\Tests\Http\Server\Command\BaseCommand;
use Spiral\RoadRunner\Tests\Http\Server\Command\StreamStop;

/**
 * Client state on the server side.
 */
class Client
{
    private \Socket $socket;

    /** @var string[] */
    private array $writeQueue = [];

    /** @var string */
    private string $readBuffer = '';

    public function __construct(
        \Socket $socket,
    ) {
        $this->socket = $socket;
        \socket_set_nonblock($this->socket);
    }

    public function __destruct()
    {
        \socket_close($this->socket);
    }

    public static function init(\Socket $socket): self
    {
        return new self($socket);
    }

    public function process(): void
    {
        $this->onInit();

        do {
            $read = [$this->socket];
            $write = [$this->socket];
            $except = [$this->socket];
            if (\socket_select($read, $write, $except, 0, 0) === false) {
                throw new \RuntimeException('Socket select failed.');
            }

            if ($read !== []) {
                $this->readMessage();
            }

            if ($write !== [] && $this->writeQueue !== []) {
                $this->writeQueue();
            }

            Fiber::suspend();
        } while (true);
    }

    private function onInit()
    {
        $this->writeQueue[] =  Frame::packFrame(new Frame('{"pid":true}', [], Frame::CONTROL));
    }

    private function onFrame(Frame $frame): void
    {
        $command = $this->getCommand($frame);

        if ($command === null) {
            echo \substr($frame->payload, $frame->options[0]) . "\n";
            return;
        }

        $this->onCommand($command);
    }

    private function writeQueue(): void
    {
        foreach ($this->writeQueue as $data) {
            \socket_write($this->socket, $data);
        }
        socket_set_nonblock($this->socket);

        $this->writeQueue = [];
    }

    /**
     * @see \Spiral\Goridge\SocketRelay::waitFrame()
     */
    private function readMessage(): void
    {
        $header = $this->readNBytes(12);

        $parts = Frame::readHeader($header);
        // total payload length
        $length = $parts[1] * 4 + $parts[2];

        if ($length >= 8 * 1024 * 1024) {
            throw new \RuntimeException('Frame payload is too large.');
        }
        $payload = $this->readNBytes($length);

        $frame = Frame::initFrame($parts, $payload);

        $this->onFrame($frame);
    }

    /**
     * @param positive-int $bytes
     *
     * @return non-empty-string
     */
    private function readNBytes(int $bytes, bool $canBeLess = false): string
    {
        while (($left = $bytes - \strlen($this->readBuffer)) > 0) {
            $data = @\socket_read($this->socket, $left, \PHP_BINARY_READ);
            if ($data === false) {
                $errNo = \socket_last_error($this->socket);
                throw new \RuntimeException('Socket read failed [' . $errNo . ']: ' . \socket_strerror($errNo));
            }

            if ($canBeLess) {
                return $data;
            }

            if ($data === '') {
                Fiber::suspend();
                continue;
            }

            $this->readBuffer .= $data;
        }

        $result = \substr($this->readBuffer, 0, $bytes);
        $this->readBuffer = \substr($this->readBuffer, $bytes);

        return $result;
    }

    private function getCommand(Frame $frame): ?BaseCommand
    {
        $payload = $frame->payload;
        try {
            $data = \json_decode($payload, true, 3, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return match (false) {
            \is_array($data),
            \array_key_exists(BaseCommand::COMMAND_KEY, $data),
            \is_string($data[BaseCommand::COMMAND_KEY]),
            \class_exists($data[BaseCommand::COMMAND_KEY]),
            \is_a($data[BaseCommand::COMMAND_KEY], BaseCommand::class, true) => null,
            default => new ($data[BaseCommand::COMMAND_KEY])(),
        };
    }

    private function onCommand(BaseCommand $command): void
    {
        switch ($command::class) {
            case StreamStop::class:
                $this->writeQueue[] = $command->getResponse();
                break;
        }
    }
}