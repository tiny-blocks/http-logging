# Http Logging

[![License](https://img.shields.io/badge/license-MIT-green)](https://github.com/tiny-blocks/http-logging/blob/main/LICENSE)

* [Overview](#overview)
* [Installation](#installation)
* [How to use](#how-to-use)
    + [Wiring the middleware](#wiring-the-middleware)
    + [Correlated logs](#correlated-logs)
    + [Customizing the clock](#customizing-the-clock)
    + [Ignoring paths](#ignoring-paths)
    + [Logging paths only on failure](#logging-paths-only-on-failure)
    + [Limiting logged bodies](#limiting-logged-bodies)
* [License](#license)
* [Contributing](#contributing)

## Overview

Provides a PSR-15 middleware that records every inbound HTTP exchange. Before invoking the next handler, the
middleware logs the request method, URI, query parameters, and parsed body. After the handler runs, it logs the
response status code, decoded JSON body when present, and the elapsed duration in milliseconds. Responses in the
5xx range are logged at `error` level, responses in the 4xx range at `warning` level, and every other response at
`info` level, so a client mistake (a wrong password, a failed validation, an unknown resource) does not reach the
alerts that watch the `error` level. Chosen paths can be left out of the log entirely, or logged only when their
response is an error, so health checks and routes called on a schedule do not bury the entries that matter. A body
longer than 2048 bytes is logged as an excerpt that fits within that limit, so a single entry stays well under the
line length a log driver accepts.

Duration is measured through a `TinyBlocks\Time\MonotonicClock` and a `Stopwatch`, so the reading is unaffected
by wall-clock adjustments. When the request carries a correlation identifier (under the `correlationId`
attribute, as exposed by [tiny-blocks/http-correlation-id](https://github.com/tiny-blocks/http-correlation-id)),
every log entry produced by this middleware is automatically tagged with the `correlation_id` context key
through the `CorrelatedLogger` collaborator.

## Installation

```bash
composer require tiny-blocks/http-logging
```

## How to use

### Wiring the middleware

Builds the middleware with a PSR-3 logger and processes a request through it.

```php
<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use TinyBlocks\Http\Logging\LogMiddleware;

# Build the middleware with the default system monotonic clock and a PSR-3 logger.
$middleware = LogMiddleware::create()->withLogger(logger: $logger)->build();

# Process an inbound request through the middleware.
$response = $middleware->process(
    new ServerRequest('GET', '/orders'),
    new class () implements RequestHandlerInterface {
        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            return new Response(200, [], '{"status":"ok"}');
        }
    }
);

# The middleware logged a `request` entry before, and a `response` entry after the handler call.
$response->getStatusCode();
```

### Correlated logs

When this middleware runs downstream from `CorrelationIdMiddleware`, every log entry it emits carries the
request's correlation identifier under the `correlation_id` context key, with no extra plumbing.

```php
<?php

declare(strict_types=1);

use TinyBlocks\Http\CorrelationId\CorrelationIdMiddleware;
use TinyBlocks\Http\Logging\LogMiddleware;

# Composes the two middlewares: correlation-id first so the attribute exists by the time log runs.
$correlationId = CorrelationIdMiddleware::create()->build();
$log = LogMiddleware::create()->withLogger(logger: $logger)->build();

# Every `request` and `response` entry emitted by $log will carry `correlation_id` in context.
```

### Customizing the clock

Replaces the default `SystemMonotonicClock` with a custom monotonic clock (for example, a deterministic
clock in tests).

```php
<?php

declare(strict_types=1);

use TinyBlocks\Http\Logging\LogMiddleware;
use TinyBlocks\Time\MonotonicClock;

# Custom monotonic clock that returns a fixed reading on each call.
$clock = new readonly class () implements MonotonicClock {
    public function nanoseconds(): int
    {
        return 0;
    }
};

# Build the middleware with the custom clock.
$middleware = LogMiddleware::create()
    ->withClock(clock: $clock)
    ->withLogger(logger: $logger)
    ->build();
```

### Ignoring paths

Leaves the given paths out of the log entirely. Neither the `request` entry nor the `response` entry is written for
them, whatever the response status, which suits health checks that a load balancer or an orchestrator polls every few
seconds. Each path is compared by exact equality with the request URI path, so `/health` does not match
`/health/readiness`, and the query string plays no part in the comparison.

```php
<?php

declare(strict_types=1);

use TinyBlocks\Http\Logging\LogMiddleware;

# Requests to the health check paths never produce a log entry, whether they succeed or fail.
$middleware = LogMiddleware::create()
    ->withLogger(logger: $logger)
    ->withIgnoredPaths('/health/liveness', '/health/readiness')
    ->build();
```

### Logging paths only on failure

Logs the given paths only when their response is an error, meaning a status in the 4xx/5xx range. Any other response
produces no entry. An error response produces both the `request` entry and the `response` entry, with the same context
as on any other path, so the failure stays fully visible. This suits internal routes called on a schedule that almost
always succeed with nothing to report. Each path is compared by exact equality with the request URI path.

The outcome is known only once the handler returns, so on these paths the `request` entry is written after the handler
runs instead of before it. A handler that throws instead of returning a response leaves no entry on these paths. A
path that is also ignored is never logged.

```php
<?php

declare(strict_types=1);

use TinyBlocks\Http\Logging\LogMiddleware;

# Health checks are never logged, and the scheduled dispatch route is logged only when it fails.
$middleware = LogMiddleware::create()
    ->withLogger(logger: $logger)
    ->withIgnoredPaths('/health/readiness')
    ->withFailureOnlyPaths('/v1/outbox/dispatches')
    ->build();
```

### Limiting logged bodies

Caps the size of every request and response body written to the log. The size of a body is the length in bytes of its
JSON encoding, written the way a JSON logger writes it (unescaped slashes and unescaped UTF-8). A body within the limit
is logged whole. A longer body is logged as an excerpt that fits within the limit, and the entry gains
`body_truncated_from_bytes`, the size of the whole body. Without `withBodyLimit` the limit is 2048 bytes.

The excerpt is the body read in document order until the limit is reached:

| Part of the body                   | In the excerpt                                     |
|------------------------------------|----------------------------------------------------|
| Fields and elements that fit       | Kept as they are, with their field names.          |
| The string where the limit falls   | Cut on a whole UTF-8 character and ended with `…`. |
| The object or array where it falls | Read the same way, one level down.                 |
| Everything after the limit         | Left out.                                          |

The excerpt stays a JSON object or array, never a string holding broken JSON, so the entry remains valid JSON and a
logger that redacts fields by name, such as the redactions of
[tiny-blocks/logger](https://github.com/tiny-blocks/logger), still finds and masks them in the excerpt.

```php
<?php

declare(strict_types=1);

use TinyBlocks\Http\Logging\LogMiddleware;

# Bodies up to 4096 bytes are logged whole. A longer body is logged as an excerpt of at most 4096 bytes.
$middleware = LogMiddleware::create()
    ->withLogger(logger: $logger)
    ->withBodyLimit(bytes: 4096)
    ->build();

# A response body of 18342 bytes is then logged as, for example:
# "body":{"version":"3","text":"Ao aceitar estes termos…"},"body_truncated_from_bytes":18342
```

A limit of zero logs the size of every body and none of its content. A negative limit raises `BodyLimitOutOfRange`
when the middleware is built.

The default leaves room for the rest of the entry. Docker's `awslogs` driver splits a line longer than 16 KB into
fragments that are no longer valid JSON, and an entry carries one body next to the method, the URI, the query
parameters and the correlation identifier. 2048 bytes keeps the whole entry well under that line length, even when a
logger escapes every non-ASCII character and the body triples in size.

## License

Http Logging is licensed under [MIT](LICENSE).

## Contributing

Please follow the [contributing guidelines](https://github.com/tiny-blocks/.github/blob/main/CONTRIBUTING.md) to
contribute to the project.
