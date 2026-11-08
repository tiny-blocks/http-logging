<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Http\Logging\Unit;

use Psr\Log\AbstractLogger;
use Stringable;

final class RedactingLogger extends AbstractLogger
{
    private array $entries = [];

    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        array_walk_recursive($context, static function (mixed &$value, int|string $key): void {
            $value = $key === 'email' ? '***' : $value;
        });

        $this->entries[] = ['level' => $level, 'message' => $message, 'context' => $context];
    }

    public function entries(): array
    {
        return $this->entries;
    }
}
