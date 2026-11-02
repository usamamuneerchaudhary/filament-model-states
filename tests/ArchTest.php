<?php

declare(strict_types=1);

arch('uses strict types')
    ->expect('Usamamuneerchaudhary\FilamentModelStates')
    ->toUseStrictTypes();

arch('does not use debug functions')
    ->expect('Usamamuneerchaudhary\FilamentModelStates')
    ->not->toUse(['dd', 'dump', 'ray', 'var_dump', 'die', 'eval']);
