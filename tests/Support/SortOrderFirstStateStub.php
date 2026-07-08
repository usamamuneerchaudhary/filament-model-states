<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\FilamentModelStates\Tests\Support;

class SortOrderFirstStateStub
{
    public static int $sortOrder = 1;

    public static function getMorphClass(): string
    {
        return 'first';
    }
}
