<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\FilamentModelStates\Tests\Support;

class PendingStateOrderingStub
{
    public static string $name = 'pending';

    public static function getMorphClass(): string
    {
        return self::$name;
    }
}
