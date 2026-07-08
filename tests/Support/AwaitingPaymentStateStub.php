<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\FilamentModelStates\Tests\Support;

class AwaitingPaymentStateStub
{
    public static function label(): string
    {
        return 'Awaiting Payment';
    }

    public static function description(): ?string
    {
        return 'Customer has selected their flights and is in the process of making payment.';
    }
}
