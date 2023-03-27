<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Tests\Http\Server;

include __DIR__ . '/../../vendor/autoload.php';

$server = Server::init(6002);

while (true) {
    $server->process();
    \usleep(5_000);
}
