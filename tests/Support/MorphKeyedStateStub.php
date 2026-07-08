<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\FilamentModelStates\Tests\Support;

class MorphKeyedStateStub
{
    public string $name = 'legacy_name';

    public static function getMorphClass(): string
    {
        return 'awaiting_payment';
    }

    public function getValue(): string
    {
        return 'legacy_name';
    }
}
