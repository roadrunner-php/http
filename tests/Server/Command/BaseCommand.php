<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Tests\Http\Server\Command;

use Spiral\Goridge\Frame;

abstract class BaseCommand
{
    public const COMMAND_KEY = 'test-command';
    protected Frame $frame;

    public function __construct() {
        $this->frame = new Frame(\json_encode([self::COMMAND_KEY => static::class]));
    }

    public function getRequestFrame(): Frame
    {
        return $this->frame;
    }

    public function getResponse(): string
    {
        return Frame::packFrame($this->getResponseFrame());
    }

    public abstract function getResponseFrame(): Frame;
}