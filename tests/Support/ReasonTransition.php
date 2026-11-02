<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\FilamentModelStates\Tests\Support;

class ReasonTransition
{
    public function __construct(
        public Order $order,
        public string $reason,
        public ?int $code = null,
    ) {}
}
