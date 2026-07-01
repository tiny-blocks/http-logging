<?php

declare(strict_types=1);

namespace TinyBlocks\Http\Logging;

use Psr\Log\LoggerInterface;
use TinyBlocks\Http\Logging\Exceptions\LoggerNotConfigured;
use TinyBlocks\Time\MonotonicClock;
use TinyBlocks\Time\SystemMonotonicClock;

/**
 * Fluent builder that assembles a LogMiddleware with an optional custom monotonic clock.
 */
final class LogMiddlewareBuilder
{
    private MonotonicClock $clock;
    private ?LoggerInterface $logger = null;

    public function __construct()
    {
        $this->clock = new SystemMonotonicClock();
    }

    /**
     * Builds a LogMiddleware from the configured logger and monotonic clock.
     *
     * @return LogMiddleware The configured middleware instance.
     * @throws LoggerNotConfigured If no logger was configured.
     */
    public function build(): LogMiddleware
    {
        if (is_null($this->logger)) {
            throw new LoggerNotConfigured(message: 'A Logger must be provided to build the LogMiddleware.');
        }

        return LogMiddleware::build(clock: $this->clock, logger: $this->logger);
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
}
