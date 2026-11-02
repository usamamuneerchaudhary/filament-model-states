<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\FilamentModelStates\Tests\Support;

class WeirdTransition
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public Order $order,
        public array $meta,
    ) {}
}
