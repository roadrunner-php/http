<p align="center">
    <a href="https://roadrunner.dev"><picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://github.com/roadrunner-server/.github/assets/8040338/e6bde856-4ec6-4a52-bd5b-bfe78736c1ff">
        <img alt="RoadRunner" src="https://github.com/roadrunner-server/.github/assets/8040338/040fb694-1dd3-4865-9d29-8e0748c2c8b8" style="width: 6in; display: block">
    </picture></a>
</p>

<p align="center">PSR-7 HTTP worker for the RoadRunner application server</p>

<div align="center">

[![Documentation](https://img.shields.io/badge/Documentation-blue?style=for-the-badge&logo=gitbook&logoColor=white)](https://docs.roadrunner.dev/docs/http/http)
[![Sponsor](https://img.shields.io/static/v1?style=for-the-badge&label=&message=Sponsor&logo=githubsponsors&logoColor=white&color=%23EA4AAA)](https://github.com/sponsors/roadrunner-server)

[![Psalm Level](https://shepherd.dev/github/roadrunner-php/http/level.svg)](https://shepherd.dev/github/roadrunner-php/http)
[![Type Coverage](https://shepherd.dev/github/roadrunner-php/http/coverage.svg)](https://shepherd.dev/github/roadrunner-php/http)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Froadrunner-php%2Fhttp%2F4.x)](https://dashboard.stryker-mutator.io/reports/github.com/roadrunner-php/http/4.x)

</div>

<br />

RoadRunner HTTP is the PHP side of the [RoadRunner](https://github.com/roadrunner-server/roadrunner) HTTP plugin.
It turns requests served by RoadRunner into PSR-7 objects and sends PSR-7 responses back, including streamed responses and HTTP 103 Early Hints.

## Get Started

### Installation

```bash
composer require roadrunner/http
```

[![PHP](https://img.shields.io/packagist/php-v/roadrunner/http.svg?style=flat-square&logo=php)](https://packagist.org/packages/roadrunner/http)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/roadrunner/http.svg?style=flat-square&logo=packagist)](https://packagist.org/packages/roadrunner/http)
[![License](https://img.shields.io/packagist/l/roadrunner/http.svg?style=flat-square)](LICENSE)
[![Total Downloads](https://img.shields.io/packagist/dt/roadrunner/http.svg?style=flat-square)](https://packagist.org/packages/roadrunner/http/stats)

The worker needs a [PSR-17 implementation](https://packagist.org/providers/psr/http-factory-implementation), for example
`nyholm/psr7`, which is used in the examples below:

```bash
composer require nyholm/psr7
```

The RoadRunner binary can be downloaded with the [RoadRunner CLI](https://github.com/roadrunner-php/cli):

```bash
composer require roadrunner/cli --dev
vendor/bin/rr get
```

### Requirements

Ensure that your server is configured with the following PHP versions and extensions:

- PHP >=8.2
- ext-protobuf: This extension is optional but **highly recommended for installation**.
  Without it, performance may be up to 50% lower.
- RoadRunner ^3.0

### Configuration

Enable the `http` plugin in `.rr.yaml` and point the server at your worker script:

```yaml
version: "3"

server:
  command: "php worker.php"

http:
  address: "0.0.0.0:8080"
```

All available options are described in the [plugin documentation](https://docs.roadrunner.dev/docs/http/http).

### Writing a Worker

A `worker.php` that answers every request:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Nyholm\Psr7\Response;
use Nyholm\Psr7\Factory\Psr17Factory;

use Spiral\RoadRunner\Worker;
use Spiral\RoadRunner\Http\PSR7Worker;


// Create new RoadRunner worker from global environment
$worker = Worker::create();

// Create common PSR-17 HTTP factory
$factory = new Psr17Factory();

//
// Create PSR-7 worker and pass:
//  - RoadRunner worker
//  - PSR-17 ServerRequestFactory
//  - PSR-17 StreamFactory
//  - PSR-17 UploadFilesFactory
//
$psr7 = new PSR7Worker($worker, $factory, $factory, $factory);

while (true) {
    try {
        $request = $psr7->waitRequest();
    } catch (\Throwable $e) {
        // Although the PSR-17 specification clearly states that there can be
        // no exceptions when creating a request, however, some implementations
        // may violate this rule. Therefore, it is recommended to process the 
        // incoming request for errors.
        //
        // Send "Bad Request" response.
        $psr7->respond(new Response(400));
        continue;
    }

    // The worker has been asked to stop
    if ($request === null) {
        break;
    }

    try {
        // Here is where the call to your application code will be located. 
        // For example:
        //
        //  $response = $app->send($request);
        //
        // Reply by the 200 OK response
        $psr7->respond(new Response(200, [], 'Hello RoadRunner!'));
    } catch (\Throwable $e) {
        // In case of any exceptions in the application code, you should handle
        // them and inform the client about the presence of a server error.
        //
        // Reply by the 500 Internal Server Error response
        $psr7->respond(new Response(500, [], 'Something Went Wrong!'));

        // Additionally, we can inform the RoadRunner that the processing 
        // of the request failed.
        $worker->error((string)$e);
    }
}
```

Start the server with `./rr serve`.

## Stream response

To send a response in a stream, set the `$chunkSize` property in `PSR7Worker`:

```php
$psr7 = new PSR7Worker($worker, $factory, $factory, $factory);
$psr7->chunkSize = 512 * 1024; // 512KB
```

Now PSR7Worker will cut the response into chunks of 512KB and send them to the stream.

## Early hints

To send multiple responses you may use the `\Spiral\RoadRunner\Http\HttpWorker::respond()` method with
the `endOfStream` parameter set to `false`. This will send the response to the client and allow you to send
additional responses.

```php
/** @var \Spiral\RoadRunner\Http\PSR7Worker $psr7 */
$psr7->getHttpWorker()
    ->respond(103, headers: ['Link' => ['</style.css>; rel=preload; as=style']], endOfStream: false);

// End of stream will be sent automatically after PSR7Worker::respond() call
$psr7->respond(new Response(200, [], 'Hello RoadRunner!'));
```

## Testing

```bash
composer test
```

[![try Spiral Framework](https://user-images.githubusercontent.com/773481/220979012-e67b74b5-3db1-41b7-bdb0-8a042587dedc.jpg)](https://spiral.dev/)
