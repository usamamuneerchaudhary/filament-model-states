<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\FilamentModelStates\Tests\Support;

class SortOrderSecondStateStub
{
    public static int $sortOrder = 2;

    public static function getMorphClass(): string
    {
        return 'second';
    }
}
