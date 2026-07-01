<?php

declare(strict_types=1);

namespace TinyBlocks\Http\Logging;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use TinyBlocks\Http\CorrelationId\CorrelatedLogger;
use TinyBlocks\Http\Logging\Internal\LogExchange;
use TinyBlocks\Time\MonotonicClock;

/**
 * PSR-15 middleware that logs request and response metadata (method, URI, status, duration) and attaches
 * <code>correlation_id</code> to every log entry when a correlation identifier is present on the request.
 */
final readonly class LogMiddleware implements MiddlewareInterface
{
    private function __construct(private MonotonicClock $clock, private CorrelatedLogger $correlatedLogger)
    {
    }

    /**
     * Builds a LogMiddleware from a monotonic clock and a logger.
     *
     * @param MonotonicClock $clock The monotonic clock used to measure request duration.
     * @param LoggerInterface $logger The logger to use for request and response logging.
     * @return LogMiddleware The configured middleware instance.
     */
    public static function build(MonotonicClock $clock, LoggerInterface $logger): LogMiddleware
    {
        return new LogMiddleware(clock: $clock, correlatedLogger: CorrelatedLogger::from(logger: $logger));
    }

    /**
     * Creates a LogMiddlewareBuilder for fluent configuration.
     *
     * @return LogMiddlewareBuilder The builder instance.
     */
    public static function create(): LogMiddlewareBuilder
    {
        return new LogMiddlewareBuilder();
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $exchange = LogExchange::start(
            clock: $this->clock,
            logger: $this->correlatedLogger->resolve(request: $request),
            request: $request
        );

        $response = $handler->handle($request);

        return $exchange->complete(response: $response);
    }
}
