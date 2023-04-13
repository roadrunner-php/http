<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Tests\Http\Server\Command;

use Spiral\Goridge\Frame;

final class StreamStop extends BaseCommand
{
    public function getResponseFrame(): Frame
    {
        $frame = new Frame('', [0]);
        $frame->byte10 |= Frame::BYTE10_STOP;

        return $frame;
    }
}
