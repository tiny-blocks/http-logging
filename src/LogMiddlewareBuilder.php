<?php

declare(strict_types=1);

namespace TinyBlocks\Http\Logging;

use Psr\Log\LoggerInterface;
use TinyBlocks\Http\Logging\Exceptions\LoggerNotConfigured;
use TinyBlocks\Time\MonotonicClock;
use TinyBlocks\Time\SystemMonotonicClock;

/**
 * Fluent builder that assembles a LogMiddleware with an optional custom monotonic clock and optional path filters.
 */
final class LogMiddlewareBuilder
{
    private MonotonicClock $clock;
    private ?LoggerInterface $logger = null;

    /** @var array<string> */
    private array $ignoredPaths = [];

    /** @var array<string> */
    private array $failureOnlyPaths = [];

    public function __construct()
    {
        $this->clock = new SystemMonotonicClock();
    }

    /**
     * Builds a LogMiddleware from the configured logger, monotonic clock, and path filters.
     *
     * @return LogMiddleware The configured middleware instance.
     * @throws LoggerNotConfigured If no logger was configured.
     */
    public function build(): LogMiddleware
    {
        if (is_null($this->logger)) {
            throw new LoggerNotConfigured(message: 'A Logger must be provided to build the LogMiddleware.');
        }

        return LogMiddleware::build(
            clock: $this->clock,
            logger: $this->logger,
            ignoredPaths: $this->ignoredPaths,
            failureOnlyPaths: $this->failureOnlyPaths
        );
    }

    /**
     * Sets the monotonic clock used to measure request duration and returns the builder.
     *
     * @param MonotonicClock $clock The monotonic clock used to measure request duration.
     * @return LogMiddlewareBuilder The builder instance for fluent configuration.
     */
    public function withClock(MonotonicClock $clock): LogMiddlewareBuilder
    {
        $this->clock = $clock;
        return $this;
    }

    /**
     * Sets the PSR-3 logger used for request and response logging and returns the builder.
     *
     * @param LoggerInterface $logger The logger to use for request and response logging.
     * @return LogMiddlewareBuilder The builder instance for fluent configuration.
     */
    public function withLogger(LoggerInterface $logger): LogMiddlewareBuilder
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * Sets the request paths that are never logged and returns the builder.
     *
     * <p>A request whose URI path equals one of these paths produces neither the <code>request</code> entry nor
     * the <code>response</code> entry, whatever the response status. The comparison is an exact string equality
     * with the URI path, so <code>/health</code> does not match <code>/health/readiness</code>. Suited to health
     * checks and other probes that carry nothing worth reading.</p>
     *
     * @param string ...$paths The exact request paths that are never logged.
     * @return LogMiddlewareBuilder The builder instance for fluent configuration.
     */
    public function withIgnoredPaths(string ...$paths): LogMiddlewareBuilder
    {
        $this->ignoredPaths = $paths;
        return $this;
    }

    /**
     * Sets the request paths that are logged only when the response is an error and returns the builder.
     *
     * <p>A request whose URI path equals one of these paths produces no entry while its response is not an
     * error. When the response status is in the 4xx or 5xx range, the <code>request</code> entry and the
     * <code>response</code> entry are both written after the handler returns, with the same context as on any
     * other path. The comparison is an exact string equality with the URI path. A path also set through
     * {@see LogMiddlewareBuilder::withIgnoredPaths()} is never logged.</p>
     *
     * @param string ...$paths The exact request paths that are logged only when the response is an error.
     * @return LogMiddlewareBuilder The builder instance for fluent configuration.
     */
    public function withFailureOnlyPaths(string ...$paths): LogMiddlewareBuilder
    {
        $this->failureOnlyPaths = $paths;
        return $this;
    }
}
