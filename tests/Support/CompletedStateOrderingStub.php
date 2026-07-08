<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\FilamentModelStates\Tests\Support;

class CompletedStateOrderingStub
{
    public static string $name = 'completed';

    public static function getMorphClass(): string
    {
        return self::$name;
    }
}
