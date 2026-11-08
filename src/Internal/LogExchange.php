<?php

declare(strict_types=1);

namespace TinyBlocks\Http\Logging\Internal;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TinyBlocks\Time\MonotonicClock;
use TinyBlocks\Time\Stopwatch;

final readonly class LogExchange
{
    private function __construct(
        private LoggerInterface $logger,
        private LogRequest $request,
        private BodyLimit $bodyLimit,
        private Stopwatch $stopwatch
    ) {
    }

    public static function start(
        MonotonicClock $clock,
        LoggerInterface $logger,
        ServerRequestInterface $request,
        BodyLimit $bodyLimit
    ): LogExchange {
        $requestLog = LogRequest::from(request: $request, bodyLimit: $bodyLimit);
        $requestLog->writeTo(logger: $logger);

        return new LogExchange(
            logger: $logger,
            request: $requestLog,
            bodyLimit: $bodyLimit,
            stopwatch: Stopwatch::start(clock: $clock)
        );
    }

    public function complete(ResponseInterface $response): ResponseInterface
    {
        LogResponse::from(
            elapsed: $this->stopwatch->elapsed(),
            request: $this->request,
            response: $response,
            bodyLimit: $this->bodyLimit
        )->writeTo(logger: $this->logger);

        return $response;
    }
}
