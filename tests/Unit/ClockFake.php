<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Http\Logging\Unit;

use TinyBlocks\Time\MonotonicClock;

final class ClockFake implements MonotonicClock
{
    private int $current;

    public function __construct(int $initial, private readonly int $increment)
    {
        $this->current = $initial;
    }

    public function nanoseconds(): int
    {
        $reading = $this->current;
        $this->current += $this->increment;
        return $reading;
    }
}
