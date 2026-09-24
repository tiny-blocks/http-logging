<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Http\Logging\Unit;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

final readonly class LoggingHandler implements RequestHandlerInterface
{
    public function __construct(private LoggerInterface $logger, private ResponseInterface $response)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->logger->info('handled');
        return $this->response;
    }
}
