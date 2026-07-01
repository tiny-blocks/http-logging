<?php

declare(strict_types=1);

namespace TinyBlocks\Http\Logging\Internal;

use Psr\Http\Message\ResponseInterface;
use TinyBlocks\Http\Code;
use TinyBlocks\Time\Elapsed;

final readonly class LogResponse
{
    private function __construct(
        private string $uri,
        private ?array $body,
        private string $method,
        private Elapsed $elapsed,
        private int $statusCode
    ) {
    }

    public static function from(Elapsed $elapsed, LogRequest $request, ResponseInterface $response): LogResponse
    {
        $context = $request->toContext();
        $decodedBody = json_decode($response->getBody()->__toString(), true);

        return new LogResponse(
            uri: $context['uri'],
            body: is_array($decodedBody) ? $decodedBody : null,
            method: $context['method'],
            elapsed: $elapsed,
            statusCode: $response->getStatusCode()
        );
    }

    public function isError(): bool
    {
        return Code::isErrorCode(code: $this->statusCode);
    }

    public function toContext(): array
    {
        $context = [
            'method'      => $this->method,
            'uri'         => $this->uri,
            'status_code' => $this->statusCode,
            'duration_ms' => $this->elapsed->toMilliseconds()
        ];

        if (!empty($this->body)) {
            $context['body'] = $this->body;
        }

        return $context;
    }
}
