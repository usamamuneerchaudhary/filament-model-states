<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\FilamentModelStates\Tests\Support;

class InitiatedStateStub
{
    public static function label(): string
    {
        return 'Initiated';
    }

    public static function description(): ?string
    {
        return 'Booking has just been created and the customer is selecting flights or options.';
    }
}
