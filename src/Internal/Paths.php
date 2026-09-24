<?php

declare(strict_types=1);

namespace TinyBlocks\Http\Logging\Internal;

final readonly class Paths
{
    private function __construct(private array $paths)
    {
    }

    public static function from(string ...$paths): Paths
    {
        return new Paths(paths: array_flip($paths));
    }

    public function contains(string $path): bool
    {
        return isset($this->paths[$path]);
    }
}
