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
        private Stopwatch $stopwatch
    ) {
    }

    public static function start(
        MonotonicClock $clock,
        LoggerInterface $logger,
        ServerRequestInterface $request
    ): LogExchange {
        $requestLog = LogRequest::from(request: $request);
        $logger->info('request', $requestLog->toContext());

        return new LogExchange(
            logger: $logger,
            request: $requestLog,
            stopwatch: Stopwatch::start(clock: $clock)
        );
    }

    public function complete(ResponseInterface $response): ResponseInterface
    {
        $responseLog = LogResponse::from(
            elapsed: $this->stopwatch->elapsed(),
            request: $this->request,
            response: $response
        );

        match ($responseLog->isError()) {
            true  => $this->logger->error('response', $responseLog->toContext()),
            false => $this->logger->info('response', $responseLog->toContext())
        };

        return $response;
    }
}
