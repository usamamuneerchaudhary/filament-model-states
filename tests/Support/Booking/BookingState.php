<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\FilamentModelStates\Tests\Support\Booking;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class BookingState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Initiated::class)
            ->allowTransition(Initiated::class, Cancelled::class, ToCancelled::class);
    }
}
