<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\FilamentModelStates\Tests\Support;

class InitiatedStateOrderingStub
{
    public static string $name = 'initiated';

    public static function getMorphClass(): string
    {
        return self::$name;
    }
}
