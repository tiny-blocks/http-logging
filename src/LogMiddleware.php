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
use TinyBlocks\Http\Logging\Exceptions\BodyLimitOutOfRange;
use TinyBlocks\Http\Logging\Internal\BodyLimit;
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
        private BodyLimit $bodyLimit,
        private Paths $ignoredPaths,
        private Paths $failureOnlyPaths
    ) {
    }

    /**
     * Builds a LogMiddleware from a monotonic clock, a logger, an optional body limit, and optional path filters.
     *
     * <p>Paths are compared by exact string equality with the request URI path. A path present in both lists is
     * never logged.</p>
     *
     * <p>A request or response body whose JSON encoding is longer than the body limit is logged as an excerpt that
     * fits within the limit, together with <code>body_truncated_from_bytes</code>, the size of the whole body. The
     * excerpt is still a JSON object or array and keeps its field names, so a logger that redacts fields by name
     * keeps redacting them.</p>
     *
     * @param MonotonicClock $clock The monotonic clock used to measure request duration.
     * @param LoggerInterface $logger The logger to use for request and response logging.
     * @param int|null $bodyLimit The largest body, in bytes of its JSON encoding, logged whole, or null for 2048.
     * @param array<string> $ignoredPaths The request paths that are never logged.
     * @param array<string> $failureOnlyPaths The request paths that are logged only when the response is an error.
     * @return LogMiddleware The configured middleware instance.
     * @throws BodyLimitOutOfRange If the body limit is negative.
     */
    public static function build(
        MonotonicClock $clock,
        LoggerInterface $logger,
        ?int $bodyLimit = null,
        array $ignoredPaths = [],
        array $failureOnlyPaths = []
    ): LogMiddleware {
        return new LogMiddleware(
            clock: $clock,
            logger: $logger,
            bodyLimit: BodyLimit::from(bytes: $bodyLimit),
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
            ? FailureOnlyLogExchange::start(
                clock: $this->clock,
                logger: $logger,
                request: $request,
                bodyLimit: $this->bodyLimit
            )
            : LogExchange::start(
                clock: $this->clock,
                logger: $logger,
                request: $request,
                bodyLimit: $this->bodyLimit
            );

        $response = $handler->handle($request);

        return $exchange->complete(response: $response);
    }
}
