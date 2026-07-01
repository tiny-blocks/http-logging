<?php

declare(strict_types=1);

namespace TinyBlocks\Http\Logging\Internal;

use Psr\Http\Message\ServerRequestInterface;
use TinyBlocks\Http\Server\Request;

final readonly class LogRequest
{
    private function __construct(
        private string $uri,
        private ?array $body,
        private string $method,
        private array $queryParameters
    ) {
    }

    public static function from(ServerRequestInterface $request): LogRequest
    {
        $typedRequest = Request::from(request: $request);
        $decodedRequest = $typedRequest->decode();
        $uri = $decodedRequest->uri();

        return new LogRequest(
            uri: $uri->toString(),
            body: $decodedRequest->body()->toArray(),
            method: $typedRequest->method()->value,
            queryParameters: $uri->queryParameters()->toArray()
        );
    }

    public function toContext(): array
    {
        $context = [
            'method' => $this->method,
            'uri'    => $this->uri
        ];

        if (!empty($this->queryParameters)) {
            $context['query_parameters'] = $this->queryParameters;
        }

        if (!empty($this->body)) {
            $context['body'] = $this->body;
        }

        return $context;
    }
}
