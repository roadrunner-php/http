<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Tests\Http\Unit\Stub;

use Spiral\Goridge\Frame;
use Spiral\Goridge\Relay;

final class TestRelay extends Relay
{
    /** @var Frame[] */
    private array $frames = [];

    /** @var Frame[] */
    private array $received = [];

    public function addFrames(Frame ...$frames): self
    {
        $this->frames = [...$this->frames, ...\array_values($frames)];
        return $this;
    }

    public function addFrame(
        int $status = 200,
        string $body = '',
        array $headers = [],
        bool $stream = false,
        bool $stopStream = false,
    ): self {
        $head = (string)\json_encode([
            'status'  => $status,
            'headers' => $headers,
        ], \JSON_THROW_ON_ERROR);
        $frame = new Frame($head .$body, [\strlen($head)]);
        $frame->byte10 |= $stream ? Frame::BYTE10_STREAM : 0;
        $frame->byte10 |= $stopStream ? Frame::BYTE10_STOP : 0;
        return $this->addFrames($frame);
    }

    public function addStopStreamFrame(): self
    {
        return $this->addFrame(stopStream: true);
    }

    public function getReceived(): array
    {
        return $this->received;
    }

    public function getReceivedBody(): string
    {
        return \implode('', \array_map(static fn (Frame $frame)
            => \substr($frame->payload, $frame->options[0]), $this->received));
    }

    public function waitFrame(): Frame
    {
        if ($this->frames === []) {
            throw new \RuntimeException('There are no frames to return.');
        }

        return \array_shift($this->frames);
    }

    public function send(Frame $frame): void
    {
        $this->received[] = $frame;
    }

    public function hasFrame(): bool
    {
        return $this->frames !== [];
    }
}
