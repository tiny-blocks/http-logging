<?php

declare(strict_types=1);

namespace TinyBlocks\Http\Logging;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use TinyBlocks\Http\CorrelationId\CorrelatedLogger;
use TinyBlocks\Http\CorrelationId\CorrelationId;
use TinyBlocks\Http\CorrelationId\CorrelationIdMiddleware;
use TinyBlocks\Http\Logging\Internal\FailureOnlyLogExchange;
use TinyBlocks\Http\Logging\Internal\LogExchange;
use TinyBlocks\Http\Logging\Internal\Paths;
use TinyBlocks\Time\MonotonicClock;

/**
 * PSR-15 middleware that logs request and response metadata (method, URI, status, duration) and attaches
 * <code>correlation_id</code> to every log entry when a correlation identifier is present on the request.
 */
final readonly class LogMiddleware implements MiddlewareInterface
{
    private function __construct(
        private MonotonicClock $clock,
        private LoggerInterface $logger,
        private Paths $ignoredPaths,
        private Paths $failureOnlyPaths
    ) {
    }

    /**
     * Builds a LogMiddleware from a monotonic clock, a logger, and optional path filters.
     *
     * <p>Paths are compared by exact string equality with the request URI path. A path present in both lists is
     * never logged.</p>
     *
     * @param MonotonicClock $clock The monotonic clock used to measure request duration.
     * @param LoggerInterface $logger The logger to use for request and response logging.
     * @param array<string> $ignoredPaths The request paths that are never logged.
     * @param array<string> $failureOnlyPaths The request paths that are logged only when the response is an error.
     * @return LogMiddleware The configured middleware instance.
     */
    public static function build(
        MonotonicClock $clock,
        LoggerInterface $logger,
        array $ignoredPaths = [],
        array $failureOnlyPaths = []
    ): LogMiddleware {
        return new LogMiddleware(
            clock: $clock,
            logger: $logger,
            ignoredPaths: Paths::from(...$ignoredPaths),
            failureOnlyPaths: Paths::from(...$failureOnlyPaths)
        );
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
        $path = $request->getUri()->getPath();

        if ($this->ignoredPaths->contains(path: $path)) {
            return $handler->handle($request);
        }

        $correlationId = $request->getAttribute(CorrelationIdMiddleware::ATTRIBUTE_NAME);
        $logger = $correlationId instanceof CorrelationId
            ? CorrelatedLogger::from(logger: $this->logger, correlationId: $correlationId)
            : $this->logger;

        $exchange = $this->failureOnlyPaths->contains(path: $path)
            ? FailureOnlyLogExchange::start(clock: $this->clock, logger: $logger, request: $request)
            : LogExchange::start(clock: $this->clock, logger: $logger, request: $request);

        $response = $handler->handle($request);

        return $exchange->complete(response: $response);
    }
}
