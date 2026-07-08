<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\FilamentModelStates\Tests\Support;

class BookingStateOrderingStub
{
    /**
     * @return list<string>
     */
    public static function columnOrder(): array
    {
        return ['pending', 'initiated', 'completed'];
    }
}
