<?php

declare(strict_types=1);

namespace TinyBlocks\Http\Logging;

use Psr\Log\LoggerInterface;
use TinyBlocks\Http\Logging\Exceptions\BodyLimitOutOfRange;
use TinyBlocks\Http\Logging\Exceptions\LoggerNotConfigured;
use TinyBlocks\Time\MonotonicClock;
use TinyBlocks\Time\SystemMonotonicClock;

/**
 * Fluent builder that assembles a LogMiddleware with an optional custom monotonic clock, an optional body limit,
 * and optional path filters.
 */
final class LogMiddlewareBuilder
{
    private MonotonicClock $clock;
    private ?LoggerInterface $logger = null;
    private ?int $bodyLimit = null;

    /** @var array<string> */
    private array $ignoredPaths = [];

    /** @var array<string> */
    private array $failureOnlyPaths = [];

    public function __construct()
    {
        $this->clock = new SystemMonotonicClock();
    }

    /**
     * Builds a LogMiddleware from the configured logger, monotonic clock, body limit, and path filters.
     *
     * @return LogMiddleware The configured middleware instance.
     * @throws LoggerNotConfigured If no logger was configured.
     * @throws BodyLimitOutOfRange If the configured body limit is negative.
     */
    public function build(): LogMiddleware
    {
        if (is_null($this->logger)) {
            throw new LoggerNotConfigured(message: 'A Logger must be provided to build the LogMiddleware.');
        }

        return LogMiddleware::build(
            clock: $this->clock,
            logger: $this->logger,
            bodyLimit: $this->bodyLimit,
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
     * Sets the largest request or response body logged whole and returns the builder.
     *
     * <p>The size of a body is the length in bytes of its JSON encoding, written the way a JSON logger writes it
     * (unescaped slashes and unescaped UTF-8). A body within the limit is logged whole, exactly as before. A longer
     * body is logged as an excerpt that fits within the limit, together with <code>body_truncated_from_bytes</code>,
     * the size of the whole body. The excerpt is the body read in document order up to the limit: it stays a JSON
     * object or array with its field names, a string cut short ends in <code>…</code> on a whole UTF-8 character,
     * and the fields past the limit are left out. Without this call the limit is 2048 bytes. A limit of zero logs
     * the size of every body and none of its content.</p>
     *
     * @param int $bytes The largest body, in bytes of its JSON encoding, that is logged whole.
     * @return LogMiddlewareBuilder The builder instance for fluent configuration.
     */
    public function withBodyLimit(int $bytes): LogMiddlewareBuilder
    {
        $this->bodyLimit = $bytes;
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
