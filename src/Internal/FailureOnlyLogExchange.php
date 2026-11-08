<?php

declare(strict_types=1);

namespace TinyBlocks\Http\Logging\Internal;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TinyBlocks\Time\MonotonicClock;
use TinyBlocks\Time\Stopwatch;

final readonly class FailureOnlyLogExchange
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
    ): FailureOnlyLogExchange {
        return new FailureOnlyLogExchange(
            logger: $logger,
            request: LogRequest::from(request: $request, bodyLimit: $bodyLimit),
            bodyLimit: $bodyLimit,
            stopwatch: Stopwatch::start(clock: $clock)
        );
    }

    public function complete(ResponseInterface $response): ResponseInterface
    {
        $responseLog = LogResponse::from(
            elapsed: $this->stopwatch->elapsed(),
            request: $this->request,
            response: $response,
            bodyLimit: $this->bodyLimit
        );

        if (!$responseLog->isError()) {
            return $response;
        }

        $this->request->writeTo(logger: $this->logger);
        $responseLog->writeTo(logger: $this->logger);

        return $response;
    }
}
