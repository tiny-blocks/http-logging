<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Http\Logging\Unit;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class CapturingHandler implements RequestHandlerInterface
{
    public function __construct(private readonly ResponseInterface $response = new Response())
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->response;
    }
}
